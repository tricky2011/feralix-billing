# Feralix Billing — Dokumentasi Lengkap

> Sistem manajemen billing dan provisioning terintegrasi untuk penyedia layanan internet (ISP) berbasis FTTH dan Hotspot WiFi.

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Teknologi Stack](#2-teknologi-stack)
3. [Struktur Direktori](#3-struktur-direktori)
4. [Konfigurasi Environment](#4-konfigurasi-environment)
5. [Database Schema](#5-database-schema)
6. [Multi-Router (8 Router)](#6-multi-router-8-router)
7. [Manajemen VLAN ID](#7-manajemen-vlan-id)
8. [Hotspot & RADIUS Antar Router](#8-hotspot--radius-antar-router)
9. [Manajemen Customer Baru](#9-manajemen-customer-baru)
10. [Sistem Billing](#10-sistem-billing)
11. [API Endpoints](#11-api-endpoints)
12. [Otomasi & Penjadwalan](#12-otomasi--penjadwalan)
13. [Bug yang Telah Diperbaiki](#13-bug-yang-telah-diperbaiki)
14. [Panduan Deployment](#14-panduan-deployment)

---

## 1. Gambaran Umum

**Feralix Billing** adalah platform manajemen ISP all-in-one yang mengintegrasikan:

- **Billing**: Invoice otomatis bulanan, pelacakan pembayaran, cashflow
- **Provisioning**: Manajemen service FTTH/PPPoE/VLAN per pelanggan
- **Network**: Inventaris OLT/ONT, VLAN, IP Pool di banyak router (hingga 8+)
- **Hotspot**: Sistem voucher WiFi dengan RADIUS terpusat (FreeRADIUS)
- **Monitoring**: Status PPPoE real-time, isolasi bandwidth otomatis
- **Support**: Ticket helpdesk, work order teknisi, notifikasi Telegram

### Alur Bisnis Utama

```
Pelanggan Baru → Onboarding → Provisioning VLAN/PPPoE → Service Aktif
     ↓
Invoice Bulanan → Reminder → Jatuh Tempo → Isolasi Otomatis → Bayar → Release
     ↓
Monitoring → Ticket Support → Work Order Teknisi
```

---

## 2. Teknologi Stack

| Komponen | Teknologi | Versi |
|----------|-----------|-------|
| Framework | Laravel | 12.x |
| Runtime | PHP | 8.2+ |
| Database | MySQL / MariaDB | 8+ / 10.6+ |
| ORM | Eloquent | — |
| Auth | Laravel Sanctum | — |
| Queue | Laravel Queue | sync / redis |
| Frontend | Alpine.js | 3.14+ |
| Styling | Tailwind CSS | 4.0 |
| Build | Vite | 7.0+ |
| HTTP Client | Axios | 1.11+ |
| PDF | barryvdh/laravel-dompdf | 3.1 |
| RADIUS | FreeRADIUS + MySQL | — |
| Router | MikroTik RouterOS | v6 / v7 |
| ONT/ACS | GenieACS | — |

---

## 3. Struktur Direktori

```
feralix-billing/
├── app/
│   ├── Actions/Mikrotik/           # Action classes (VLAN conflict detection)
│   ├── Console/Commands/           # Artisan commands (billing, sync, monitor)
│   ├── Contracts/                  # Interfaces (HotspotRadiusProvider, MikrotikApiClient, dll)
│   ├── Data/                       # Data Transfer Objects (DTO)
│   ├── Enums/                      # Enumerasi status dan tipe
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Admin/              # 40+ controller admin panel
│   │   │   ├── Auth/               # Login, logout, me
│   │   │   └── Internal/           # Endpoint hotspot RADIUS (tanpa auth)
│   │   ├── Requests/               # Form request & validasi per fitur
│   │   └── Resources/              # API resource transformer
│   ├── Models/                     # 48 Eloquent model
│   ├── Services/
│   │   ├── Billing/                # Invoice, payment, overdue, cashflow
│   │   ├── Customer/               # Onboarding, terminasi, bulk action
│   │   ├── Hotspot/                # Voucher, RADIUS, FreeRADIUS sync
│   │   ├── Mikrotik/               # API client, VID sync, IP pool, PPPoE
│   │   ├── Network/                # Router sync service
│   │   ├── Provisioning/           # Isolasi, PPPoE credential, state manager
│   │   └── ...
│   └── Providers/                  # Service provider (App, Hotspot, Mikrotik, GenieAcs)
├── config/
│   ├── hotspot.php                 # Konfigurasi RADIUS provider
│   ├── mikrotik.php                # Konfigurasi MikroTik API & sync
│   ├── billing.php                 # Konfigurasi billing & isolasi
│   └── automation.php              # Jadwal otomasi
├── database/migrations/            # 62+ migration file
├── resources/
│   ├── js/                         # Alpine.js komponen frontend
│   └── views/                      # Blade template (admin panel, PDF)
├── routes/
│   ├── api.php                     # REST API routes
│   └── web.php                     # Web routes (SPA)
└── tests/                          # Feature & Unit tests
```

---

## 4. Konfigurasi Environment

Salin `.env.example` ke `.env` dan sesuaikan:

```env
# === Aplikasi ===
APP_NAME="Feralix Billing"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.domain.anda.com

# === Database Utama ===
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=feralix_billing
DB_USERNAME=feralix_user
DB_PASSWORD=password_aman

# === Sanctum (Token Auth) ===
SANCTUM_TOKEN_EXPIRATION=10080        # 7 hari dalam menit

# === Queue & Cache ===
QUEUE_CONNECTION=database             # gunakan 'redis' untuk production
CACHE_STORE=file                      # gunakan 'redis' untuk production

# === Hotspot RADIUS ===
HOTSPOT_RADIUS_PROVIDER=freeradius-sql          # atau 'stub' untuk development
HOTSPOT_RADIUS_INTERNAL_SECRET=rahasia_kuat     # shared secret internal
HOTSPOT_RADIUS_EXPIRED_REDIRECT_URL=https://portal.domain.anda.com/expired?u={username}&r={reason}
HOTSPOT_DEFAULT_NAS_SECRET=mikrotik_nas_secret

# === MikroTik ===
MIKROTIK_SYNC_PROVIDER=routeros-api   # atau 'fake' untuk development
MIKROTIK_IP_POOL_PROVIDER=routeros-api

# === GenieACS (ONT provisioning) ===
GENIEACS_SYNC_PROVIDER=http           # atau 'fake' untuk development

# === Otomasi Billing ===
AUTOMATION_SCHEDULE_TIMEZONE=Asia/Jakarta
AUTOMATION_SCHEDULE_GENERATE_INVOICES_AT=00:05   # jam generate invoice
AUTOMATION_SCHEDULE_CHECK_OVERDUE_AT=00:20        # jam cek overdue
AUTOMATION_SCHEDULE_CREATE_ISOLATION_AT=00:30     # jam buat isolasi

# === Billing Config ===
BILLING_MONTHLY_INVOICE_DUE_IN_DAYS=10
BILLING_MONTHLY_INVOICE_PENALTY_AMOUNT=0

# === FTTH Isolasi ===
FTTH_ISOLATION_REDIRECT_URL=http://isolir.domain.anda.com
```

---

## 5. Database Schema

### Tabel Utama

#### `routers` — Router MikroTik
```sql
id, router_code, router_name, router_role
host, mgmt_ip, api_port, api_username, api_password (encrypted)
ros_version, use_ssl, timeout
use_rest_api, rest_port, rest_tls
acs_inform_url, acs_nbi_url, acs_username, acs_password (encrypted)
status, is_active, description, location_name
```

#### `router_scopes` — Rentang VLAN per Router
```sql
id, router_id, scope_name, monitor_vid
vid_start, vid_end, is_special, notes
```

#### `vids` — VLAN Interface Detail
```sql
id, router_id, scope_id
vid (integer), vid_type (monitor/internet/management)
subnet_cidr, gateway_ip, pool_start_ip, pool_end_ip, pool_ip_count
rate_limit_mbps, sync_source
status (available/reserved/assigned/full)
customer_id, service_id, last_synced_at
```

#### `customers` — Pelanggan
```sql
id, customer_code, full_name, phone, contact, email, address
location_id, network_location_id, preferred_olt_id, assigned_technician_id
latitude, longitude, customer_type (individual/business)
status (active/suspended/terminated)
install_date, billing_day, ip_count, monthly_price
pppoe_username, pppoe_password, notes
```

#### `services` — Layanan Pelanggan
```sql
id, customer_id, package_id, router_id, olt_id, ont_id, vid_id
service_code, monitor_vid, internet_vid
subnet_cidr, gateway_ip, dhcp_pool_start, dhcp_pool_end, ip_pool_count
access_mode (vlan/pppoe/static/hotspot), rate_limit_mbps
pppoe_username, pppoe_password
static_ip_address, static_mac_address, static_queue_name
isolation_method (address_list/queue), address_list_name
billing_status, network_status, overall_status
activation_date, monthly_price, notes, deleted_at
```

#### `invoices` — Invoice Billing
```sql
id, customer_id, service_id, invoice_number
billing_period (YYYY-MM), invoice_date, due_date
subtotal, penalty_amount, total_amount
payment_status (unpaid/issued/overdue/paid/partially_paid/canceled)
issued_at, paid_at, overdue_marked_at, whatsapp_sent_at
deleted_at
UNIQUE: (service_id, billing_period) WHERE deleted_at IS NULL
```

#### `hotspot_vouchers` — Voucher Hotspot
```sql
id, batch_id, reseller_id, hotspot_profile_id
username, password (encrypted), password_plain
voucher_code, locked_mac, first_login_at, expires_at
bytes_used, status (generated/active/expired)
```

#### `hotspot_services` — Voucher ↔ Router Binding
```sql
id, hotspot_voucher_id, router_id, hotspot_username
mikrotik_user_id, status, sync_error
UNIQUE: (hotspot_voucher_id, router_id)
```

#### FreeRADIUS Tables
```sql
radcheck  (username, attribute, op, value)   -- kredensial auth
radreply  (username, attribute, op, value)   -- atribut balasan
radusergroup (username, groupname, priority) -- grup profil
radacct   (acctuniqueid, acctsessionid, ...)  -- akuntansi sesi
```

---

## 6. Multi-Router (8 Router)

Sistem mendukung **unlimited router** (didesain untuk 8+ router) tanpa batasan hardcode.

### Cara Menambah Router

1. **Via API:**
```bash
POST /api/v1/admin/routers
{
  "name": "Router-8-Kelapa-Gading",
  "host": "192.168.8.1",
  "api_port": 8728,
  "api_username": "admin",
  "api_password": "password",
  "ros_version": "7",
  "use_rest_api": true,
  "rest_port": 443,
  "rest_tls": true,
  "router_role": "access",
  "location_name": "Kelapa Gading",
  "status": "active"
}
```

2. **Definisikan Router Scope (Rentang VLAN):**
```bash
POST /api/v1/admin/router-scopes
{
  "router_id": 8,
  "scope_name": "KG-INTERNET",
  "monitor_vid": 100,
  "vid_start": 200,
  "vid_end": 500
}
```

### Arsitektur Multi-Router

```
┌─────────────────────────────────────────────┐
│              Feralix Billing                │
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │ Router 1 │  │ Router 2 │  │ Router 8 │  │
│  │ (Core)   │  │ (Access) │  │ (Access) │  │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  │
│       │              │              │        │
│  [VID Scope]   [VID Scope]   [VID Scope]    │
│  [IP Pool ]    [IP Pool ]    [IP Pool ]     │
│  [Services]    [Services]    [Services]     │
└─────────────────────────────────────────────┘
```

### Role-Based Router Access

User dapat dibatasi hanya mengakses router tertentu:

```
POST /api/v1/admin/users/{id}/router-assignments
{
  "router_ids": [1, 3, 5]  // user hanya lihat data router 1, 3, 5
}
```

Tabel `user_router_assignments` menyimpan binding user ↔ router.

### Sync Data dari Router

```bash
# Sync VLAN dari MikroTik
POST /api/v1/admin/router-sync/all

# Sync VID (VLAN) spesifik
POST /api/v1/admin/routers/{id}/sync-ont

# Test koneksi ke router
POST /api/v1/admin/routers/{id}/test-connection

# Deteksi versi RouterOS
POST /api/v1/admin/routers/{id}/detect-version
```

---

## 7. Manajemen VLAN ID

### Konsep VLAN dalam Sistem

Setiap VID (VLAN ID) dikelola per router dan per scope:

```
Router → Router Scope (rentang VID) → VID (VLAN individual)
                                         ↓
                                   Customer Service
```

### VID Type

| Type | Keterangan |
|------|-----------|
| `monitor` | VLAN untuk monitoring PPPoE pelanggan |
| `internet` | VLAN untuk akses internet pelanggan |
| `management` | VLAN untuk manajemen jaringan |

### VID Status

| Status | Keterangan |
|--------|-----------|
| `available` | Siap diassign ke pelanggan |
| `reserved` | Dipesan, belum diassign |
| `assigned` | Sudah dipakai pelanggan aktif |
| `full` | Pool IP habis |

### Pengelolaan VID via API

```bash
# List semua VLAN di router tertentu (urut by vid ASC)
GET /api/v1/admin/vids?router_id=3&status=available&vid_type=internet

# Buat VID baru
POST /api/v1/admin/vids
{
  "router_id": 3,
  "scope_id": 7,
  "vid": 210,
  "vid_type": "internet",
  "subnet_cidr": "10.10.210.0/29",
  "gateway_ip": "10.10.210.1",
  "pool_start_ip": "10.10.210.2",
  "pool_end_ip": "10.10.210.6",
  "pool_ip_count": 5,
  "rate_limit_mbps": 20,
  "status": "available"
}

# Update VID
PATCH /api/v1/admin/vids/{id}

# Hapus VID (hanya jika status bukan assigned)
DELETE /api/v1/admin/vids/{id}

# Sync VID dari MikroTik RouterOS
POST /api/v1/admin/router-sync/all
```

### Deteksi Konflik VID

Sistem otomatis mendeteksi konflik VID antar scope:
- Artisan command: `php artisan audit:vid-assignments`
- Action class: `DetectMikrotikVidConflictAction`

### Rekomendasi Konfigurasi 8 Router

```
Router 1 (Core/Gateway)    → Scope: CORE-MGMT   → VID: 100-199 (monitoring)
Router 2 (Access North)    → Scope: NORTH-INT    → VID: 200-399 (internet)
Router 3 (Access South)    → Scope: SOUTH-INT    → VID: 400-599
Router 4 (Access East)     → Scope: EAST-INT     → VID: 600-799
Router 5 (Access West)     → Scope: WEST-INT     → VID: 800-999
Router 6 (Access Center)   → Scope: CENTER-INT   → VID: 1000-1199
Router 7 (Access Suburb 1) → Scope: SUB1-INT     → VID: 1200-1399
Router 8 (Access Suburb 2) → Scope: SUB2-INT     → VID: 1400-1599
```

> **Penting**: Setiap router scope memiliki rentang VID yang tidak overlap untuk menghindari konflik.

---

## 8. Hotspot & RADIUS Antar Router

### Arsitektur Hotspot Multi-Router

```
┌──────────────┐      RADIUS Auth       ┌───────────────────┐
│  MikroTik    │ ─────────────────────► │  FreeRADIUS Server│
│  Router 1-8  │      (port 1812)       │                   │
│  (NAS)       │ ◄─────────────────────  │  reads: radcheck  │
└──────┬───────┘   Access-Accept/Reject  │  reads: radreply  │
       │                                └────────┬──────────┘
       │ Acct Packet                             │
       ▼ (port 1813)                             │ SQL
┌──────────────┐                        ┌────────▼──────────┐
│ /v1/internal │                        │  MySQL Database   │
│ /hotspot-    │                        │  radcheck         │
│ radius/acct  │                        │  radreply         │
└──────┬───────┘                        │  radusergroup     │
       │                                │  radacct          │
       ▼                                └───────────────────┘
┌──────────────┐
│ Feralix      │
│ Billing DB   │ ← tracks voucher usage, sessions, events
└──────────────┘
```

### Flow Autentikasi Hotspot

1. Pelanggan membuka browser → MikroTik redirect ke halaman login hotspot
2. Pelanggan masuk voucher username + password
3. MikroTik kirim RADIUS Access-Request ke FreeRADIUS (port 1812)
4. FreeRADIUS query `radcheck` → validasi Cleartext-Password
5. FreeRADIUS kembalikan Access-Accept + atribut dari `radreply`
6. MikroTik izinkan akses internet
7. MikroTik kirim RADIUS Accounting-Request (Start/Interim/Stop) ke endpoint internal

### Endpoint RADIUS Internal

```
POST /api/v1/internal/hotspot-radius/authorize
POST /api/v1/internal/hotspot-radius/accounting
```

Endpoint ini **tidak memerlukan autentikasi** (diakses oleh FreeRADIUS/NAS langsung).

### Konfigurasi NAS (Router) di Sistem

```bash
# Tambah NAS (router sebagai RADIUS client)
POST /api/v1/admin/nas
{
  "nas_name": "Router-1-Core",
  "nas_ip": "192.168.1.1",
  "nas_secret": "radius_secret",
  "nas_type": "mikrotik"
}
```

### Aktivasi Voucher pada Router

```bash
# Aktifkan voucher di SATU router
POST /api/v1/admin/hotspot-router/activate
{
  "voucher_id": 123,
  "router_id": 3
}

# Aktifkan voucher di SEMUA router aktif (cross-router SSO)
POST /api/v1/admin/hotspot-router/activate-all
{
  "voucher_id": 123
}

# Nonaktifkan dari router tertentu
DELETE /api/v1/admin/hotspot-router/deactivate
{
  "voucher_id": 123,
  "router_id": 3
}

# Sync ulang ke FreeRADIUS (setelah profil berubah)
POST /api/v1/admin/hotspot-router/sync-radius
{
  "voucher_id": 123
}
```

### Cross-Router SSO

Satu voucher dapat digunakan di **banyak router sekaligus**:
- FreeRADIUS adalah backend auth terpusat — satu entry untuk semua NAS
- Setiap router punya record `HotspotService` tersendiri
- Jika voucher di-deactivate dari semua router → FreeRADIUS entry dihapus

### Profil Hotspot

```bash
# Buat profil voucher
POST /api/v1/admin/hotspot-profiles
{
  "profile_name": "1DAY-UNLIMITED",
  "validity_mode": "time",
  "validity_days": 1,
  "data_limit_bytes": null,
  "price": 5000,
  "selling_price": 7000,
  "user_lock": "mac",       # 'none' atau 'mac' (lock per perangkat)
  "expired_mode": "remove", # 'remove', 'disable', atau 'keep'
  "is_active": true
}
```

### Generate & Distribusi Voucher

```bash
# Generate batch voucher
POST /api/v1/admin/voucher-batches
{
  "profile_id": 1,
  "quantity": 100,
  "reseller_id": 5
}

# List voucher (dengan status, expired, dll)
GET /api/v1/admin/hotspot-vouchers?status=generated&batch_id=10
```

---

## 9. Manajemen Customer Baru

### Flow Onboarding Customer

#### Step 1: Onboard Lengkap (Customer + Service + Work Order)

```bash
POST /api/v1/admin/customers/onboard
{
  # Data Customer
  "customer_code": "CUST-2026-001",
  "full_name": "Budi Santoso",
  "phone": "08123456789",
  "contact": "08123456789",
  "email": "budi@email.com",
  "address": "Jl. Merdeka No. 1, Jakarta",
  "network_location_id": 2,
  "customer_type": "individual",
  "install_date": "2026-05-15",
  "notes": "Pelanggan baru area utara",

  # Data Service
  "package_id": 3,
  "router_id": 2,
  "access_mode": "vlan",
  "vid_id": 215,
  "monthly_price": 250000,

  # Work Order untuk teknisi
  "scheduled_date": "2026-05-17",
  "assigned_technician_id": 4
}
```

#### Step 2: Preview Provisioning (Opsional)

```bash
POST /api/v1/admin/customers/provisioning-preview
{
  "router_id": 2,
  "package_id": 3,
  "access_mode": "vlan"
}
# Response: saran VID tersedia, IP pool, estimasi subnet
```

#### Step 3: Provisioning Manual (Jika Perlu)

```bash
POST /api/v1/admin/customers/provisioning
{
  "service_id": 45,
  "provision_type": "vlan"  # atau 'pppoe', 'static'
}
```

### CRUD Customer

```bash
# List pelanggan (dengan filter, search, sort)
GET /api/v1/admin/customers?search=budi&status=active&router_id=2&per_page=20

# Buat customer baru
POST /api/v1/admin/customers

# Lihat detail (termasuk riwayat service)
GET /api/v1/admin/customers/{id}

# Update customer
PATCH /api/v1/admin/customers/{id}

# Terminasi customer (menonaktifkan semua service)
DELETE /api/v1/admin/customers/{id}/terminate
```

### Status Customer

| Status | Keterangan |
|--------|-----------|
| `active` | Customer aktif, service berjalan |
| `suspended` | Dibekukan sementara |
| `terminated` | Dihentikan permanen |

### Tipe Akses Service

| Mode | Keterangan |
|------|-----------|
| `vlan` | VLAN statik per subnet (/29 atau /30) |
| `pppoe` | Dial-up PPPoE username/password |
| `static` | IP statik dengan MAC binding |
| `hotspot` | Voucher-based WiFi hotspot |

### Referensi Data untuk Form Customer

```bash
GET /api/v1/admin/customer-references
# Response: daftar router, package, OLT, teknisi, lokasi jaringan
```

---

## 10. Sistem Billing

### Siklus Invoice Bulanan

```
Awal Bulan (00:05)
    │
    ▼
GenerateMonthlyInvoicesCommand
    │ → Cek service aktif (status: active/down/suspended/isolated)
    │ → Skip jika invoice periode ini sudah ada
    │ → Hitung harga (service.monthly_price atau package.price)
    │ → Set due_date berdasarkan install_date pelanggan
    │ → Create Invoice (status: issued)
    ▼
00:20 → CheckOverdueInvoicesCommand
    │ → Tandai invoice melewati due_date sebagai 'overdue'
    ▼
00:30 → CreateOverdueServiceIsolationCommand
    │ → Buat ServiceIsolation untuk service bertagihan overdue
    │ → Isolasi diterapkan di router (address list / queue)
    ▼
Pelanggan Bayar
    │
    ▼
POST /api/v1/admin/payments
    │ → Catat pembayaran
    │ → Update invoice status → 'paid'
    │ → Release isolasi di router
    │ → Update service billing_status → 'active'
```

### API Invoice

```bash
# Generate invoice bulanan
POST /api/v1/admin/invoices/generate-monthly
{
  "billing_period": "2026-05",
  "invoice_date": "2026-05-01",
  "due_in_days": 10,
  "penalty_amount": 0,
  "router_id": 2  # opsional, filter per router
}

# Generate invoice manual
POST /api/v1/admin/invoices/manual-generate
{
  "customer_id": 45,
  "service_id": 67,
  "billing_period": "2026-05",
  "invoice_date": "2026-05-01",
  "due_date": "2026-05-15",
  "subtotal": 250000,
  "penalty_amount": 0
}

# Tandai sebagai dibayar
PATCH /api/v1/admin/invoices/{id}/mark-paid

# Tandai overdue manual
PATCH /api/v1/admin/invoices/{id}/mark-overdue

# Download PDF invoice
GET /api/v1/admin/invoices/{id}/pdf

# Kirim reminder WhatsApp
POST /api/v1/admin/invoices/{id}/send-whatsapp

# Auto-suspend service yang overdue
POST /api/v1/admin/invoices/auto-suspend
```

### Pembayaran

```bash
POST /api/v1/admin/payments
{
  "invoice_id": 123,
  "amount_paid": 250000,
  "payment_method": "transfer",
  "reference_no": "TRX2026050001",
  "paid_at": "2026-05-10 14:30:00",
  "notes": "BCA Transfer"
}
```

### Isolasi Layanan (Bandwidth Control)

```bash
# Daftar saran isolasi (overdue belum diisolasi)
GET /api/v1/admin/service-isolations/suggestions

# Buat isolasi billing
POST /api/v1/admin/service-isolations
{
  "service_id": 45,
  "isolation_type": "billing_isolation",
  "notes": "Invoice 2026-05 overdue"
}

# Isolasi manual (bukan billing)
POST /api/v1/admin/isolir/manual
{
  "service_id": 45,
  "reason": "Penggunaan tidak wajar"
}

# Release isolasi
PATCH /api/v1/admin/service-isolations/{id}/release

# Release manual
POST /api/v1/admin/isolir/release
{
  "service_id": 45
}
```

---

## 11. API Endpoints

### Base URL
```
/api/v1
```

### Autentikasi

```bash
# Login
POST /api/v1/auth/login
{ "username": "admin", "password": "password" }
# Response: { "token": "..." }

# Semua request admin perlu header:
Authorization: Bearer {token}
```

### Ringkasan Endpoint

| Grup | Prefix | Keterangan |
|------|--------|-----------|
| Auth | `/auth` | Login, logout, me |
| Router | `/admin/routers` | CRUD + test + sync |
| Router Scope | `/admin/router-scopes` | Rentang VLAN per router |
| VLAN (VID) | `/admin/vids` | CRUD VLAN ID |
| IP Pool | `/admin/ip-pools` | CRUD + sync + suggest |
| Customer | `/admin/customers` | CRUD + onboard + bulk |
| Service | `/admin/services` | CRUD service pelanggan |
| Package | `/admin/packages` | CRUD paket layanan |
| Invoice | `/admin/invoices` | Generate + mark + PDF |
| Payment | `/admin/payments` | Catat pembayaran |
| Cashflow | `/admin/cashflows` | Laporan keuangan |
| OLT | `/admin/olts` | CRUD OLT |
| ONT | `/admin/onts` | CRUD + online/offline |
| Hotspot Profile | `/admin/hotspot-profiles` | CRUD profil voucher |
| Hotspot Voucher | `/admin/hotspot-vouchers` | CRUD + aktivasi |
| Voucher Batch | `/admin/voucher-batches` | Generate batch |
| Hotspot Router | `/admin/hotspot-router` | Aktivasi per router |
| Reseller | `/admin/resellers` | CRUD reseller |
| Ticket | `/admin/tickets` | CRUD support ticket |
| Work Order | `/admin/work-orders` | CRUD work order |
| User | `/admin/users` | CRUD user sistem |
| Activity Log | `/admin/activity-logs` | Log aktivitas |
| Dashboard | `/admin/dashboard` | Statistik utama |
| RADIUS (internal) | `/internal/hotspot-radius` | Authorize + accounting |

---

## 12. Otomasi & Penjadwalan

### Jadwal Otomatis

| Waktu | Command | Keterangan |
|-------|---------|-----------|
| Setiap hari 00:05 | `billing:generate-monthly` | Generate invoice bulanan |
| Setiap hari 00:20 | `billing:check-overdue` | Cek dan tandai overdue |
| Setiap hari 00:30 | `billing:create-isolation` | Buat isolasi untuk overdue |
| Setiap 15 menit | `sync:pppoe-monitor` | Monitor status PPPoE |
| Setiap 5 menit | `sync:genieacs-ont` | Sync data ONT dari GenieACS |

### Jalankan Manual

```bash
# Generate invoice bulan ini
php artisan billing:generate-monthly --period=2026-05

# Cek invoice overdue
php artisan billing:check-overdue

# Sync VLAN dari MikroTik
php artisan sync:mikrotik-vid --router-id=3

# Sync IP Pool
php artisan sync:ip-pools

# Audit assignment VLAN
php artisan audit:vid-assignments

# Health check database
php artisan db:health-check
```

---

## 13. Bug yang Telah Diperbaiki

Berikut adalah 7 bug yang ditemukan dan telah diperbaiki pada tanggal **14 Mei 2026**:

### BUG-1 (CRITICAL) — FreeRadiusSqlProvider Tidak Implement Interface

**File:** `app/Services/Hotspot/Radius/FreeRadiusSqlProvider.php`

**Masalah:**
- Class tidak punya deklarasi `implements HotspotRadiusProvider`
- Method `authorize()` punya 2 parameter (harusnya 1)
- Method `account()` punya 2 parameter (harusnya 1)
- Tidak ada method `name()`

**Dampak:** Hotspot RADIUS provider `freeradius-sql` (default) **tidak bisa digunakan sama sekali** — sistem lempar `InvalidArgumentException` setiap kali ada auth/accounting request.

**Perbaikan:** Implementasikan interface dengan benar. `authorize()` dan `account()` mendelegasikan ke `HotspotRadiusService`, lalu sync ke FreeRADIUS. Tambahkan `name()` mengembalikan `'freeradius-sql'`.

---

### BUG-2 (HIGH) — Typo Nama Kolom `'acctstatus type'` di `radacct`

**File:** `app/Services/Hotspot/Radius/FreeRadiusSqlProvider.php` (baris lama: 297)

**Masalah:** Ada spasi di nama kolom `'acctstatus type'` → SQL error saat insert radacct.

**Perbaikan:** Kolom tersebut dihapus dari insert karena tidak ada di schema standar FreeRADIUS. Insert radacct kini mengikuti field yang benar-benar ada di tabel.

---

### BUG-3 (HIGH) — Leading Space di Nama Kolom Migration

**File:** `database/migrations/2026_05_11_070747_create_freeradius_tables.php` (baris 98)

**Masalah:** `$table->string(' delegatedipv6prefix', ...)` — ada spasi di depan nama kolom, membuat nama kolom di database menjadi `' delegatedipv6prefix'`.

**Perbaikan:** Ganti menjadi `'delegatedipv6prefix'` (tanpa spasi).

---

### BUG-4 (HIGH) — Urutan Priority Salah di `resolvePlaintextPassword()`

**File:** `app/Services/Hotspot/Radius/FreeRadiusSqlProvider.php`

**Masalah:** Method mengambil `getRawOriginal('password')` (nilai terenkripsi) **sebelum** `password_plain`. Akibatnya password terenkripsi dikirim ke FreeRADIUS → auth selalu gagal.

**Perbaikan:** Prioritas dibalik: `password_plain` → decrypt `password` → `getAttribute('password')`. Jika semua gagal, throw `RuntimeException` (fail-fast).

---

### BUG-5 (MEDIUM) — Response Message "simulated" di HotspotRadiusController

**File:** `app/Http/Controllers/Api/V1/Internal/HotspotRadiusController.php`

**Masalah:** Pesan response menyebut "simulated successfully" padahal endpoint ini adalah produksi nyata.

**Perbaikan:** Ganti menjadi "processed successfully".

---

### BUG-6 (MEDIUM) — Customer Model Tidak Punya `networkLocation()` Relationship

**File:** `app/Models/Customer.php`

**Masalah:** Field `network_location_id` ada di `$fillable` tapi tidak ada method `networkLocation()` dan tidak ada cast.

**Dampak:** Eager loading `networkLocation` akan error atau return `null` salah arah. Data lokasi jaringan customer tidak bisa dimuat.

**Perbaikan:** Tambahkan cast `'network_location_id' => 'integer'` dan method `networkLocation(): BelongsTo`.

---

### BUG-7 (MEDIUM) — CustomerService Tidak Menyimpan Beberapa Field Penting

**File:** `app/Services/MasterData/CustomerService.php`

**Masalah:** Method `customerPayload()` menggunakan `Arr::only()` dengan daftar field yang tidak lengkap. Field `email`, `contact`, `notes`, `install_date` tidak disertakan.

**Dampak:** Saat create/update customer via API, field-field tersebut **tidak tersimpan ke database** meskipun dikirim dalam request.

**Perbaikan:** Tambahkan `email`, `contact`, `notes`, `install_date` ke array `Arr::only()`.

---

## 14. Panduan Deployment

### Instalasi

```bash
# Clone repository
git clone <repo-url> feralix-billing
cd feralix-billing

# Install dependency PHP
composer install --no-dev --optimize-autoloader

# Install dependency Node.js
npm install

# Build assets frontend
npm run build

# Salin dan sesuaikan environment
cp .env.example .env
php artisan key:generate

# Jalankan migration
php artisan migrate --force

# Seed data awal (opsional)
php artisan db:seed

# Buat symbolic link storage
php artisan storage:link

# Cache konfigurasi dan route untuk production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Konfigurasi Web Server (Nginx)

```nginx
server {
    listen 80;
    server_name billing.domain.anda.com;
    root /var/www/feralix-billing/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Konfigurasi Queue Worker

```bash
# Supervisor config (/etc/supervisor/conf.d/feralix-worker.conf)
[program:feralix-worker]
command=php /var/www/feralix-billing/artisan queue:work --tries=3
directory=/var/www/feralix-billing
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/feralix-worker.log
```

### Konfigurasi Scheduler (Cron)

```bash
# Tambahkan ke crontab www-data
* * * * * cd /var/www/feralix-billing && php artisan schedule:run >> /dev/null 2>&1
```

### Konfigurasi FreeRADIUS

FreeRADIUS harus dikonfigurasi untuk:
1. Gunakan database MySQL yang sama (atau database terpisah untuk tabel `radcheck`, `radreply`, dll)
2. Module `rlm_sql` aktif dan pointing ke database billing
3. NAS (router MikroTik) dikonfigurasi sebagai RADIUS client di FreeRADIUS
4. MikroTik dikonfigurasi untuk mengirim accounting ke endpoint `/v1/internal/hotspot-radius/accounting`

```
# /etc/freeradius/3.0/mods-enabled/sql (contoh)
sql {
    driver = "rlm_sql_mysql"
    server = "127.0.0.1"
    port = 3306
    login = "feralix_user"
    password = "password_aman"
    radius_db = "feralix_billing"
    ...
}
```

### Checklist Production

- [ ] `.env` `APP_DEBUG=false` dan `APP_ENV=production`
- [ ] `HOTSPOT_RADIUS_PROVIDER=freeradius-sql`
- [ ] `MIKROTIK_SYNC_PROVIDER=routeros-api`
- [ ] Database migration sudah jalan (`php artisan migrate`)
- [ ] Queue worker berjalan (supervisor)
- [ ] Cron scheduler aktif
- [ ] FreeRADIUS terhubung ke database
- [ ] MikroTik tiap router sudah dikonfigurasi sebagai RADIUS client
- [ ] Router scope dan VID range sudah dikonfigurasi per router
- [ ] Test koneksi router via `POST /api/v1/admin/routers/{id}/test-connection`

---

*Dokumentasi ini dibuat otomatis pada 14 Mei 2026. Versi project: Laravel 12, PHP 8.2+*
