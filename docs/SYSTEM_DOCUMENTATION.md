# Feralix Billing — Dokumentasi Sistem

> Stack: Laravel 12, MySQL, Alpine.js, Mikrotik API
> Terakhir diupdate: 2026-05-09

---

## Daftar Isi

1. [Arsitektur Sistem](#1-arsitektur-sistem)
2. [Fitur yang Sudah Dibangun](#2-fitur-yang-sudah-dibangun)
3. [Billing Engine — Analisis & Status](#3-billing-engine--analisis--status)
4. [Flow Utama](#4-flow-utama)
5. [Database Schema Ringkasan](#5-database-schema-ringkasan)
6. [API Endpoints](#6-api-endpoints)
7. [Scheduler & Jobs](#7-scheduler--jobs)
8. [Known Issues & TODO](#8-known-issues--todo)

---

## 1. Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Feralix Billing                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │   Admin Panel     │    │   Technician UI   │    │   API (Sanctum)      │  │
│  │   (Alpine.js)     │    │   (Alpine.js)     │    │   /v1/admin/*        │  │
│  └────────┬─────────┘    └────────┬───────────┘    └──────────┬───────────┘  │
│           │                       │                           │              │
│           └───────────────────────┼───────────────────────────┘              │
│                                   ▼                                          │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                      Laravel 12 Backend                               │  │
│  │  ┌────────────────┐ ┌────────────────┐ ┌────────────────────────────┐ │  │
│  │  │ Billing Engine │ │  Network Sync  │ │  Customer Provisioning    │ │  │
│  │  │                │ │                │ │                            │ │  │
│  │  │ - Monthly Gen  │ │ - IP Pool Sync │ │ - Add Customer            │ │  │
│  │  │ - Auto Suspend  │ │ - VID Mgmt     │ │ - PPPoE Provisioning      │ │  │
│  │  │ - Isolation    │ │ - ONT Sync     │ │ - VID Assignment          │ │  │
│  │  │ - Payment Sync │ │ - GenieACS     │ │ - Terminate Customer      │ │  │
│  │  └────────────────┘ └────────────────┘ └────────────────────────────┘ │  │
│  │                                                                       │  │
│  │  ┌─────────────────────────────────────────────────────────────────┐ │  │
│  │  │                      Queue Workers                              │ │  │
│  │  │  billing | provisioning | network | monitoring | notifications  │ │  │
│  │  └─────────────────────────────────────────────────────────────────┘ │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                   │                                          │
│           ┌───────────────────────┼───────────────────────┐                  │
│           ▼                       ▼                       ▼                  │
│  ┌────────────────┐    ┌────────────────┐    ┌────────────────────────┐      │
│  │     MySQL      │    │   MikroTik     │    │      GenieACS         │      │
│  │  (Database)    │    │  (API/REST)    │    │     (TR-069)          │      │
│  └────────────────┘    └────────────────┘    └────────────────────────┘      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Arsitektur Billing Engine

```
┌─────────────────────────────────────────────────────────────────┐
│                    Billing Engine Flow                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐                                           │
│  │   Scheduler     │  Setiap tanggal 1 bulan, jam 00:05        │
│  │  (Laravel Task) │  → billing:generate-monthly-invoices       │
│  └────────┬────────┘                                           │
│           ▼                                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │            MonthlyInvoiceGenerationService              │    │
│  │  - Query semua service dengan overall_status:          │    │
│  │    active, down, suspended, isolated                    │    │
│  │  - Skip jika invoice periode sama sudah ada             │    │
│  │  - Generate invoice per service (subtotal dari package) │    │
│  │  - Sync billing_status service                          │    │
│  └────────┬────────────────────────────────────────────────┘    │
│           ▼                                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │               InvoicePaymentStatusService               │    │
│  │  - Resolve status berdasarkan:                          │    │
│  │    - Total amount vs amount paid                        │    │
│  │    - Due date vs reference date                         │    │
│  │    - Issued status                                      │    │
│  │  - Status: unpaid, issued, overdue, partially_paid,     │    │
│  │           paid, canceled                                 │    │
│  └────────┬────────────────────────────────────────────────┘    │
│           ▼                                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │             ServiceBillingStatusService                 │    │
│  │  - Sync billing_status di Service berdasarkan invoice   │    │
│  │  - Status: pending, paid, overdue, suspended, closed    │    │
│  └────────┬────────────────────────────────────────────────┘    │
│           │                                                     │
│     ┌─────┴────────────────────────────────────────────────┐    │
│     │                                                          │    │
│     ▼                                                          ▼    │
│  ┌──────────────────────┐              ┌──────────────────────────────┐  │
│  │ InvoiceAutoSuspend  │              │ InvoiceIsolationAutomation  │  │
│  │  Service             │              │  Service                    │  │
│  │  - Create isolation  │              │  - Jika overdue → isolate    │  │
│  │    (InvoiceOverdue)  │              │  - Jika lunas → release     │  │
│  └──────────────────────┘              └──────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Fitur yang Sudah Dibangun

### 2.1 Master Data Jaringan

- **Master Lokasi** (`network_locations`) — Lokasi jaringan fisik
- **Master OLT** (`olts`) — Optical Line Terminal dengan PON port management
- **Router Management** (`routers`) — MikroTik router management via API
- **Router Scopes** (`router_scopes`) — VID range per router dengan monitor_vid exclusion
- **VID Management** (`vids`) — Virtual LAN ID assignment per pelanggan

### 2.2 IP Pool Management

- **Sync dari Mikrotik ke DB cache** (`ip_pool_snapshots`) — Sinkronisasi pool secara periodik
- **Selective tracking** — Admin pilih pool mana yang di-track
- **Status pool**: Available (`used_ips=0`) / Reserved (`used_ips>=1`) / Full
- **VID Reservation atomic** dengan `SELECT FOR UPDATE` — Race-condition safe
- **Auto-suggest VID** berdasarkan `RouterScope.vid_start` & `vid_end`
- **Scheduler sync setiap 5 menit** (`ip-pools:sync`)

### 2.3 Customer Management

- **Add customer dengan provisioning lengkap**
- **Auto-generate PPPoE username & password**
- **Auto-assign VID dari IP Pool** (atomic, race-condition safe)
- **Create PPPoE secret di Mikrotik** (profile: `default`)
- **Terminate customer**: Hapus PPPoE + release VID + write-off invoice

### 2.4 Billing Engine

Lihat Section 3 untuk analisis mendalam.

### 2.5 Teknisi

- User role `technician` → auto-create record Technician
- Format code: `TIM-{NamaUser}`
- Router assignment via `user_router` pivot table

---

## 3. Billing Engine — Analisis & Status

### 3.1 Invoice Payment Status (Enum)

| Status | Kondisi |
|--------|---------|
| `unpaid` | Belum diterbitkan, belum ada pembayaran |
| `issued` | Sudah diterbitkan, belum dibayar |
| `overdue` | Melewati due date, belum lunas |
| `partially_paid` | Ada pembayaran tapi belum lunas |
| `paid` | Lunas |
| `canceled` | Dibatalkan |

### 3.2 Service Billing Status (Enum)

| Status | Kondisi |
|--------|---------|
| `pending` | Ada invoice unpaid/issued/partial |
| `paid` | Semua invoice lunas |
| `overdue` | Ada invoice overdue |
| `suspended` | Service disuspend karena tunggakan |
| `closed` | Service ditutup (terminate) |

### 3.3 Generate Invoice Bulanan

**Command:**
```bash
php artisan billing:generate-monthly-invoices {billing_period} [options]
```

**Options:**
- `--invoice-date=` — Tanggal invoice
- `--due-date=` — Tanggal jatuh tempo
- `--due-in-days=` — Offset hari jatuh tempo
- `--penalty-amount=` — Denda tambahan
- `--customer=` — Limit ke customer ID tertentu
- `--service=` — Limit ke service ID tertentu
- `--queue` — Dispatch ke queue

**Analisis:**
- Generate untuk semua service dengan `overall_status`: `active`, `down`, `suspended`, `isolated`
- Skip jika invoice periode yang sama sudah ada (idempotent, termasuk soft-deleted)
- Support: specific customer, specific service, router filter
- Due date default: invoice_date + 10 hari (dari config)

### 3.4 Auto Suspend (InvoiceAutoSuspendService)

- Trigger berdasarkan overdue invoices
- Buat `ServiceIsolation` dengan type `InvoiceOverdue`
- Skip jika service sudah punya open isolation
- Skip jika router_id atau target subnet tidak ada

### 3.5 Invoice Isolation Automation (InvoiceIsolationAutomationService)

- **Sync per invoice** — Dipanggil saat invoice berubah status
- **Jika ada invoice overdue** → Pastikan isolation aktif
- **Jika tidak ada overdue** → Release billing isolation
- Logging ke automation channel

### 3.6 GAP yang Ditemukan di Billing Engine

#### GAP #1: `monthly_price` dari Package (CRITICAL)

**Lokasi:** `MonthlyInvoiceGenerationService.php:71`
```php
'subtotal' => $service->package->monthly_price,
```

**Masalah:**
- `MonthlyInvoiceGenerationService` mengambil harga dari `service.package.monthly_price`
- `package_id` di Service **nullable** (confirmed dari migration `2026_05_09_084020_make_package_id_nullable_in_services_table.php`)
- **Model bisnis Feralix tidak wajib pakai paket** — Customer bisa langsung input harga manual
- Jika service tidak punya package, invoice akan **error 500** karena null pointer

**Solusi yang perlu dipertimbangkan:**
1. Fallback ke `customer.monthly_price` jika `package_id` null
2. Tambah field `monthly_price` langsung di Service
3. Validasi: service harus punya package atau customer harus punya monthly_price

---

#### GAP #2: `billing_day` Tidak Dipakai

**Lokasi:** `Customer.php:35` — field `billing_day` ada di fillable
```php
'billing_day',
```

**Masalah:**
- Field `billing_day` ada di model Customer
- Tapi **tidak digunakan** dalam `MonthlyInvoiceGenerationService`
- Semua customer di-generate invoice di tanggal yang sama (default: tanggal 1)
- Di bisnis nyata, billing day bisa beda-beda per customer (misal: tanggal 5, 10, 15)

**Impact:** Tidak bisa implementasi billing day per customer

---

#### GAP #3: Due Date Config Tidak Respect Router/Timezone

**Lokasi:** `routes/console.php:15` dan `config/automation.php`
```php
Schedule::command('billing:generate-monthly-invoices --queue')
    ->monthlyOn(1, config('automation.schedule.generate_monthly_invoices_at', '00:05'))
```

**Masalah:**
- Config `timezone` ada di config, tapi invoice date selalu pakai `now()` tanpa timezone awareness
- Tidak ada option untuk billing period berbeda per customer/router
- Due date offset global, tidak bisa per customer

---

#### GAP #4: Tidak Ada Payment Recording di Generate Invoice

**Lokasi:** `MonthlyInvoiceGenerationService.php` — hanya create invoice

**Masalah:**
- Generate invoice hanya create record, tidak handle prepaid customers
- Customer yang sudah bayar di muka (prepaid) tetap di-generate invoice
- Tidak ada logic untuk skip/hapus invoice untuk customer prepaid

---

#### GAP #5: Service `overall_status` = `provisioning` Tidak Termasuk

**Lokasi:** `MonthlyInvoiceGenerationService.php:62-67`
```php
->whereIn('overall_status', [
    ServiceOverallStatus::Active->value,
    ServiceOverallStatus::Down->value,
    ServiceOverallStatus::Suspended->value,
    ServiceOverallStatus::Isolated->value,
])
```

**Masalah:**
- `provisioning` status **tidak di-include**
- Service yang masih provisioning (belum aktif) tidak akan di-generate invoice
- Ini bisa jadi benar secara business logic, tapi perlu konfirmasi

---

## 4. Flow Utama

### 4.1 Add Customer Baru

```
Admin input form
    │
    ├── Pilih Router → Load PPPoE Server dari Mikrotik API
    │
    ├── Generate Username & Password (auto)
    │
    ├── Suggest VID → /api/v1/admin/ip-pools/suggest
    │       ├── SELECT FOR UPDATE (atomic lock)
    │       ├── Filter berdasarkan RouterScope.vid_start/vid_end
    │       └── Exclude monitor_vid
    │
    ├── Submit
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  CustomerProvisioningService                                     │
│  1. Create Customer di DB                                        │
│  2. Create Service dengan VID                                    │
│  3. Create PPPoE Secret di Mikrotik (profile: default)            │
│  4. Confirm VID reservation → reserved_by_customer_id = customer │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Generate Invoice Bulanan

```
Scheduler (tanggal 1, jam 00:05) / Manual trigger
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  billing:generate-monthly-invoices 2026-05                       │
│  MonthlyInvoiceGenerationService                                │
│  1. Query semua billable services                               │
│     - overall_status: active, down, suspended, isolated        │
│     - activation_date <= period end                            │
│  2. Skip jika invoice bulan ini sudah ada                       │
│  3. Create invoice per service (subtotal dari package)        │
│  4. Sync billing_status service                                │
└─────────────────────────────────────────────────────────────────┘
```

### 4.3 Terminate Customer

```
Admin klik Terminate → DELETE /api/v1/admin/customers/{id}/terminate
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  CustomerTerminationService                                      │
│  1. Remove PPPoE Secret dari Mikrotik (per service)             │
│  2. Release VID → reserved_by_customer_id = null                │
│  3. Mark unpaid invoices as paid (write-off)                   │
│  4. Update services: overall_status = terminated              │
│  5. Hard delete: invoices → services → customer                 │
│     (sesuai urutan karena FK constraint)                        │
└─────────────────────────────────────────────────────────────────┘
```

### 4.4 IP Pool Sync

```
Manual (klik Refresh) / Scheduler (setiap 5 menit)
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  IpPoolSyncService                                              │
│  1. Fetch pools dari Mikrotik API                               │
│  2. Jika ada confirmed reservation → respect (don't reset)      │
│  3. Update ip_pool_snapshots                                   │
│  4. Sync tracked pools saja (is_tracked = true)                │
└─────────────────────────────────────────────────────────────────┘
```

### 4.5 Invoice Overdue → Auto Isolate

```
Scheduler (daily, jam 00:30) / Manual trigger
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  billing:create-overdue-isolations                               │
│  InvoiceIsolationAutomationService                               │
│  1. Cek setiap invoice overdue                                  │
│  2. Jika ada service overdue:                                   │
│     - Create ServiceIsolation (type: InvoiceOverdue)          │
│     - Router apply isolation profile                             │
│  3. Jika semua invoice lunas:                                   │
│     - Release billing isolations                               │
│     - Router apply default profile                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Database Schema Ringkasan

| Tabel | Kolom Kunci | Relasi |
|-------|-------------|--------|
| `customers` | id, customer_code, full_name, network_location_id, preferred_olt_id, assigned_technician_id, monthly_price, billing_day | → services, invoices, location, preferredOlt |
| `services` | id, customer_id, package_id, router_id, olt_id, vid_id, internet_vid, pppoe_username, overall_status, billing_status, activation_date | → customer, package, router, olt, vid, invoices |
| `invoices` | id, customer_id, service_id, billing_period, payment_status, subtotal, penalty_amount, total_amount, due_date, issued_at, paid_at | → customer, service, payments |
| `payments` | id, customer_id, invoice_id, amount_paid, payment_date, payment_method | → customer, invoice |
| `packages` | id, package_name, monthly_price, ip_pool_count, rate_limit_mbps | → services |
| `ip_pool_snapshots` | id, router_id, pool_name, vlan_id, used_ips, free_ips, availability_status, is_tracked, reserved_by_customer_id | → router |
| `network_locations` | id, name, code, status | → olts, customers |
| `olts` | id, name, code, host, network_location_id, router_id, status | → network_location, router |
| `routers` | id, router_code, router_name, host, api_port, api_username | |
| `router_scopes` | id, router_id, scope_name, vid_start, vid_end, monitor_vid, is_special | → router |
| `vids` | id, vlan_id, subnet_cidr, gateway_ip, dhcp_pool_start, dhcp_pool_end, router_id | → router |
| `service_isolations` | id, service_id, router_id, invoice_id, isolation_type, status, applied_at, released_at | → service, router, invoice |
| `technicians` | id, technician_code, full_name, is_active | → user |
| `cashflow_transactions` | id, invoice_id, amount, transaction_type | → invoice |

---

## 6. API Endpoints

### Customer

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/admin/customers` | List customers dengan search & filter |
| POST | `/api/v1/admin/customers/provisioning` | Add customer baru dengan provisioning lengkap |
| DELETE | `/api/v1/admin/customers/{id}/terminate` | Terminate customer (write-off + cleanup) |
| POST | `/api/v1/admin/customers/onboard` | Onboard customer |
| POST | `/api/v1/admin/customers/bulk-generate-invoice` | Bulk generate invoice |

### IP Pool

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/admin/ip-pools` | List pools dari DB cache |
| GET | `/api/v1/admin/ip-pools/suggest` | Suggest & atomic lock VID |
| GET | `/api/v1/admin/ip-pools/preview` | Preview live dari Mikrotik |
| POST | `/api/v1/admin/ip-pools/sync` | Manual sync ke DB |
| POST | `/api/v1/admin/ip-pools/save-selection` | Simpan pilihan tracked pools |
| GET | `/api/v1/admin/routers/{router}/ip-pools` | List pools per router |
| GET | `/api/v1/admin/routers/{router}/ip-pools/summary` | Summary utilization |
| GET | `/api/v1/admin/routers/{router}/ip-pools/utilization` | Utilization detail |
| GET | `/api/v1/admin/routers/{router}/ip-pools/suggest-for-vid` | Suggest pool untuk VID |
| GET | `/api/v1/admin/routers/{router}/ip-pools/vids-with-availability` | VIDs dengan availability |

### Network

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET/POST/PUT/DELETE | `/api/v1/admin/network-locations` | CRUD Master Lokasi |
| GET/POST/PUT/DELETE | `/api/v1/admin/olts` | CRUD Master OLT |
| GET | `/api/v1/admin/olts/{olt}/pon-status` | PON port status |
| GET/POST/PUT/DELETE | `/api/v1/admin/olts/{olt}/pon-ports` | CRUD PON Port |
| GET/POST/PUT/DELETE | `/api/v1/admin/odcs` | CRUD ODC |
| GET/POST/PUT/DELETE | `/api/v1/admin/odps` | CRUD ODP |
| GET | `/api/v1/admin/mikrotik/pppoe-servers` | List PPPoE servers dari Mikrotik |
| GET/POST/PUT/DELETE | `/api/v1/admin/router-scopes` | CRUD Router Scopes |

### Billing

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/admin/invoices` | List invoices (paginated) |
| POST | `/api/v1/admin/invoices/generate-monthly` | Generate invoice bulanan |
| POST | `/api/v1/admin/invoices/auto-suspend` | Auto suspend services |
| GET | `/api/v1/admin/invoices/overdue` | List overdue invoices |
| GET | `/api/v1/admin/invoices/paid` | List paid invoices |
| GET | `/api/v1/admin/invoices/unpaid` | List unpaid invoices |
| PATCH | `/api/v1/admin/invoices/{id}/mark-overdue` | Mark invoice overdue |
| PATCH | `/api/v1/admin/invoices/{id}/mark-paid` | Mark invoice lunas |
| POST | `/api/v1/admin/invoices/{id}/send-whatsapp` | Kirim invoice via WhatsApp |
| POST | `/api/v1/admin/invoices/bulk-action` | Bulk action invoices |

### Service Isolation

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/admin/service-isolations` | List isolations |
| GET | `/api/v1/admin/service-isolations/suggestions` | Suggest isolation |
| POST | `/api/v1/admin/service-isolations` | Create isolation |
| PATCH | `/api/v1/admin/service-isolations/{id}/applied` | Mark applied |
| PATCH | `/api/v1/admin/service-isolations/{id}/release` | Release isolation |
| POST | `/api/v1/admin/isolir/manual` | Manual isolir |
| POST | `/api/v1/admin/isolir/release` | Release manual isolir |

### Payments

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/admin/payments` | List payments |
| POST | `/api/v1/admin/payments` | Record payment |
| GET | `/api/v1/admin/payments/{id}` | Payment detail |

---

## 7. Scheduler & Jobs

| Schedule | Command/Job | Fungsi |
|----------|-------------|--------|
| Tanggal 1, 00:05 | `billing:generate-monthly-invoices --queue` | Generate invoice bulanan |
| Daily, 00:20 | `billing:check-overdue-invoices --queue` | Cek dan sync overdue status |
| Daily, 00:30 | `billing:create-overdue-isolations --queue` | Auto isolate overdue |
| Setiap 5 menit | `ip-pools:sync` | Sync IP Pool dari Mikrotik |
| Setiap 15 menit | `mikrotik:sync-vids --queue` | Sync VID dari Mikrotik |
| Setiap 1 menit | `monitor:sync-pppoe --queue` | Sync PPPoE status |
| Setiap 5 menit | `genieacs:sync-onts --queue` | Sync ONT dari GenieACS |
| Setiap 1 menit | `notifications:process-telegram --queue` | Process Telegram notifications |

### Config Timezone

Timezone scheduler bisa dikonfigurasi di:
```bash
AUTOMATION_SCHEDULE_TIMEZONE=Asia/Jakarta
```

---

## 8. Known Issues & TODO

### CRITICAL — Billing Engine

| Issue | Severity | Lokasi | Status |
|-------|----------|--------|--------|
| `monthly_price` null jika service tidak punya package → 500 error | **CRITICAL** | `MonthlyInvoiceGenerationService.php:71` | ⚠️ Belum fixed |
| `billing_day` tidak dipakai — semua customer billing tanggal 1 | HIGH | `Customer.php`, `MonthlyInvoiceGenerationService.php` | ⚠️ Belum fixed |

### TODO — Fitur yang Perlu Disempurnakan

- [ ] Service `pppoe` field di Mikrotik secret — saat ini `any` (wajib `pppoe`)
- [ ] Detail customer UI: Tanggal Jatuh Tempo (perlu dari invoice terbaru)
- [ ] Fallback `customer.monthly_price` jika `package_id` null
- [ ] Billing day per customer (billing_day field sudah ada, belum dipakai)
- [ ] Prepaid handling — skip invoice untuk customer yang sudah bayar di muka
- [ ] Payment recording di generate invoice flow
- [ ] Due date per customer/router (saat ini global config)
- [ ] Logging invoice isolation automation ke activity log

### Nice-to-Have

- [ ] PDF invoice generation
- [ ] Email invoice notification
- [ ] Payment reminder via WhatsApp/Telegram
- [ ] Aging report (rincian tunggakan per bulan)
- [ ] Revenue projection berdasarkan billing history

---

## Catatan untuk Developer

### Billing Engine — Cara Kerja

1. **Generate Invoice** dijalankan tanggal 1 setiap bulan jam 00:05
2. Invoice menggunakan `billing_period` dalam format `YYYY-MM`
3. Harga diambil dari `service.package.monthly_price`
4. Billing status service di-sync setelah invoice dibuat
5. Scheduler `billing:check-overdue-invoices` cek overdue daily jam 00:20
6. Scheduler `billing:create-overdue-isolations` auto-isolate overdue jam 00:30

### IP Pool — Penting

1. VID reservation menggunakan `SELECT FOR UPDATE` untuk race-condition safety
2. Sync scheduler setiap 5 menit
3. Reserved VID tidak di-reset saat sync (menghormati customer existing)
4. VID baru di-suggest berdasarkan RouterScope range

### Customer Termination — Urutan Penting

1. Hapus PPPoE dari Mikrotik
2. Release VID
3. Mark unpaid invoices as paid (write-off)
4. Terminate services
5. Hard delete: invoices → services → customer

**Urutan deletion penting karena Foreign Key constraint.**

### Queue Names

- `billing` — Invoice generation, overdue check
- `provisioning` — Customer creation, PPPoE provisioning
- `network` — IP pool sync, VID sync
- `monitoring` — PPPoE status sync, GenieACS ONT sync
- `notifications` — Telegram, WhatsApp

### Troubleshooting

- **Invoice error 500**: Cek apakah service punya `package_id` atau `customer.monthly_price`
- **VID tidak suggest**: Cek `ip_pool_snapshots` apakah ada tracked pools
- **Isolation tidak jalan**: Cek queue worker `billing` apakah running
- **Scheduler tidak jalan**: Cek cron/Task Scheduler server

---

*Generated dengan Claude Code — 2026-05-09*
