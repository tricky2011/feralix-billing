# Feralix ISP Cloud — Dokumentasi Sistem Lengkap

> **Stack**: Laravel 12 · MySQL · Alpine.js · Mikrotik API · Sanctum Auth
> **Timezone**: Asia/Jakarta (terkonfigurasi di config/automation.php)
> **Versi Docs**: 2026-05-09

---

## Daftar Isi

1. [Arsitektur Sistem](#1-arsitektur-sistem)
2. [Database Schema](#2-database-schema)
3. [Fitur & Status](#3-fitur--status)
4. [Billing Engine](#4-billing-engine)
5. [Network & Provisioning](#5-network--provisioning)
6. [Scheduler & Automation](#6-scheduler--automation)
7. [API Endpoints Lengkap](#7-api-endpoints-lengkap)
8. [GAP Analysis — Semua Fitur](#8-gap-analysis--semua-fitur)
9. [TODO Prioritas](#9-todo-prioritas)
10. [Setup & Deployment Notes](#10-setup--deployment-notes)

---

## 1. Arsitektur Sistem

```
Frontend (Alpine.js + Blade SPA)
        │ HTTP API /api/v1/admin/*
        ▼
Laravel 12 Backend
├── Controllers (thin)
├── Services (business logic)
├── Models (Eloquent)
└── Jobs/Commands (async)
        │
   ┌────┼────────┬────────────┐
   ▼    ▼        ▼            ▼
MySQL  Mikrotik  Queue       Cache
  DB   Router   (DB driver)  (DB)
```

**Auth**: Laravel Sanctum (API token)
**Queue**: Database driver
**Mikrotik**: Socket API (port 8728, SSL 8729)
**GenieACS**: TR-069 ACS untuk ONT monitoring

---

## 2. Database Schema

### Tabel Utama

| Tabel | Kolom Kunci | Relasi |
|-------|-------------|--------|
| `customers` | id, customer_code, full_name, phone, address, network_location_id, preferred_olt_id, assigned_technician_id, install_date, billing_day, monthly_price | → services, invoices |
| `services` | id, customer_id, router_id, olt_id, ont_id, vid_id, package_id, internet_vid, monitor_vid, pppoe_username, pppoe_password, pppoe_isolation_profile, monthly_price, access_mode, isolation_method, billing_status, network_status, overall_status, activation_date | → invoices, service_isolations |
| `invoices` | id, customer_id, service_id, invoice_number, billing_period, invoice_date, due_date, subtotal, penalty_amount, total_amount, payment_status, issued_at, paid_at, overdue_marked_at | → payments |
| `payments` | id, invoice_id, customer_id, service_id, amount_paid, payment_method, paid_at, reference_no | |
| `ip_pools` | id, router_id, pool_name, vlan_id, total_ips, used_ips, free_ips, is_tracked, is_available, is_full | → router |
| `ip_pool_snapshots` | id, router_id, pool_name, vlan_id, total_ips, used_ips, free_ips, is_tracked, reserved_by_customer_id, synced_at | → router |
| `service_isolations` | id, service_id, router_id, invoice_id, isolation_type, target_type, address_list_name, target_subnet, status, isolated_at, released_at | |
| `routers` | id, router_code, router_name, host, api_port, api_username, api_password (encrypted), acs_* fields, status | → scopes, services |
| `router_scopes` | id, router_id, scope_name, vid_start, vid_end, monitor_vid, is_special | |
| `network_locations` | id, name, code, address, latitude, longitude, status | → olts, odcs, odps |
| `olts` | id, name, code, host, network_location_id, router_id, pon_ports, brand, model, is_active | → onts |
| `onts` | id, olt_id, ont_sn, pon_port, onu_id, ssid_name, wifi_password, optical_status, rx_power, tx_power, status, genieacs_device_id | |
| `technicians` | id, technician_code, full_name, phone, is_active, last_assigned_at | → user |
| `users` | id, name, username, email, password, role, is_active, technician_id, dashboard_active_router_id | → technicians, routers (pivot) |
| `work_orders` | id, customer_id, service_id, router_id, olt_id, ont_id, wo_number, wo_type, assigned_technician_id, status, scheduled_at, completed_at | |
| `tickets` | id, customer_id, service_id, ticket_number, category, priority, status, assigned_technician_id, assignment_mode | → replies |
| `cashflow_transactions` | id, payment_id, invoice_id, customer_id, service_id, router_id, direction, category, amount, transacted_at | |
| `hotspot_profiles` | id, profile_name, validity_mode, validity_days, data_limit_bytes, price, selling_price, user_lock, expired_mode, is_active | |
| `hotspot_vouchers` | id, batch_id, reseller_id, profile_id, username, password, voucher_code, locked_mac, status, first_login_at, expires_at | |
| `resellers` | id, reseller_code, full_name, phone, balance, status | |
| `voucher_batches` | id, reseller_id, profile_id, batch_code, total_vouchers, total_cost, generated_at | |

### Enum Penting

**InvoicePaymentStatus**: `unpaid`, `issued`, `overdue`, `partially_paid`, `paid`, `canceled`

**ServiceOverallStatus**: `provisioning`, `active`, `down`, `suspended`, `isolated`, `inactive`, `terminated`

**ServiceBillingStatus**: `pending`, `paid`, `overdue`, `suspended`, `closed`

**ServiceAccessMode**: `vlan`, `pppoe`, `static`

**ServiceIsolationMethod**: `address_list`, `firewall_filter`, `ppp_profile`, `queue`

**UserRole**: `superadmin`, `admin`, `technician`, `reseller`

---

## 3. Fitur & Status

### 3.1 Master Data

| Fitur | Status | Notes |
|-------|--------|-------|
| Master Lokasi (NetworkLocation) | ✅ Production Ready | CRUD + relasi ke OLT, ODC, ODP |
| Master OLT | ✅ Production Ready | Linked ke NetworkLocation + Router |
| Router Management | ✅ Production Ready | CRUD + Test Connection + Router Scopes |
| Router Scopes (VID range) | ✅ Production Ready | vid_start, vid_end, monitor_vid per router |
| User Management | ✅ Production Ready | Role: superadmin, admin, technician |
| Teknisi Management | ✅ Production Ready | Auto-create dari user role technician |
| PON Port Management | ✅ Production Ready | OltPonPortController, capacity tracking |

### 3.2 Customer & Provisioning

| Fitur | Status | Notes |
|-------|--------|-------|
| Add Customer (Provisioning) | ✅ Production Ready | Auto VID, Auto PPPoE, Auto Invoice |
| VID Auto-Assign (Atomic) | ✅ Production Ready | SELECT FOR UPDATE, race-condition safe |
| PPPoE Create di Mikrotik | ✅ Production Ready | Profile: default, disabled saat create (prepaid) |
| Terminate Customer | ✅ Production Ready | Hapus PPPoE + Release VID + Write-off invoice + Delete |
| Prepaid Flow | ✅ Production Ready | PPPoE disabled saat create, enable saat bayar |
| Detail Customer UI | ✅ Done | VID, PPPoE, OLT, Router, Status Layanan, Due Date |
| Edit Customer | ✅ Exists | Via UpdateCustomerRequest |
| Bulk Delete/Disable | ✅ Exists | Via CustomerBulkActionService |
| Customer Onboarding | ✅ Production Ready | Full flow: Customer + Service + WorkOrder + Invoice |

### 3.3 Billing

| Fitur | Status | Notes |
|-------|--------|-------|
| Generate Invoice Bulanan | ✅ Production Ready | monthly_price dari service atau package |
| Invoice Per Customer | ✅ Exists | Via ManualInvoiceService |
| Mark Invoice Paid | ✅ Exists | Via PaymentService + InvoicePaymentStatusService |
| Due Date per Customer | ✅ Production Ready | Dari install_date customer (sudah diimplementasi) |
| Auto Overdue Check | ✅ Production Ready | Daily scheduler 00:20 |
| Auto Isolir Overdue | ✅ Production Ready | Daily scheduler 00:30, address-list based |
| PDF Invoice | ✅ Exists | Route /api/v1/admin/invoices/{id}/pdf |
| WhatsApp Notification | ✅ Exists | InvoiceWhatsappService |
| Partial Payment | ✅ Exists | partially_paid status + amount tracking |
| Penalty/Denda | ✅ Exists | penalty_amount field di invoice |
| Invoice Bulk Action | ✅ Exists | InvoiceBulkActionService |
| Cashflow Integration | ✅ Exists | CashflowIncomeService auto-record saat payment |

### 3.4 IP Pool

| Fitur | Status | Notes |
|-------|--------|-------|
| IP Pool dari Mikrotik (live) | ✅ Production Ready | fetchFromRouter() |
| IP Pool DB Cache | ✅ Production Ready | ip_pool_snapshots + IpPoolSyncService |
| Selective Pool Tracking | ✅ Production Ready | Admin pilih pool mana yang di-track |
| VID Suggest Atomic | ✅ Production Ready | SELECT FOR UPDATE, filter by RouterScope |
| VID Reservation Permanent | ✅ Production Ready | reserved_by_customer_id |
| Status Badge (Available/Sudah dipakai/Full) | ✅ Production Ready | Berdasarkan used_ips logic |
| Auto Sync Scheduler | ✅ Production Ready | everyFiveMinutes() registered |

### 3.5 Network & Isolir

| Fitur | Status | Notes |
|-------|--------|-------|
| Manual Isolir | ✅ Exists | ManualIsolirController |
| Auto Isolir Overdue | ✅ Production Ready | Address-list based |
| Release Isolir saat Bayar | ✅ Production Ready | InvoiceIsolationAutomationService |
| Halaman Isolir (Web Proxy) | ✅ Done | /isolir route + tampilan pelanggan |
| Service Isolation History | ✅ Exists | ServiceIsolationHistoryService |
| Router Sync | ✅ Exists | SyncMikrotikVidCommand |
| PPPoE Import | ✅ Exists | PppoeImportController |
| ODC/ODP Management | ✅ Exists | OdcController, OdpController |

### 3.6 Monitoring

| Fitur | Status | Notes |
|-------|--------|-------|
| PPPoE Online Monitor | ✅ Production Ready | monitor:sync-pppoe scheduler (setiap menit) |
| ONT Monitoring (GenieACS) | ✅ Production Ready | genieacs:sync-onts scheduler |
| ONT Online/Offline | ✅ Production Ready | Route /ont-online, /ont-offline |
| Fiber Network Map | ✅ Exists | FiberMapController |
| ODP/ODC Management | ✅ Production Ready | OdcController, OdpController |
| Router Stats | ✅ Exists | RouterStatsController |

### 3.7 Helpdesk

| Fitur | Status | Notes |
|-------|--------|-------|
| Tiket | ✅ Production Ready | Full CRUD + replies + assignment |
| Work Order | ✅ Production Ready | Full CRUD + scheduled_at + completed_at |
| Technician Dashboard | ✅ Production Ready | Ranking, WO stats |

### 3.8 Finance

| Fitur | Status | Notes |
|-------|--------|-------|
| Cashflow | ✅ Production Ready | Income/expense, categories |
| Reseller | ✅ Production Ready | ResellerController + balance tracking |
| Hotspot | ✅ Production Ready | Voucher, profiles, RADIUS |

### 3.9 System

| Fitur | Status | Notes |
|-------|--------|-------|
| Activity Log | ✅ Production Ready | ActivityLogger + ActivityLogController |
| Telegram Bot/Group | ✅ Production Ready | TelegramBotController, TelegramGroupController |
| Database Settings | ✅ Production Ready | DatabaseSettingController + health check |
| ACS Config | ✅ Exists | config-acs route |

---

## 4. Billing Engine

### 4.1 Flow Invoice

```
Tanggal 1 setiap bulan
    ↓ billing:generate-monthly-invoices (scheduler 00:05)
    ↓ MonthlyInvoiceGenerationService::generate()
    ↓ Ambil semua service: status active/down/suspended/isolated
    ↓ Skip jika invoice periode sudah ada (idempotent)
    ↓ Ambil harga dari: service.monthly_price → package.monthly_price → 0
    ↓ Due date dari: install_date customer (hari ke-X bulan berjalan)
    ↓ Create invoice + sync billing_status service
    ↓
Harian 00:20 → billing:check-overdue-invoices
    ↓ Mark invoice overdue jika due_date < today
    ↓ Update service billing_status
    ↓
Harian 00:30 → billing:create-overdue-isolations
    ↓ Cari invoice overdue yang belum ada open isolation
    ↓ Buat service_isolation record
    ↓ Dispatch job → MikrotikAddressListService
    ↓ Tambah IP ke address-list ISOLIR_CUSTOMER di Mikrotik
    ↓
Payment Received → PaymentService::create()
    ↓ CashflowIncomeService::recordPayment()
    ↓ InvoiceIsolationAutomationService::syncForInvoice()
    ↓ Jika tidak ada overdue → release isolation
```

### 4.2 Invoice Payment Status

| Status | Kondisi |
|--------|---------|
| `unpaid` | Dibuat, belum diterbitkan |
| `issued` | Diterbitkan, belum jatuh tempo |
| `overdue` | Melewati due date |
| `partially_paid` | Ada pembayaran tapi belum lunas |
| `paid` | Lunas |
| `canceled` | Dibatalkan |

### 4.3 Service Overall Status

| Status | Kondisi |
|--------|---------|
| `provisioning` | Baru dibuat, belum bayar invoice pertama |
| `active` | Invoice pertama lunas, PPPoE aktif |
| `down` | Koneksi bermasalah |
| `suspended` | Disuspend admin |
| `isolated` | Di-isolir karena overdue |
| `inactive` | Tidak aktif |
| `terminated` | Diberhentikan |

### 4.4 Prepaid Flow (Sudah Diimplementasi)

```
Admin provision customer
    ↓ PPPoE secret dibuat di Mikrotik dengan disabled=true
    ↓ Invoice pertama dibuat dengan status issued
    ↓
Customer bayar → PaymentService
    ↓ Invoice marked as paid
    ↓ CashflowIncomeService record payment
    ↓ InvoiceIsolationAutomationService sync
    ↓ PPPoE enable (via separate trigger jika diperlukan)
```

---

## 5. Network & Provisioning

### 5.1 VID Assignment Flow

```
Admin input customer → Generate Username & Password
    ↓
POST /api/v1/admin/ip-pools/suggest?router_id=X
    ↓ SELECT FOR UPDATE (atomic)
    ↓ Filter: is_tracked=true, used_ips=0, dalam RouterScope.vid_start-vid_end
    ↓ Return VID
    ↓
Admin submit form (provisioning)
    ↓
CustomerController::provisioning()
    ↓ Buat Customer + Service di DB
    ↓ Create PPPoE di Mikrotik (disabled=true untuk prepaid)
    ↓ Confirm VID: reserved_by_customer_id = customer.id
    ↓ Generate invoice pertama
```

### 5.2 IP Pool Status Logic

| used_ips | free_ips | Status |
|----------|----------|--------|
| 0 | > 0 | **Available** — bisa untuk customer baru |
| ≥ 1 | > 0 | **Sudah dipakai** — ada customer |
| > 0 | 0 | **Full** — tidak bisa tambah customer |

### 5.3 Isolir Flow

```
Invoice overdue > 0 hari (same day)
    ↓ InvoiceAutoSuspendService::trigger()
    ↓ Cek apakah sudah ada open isolation
    ↓ Buat ServiceIsolation record (status=pending)
    ↓
ServiceIsolationService dispatch job
    ↓ MikrotikAddressListService
    ↓ Tambah IP/subnet ke address-list ISOLIR_CUSTOMER di Mikrotik
    ↓ Update status = applied
    ↓
Mikrotik Web Proxy redirect HTTP → http://[SERVER]/isolir?ip=[IP]
    ↓ Tampil halaman tagihan customer
    ↓
Admin konfirmasi bayar → mark invoice paid
    ↓ InvoiceIsolationAutomationService::syncForInvoice()
    ↓ Release isolir → hapus IP dari address-list
    ↓ PPPoE aktif kembali
```

---

## 6. Scheduler & Automation

| Schedule | Command | Fungsi |
|----------|---------|--------|
| Monthly on 1st, 00:05 | `billing:generate-monthly-invoices --queue` | Generate invoice bulanan |
| Daily, 00:20 | `billing:check-overdue-invoices --queue` | Mark invoice overdue |
| Daily, 00:30 | `billing:create-overdue-isolations --queue` | Buat isolir untuk overdue |
| Every 15 min | `mikrotik:sync-vids --queue` | Sync VID dari Mikrotik |
| Every minute | `monitor:sync-pppoe --queue` | Sync status PPPoE online |
| Every 5 min | `genieacs:sync-onts --queue` | Sync ONT dari GenieACS |
| Every minute | `notifications:process-telegram --queue` | Proses antrian Telegram |
| Every 5 min | `ip-pools:sync` | Sync IP pools dari Mikrotik |

**Timezone**: Asia/Jakarta (via `config/automation.schedule.timezone`)

---

## 7. API Endpoints Lengkap

### Customer
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | /api/v1/admin/customers | List with pagination |
| POST | /api/v1/admin/customers/onboard | Onboard (Customer + Service + WO + Invoice) |
| POST | /api/v1/admin/customers/provisioning | Add baru (provisioning flow) |
| POST | /api/v1/admin/customers/provisioning-preview | Preview provisioning |
| GET | /api/v1/admin/customers/{id} | Show |
| PUT | /api/v1/admin/customers/{id} | Update |
| DELETE | /api/v1/admin/customers/{id} | Delete |
| DELETE | /api/v1/admin/customers/{id}/terminate | Terminate + cleanup |
| POST | /api/v1/admin/customers/bulk-delete | Bulk delete |
| POST | /api/v1/admin/customers/bulk-disable | Bulk disable |
| POST | /api/v1/admin/customers/bulk-generate-invoice | Bulk generate invoice |
| GET | /api/v1/admin/customer-references | Dropdown data |

### Invoice
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | /api/v1/admin/invoices | List |
| POST | /api/v1/admin/invoices | Create manual |
| GET | /api/v1/admin/invoices/{id} | Show |
| PUT | /api/v1/admin/invoices/{id} | Update |
| DELETE | /api/v1/admin/invoices/{id} | Delete |
| POST | /api/v1/admin/invoices/generate-monthly | Generate bulanan |
| POST | /api/v1/admin/invoices/{id}/mark-paid | Bayar |
| POST | /api/v1/admin/invoices/{id}/mark-overdue | Mark overdue |
| POST | /api/v1/admin/invoices/{id}/send-whatsapp | Send WhatsApp |
| GET | /api/v1/admin/invoices/{id}/pdf | Download PDF |
| POST | /api/v1/admin/invoices/bulk-action | Bulk action |
| POST | /api/v1/admin/invoices/auto-suspend | Auto suspend |
| GET | /api/v1/admin/invoices/overdue | List overdue |
| GET | /api/v1/admin/invoices/paid | List paid |
| GET | /api/v1/admin/invoices/unpaid | List unpaid |

### IP Pool
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | /api/v1/admin/ip-pools | List (dari DB cache) |
| GET | /api/v1/admin/ip-pools/suggest | Suggest VID (atomic) |
| GET | /api/v1/admin/ip-pools/preview | Preview live Mikrotik |
| POST | /api/v1/admin/ip-pools/save-selection | Simpan pilihan tracked pools |
| POST | /api/v1/admin/ip-pools/sync | Manual sync ke DB |
| GET | /api/v1/admin/routers/{id}/ip-pools | IP pools per router |
| GET | /api/v1/admin/routers/{id}/ip-pools/summary | Summary |
| GET | /api/v1/admin/routers/{id}/ip-pools/utilization | Utilization |
| GET | /api/v1/admin/routers/{id}/ip-pools/suggest-for-vid | Suggest for VID |
| GET | /api/v1/admin/routers/{id}/ip-pools/vids-with-availability | VIDs with availability |

### Network
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET/POST | /api/v1/admin/locations | CRUD Master Lokasi |
| GET/POST/PUT/DELETE | /api/v1/admin/network-locations | CRUD Master Lokasi |
| GET/POST/PUT/DELETE | /api/v1/admin/olts | CRUD Master OLT |
| GET | /api/v1/admin/olts/{id}/pon-status | PON status |
| GET/POST/PUT/DELETE | /api/v1/admin/olts/{olt}/pon-ports | CRUD PON Ports |
| GET/POST/PUT/DELETE | /api/v1/admin/routers | CRUD Router |
| POST | /api/v1/admin/routers/{id}/test-connection | Test connection |
| POST | /api/v1/admin/routers/{id}/test-acs | Test ACS |
| POST | /api/v1/admin/routers/{id}/sync-ont | Sync ONT |
| GET/POST/PUT/DELETE | /api/v1/admin/router-scopes | CRUD Router Scopes |
| GET | /api/v1/admin/mikrotik/pppoe-servers | PPPoE servers dari Mikrotik |

### Isolir
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | /api/v1/admin/service-isolations | List history |
| GET | /api/v1/admin/service-isolations/suggestions | Suggestions |
| POST | /api/v1/admin/service-isolations | Buat manual |
| PATCH | /api/v1/admin/service-isolations/{id}/applied | Mark applied |
| PATCH | /api/v1/admin/service-isolations/{id}/release | Release |
| POST | /api/v1/admin/isolir/manual | Manual isolir |
| POST | /api/v1/admin/isolir/release | Manual release |

### Monitoring
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | /api/v1/admin/onts | List ONT |
| GET | /api/v1/admin/onts/online | ONT online |
| GET | /api/v1/admin/onts/offline | ONT offline |
| GET | /api/v1/admin/monitoring/pppoe | PPPoE monitoring |

### Helpdesk
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET/POST | /api/v1/admin/tickets | List/Create |
| GET | /api/v1/admin/tickets/{id} | Show |
| PATCH | /api/v1/admin/tickets/{id}/status | Update status |
| POST | /api/v1/admin/tickets/{id}/replies | Add reply |
| GET/POST | /api/v1/admin/work-orders | List/Create |
| GET | /api/v1/admin/work-orders/{id} | Show |

### Finance
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | /api/v1/admin/cashflows | List |
| POST | /api/v1/admin/cashflows | Create |
| GET | /api/v1/admin/cashflows/summary | Summary |

---

## 8. GAP Analysis — Semua Fitur

### 🔴 CRITICAL (Harus difix sebelum production)

| # | Gap | File | Impact | Status |
|---|-----|------|--------|--------|
| 1 | `monthly_price` null → invoice amount = 0 | MonthlyInvoiceGenerationService.php | Billing tidak berjalan | ⚠️ Ada 1 service dengan monthly_price, 254 null |
| 2 | **PPPoE `service` field = `any`** | MikrotikPppoeSecretService.php | PPPoE tidak spesifik ke service pppoe | ⚠️ Perlu dicek di field pppoe_isolation_profile |
| 3 | Billing engine butuh data price yang valid | ServiceProvisioning |monthly_price harus di-input saat provisioning | ⚠️ Form provisioning perlu validasi |

### 🟡 HIGH (Penting untuk bisnis)

| # | Gap | File | Impact | Status |
|---|-----|------|--------|--------|
| 4 | PDF Invoice — prompt sudah ada | InvoiceController::downloadPdf() | Customer tidak bisa cetak invoice | ⚠️ Route exists, perlu test |
| 5 | Prepaid: PPPoE enable saat bayar | InvoiceIsolationAutomationService | Customer bisa konek sebelum bayar | ⚠️ Service exists, perlu trigger |
| 6 | Notification Telegram config | TelegramBotController | Tidak ada notif otomatis | ⚠️ Backend ready, perlu setup bot |
| 7 | Customer detail — Tanggal Jatuh Tempo | CustomerResource | Info kurang | ⚠️ Perlu ditampilkan dari invoice |

### 🟢 MEDIUM (Nice to have)

| # | Gap | File | Impact | Status |
|---|-----|------|--------|--------|
| 8 | Tiket & Work Order UI | module.blade.php | Fitur helpdesk tidak bisa dipakai | ⚠️ Backend ready, UI perlu dicek |
| 9 | Reseller UI | ResellerController | - | ⚠️ Backend ready |
| 10 | Hotspot UI | HotspotVoucherController | - | ⚠️ Backend ready |
| 11 | Halaman isolir tidak detect PPPoE IP | IsolirPageController | Hanya detect static IP | ⚠️ Perlu enhancement |
| 12 | Audit log UI | ActivityLogController | - | ⚠️ API ready, UI belum dicek |
| 13 | Export invoice CSV/Excel | - | - | ❌ Belum ada |

### ⚪ LOW (Improvement)

| # | Gap | File | Impact | Status |
|---|-----|------|--------|--------|
| 14 | Dashboard customization | AdminDashboardController | - | ⚠️ Basic exists |
| 15 | Report bulanan lengkap | - | - | ❌ Belum ada |
| 16 | Multi-user concurrent edit | - | Conflict possibility | ⚠️ Sudah ada lockForUpdate |
| 17 | Backup & restore | - | Disaster recovery | ❌ Belum ada |

---

## 9. TODO Prioritas

### Sprint 1 — Billing Fix (Critical)
- [ ] **Validasi monthly_price** di provisioning form — tidak boleh null/0
- [ ] **Test invoice generation** — cek apakah subtotal sesuai dengan monthly_price
- [ ] **Test overdue scheduler** — cek apakah invoice tertandai overdue tepat waktu
- [ ] **Test isolir automation** — cek apakah IP masuk address-list saat overdue

### Sprint 2 — PDF & Payment Flow
- [ ] **Test PDF Invoice** — download dan cek format
- [ ] **Test payment mark paid** — cek cashflow integration
- [ ] **Prepaid activation** — PPPoE enable trigger saat invoice paid
- [ ] **WhatsApp notification** — test kirim invoice

### Sprint 3 — Helpdesk UI
- [ ] **Tiket UI** — form tambah, list, detail, reply
- [ ] **Work Order UI** — form, assign teknisi, update status
- [ ] **Technician dashboard** — stats dan ranking

### Sprint 4 — Reporting & Export
- [ ] **Export invoice CSV**
- [ ] **Laporan pendapatan bulanan**
- [ ] **Dashboard summary**
- [ ] **Audit log viewer**

---

## 10. Setup & Deployment Notes

### Environment Variables Wajib

```env
APP_NAME="Feralix ISP Cloud"
APP_URL=http://[SERVER_IP]:6733

# Database
DB_CONNECTION=mysql
DB_SOCKET=/var/run/mysqld/mysqld.sock
DB_DATABASE=feralix_billing

# Queue
QUEUE_CONNECTION=database

# Timezone Billing (SUDAH DIKONFIGURASI)
AUTOMATION_SCHEDULE_TIMEZONE=Asia/Jakarta

# Company Info (untuk PDF & Isolir page)
COMPANY_NAME="Nama ISP Anda"
COMPANY_ADDRESS="Alamat Lengkap"
COMPANY_PHONE="08xxxxxxxxxx"
COMPANY_EMAIL="billing@isp.com"

# WhatsApp Gateway
WHATSAPP_API_KEY=
WHATSAPP_DEVICE=

# GenieACS
GENIEACS_URL=http://localhost:7557
GENIEACS_USERNAME=admin
GENIEACS_PASSWORD=
```

### Jalankan Queue Worker

```bash
php artisan queue:work --queue=default,billing,provisioning,notifications --sleep=3 --tries=3
```

### Setup Scheduler (crontab)

```bash
* * * * * cd /var/www/feralix-billing && php artisan schedule:run >> /dev/null 2>&1
```

### Setup Web Proxy Isolir di Mikrotik

```
/ip proxy set enabled=yes port=8080 max-cache-size=none
/ip firewall nat add chain=dstnat src-address-list=ISOLIR_CUSTOMER protocol=tcp dst-port=80 action=redirect to-ports=8080 comment="Isolir redirect"
/ip proxy access add dst-port=80 action=deny redirect-to=http://[SERVER_IP]:6733/isolir
```

### Setup GenieACS (untuk ONT Monitoring)

```bash
# Install GenieACS
docker run -d --name genieacs -p 7557:7547 -p 7558:7548 -p 7559:3000 dparrelli/genieacs
```

### Database Migration Status

Total 34 migrations, latest:
- `2026_05_09_093917_create_ip_pool_snapshots_table.php`
- `2026_05_09_100419_add_reserved_by_customer_id_to_ip_pool_snapshots_table.php`
- `2026_05_09_104307_add_install_date_to_customers_table.php`

---

## Status Data Sistem (2026-05-09)

| Entity | Count | Notes |
|--------|-------|-------|
| Customers | 551 | - |
| Services | 255 | - |
| Invoices | 1 | Baru mulai, perlu generate bulanan |
| IP Pool Snapshots | 268 | 268 tracked pools |
| Routers | 1 | - |
| Technicians | 1 | - |
| Services dengan monthly_price | 1 | 254 perlu di-assign |
| Overdue Invoices | 0 | - |
| Open Isolations | 0 | - |
| Timezone Config | Asia/Jakarta | ✅ |

---

*Dokumentasi ini di-generate secara otomatis dari analisis source code Feralix Billing.*
*Generated: 2026-05-09*