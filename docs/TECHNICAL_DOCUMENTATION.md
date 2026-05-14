# Feralix ISP Cloud — Dokumentasi Teknis

> **Stack**: Laravel 12 · MySQL/MariaDB · Alpine.js · Tailwind CSS 4 · Vite 7 · MikroTik API · GenieACS TR-069 · FreeRADIUS · Laravel Sanctum
> **Versi Dokumentasi**: 2026-05-14
> **PHP**: 8.2+ | **Node.js**: 20+

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Tech Stack](#2-tech-stack)
3. [Arsitektur Sistem](#3-arsitektur-sistem)
4. [Struktur Direktori](#4-struktur-direktori)
5. [Database Schema](#5-database-schema)
6. [Modul & Fitur](#6-modul--fitur)
7. [Billing Engine](#7-billing-engine)
8. [Network & Provisioning](#8-network--provisioning)
9. [Integrasi MikroTik](#9-integrasi-mikrotik)
10. [Hotspot & FreeRADIUS](#10-hotspot--freeradius)
11. [GenieACS / TR-069](#11-genieacs--tr-069)
12. [Scheduler & Automation](#12-scheduler--automation)
13. [API Endpoints Lengkap](#13-api-endpoints-lengkap)
14. [Roles & Router Scope](#14-roles--router-scope)
15. [Frontend Architecture](#15-frontend-architecture)
16. [Setup & Deployment](#16-setup--deployment)
17. [Common Tasks](#17-common-tasks)
18. [Troubleshooting](#18-troubleshooting)

---

## 1. Gambaran Umum

**Feralix ISP Cloud** adalah sistem manajemen billing dan operasional lengkap untuk Internet Service Provider (ISP) berbasis FTTH (Fiber-to-the-Home). Sistem mengotomatisasi seluruh siklus bisnis ISP mulai dari onboarding pelanggan, provisioning jaringan, penagihan bulanan, hingga manajemen insiden.

### Fitur Utama

| Domain | Fitur |
|--------|-------|
| **Customer Management** | CRUD, onboarding, provisioning FTTH, terminasi, bulk operations |
| **Billing & Invoicing** | Generate invoice bulanan otomatis, tracking payment, overdue management, PDF export |
| **IP Address Management** | IP pool sync dari MikroTik, VID assignment atomik, subnet tracking |
| **Service Isolation** | Auto-isolir pelanggan overdue via MikroTik address-list, release otomatis saat bayar |
| **PPPoE Management** | Provisioning PPPoE secret, monitoring online/offline, import dari router |
| **ONT Management** | Sync perangkat ONT via GenieACS TR-069, status optik real-time |
| **Hotspot** | Voucher management, batch generation, centralized multi-router, FreeRADIUS |
| **Network Inventory** | OLT, ODC, ODP, PON Port, fiber map |
| **Finance** | Cashflow, kategori pemasukan/pengeluaran, reseller management |
| **Helpdesk** | Tiket, work order, dashboard teknisi |
| **Monitoring** | PPPoE status real-time, router statistics, ONT online/offline |
| **Notifikasi** | Telegram bot integration, queued notification processing |

### Target Pengguna

| Role | Akses |
|------|-------|
| `superadmin` | Full access semua router, semua data, semua konfigurasi sistem |
| `admin` | Full access tetapi dibatasi berdasarkan router scope yang dikonfigurasi |
| `technician` | Dashboard teknisi, WO assignment, manajemen tiket |
| `reseller` | Manajemen hotspot voucher |

---

## 2. Tech Stack

### Backend

| Komponen | Versi | Keterangan |
|----------|-------|------------|
| PHP | 8.2+ | Versi minimum |
| Laravel | 12.x | Framework utama |
| Laravel Sanctum | 4.3+ | Token-based API authentication |
| DomPDF | 3.1+ | Generasi PDF invoice |
| MySQL | 8.0+ | Database primer |
| MariaDB | 10.6+ | Alternatif database |

### Frontend

| Komponen | Versi | Keterangan |
|----------|-------|------------|
| Vite | 7.0.7 | Build tool & dev server |
| Alpine.js | 3.14.9 | Reactive UI framework |
| Tailwind CSS | 4.0.0 | Utility-first CSS |
| Axios | 1.11.0 | HTTP client |

### Dependensi Development

| Package | Keterangan |
|---------|------------|
| Laravel Pint | PHP code style fixer |
| Laravel Sail | Docker dev environment |
| Laravel Pail | Real-time log viewer |
| PHPUnit 11 | Unit & feature testing |
| Faker | Test data generation |

### Integrasi Eksternal

| Sistem | Protokol | Keterangan |
|--------|----------|------------|
| MikroTik RouterOS | MikroTik API (port 8728/8729) | Manajemen router, PPPoE, address-list |
| GenieACS | HTTP REST (port 7557) | TR-069 ACS untuk manajemen ONT |
| FreeRADIUS | HTTP Internal API | Hotspot RADIUS authorization & accounting |
| Telegram Bot API | HTTPS | Notifikasi helpdesk dan sistem |

---

## 3. Arsitektur Sistem

```
┌──────────────────────────────────────────────────────────────────┐
│                           CLIENT                                  │
│   ┌──────────────────┐        ┌──────────────────┐               │
│   │   Admin Panel    │        │  Teknisi Panel   │               │
│   │   (Alpine.js)    │        │  (Alpine.js)     │               │
│   └────────┬─────────┘        └────────┬─────────┘               │
└────────────┼──────────────────────────┼────────────────────────-─┘
             │ HTTPS / API              │ HTTPS / API
             ▼                          ▼
┌──────────────────────────────────────────────────────────────────┐
│                    LARAVEL 12 BACKEND                             │
│                                                                   │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Controllers │  │  Services   │  │    Jobs     │              │
│  │ (API Layer) │  │ (Biz Logic) │  │  (Async)    │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         └────────────────┼────────────────┘                      │
│                          ▼                                        │
│              ┌───────────────────────┐                           │
│              │   Models (Eloquent)    │                           │
│              └───────────┬───────────┘                           │
└─────────────────────────┼────────────────────────────────────────┘
                           │
         ┌─────────────────┼──────────────────────┐
         ▼                 ▼                       ▼
┌───────────────┐  ┌──────────────┐   ┌──────────────────┐
│  MySQL/       │  │  MikroTik    │   │   GenieACS       │
│  MariaDB      │  │  Router API  │   │   TR-069 ACS     │
│               │  │  (8728/8729) │   │   (HTTP :7557)   │
└───────────────┘  └──────────────┘   └──────────────────┘
```

### Alur Request

```
HTTP Request
  → Sanctum Auth Middleware
  → panel.role Middleware (RBAC)
  → router.scope.bindings Middleware
  → Controller
  → Service Layer (business logic)
  → Model (Eloquent)
  → Database
```

---

## 4. Struktur Direktori

```
feralix-billing/
├── app/
│   ├── Actions/Mikrotik/           # Single-purpose action classes
│   ├── Console/Commands/           # 11 Artisan commands
│   ├── Contracts/                  # Interface definitions
│   │   ├── Billing/
│   │   ├── GenieAcs/
│   │   ├── Hotspot/
│   │   └── Mikrotik/
│   ├── Data/                       # DTOs (Data Transfer Objects)
│   │   ├── GenieAcs/
│   │   ├── Hotspot/
│   │   └── Mikrotik/
│   ├── Enums/                      # 31 PHP 8.1+ enums
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Admin/              # ~47 admin controllers
│   │   │   ├── Auth/               # Login, logout, me
│   │   │   ├── Internal/           # Internal RADIUS endpoints
│   │   │   └── Technician/         # Technician endpoints
│   │   ├── Middleware/
│   │   ├── Requests/               # 115 Form Request classes
│   │   └── Resources/              # Eloquent API Resources
│   ├── Jobs/                       # 14 queue job classes
│   ├── Models/                     # 51 Eloquent models
│   ├── Observers/                  # Model event observers
│   ├── Providers/                  # 4 service providers
│   ├── Services/
│   │   ├── Access/                 # RBAC, router scope
│   │   ├── Audit/                  # Activity logging
│   │   ├── Auth/                   # Token management
│   │   ├── Billing/                # Invoice, payment, cashflow
│   │   ├── Customer/               # Customer lifecycle
│   │   ├── Dashboard/              # Analytics & metrics
│   │   ├── FieldWork/              # Work order
│   │   ├── Finance/                # Cashflow tracking
│   │   ├── GenieAcs/               # TR-069 integration
│   │   ├── Helpdesk/               # Ticket management
│   │   ├── Hotspot/                # Voucher, profile, RADIUS
│   │   ├── Inventory/              # Network inventory
│   │   ├── MasterData/             # Core data management
│   │   ├── Mikrotik/               # Router API integration
│   │   ├── Monitoring/             # PPPoE & system monitoring
│   │   ├── Network/                # Network configuration
│   │   ├── Provisioning/           # FTTH service lifecycle
│   │   └── Settings/               # System settings
│   └── Support/
├── config/
│   ├── automation.php              # Schedule, queue, billing config
│   ├── billing.php
│   ├── genieacs.php
│   ├── hotspot.php
│   └── mikrotik.php
├── database/
│   ├── migrations/                 # 69 migration files
│   ├── factories/
│   └── seeders/
├── docs/                           # Dokumentasi proyek
├── resources/
│   ├── js/
│   │   ├── app.js                  # Entry point SPA
│   │   ├── admin.js                # Admin panel logic
│   │   ├── login.js                # Login page
│   │   └── services/
│   │       ├── api.js              # Axios wrapper
│   │       └── token.js            # Token management
│   └── views/                      # Blade templates
├── routes/
│   ├── api.php                     # API route definitions
│   ├── web.php                     # Web routes (isolir page)
│   └── console.php
└── tests/
    ├── Feature/
    └── Unit/
```

---

## 5. Database Schema

### 5.1 ERD Overview

```
Customer ─── 1:N ──> Service ─── 1:N ──> Invoice ─── 1:1 ──> Payment
                         │
                         ├──> Router ─── 1:N ──> OLT ─── 1:N ──> ONT
                         │                           └── 1:N ──> PonPort
                         ├──> Package
                         ├──> VID (ip_pool_snapshots)
                         └──> ServiceIsolation

NetworkLocation ─── 1:N ──> OLT
                        └── 1:N ──> ODC ─── 1:N ──> ODP
```

### 5.2 Tabel Inti

#### `customers`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_code` | varchar | Format: CUST-XXXXX |
| `full_name` | varchar | Nama lengkap |
| `phone` | varchar | No. telepon |
| `email` | varchar nullable | |
| `address` | text | Alamat lengkap |
| `network_location_id` | FK nullable | Lokasi jaringan |
| `preferred_olt_id` | FK nullable | OLT preferensi |
| `assigned_technician_id` | FK nullable | Teknisi penanggung jawab |
| `customer_type` | enum | `residential`, `business`, `internal` |
| `status` | enum | `active`, `inactive` |
| `monthly_price` | decimal nullable | Override harga paket |
| `billing_day` | int | Hari tagihan (1–28) |
| `install_date` | date nullable | Tanggal pemasangan |
| `notes` | text nullable | Catatan internal |
| `latitude`, `longitude` | decimal nullable | Koordinat GPS |

#### `services`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK | |
| `service_code` | varchar | Format: SVC-XXXXX |
| `package_id` | FK nullable | Paket internet |
| `router_id` | FK | MikroTik router |
| `olt_id` | FK nullable | OLT terhubung |
| `ont_id` | FK nullable | ONT pelanggan |
| `vid_id` | FK nullable | IP pool snapshot |
| `access_mode` | enum | `pppoe`, `vlan`, `static` |
| `pppoe_username` | varchar nullable | |
| `pppoe_password` | varchar nullable | Terenkripsi |
| `internet_vid` | int nullable | VLAN ID internet |
| `monitor_vid` | int nullable | VLAN ID monitoring |
| `subnet_cidr` | varchar nullable | Untuk static IP |
| `isolation_method` | enum | `address_list`, `firewall_filter`, `ppp_profile`, `queue` |
| `billing_status` | enum | `pending`, `paid`, `overdue`, `suspended`, `closed` |
| `network_status` | enum | `provisioning`, `active`, `isolated`, `down`, `inactive` |
| `overall_status` | enum | `provisioning`, `active`, `down`, `suspended`, `isolated`, `inactive`, `terminated` |
| `activation_date` | date nullable | Tanggal aktivasi |
| `monthly_price` | decimal nullable | Override harga |

#### `invoices`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK | |
| `service_id` | FK | |
| `invoice_number` | varchar unique | Format: INV-YYYYMM-XXXXX |
| `billing_period` | varchar | Format: `YYYY-MM` |
| `invoice_date` | date | Tanggal dibuat |
| `due_date` | date | Jatuh tempo |
| `subtotal` | decimal | Sebelum denda |
| `penalty_amount` | decimal | Denda keterlambatan |
| `total_amount` | decimal | Total tagihan |
| `remaining_amount` | decimal | Sisa belum dibayar |
| `payment_status` | enum | `unpaid`, `issued`, `overdue`, `partially_paid`, `paid`, `canceled` |
| `issued_at` | timestamp nullable | |
| `paid_at` | timestamp nullable | |
| `overdue_marked_at` | timestamp nullable | |

#### `payments`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `invoice_id` | FK | |
| `customer_id` | FK | |
| `service_id` | FK | |
| `amount_paid` | decimal | Jumlah dibayar |
| `payment_method` | varchar | Metode pembayaran |
| `paid_at` | timestamp | |
| `reference_no` | varchar nullable | Referensi eksternal |

#### `service_isolations`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `service_id` | FK | |
| `router_id` | FK | Router tempat isolasi diterapkan |
| `invoice_id` | FK nullable | Invoice pemicu |
| `isolation_type` | enum | `manual`, `auto` |
| `target_type` | enum | `subnet`, `pppoe`, `static` |
| `address_list_name` | varchar | Nama address-list MikroTik |
| `target_subnet` | varchar nullable | Subnet yang diblokir |
| `target_identifier` | varchar nullable | IP/username |
| `status` | enum | `pending`, `applied`, `released`, `failed` |
| `isolated_at` | timestamp nullable | |
| `released_at` | timestamp nullable | |

### 5.3 Tabel Jaringan

#### `routers`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `router_code` | varchar | Kode unik router |
| `router_name` | varchar | Nama deskriptif |
| `host` | varchar | IP/hostname MikroTik |
| `api_port` | int | Default: 8728 |
| `api_username` | varchar | |
| `api_password` | text | Terenkripsi |
| `ros_version` | varchar nullable | Versi RouterOS (auto-detect) |
| `acs_inform_url` | varchar nullable | GenieACS inform URL |
| `acs_nbi_url` | varchar nullable | GenieACS NBI URL |
| `acs_username` | varchar nullable | |
| `acs_password` | text nullable | |
| `rest_api_url` | varchar nullable | MikroTik REST API URL |
| `rest_api_username` | varchar nullable | |
| `rest_api_password` | text nullable | |
| `status` | enum | `active`, `inactive` |

#### `olts`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `code` | varchar | Kode OLT |
| `name` | varchar | Nama |
| `host` | varchar | IP OLT |
| `network_location_id` | FK | |
| `router_id` | FK | Router terkait |
| `pon_ports` | int | Jumlah PON port |
| `brand`, `model` | varchar | Merek & model |
| `status` | enum | `active`, `inactive` |

#### `ip_pool_snapshots`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `router_id` | FK | |
| `vid` | int | VLAN ID |
| `pool_name` | varchar | Nama pool di MikroTik |
| `network` | varchar | Network address |
| `total_ips` | int | Total IP di pool |
| `used_ips` | int | IP yang terpakai |
| `free_ips` | int | IP tersedia |
| `is_available` | bool | Pool bisa digunakan |
| `reserved_by_customer_id` | FK nullable | Customer yang mereservasi |
| `last_synced_at` | timestamp | Terakhir sinkronisasi |

#### `hotspot_services`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `hotspot_voucher_id` | FK | |
| `router_id` | FK | |
| `hotspot_username` | varchar(100) | Username hotspot |
| `mikrotik_user_id` | varchar(50) nullable | `.id` dari MikroTik |
| `status` | varchar | `provisioning`, `active`, `suspended`, `removed` |
| `sync_error` | varchar nullable | Pesan error sinkronisasi |

#### Tabel FreeRADIUS
Tabel-tabel RADIUS (`radcheck`, `radreply`, `radusergroup`, `radgroupcheck`, `radgroupreply`, `radacct`, `radpostauth`, `nas`) mengikuti skema FreeRADIUS standar dan digunakan untuk autentikasi dan akuntansi hotspot.

### 5.4 Enums Reference

```php
// Status invoice
InvoicePaymentStatus: 'unpaid' | 'issued' | 'overdue' | 'partially_paid' | 'paid' | 'canceled'

// Status keseluruhan layanan
ServiceOverallStatus: 'provisioning' | 'active' | 'down' | 'suspended' | 'isolated' | 'inactive' | 'terminated'

// Status billing layanan
ServiceBillingStatus: 'pending' | 'paid' | 'overdue' | 'suspended' | 'closed'

// Mode akses layanan
ServiceAccessMode: 'pppoe' | 'vlan' | 'static'

// Metode isolasi
ServiceIsolationMethod: 'address_list' | 'firewall_filter' | 'ppp_profile' | 'queue'

// Tipe VID
VidType: 'CUSTOMER' | 'OLT' | 'TRANSIT' | 'INFRA'

// Status VID
VidStatus: 'available' | 'reserved' | 'in_use' | 'maintenance'

// Status tiket
TicketStatus: 'open' | 'in_progress' | 'resolved' | 'closed'
TicketPriority: 'low' | 'medium' | 'high' | 'critical'

// Status hotspot voucher
HotspotVoucherStatus: 'unused' | 'active' | 'expired' | 'suspended' | 'depleted'

// Role pengguna
UserRole: 'superadmin' | 'admin' | 'technician' | 'reseller'
```

---

## 6. Modul & Fitur

### 6.1 Customer Management

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/customers` | GET | List pelanggan dengan filter & pagination |
| `/api/v1/admin/customers` | POST | Buat pelanggan baru |
| `/api/v1/admin/customers/{id}` | GET | Detail pelanggan |
| `/api/v1/admin/customers/{id}` | PUT | Update pelanggan |
| `/api/v1/admin/customers/{id}` | DELETE | Hapus pelanggan |
| `/api/v1/admin/customers/{id}/terminate` | DELETE | Terminasi lengkap (layanan + MikroTik cleanup) |
| `/api/v1/admin/customers/onboard` | POST | Onboard pelanggan baru dengan provisioning penuh |
| `/api/v1/admin/customers/provisioning` | POST | Provisioning layanan untuk pelanggan existing |
| `/api/v1/admin/customers/provisioning-preview` | POST | Preview VID yang akan diassign |
| `/api/v1/admin/customers/bulk-delete` | POST | Hapus massal |
| `/api/v1/admin/customers/bulk-disable` | POST | Nonaktifkan massal |
| `/api/v1/admin/customers/bulk-generate-invoice` | POST | Generate invoice untuk banyak pelanggan |

### 6.2 Billing & Invoice

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/invoices` | GET | List invoice dengan filter |
| `/api/v1/admin/invoices` | POST | Buat invoice manual |
| `/api/v1/admin/invoices/{id}` | GET | Detail invoice |
| `/api/v1/admin/invoices/generate-monthly` | POST | Generate invoice bulanan manual |
| `/api/v1/admin/invoices/manual-generate` | POST | Generate invoice manual untuk service tertentu |
| `/api/v1/admin/invoices/{id}/mark-paid` | PATCH | Tandai lunas + release isolasi |
| `/api/v1/admin/invoices/{id}/mark-overdue` | PATCH | Tandai overdue |
| `/api/v1/admin/invoices/{id}/send-whatsapp` | POST | Kirim tagihan via WhatsApp |
| `/api/v1/admin/invoices/{id}/pdf` | GET | Download PDF invoice |
| `/api/v1/admin/invoices/bulk-action` | POST | Aksi massal pada invoice |
| `/api/v1/admin/invoices/auto-suspend` | POST | Trigger auto-isolasi overdue |
| `/api/v1/admin/payments` | GET/POST/GET{id} | Manajemen pembayaran |

### 6.3 Network Inventory

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/network-locations` | CRUD | Lokasi jaringan |
| `/api/v1/admin/olts` | CRUD | Manajemen OLT |
| `/api/v1/admin/olts/{id}/pon-status` | GET | Status PON port |
| `/api/v1/admin/olts/{id}/pon-ports` | CRUD | Manajemen PON port |
| `/api/v1/admin/odcs` | CRUD | Optical Distribution Cabinet |
| `/api/v1/admin/odps` | CRUD | Optical Distribution Point |
| `/api/v1/admin/onts` | CRUD | Manajemen ONT |
| `/api/v1/admin/onts/online` | GET | ONT yang sedang online |
| `/api/v1/admin/onts/offline` | GET | ONT yang offline |
| `/api/v1/admin/fiber-map` | GET | Peta jaringan fiber |

### 6.4 IP Pool & VID

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/ip-pools` | GET | List semua pool |
| `/api/v1/admin/ip-pools/suggest` | GET | Saran VID tersedia |
| `/api/v1/admin/ip-pools/preview` | GET | Preview pool live dari router |
| `/api/v1/admin/ip-pools/save-selection` | POST | Simpan pilihan pool |
| `/api/v1/admin/ip-pools/sync` | POST | Sync pool dari MikroTik |
| `/api/v1/admin/routers/{id}/ip-pools` | GET | Pool milik router tertentu |
| `/api/v1/admin/routers/{id}/ip-pools/summary` | GET | Ringkasan utilisasi |
| `/api/v1/admin/routers/{id}/ip-pools/utilization` | GET | Detail utilisasi |
| `/api/v1/admin/routers/{id}/ip-pools/suggest-for-vid` | GET | Saran pool untuk VID |
| `/api/v1/admin/routers/{id}/ip-pools/vids-with-availability` | GET | VID + ketersediaan |
| `/api/v1/admin/vids` | CRUD | Manajemen VID |

### 6.5 Service Isolation

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/service-isolations` | GET | Riwayat isolasi |
| `/api/v1/admin/service-isolations/suggestions` | GET | Layanan yang perlu diisolasi |
| `/api/v1/admin/service-isolations` | POST | Buat isolasi manual |
| `/api/v1/admin/service-isolations/{id}/applied` | PATCH | Tandai isolasi teraplikasi |
| `/api/v1/admin/service-isolations/{id}/release` | PATCH | Lepas isolasi |
| `/api/v1/admin/isolir/manual` | POST | Isolir langsung via MikroTik |
| `/api/v1/admin/isolir/release` | POST | Release isolir langsung |
| `/isolir` | GET | Halaman publik redirect isolasi |

### 6.6 Router & Sync

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/routers` | CRUD | Manajemen router |
| `/api/v1/admin/routers/{id}/test-connection` | POST | Test koneksi MikroTik API |
| `/api/v1/admin/routers/{id}/test-acs` | POST | Test koneksi GenieACS |
| `/api/v1/admin/routers/{id}/sync-ont` | POST | Sync ONT dari GenieACS |
| `/api/v1/admin/routers/{id}/detect-version` | POST | Auto-detect versi RouterOS |
| `/api/v1/admin/routers/{id}/stats` | GET | Statistik resource router |
| `/api/v1/admin/router-scopes` | CRUD | Scope router per user |
| `/api/v1/admin/router-sync/pppoe` | POST | Sync PPPoE dari router |
| `/api/v1/admin/router-sync/static` | POST | Sync static IP |
| `/api/v1/admin/router-sync/address-list` | POST | Sync address-list |
| `/api/v1/admin/router-sync/all` | POST | Sync semua |
| `/api/v1/admin/mikrotik/pppoe-servers` | GET | Daftar PPPoE server |
| `/api/v1/admin/pppoe-import/candidates` | GET | Kandidat PPPoE untuk import |
| `/api/v1/admin/pppoe-import/import` | POST | Import PPPoE secrets |

### 6.7 Hotspot

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/hotspot-profiles` | CRUD (no delete) | Profil hotspot |
| `/api/v1/admin/hotspot-vouchers` | GET, GET{id} | Daftar & detail voucher |
| `/api/v1/admin/hotspot-vouchers/{id}/activate` | POST | Aktivasi voucher |
| `/api/v1/admin/voucher-batches` | GET, POST, GET{id} | Batch voucher |
| `/api/v1/admin/resellers` | CRUD (no delete) | Manajemen reseller |
| `/api/v1/admin/hotspot-router/activate` | POST | Aktifkan voucher di router |
| `/api/v1/admin/hotspot-router/activate-all` | POST | Aktifkan di semua router |
| `/api/v1/admin/hotspot-router/deactivate` | DELETE | Nonaktifkan di router |
| `/api/v1/admin/hotspot-router/deactivate-all` | DELETE | Nonaktifkan di semua router |
| `/api/v1/admin/hotspot-router/sync-radius` | POST | Sync ke FreeRADIUS |
| `/api/v1/admin/hotspot-router/services/{voucher}` | GET | Layanan voucher di router |
| `/api/v1/admin/hotspot-router/routers/{router}/services` | GET | Layanan hotspot di router |

### 6.8 Helpdesk

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/tickets` | GET, POST, GET{id} | Manajemen tiket |
| `/api/v1/admin/tickets/{id}/status` | PATCH | Update status tiket |
| `/api/v1/admin/tickets/{id}/replies` | POST | Balas tiket |
| `/api/v1/admin/work-orders` | CRUD | Work order teknisi |
| `/api/v1/admin/technician-dashboard` | GET | Dashboard teknisi |
| `/api/v1/admin/technician-dashboard/export-pdf` | POST | Export PDF laporan teknisi |

### 6.9 Finance & System

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/v1/admin/cashflows` | CRUD | Cashflow |
| `/api/v1/admin/cashflows/summary` | GET | Ringkasan keuangan |
| `/api/v1/admin/packages` | CRUD | Paket internet |
| `/api/v1/admin/users` | CRUD | Manajemen user + disable/enable/reset-password |
| `/api/v1/admin/activity-logs` | GET | Audit trail |
| `/api/v1/admin/monitoring/pppoe` | GET | Status PPPoE real-time |
| `/api/v1/admin/telegram-bots` | CRUD + test | Konfigurasi Telegram bot |
| `/api/v1/admin/telegram-groups` | CRUD + test | Konfigurasi Telegram grup |
| `/api/v1/admin/database-settings` | GET/PATCH/test | Pengaturan database |

### 6.10 Internal & Technician

| Endpoint | Method | Auth | Deskripsi |
|----------|--------|------|-----------|
| `/api/v1/internal/hotspot-radius/authorize` | POST | None | RADIUS authorization callback |
| `/api/v1/internal/hotspot-radius/accounting` | POST | None | RADIUS accounting callback |
| `/api/v1/technician/dashboard` | GET | Sanctum + role:technician | Dashboard teknisi |

---

## 7. Billing Engine

### 7.1 Generate Invoice Bulanan (Otomatis)

```
[SCHEDULER] billing:generate-monthly-invoices
   Jadwal: Setiap tanggal 1 bulan, pukul 00:05
   Queue: billing
        │
        ▼
MonthlyInvoiceGenerationService::generate()
   1. Ambil semua service dengan overall_status IN
      [active, down, suspended, isolated]
   2. Untuk setiap service (chunked):
      a. Skip jika invoice untuk billing_period sudah ada
      b. Hitung amount: service.monthly_price
                      ?? package.monthly_price ?? 0
      c. Hitung due_date: install_date hari ini bulan ini
                         ?? billing_day 7 bulan ini
      d. Buat record invoice (payment_status = 'unpaid')
      e. Update service.billing_status = 'pending'
```

### 7.2 Check Overdue

```
[SCHEDULER] billing:check-overdue-invoices
   Jadwal: Setiap hari, pukul 00:20
   Queue: billing
        │
        ▼
InvoiceOverdueService::markOverdue()
   1. Cari invoice:
      - payment_status IN ['unpaid', 'issued']
      - due_date < today
      - overdue_marked_at IS NULL
   2. Untuk setiap invoice:
      a. payment_status = 'overdue'
      b. overdue_marked_at = now()
      c. service.billing_status = 'overdue'
```

### 7.3 Auto-Isolasi Overdue

```
[SCHEDULER] billing:create-overdue-isolations
   Jadwal: Setiap hari, pukul 00:30
   Queue: billing
        │
        ▼
InvoiceAutoSuspendService::trigger()
   1. Cari invoice payment_status = 'overdue'
      yang belum ada isolasi terbuka
   2. Untuk setiap invoice:
      a. Buat service_isolation (status = 'pending')
      b. Dispatch job → MikrotikAddressListService
         - Tambah IP ke address-list "ISOLIR_CUSTOMER"
      c. status = 'applied'
      d. service.overall_status = 'isolated'
```

### 7.4 Pembayaran & Release Isolasi

```
POST /api/v1/admin/invoices/{id}/mark-paid
        │
        ▼
PaymentService::settleInvoice()
   1. Buat record payment
   2. Update invoice.payment_status
   3. Catat ke cashflow (CashflowIncomeService)
   4. Panggil InvoiceIsolationAutomationService::syncForInvoice()
        │
        ▼
InvoiceIsolationAutomationService::syncForInvoice()
   1. Cek apakah semua invoice service sudah lunas
   2. Jika tidak ada overdue tersisa:
      a. Cari isolasi terbuka
      b. Dispatch job → hapus dari address-list MikroTik
      c. status isolation = 'released'
      d. service.overall_status = 'active'
```

### 7.5 Aktivasi Prepaid

```
Customer di-provisioning
   → PPPoE secret dibuat di MikroTik (disabled=true)
   → Invoice pertama dibuat (status = 'issued')
   → Customer bayar invoice
   → mark-paid dipanggil
   → PppoeSecretService::setSecretEnabled(enabled=true)
   → service.overall_status = 'active'
```

---

## 8. Network & Provisioning

### 8.1 Alur Onboarding Pelanggan

```
POST /api/v1/admin/customers/onboard
        │
        ▼
1. Validasi data customer, package, router, OLT
2. Generate PPPoE username/password
3. GET /ip-pools/suggest → pilih VID tersedia (row lock)
4. Buat record Customer
5. Buat record Service
6. Buat PPPoE secret di MikroTik (disabled=true)
7. Reservasi VID di ip_pool_snapshots
8. Generate invoice pertama
9. Return: customer + service + invoice
```

### 8.2 VID Assignment (Atomic)

```
IpPoolService::suggestAvailableVid()
   1. BEGIN TRANSACTION + SELECT FOR UPDATE
   2. Cari ip_pool_snapshots:
      - router_id = X
      - is_available = true
      - free_ips > 0
      - reserved_by_customer_id IS NULL
      - Dalam range vid_start–vid_end dari router_scope
   3. Pilih pool dengan free_ips paling sedikit
      (strategi: isi dulu pool yang hampir penuh)
   4. COMMIT
```

### 8.3 Status IP Pool

| `used_ips` | `free_ips` | `is_available` | Status |
|------------|------------|----------------|--------|
| 0 | > 0 | true | **Available** — hijau |
| ≥ 1 | > 0 | true | **Used** — kuning |
| > 0 | 0 | false | **Full** — merah |
| 0 | 0 | false | **Empty** — abu-abu |

### 8.4 Service Termination

```
DELETE /api/v1/admin/customers/{id}/terminate
        │
        ▼
FtthServiceManager::terminate()
   1. Hapus PPPoE secret dari MikroTik
   2. Release VID di ip_pool_snapshots
   3. Release semua isolasi terbuka
   4. Update service.overall_status = 'terminated'
   5. Update customer.status = 'inactive'
```

---

## 9. Integrasi MikroTik

### 9.1 Client Factory

```php
// Mendapatkan API client untuk router
$client = app(MikrotikApiClientFactory::class)->make($router);
$client->connect();
$result = $client->execute('/ip/pppoe-server/secret/print');
$client->disconnect();
```

### 9.2 Provider Mode

Diatur via `.env`:
```env
MIKROTIK_SYNC_PROVIDER=fake   # Untuk development/testing
MIKROTIK_SYNC_PROVIDER=real   # Untuk production
```

### 9.3 PPPoE Secret Operations

```php
// Buat PPPoE secret (disabled untuk prepaid)
MikrotikPppoeSecretService::createSecret(
    router: $router,
    username: 'pelanggan001',
    password: 'secret123',
    profile: 'default',
    comment: 'Customer: Budi | VID: 100',
    disabled: true
);

// Aktifkan setelah bayar
MikrotikPppoeSecretService::setSecretEnabled(
    router: $router, username: 'pelanggan001', enabled: true
);

// Nonaktifkan (isolasi)
MikrotikPppoeSecretService::setSecretEnabled(
    router: $router, username: 'pelanggan001', enabled: false
);

// Hapus saat terminasi
MikrotikPppoeSecretService::deleteSecret(
    router: $router, username: 'pelanggan001'
);
```

### 9.4 Service Isolasi via Address-List

```
MikrotikAddressListService::addToIsolationList()
   1. Ambil IP aktif pelanggan dari PPPoE session
   2. Tambahkan ke address-list "ISOLIR_CUSTOMER"
      (/ip/firewall/address-list/add)
   3. Update service_isolation.status = 'applied'

MikrotikAddressListService::removeFromIsolationList()
   1. Cari entry di address-list berdasarkan IP/comment
   2. Hapus dari address-list
   3. Update service_isolation.status = 'released'
```

### 9.5 Konfigurasi MikroTik untuk Isolasi

```routeros
# Aktifkan API
/ip service enable api
/ip service set api port=8728

# Buat user API
/user add name=feralix group=full

# NAT redirect untuk pelanggan tersolasi
/ip firewall nat add \
  chain=dstnat \
  src-address-list=ISOLIR_CUSTOMER \
  protocol=tcp dst-port=80 \
  action=redirect to-ports=8080 \
  comment="Feralix Isolir"

# Web proxy redirect
/ip proxy set enabled=yes port=8080
/ip proxy access add dst-port=80 action=deny \
  redirect-to=http://SERVER_IP:6733/isolir
```

### 9.6 SSL API (Opsional)

```env
MIKROTIK_API_SSL_ENABLED=true
MIKROTIK_API_SSL_VERIFY_PEER=true
MIKROTIK_API_SSL_ALLOW_SELF_SIGNED=false
MIKROTIK_API_SSL_CAFILE=/path/to/ca.pem
```

---

## 10. Hotspot & FreeRADIUS

### 10.1 Arsitektur Hotspot

```
Pelanggan → MikroTik Hotspot → RADIUS Request
                                    │
                                    ▼
                         /api/v1/internal/hotspot-radius/authorize
                         /api/v1/internal/hotspot-radius/accounting
                                    │
                                    ▼
                         HotspotRadiusController
                                    │
                              ┌─────┴──────┐
                              ▼            ▼
                         Validasi      Catat sesi
                         voucher       (accounting)
```

### 10.2 Lifecycle Voucher

```
Admin buat VoucherBatch → generate N voucher
        │
        ▼
Voucher dibagikan ke pelanggan
        │
        ▼
Pelanggan connect ke hotspot MikroTik
        │
        ▼
MikroTik kirim RADIUS authorize request
        │
        ▼
Feralix validasi voucher (status, expired, quota)
        │
   ┌────┴────┐
Accept     Reject
   │
   ▼
HotspotVoucherSession dibuat
status voucher = 'active'
        │
        ▼
Pelanggan terputus / quota habis
        │
        ▼
RADIUS accounting stop
status = 'expired' / 'depleted'
```

### 10.3 Centralized Hotspot (Multi-Router)

```php
// Aktifkan voucher di satu router
POST /api/v1/admin/hotspot-router/activate
  { voucher_id, router_id }

// Aktifkan di semua router sekaligus
POST /api/v1/admin/hotspot-router/activate-all
  { voucher_id }

// Sync ke FreeRADIUS
POST /api/v1/admin/hotspot-router/sync-radius
  { router_id }
```

### 10.4 Tabel hotspot_services

Tabel ini menjadi jembatan antara `hotspot_vouchers` dan `routers`, melacak status provisioning voucher di setiap router:

- `status: 'provisioning'` → sedang dibuat
- `status: 'active'` → aktif di router
- `status: 'suspended'` → disuspend
- `status: 'removed'` → dihapus dari router

### 10.5 Provider RADIUS

```env
HOTSPOT_RADIUS_PROVIDER=stub      # Development
HOTSPOT_RADIUS_PROVIDER=freeradius # Production
HOTSPOT_RADIUS_INTERNAL_SECRET=your-shared-secret
HOTSPOT_RADIUS_EXPIRED_REDIRECT_URL=http://domain.com/expired
```

---

## 11. GenieACS / TR-069

### 11.1 Overview

GenieACS digunakan untuk monitoring ONT (Optical Network Terminal) via protokol TR-069. Router MikroTik dikonfigurasi sebagai ACS (Auto Configuration Server) proxy.

### 11.2 Sinkronisasi ONT

```
[SCHEDULER] genieacs:sync-onts
   Jadwal: Setiap 5 menit
   Queue: monitoring
        │
        ▼
GenieAcsOntSyncService::sync()
   1. Fetch devices dari GenieACS NBI API
   2. Match berdasarkan serial number (ont_sn)
   3. Update: optical_status, rx_power, tx_power,
              last_seen_at, genieacs_device_id
   4. Update ont.status = 'online' / 'offline'
```

### 11.3 Konfigurasi GenieACS

```env
GENIEACS_SYNC_PROVIDER=fake   # Development
GENIEACS_SYNC_PROVIDER=real   # Production
GENIEACS_HTTP_TIMEOUT=15
GENIEACS_HTTP_CONNECT_TIMEOUT=5
```

Per-router, konfigurasi di database:
- `routers.acs_inform_url` → URL yang dikonfigurasi di ONT
- `routers.acs_nbi_url` → GenieACS NBI endpoint
- `routers.acs_username` / `acs_password` → Credential

### 11.4 Setup GenieACS (Docker)

```bash
docker run -d \
  --name genieacs \
  -p 7547:7547 \  # TR-069 inform port
  -p 7557:7557 \  # NBI API port
  -p 3000:3000 \  # Web UI
  genieacs/genieacs
```

---

## 12. Scheduler & Automation

### 12.1 Jadwal Lengkap

| Waktu | Command | Queue | Fungsi |
|-------|---------|-------|--------|
| Tgl 1 pukul 00:05 | `billing:generate-monthly-invoices` | billing | Generate invoice bulanan |
| Setiap hari 00:20 | `billing:check-overdue-invoices` | billing | Tandai invoice overdue |
| Setiap hari 00:30 | `billing:create-overdue-isolations` | billing | Auto-isolir overdue |
| `*/15 * * * *` | `sync:mikrotik-vid` | provisioning | Sync VID dari MikroTik |
| `* * * * *` | `sync:pppoe-monitor` | monitoring | Sync status PPPoE |
| `*/5 * * * *` | `sync:genieacs-ont` | monitoring | Sync ONT dari GenieACS |
| `* * * * *` | `process-telegram-notifications` | notifications | Kirim notifikasi Telegram |

Semua jadwal dapat dikustomisasi via `.env`:
```env
AUTOMATION_SCHEDULE_TIMEZONE=Asia/Jakarta
AUTOMATION_SCHEDULE_GENERATE_INVOICES_AT=00:05
AUTOMATION_SCHEDULE_CHECK_OVERDUE_AT=00:20
AUTOMATION_SCHEDULE_CREATE_OVERDUE_ISOLATIONS_AT=00:30
AUTOMATION_SCHEDULE_SYNC_MIKROTIK_VID_CRON="*/15 * * * *"
AUTOMATION_SCHEDULE_SYNC_PPPOE_CRON="* * * * *"
AUTOMATION_SCHEDULE_SYNC_GENIEACS_CRON="*/5 * * * *"
AUTOMATION_SCHEDULE_PROCESS_TELEGRAM_CRON="* * * * *"
```

### 12.2 Queue Workers

5 queue berbeda untuk isolasi prioritas:

| Queue | Fungsi |
|-------|--------|
| `billing` | Invoice, overdue, pembayaran |
| `provisioning` | PPPoE, VLAN, VID sync |
| `network` | Operasi jaringan |
| `monitoring` | PPPoE monitoring, ONT sync |
| `notifications` | Telegram, notifikasi |

```bash
# Jalankan worker semua queue
php artisan queue:work \
  --queue=billing,provisioning,network,monitoring,notifications,default \
  --sleep=3 --tries=3 --max-time=3600
```

### 12.3 Konfigurasi Crontab

```cron
* * * * * cd /var/www/feralix-billing && php artisan schedule:run >> /dev/null 2>&1
```

### 12.4 Supervisor (Production)

```ini
[program:feralix-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/feralix-billing/artisan queue:work \
  --queue=billing,provisioning,network,monitoring,notifications,default \
  --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/feralix-worker.log
stopwaitsecs=3600
```

---

## 13. API Endpoints Lengkap

### 13.1 Autentikasi

```
POST   /api/v1/auth/login          Login (throttle: 5 req/menit)
GET    /api/v1/auth/me             Info user login
POST   /api/v1/auth/logout         Logout & revoke token
```

Token kedaluwarsa diatur via:
```env
SANCTUM_TOKEN_EXPIRATION=10080  # 7 hari (dalam menit)
```

### 13.2 Dashboard

```
GET    /api/v1/admin/dashboard
PATCH  /api/v1/admin/dashboard/router-switch
GET    /api/v1/admin/technician-dashboard
POST   /api/v1/admin/technician-dashboard/export-pdf
```

### 13.3 Customers

```
GET    /api/v1/admin/customers
POST   /api/v1/admin/customers
GET    /api/v1/admin/customers/{id}
PUT    /api/v1/admin/customers/{id}
DELETE /api/v1/admin/customers/{id}
DELETE /api/v1/admin/customers/{id}/terminate
POST   /api/v1/admin/customers/onboard
POST   /api/v1/admin/customers/provisioning
POST   /api/v1/admin/customers/provisioning-preview
POST   /api/v1/admin/customers/bulk-delete
POST   /api/v1/admin/customers/bulk-disable
POST   /api/v1/admin/customers/bulk-generate-invoice
GET    /api/v1/admin/customer-references
```

### 13.4 Services & Packages

```
GET    /api/v1/admin/services
POST   /api/v1/admin/services
GET    /api/v1/admin/services/{id}
PUT    /api/v1/admin/services/{id}
DELETE /api/v1/admin/services/{id}

GET    /api/v1/admin/packages
POST   /api/v1/admin/packages
GET    /api/v1/admin/packages/{id}
PUT    /api/v1/admin/packages/{id}
DELETE /api/v1/admin/packages/{id}
```

### 13.5 Invoices & Payments

```
GET    /api/v1/admin/invoices
POST   /api/v1/admin/invoices
GET    /api/v1/admin/invoices/{id}
PUT    /api/v1/admin/invoices/{id}
DELETE /api/v1/admin/invoices/{id}
GET    /api/v1/admin/invoices/overdue
GET    /api/v1/admin/invoices/paid
GET    /api/v1/admin/invoices/unpaid
POST   /api/v1/admin/invoices/generate-monthly
POST   /api/v1/admin/invoices/manual-generate
POST   /api/v1/admin/invoices/bulk-action
POST   /api/v1/admin/invoices/auto-suspend
PATCH  /api/v1/admin/invoices/{id}/mark-paid
PATCH  /api/v1/admin/invoices/{id}/mark-overdue
POST   /api/v1/admin/invoices/{id}/send-whatsapp
GET    /api/v1/admin/invoices/{id}/pdf

GET    /api/v1/admin/payments
POST   /api/v1/admin/payments
GET    /api/v1/admin/payments/{id}
```

### 13.6 Routers & Network

```
# Router management
GET    /api/v1/admin/routers
POST   /api/v1/admin/routers
GET    /api/v1/admin/routers/{id}
PUT    /api/v1/admin/routers/{id}
DELETE /api/v1/admin/routers/{id}
POST   /api/v1/admin/routers/{id}/test-connection
POST   /api/v1/admin/routers/{id}/test-acs
POST   /api/v1/admin/routers/{id}/sync-ont
POST   /api/v1/admin/routers/{id}/detect-version
GET    /api/v1/admin/routers/{id}/stats

# Router scopes
GET    /api/v1/admin/router-scopes
POST   /api/v1/admin/router-scopes
PUT    /api/v1/admin/router-scopes/{id}
DELETE /api/v1/admin/router-scopes/{id}

# Router sync
POST   /api/v1/admin/router-sync/pppoe
POST   /api/v1/admin/router-sync/static
POST   /api/v1/admin/router-sync/address-list
POST   /api/v1/admin/router-sync/all
GET    /api/v1/admin/mikrotik/pppoe-servers

# Network locations
GET    /api/v1/admin/network-locations
POST   /api/v1/admin/network-locations
GET    /api/v1/admin/network-locations/{id}
PUT    /api/v1/admin/network-locations/{id}
DELETE /api/v1/admin/network-locations/{id}

# OLTs
GET    /api/v1/admin/olts
POST   /api/v1/admin/olts
GET    /api/v1/admin/olts/{id}
PUT    /api/v1/admin/olts/{id}
DELETE /api/v1/admin/olts/{id}
GET    /api/v1/admin/olts/{id}/pon-status
GET    /api/v1/admin/olts/{id}/pon-ports
POST   /api/v1/admin/olts/{id}/pon-ports
PUT    /api/v1/admin/pon-ports/{id}
DELETE /api/v1/admin/pon-ports/{id}

# ODC & ODP
GET/POST/PUT/DELETE /api/v1/admin/odcs
GET/POST/PUT/DELETE /api/v1/admin/odps

# ONTs
GET    /api/v1/admin/onts
POST   /api/v1/admin/onts
GET    /api/v1/admin/onts/{id}
PUT    /api/v1/admin/onts/{id}
DELETE /api/v1/admin/onts/{id}
GET    /api/v1/admin/onts/online
GET    /api/v1/admin/onts/offline

GET    /api/v1/admin/fiber-map
GET    /api/v1/admin/monitoring/pppoe
```

### 13.7 IP Pool & VID

```
GET    /api/v1/admin/ip-pools
GET    /api/v1/admin/ip-pools/suggest
GET    /api/v1/admin/ip-pools/preview
POST   /api/v1/admin/ip-pools/save-selection
POST   /api/v1/admin/ip-pools/sync
GET    /api/v1/admin/routers/{id}/ip-pools
GET    /api/v1/admin/routers/{id}/ip-pools/summary
GET    /api/v1/admin/routers/{id}/ip-pools/utilization
GET    /api/v1/admin/routers/{id}/ip-pools/suggest-for-vid
GET    /api/v1/admin/routers/{id}/ip-pools/vids-with-availability

GET/POST/PUT/DELETE /api/v1/admin/vids
```

### 13.8 Isolation & PPPoE Import

```
GET    /api/v1/admin/service-isolations
GET    /api/v1/admin/service-isolations/suggestions
POST   /api/v1/admin/service-isolations
PATCH  /api/v1/admin/service-isolations/{id}/applied
PATCH  /api/v1/admin/service-isolations/{id}/release
POST   /api/v1/admin/isolir/manual
POST   /api/v1/admin/isolir/release

GET    /api/v1/admin/pppoe-import/candidates
POST   /api/v1/admin/pppoe-import/import
```

### 13.9 Hotspot

```
GET/POST/PUT    /api/v1/admin/hotspot-profiles
GET/GET{id}     /api/v1/admin/hotspot-vouchers
POST            /api/v1/admin/hotspot-vouchers/{id}/activate
GET/POST/GET{id}/api/v1/admin/voucher-batches
GET/POST/PUT    /api/v1/admin/resellers

POST   /api/v1/admin/hotspot-router/activate
POST   /api/v1/admin/hotspot-router/activate-all
DELETE /api/v1/admin/hotspot-router/deactivate
DELETE /api/v1/admin/hotspot-router/deactivate-all
POST   /api/v1/admin/hotspot-router/sync-radius
GET    /api/v1/admin/hotspot-router/services/{voucher}
GET    /api/v1/admin/hotspot-router/routers/{router}/services
```

### 13.10 Settings & System

```
GET/POST/CRUD /api/v1/admin/users
PATCH         /api/v1/admin/users/{id}/disable
PATCH         /api/v1/admin/users/{id}/enable
PATCH         /api/v1/admin/users/{id}/reset-password

GET/SHOW /api/v1/admin/activity-logs

GET/CRUD/test /api/v1/admin/telegram-bots
GET/CRUD/test /api/v1/admin/telegram-groups

GET   /api/v1/admin/database-settings
PATCH /api/v1/admin/database-settings/{id}
POST  /api/v1/admin/database-settings/test

GET/CRUD /api/v1/admin/cashflows
GET      /api/v1/admin/cashflows/summary

GET/CRUD /api/v1/admin/locations
```

### 13.11 Internal (Tidak Butuh Auth)

```
POST /api/v1/internal/hotspot-radius/authorize
POST /api/v1/internal/hotspot-radius/accounting
```

### 13.12 Technician

```
GET /api/v1/technician/dashboard
```

---

## 14. Roles & Router Scope

### 14.1 Middleware Stack

```
auth:sanctum        → Verifikasi Sanctum token
panel.role:X,Y,Z   → Cek role user (OR condition)
router.scope.bindings → Inject router scope ke request
```

### 14.2 Matrix Akses

| Endpoint Group | superadmin | admin | technician | reseller |
|---------------|:---:|:---:|:---:|:---:|
| Dashboard | ✅ | ✅ | ✅ | ❌ |
| Customers CRUD | ✅ | ✅ | ❌ | ❌ |
| Invoices | ✅ | ✅ | ❌ | ❌ |
| Routers CRUD | ✅ | ✅ | ❌ | ❌ |
| Network inventory | ✅ | ✅ | ❌ | ❌ |
| Users management | ✅ | ✅ | ❌ | ❌ |
| Technician dashboard | ✅ | ✅ | ✅ | ❌ |
| Hotspot vouchers | ✅ | ✅ | ❌ | ✅ |
| System settings | ✅ | ✅ | ❌ | ❌ |
| Technician endpoint | ✅ | ✅ | ✅ | ❌ |

### 14.3 Router Scope

Router scope membatasi data yang terlihat berdasarkan router yang dipilih:

| Model | Filter |
|-------|--------|
| Service | `where('router_id', $id)` |
| OLT | `where('router_id', $id)` |
| Customer | `whereHas('services', ...)` |
| Invoice | `whereHas('service', ...)` |
| ONT | `whereHas('olt', ...)` |
| ODP | `whereHas('olt', ...)` |
| ODC | `whereHas('location.olts', ...)` |
| NetworkLocation | `whereHas('olts', ...)` |
| Ticket | `whereHas('service', ...)` |
| WorkOrder | `where('router_id', $id)` |
| Cashflow | `where('router_id', $id)` |
| ServiceIsolation | `where('router_id', $id)` |

---

## 15. Frontend Architecture

### 15.1 Stack

- **Alpine.js 3.14.9** — Reactive framework (tanpa build step untuk komponen kecil)
- **Tailwind CSS 4.0.0** — Utility-first styling
- **Vite 7.0.7** — Build tool dengan HMR
- **Axios 1.11.0** — HTTP client via `resources/js/services/api.js`

### 15.2 Entry Points

| File | Fungsi |
|------|--------|
| `resources/js/app.js` | Main SPA entry point |
| `resources/js/admin.js` | State & logic panel admin |
| `resources/js/login.js` | Halaman login |
| `resources/js/services/api.js` | Wrapper Axios |
| `resources/js/services/token.js` | Token management |

### 15.3 State Utama (admin.js)

```javascript
{
  page: 'dashboard',
  items: [],
  pagination: { current_page, last_page, total },
  filters: {
    search: '',
    page: 1,
    per_page: 15,
    router_id: null
  },
  routerSwitcher: {
    enabled: false,
    active_router_id: '',
    available_routers: []
  },
  // Module-specific states
  ipPools, routerSync, provisioning,
  manualIsolir, pppoeImport, acsConfig,
  cashflow, masterLokasi, masterOlt,
  // Reference data (dropdown options)
  references: { customers, services, routers, olts, ... }
}
```

### 15.4 Router Scope Sync

Saat admin mengganti router di switcher:

```javascript
async switchRouter() {
  const routerId = Number(this.routerSwitcher.active_router_id) || null;

  // 1. Persist ke URL query string
  url.searchParams.set('router_id', routerId);
  window.history.replaceState({}, '', url);

  // 2. Simpan ke sesi server
  await api.patch('/api/v1/admin/dashboard/router-switch', { router_id: routerId });

  // 3. Sync ke semua module state
  this.filters.router_id = routerId;
  this.ipPools.router_id = routerId;
  this.routerSync.router_id = routerId;
  this.manualIsolir.router_id = routerId;
  this.provisioning.form.router_id = routerId;

  // 4. Reload halaman aktif
  await this.loadPage();
}
```

### 15.5 Build & Development

```bash
# Development (dengan HMR)
npm run dev

# Production build
npm run build
```

---

## 16. Setup & Deployment

### 16.1 Requirements

- PHP 8.2+ dengan ekstensi: `mbstring`, `xml`, `curl`, `gd`, `zip`, `pdo_mysql`, `sockets`
- MySQL 8.0+ atau MariaDB 10.6+
- Node.js 20+ & npm
- Composer 2+

### 16.2 Environment Variables Penting

```env
# === Application ===
APP_NAME="Feralix ISP Cloud"
APP_ENV=production
APP_KEY=                         # Generate dengan: php artisan key:generate
APP_URL=https://your-domain.com
APP_DEBUG=false
APP_SEED_SAMPLE_DATA=false
APP_SUPERADMIN_USERNAME=arya
APP_SUPERADMIN_PASSWORD=

# === Database ===
DB_CONNECTION=mysql              # atau 'mariadb'
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=feralix_billing
DB_USERNAME=feralix_user
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
SANCTUM_TOKEN_EXPIRATION=10080   # 7 hari

# === Queue ===
QUEUE_CONNECTION=database        # Gunakan 'redis' di production besar
AUTOMATION_QUEUE_BILLING=billing
AUTOMATION_QUEUE_PROVISIONING=provisioning
AUTOMATION_QUEUE_NETWORK=network
AUTOMATION_QUEUE_MONITORING=monitoring
AUTOMATION_QUEUE_NOTIFICATIONS=notifications

# === MikroTik ===
MIKROTIK_SYNC_PROVIDER=real      # 'fake' untuk dev
MIKROTIK_ISOLATION_ADDRESS_LIST_NAME=ISOLIR_CUSTOMER
MIKROTIK_API_CONNECT_TIMEOUT=5
MIKROTIK_API_READ_TIMEOUT=15

# === GenieACS ===
GENIEACS_SYNC_PROVIDER=real      # 'fake' untuk dev
GENIEACS_HTTP_TIMEOUT=15
GENIEACS_HTTP_CONNECT_TIMEOUT=5

# === Hotspot / RADIUS ===
HOTSPOT_RADIUS_PROVIDER=freeradius  # 'stub' untuk dev
HOTSPOT_RADIUS_INTERNAL_SECRET=
HOTSPOT_RADIUS_EXPIRED_REDIRECT_URL=

# === Telegram ===
TELEGRAM_HELPDESK_GROUP_NAME=helpdesk

# === Billing ===
BILLING_MONTHLY_INVOICE_DUE_IN_DAYS=10
BILLING_MONTHLY_INVOICE_PENALTY_AMOUNT=0
BILLING_OVERDUE_SYNC_CHUNK=200
BILLING_OVERDUE_ISOLATION_CHUNK=100

# === Automation Schedule ===
AUTOMATION_SCHEDULE_TIMEZONE=Asia/Jakarta
AUTOMATION_SCHEDULE_GENERATE_INVOICES_AT=00:05
AUTOMATION_SCHEDULE_CHECK_OVERDUE_AT=00:20
AUTOMATION_SCHEDULE_CREATE_OVERDUE_ISOLATIONS_AT=00:30
AUTOMATION_SCHEDULE_SYNC_MIKROTIK_VID_CRON="*/15 * * * *"
AUTOMATION_SCHEDULE_SYNC_PPPOE_CRON="* * * * *"
AUTOMATION_SCHEDULE_SYNC_GENIEACS_CRON="*/5 * * * *"
AUTOMATION_SCHEDULE_PROCESS_TELEGRAM_CRON="* * * * *"

# === Logging ===
LOG_CHANNEL=stack
AUTOMATION_LOG_CHANNEL=automation
AUTOMATION_LOG_LEVEL=info
```

### 16.3 Instalasi

```bash
# 1. Clone & masuk direktori
git clone <repo-url> feralix-billing
cd feralix-billing

# 2. Setup lengkap (satu perintah)
composer run setup
# Ekuivalen dengan:
#   composer install
#   cp .env.example .env (jika belum ada)
#   php artisan key:generate
#   php artisan migrate --force
#   npm install && npm run build

# 3. Konfigurasi .env
cp .env.example .env
nano .env    # Isi DB, MikroTik, dll.

# 4. Jalankan migrasi
php artisan migrate

# 5. (Opsional) Seed data awal
php artisan db:seed
```

### 16.4 Development Mode

```bash
# Jalankan semua service sekaligus:
composer run dev
# Menjalankan: server, queue worker, log viewer (pail), vite dev server
```

### 16.5 Production Checklist

```bash
# Optimasi autoloader
composer install --optimize-autoloader --no-dev

# Build assets production
npm run build

# Cache konfigurasi & routes
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Setup storage link
php artisan storage:link

# Cek health database
php artisan database:health-check
```

### 16.6 Web Server (Nginx)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/feralix-billing/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /isolir {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

## 17. Common Tasks

### 17.1 Menjalankan Command Manual

```bash
# Generate invoice bulan ini
php artisan billing:generate-monthly-invoices

# Cek dan tandai overdue
php artisan billing:check-overdue-invoices

# Buat isolasi untuk yang overdue
php artisan billing:create-overdue-isolations

# Sync IP pools dari MikroTik router tertentu
php artisan ip-pools:sync --router=103

# Sync VID dari MikroTik
php artisan mikrotik:sync-vids --router=103

# Sync ONT dari GenieACS
php artisan genieacs:sync-onts --router=103

# Monitor PPPoE status
php artisan monitor:sync-pppoe

# Kirim notifikasi Telegram yang mengantri
php artisan process-telegram-notifications

# Audit penggunaan VID
php artisan audit:vid-assignments

# Cek kesehatan database
php artisan database:health-check

# Deteksi versi RouterOS
php artisan detect:router-os-version
```

### 17.2 Menambah Controller Baru

```bash
# 1. Buat form requests
php artisan make:request ModuleName/IndexModuleNameRequest
php artisan make:request ModuleName/StoreModuleNameRequest
php artisan make:request ModuleName/UpdateModuleNameRequest

# 2. Buat API resource
php artisan make:resource ModuleNameResource

# 3. Buat controller
php artisan make:controller Api/V1/Admin/ModuleNameController

# 4. Tambah route di routes/api.php dalam group yang sesuai

# 5. Tambah filter router_id mengikuti pola:
```

```php
// Pola filter router_id di controller
$query = Model::query()
    ->when(
        $filters['router_id'] ?? null,
        fn($q, $id) => $q->where('router_id', (int) $id)
    )
    ->paginate($perPage);
```

### 17.3 Test Koneksi MikroTik via Tinker

```bash
php artisan tinker
```

```php
$router = App\Models\Router::find(1);
$factory = app(App\Contracts\Mikrotik\MikrotikApiClientFactory::class);
$client = $factory->make($router);
try {
    $client->connect();
    echo "Connected!\n";
    $client->disconnect();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 17.4 Test API via cURL

```bash
# Login
TOKEN=$(curl -s -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"arya","password":"password"}' \
  | jq -r '.data.token')

# Gunakan token
curl http://localhost/api/v1/admin/customers \
  -H "Authorization: Bearer $TOKEN"
```

---

## 18. Troubleshooting

### 18.1 Queue Job Tidak Berjalan

```bash
# Cek antrian job yang gagal
php artisan queue:failed

# Retry semua job gagal
php artisan queue:retry all

# Flush job gagal
php artisan queue:flush

# Restart worker
php artisan queue:restart
```

### 18.2 Koneksi MikroTik Gagal

Cek urutan berikut:
1. Pastikan IP router dapat di-ping dari server Feralix
2. Pastikan API service aktif di MikroTik: `/ip service print`
3. Pastikan port 8728 tidak diblok firewall
4. Cek credential di tabel `routers`
5. Coba test via: `POST /api/v1/admin/routers/{id}/test-connection`

```bash
# Test via tinker
php artisan tinker --execute="
    \$router = App\Models\Router::find(1);
    echo \$router->host . ':' . \$router->api_port;
"
```

### 18.3 Invoice Tidak Generate

```sql
-- Cek service tanpa harga
SELECT s.service_code, c.full_name, s.overall_status
FROM services s
JOIN customers c ON s.customer_id = c.id
WHERE s.monthly_price IS NULL
  AND s.package_id IS NULL
  AND s.overall_status IN ('active', 'down', 'suspended', 'isolated');

-- Cek duplikasi invoice bulan ini
SELECT service_id, billing_period, COUNT(*) as cnt
FROM invoices
WHERE billing_period = DATE_FORMAT(NOW(), '%Y-%m')
GROUP BY service_id, billing_period
HAVING cnt > 1;
```

### 18.4 Isolasi Tidak Release Setelah Bayar

```sql
-- Cek isolasi yang masih applied meski invoice sudah paid
SELECT si.id, si.status, si.service_id, i.payment_status
FROM service_isolations si
JOIN invoices i ON si.invoice_id = i.id
WHERE si.status = 'applied'
  AND i.payment_status = 'paid';
```

Penyebab umum: queue worker tidak berjalan saat pembayaran diproses.

### 18.5 Lokasi Log

| Log | Lokasi |
|-----|--------|
| Laravel utama | `storage/logs/laravel.log` |
| Automation (billing, sync) | `storage/logs/automation-YYYY-MM-DD.log` |
| Job gagal | Database: tabel `failed_jobs` |
| MikroTik sync | Database: tabel `mikrotik_sync_vid_logs` |
| Router operations | Database: tabel `service_router_operation_logs` |
| Telegram | Database: tabel `telegram_logs` |

### 18.6 Debug Query Berguna

```sql
-- Invoice overdue yang belum terisolasi
SELECT i.invoice_number, c.full_name, s.pppoe_username, i.due_date
FROM invoices i
JOIN customers c ON i.customer_id = c.id
JOIN services s ON i.service_id = s.id
WHERE i.payment_status = 'overdue'
  AND NOT EXISTS (
    SELECT 1 FROM service_isolations si
    WHERE si.service_id = i.service_id
      AND si.status IN ('pending', 'applied')
  );

-- Isolasi terbuka
SELECT si.id, si.status, c.full_name, s.pppoe_username, si.isolated_at
FROM service_isolations si
JOIN services s ON si.service_id = s.id
JOIN customers c ON s.customer_id = c.id
WHERE si.status IN ('pending', 'applied')
ORDER BY si.isolated_at DESC;

-- IP pool utilization per router
SELECT r.router_name, ips.vid, ips.pool_name,
       ips.used_ips, ips.free_ips, ips.is_available
FROM ip_pool_snapshots ips
JOIN routers r ON ips.router_id = r.id
ORDER BY r.id, ips.vid;
```

---

## Appendix A: Artisan Commands Reference

| Command | Deskripsi |
|---------|-----------|
| `billing:generate-monthly-invoices` | Generate invoice bulanan |
| `billing:check-overdue-invoices` | Tandai invoice overdue |
| `billing:create-overdue-isolations` | Buat isolasi untuk overdue |
| `sync:mikrotik-vid` | Sync VID dari MikroTik |
| `sync:pppoe-monitor` | Sync status PPPoE |
| `sync:genieacs-ont` | Sync ONT dari GenieACS |
| `ip-pools:sync` | Sync IP pool |
| `process-telegram-notifications` | Proses antrian notifikasi Telegram |
| `audit:vid-assignments` | Audit penggunaan VID |
| `database:health-check` | Cek kesehatan database |
| `detect:router-os-version` | Detect versi RouterOS |

---

## Appendix B: Service Layer Reference

| Service | Lokasi | Fungsi Utama |
|---------|--------|--------------|
| `InvoiceService` | `Services/Billing/` | CRUD invoice |
| `MonthlyInvoiceGenerationService` | `Services/Billing/` | Generate invoice bulanan |
| `InvoiceOverdueService` | `Services/Billing/` | Tandai overdue |
| `InvoiceAutoSuspendService` | `Services/Billing/` | Trigger auto-isolasi |
| `PaymentService` | `Services/Billing/` | Proses pembayaran |
| `CashflowIncomeService` | `Services/Billing/` | Catat pemasukan |
| `FtthServiceManager` | `Services/Provisioning/` | Lifecycle layanan FTTH |
| `PppoeCredentialService` | `Services/Provisioning/` | Generate credential PPPoE |
| `ServiceIsolationService` | `Services/Provisioning/` | Manajemen isolasi |
| `MikrotikPppoeSecretService` | `Services/Mikrotik/` | PPPoE secret operations |
| `MikrotikAddressListService` | `Services/Mikrotik/` | Address-list isolasi |
| `IpPoolSyncService` | `Services/Mikrotik/` | Sync IP pool dari router |
| `IpPoolService` | `Services/Mikrotik/` | VID suggestion & management |
| `CentralizedHotspotService` | `Services/Hotspot/` | Multi-router hotspot |
| `HotspotVoucherService` | `Services/Hotspot/` | Operasi voucher |
| `VoucherBatchService` | `Services/Hotspot/` | Batch generation |

---

*Dokumentasi ini dibuat ulang secara menyeluruh berdasarkan analisis kodebase.*
*Versi: 2026-05-14*
