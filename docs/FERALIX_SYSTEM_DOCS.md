# Feralix ISP Cloud — Dokumentasi Sistem Lengkap

> **Stack**: Laravel 12 · MySQL · Alpine.js · Vite · Mikrotik API · GenieACS TR-069 · Sanctum Auth
> **Timezone**: Asia/Jakarta (terkonfigurasi di `config/automation.php`)
> **Versi**: 2026-05-10
> **Dokumentasi untuk Developer Baru**

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Database Schema](#3-database-schema)
4. [Fitur & Module](#4-fitur--module)
5. [Billing Engine](#5-billing-engine)
6. [Network & Provisioning](#6-network--provisioning)
7. [Mikrotik Integration](#7-mikrotik-integration)
8. [Scheduler & Automation](#8-scheduler--automation)
9. [API Endpoints](#9-api-endpoints)
10. [Router Scope Architecture](#10-router-scope-architecture)
11. [Frontend Architecture](#11-frontend-architecture)
12. [Setup & Deployment](#12-setup--deployment)
13. [Common Tasks](#13-common-tasks)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. Gambaran Umum

### 1.1 Apa itu Feralix ISP Cloud?

Feralix adalah sistem manajemen billing dan operasional untuk Internet Service Provider (ISP) berbasis Fiber-to-the-Home (FTTH). Sistem ini mengotomatisasi seluruh siklus bisnis ISP:

- **Customer Management** — Pendaftaran, provisioning, dan terminasi pelanggan
- **Billing & Invoicing** — Generate invoice bulanan, tracking payment, overdue management
- **IP Address Management** — Pool management, VID assignment, subnet allocation
- **Service Isolation** — Auto isolir pelanggan overdue, release saat bayar
- **PPPoE Monitoring** — Monitoring online/offline pelanggan dari Mikrotik
- **ONT Management** — Monitoring ONT via GenieACS TR-069
- **Network Inventory** — OLT, ODC, ODP management
- **Helpdesk** — Tiket dan Work Order management
- **Finance** — Cashflow dan Hotspot management

### 1.2 Target Pengguna

| Role | Akses |
|------|-------|
| Superadmin | Full access, semua router, semua data |
| Admin | Full access tapi dibatasi router tertentu |
| Technician | Dashboard teknisi,WO assignment, tiket |
| Reseller | Hotspot voucher management |

### 1.3 Browser Requirements

- Modern browser (Chrome, Firefox, Safari, Edge terbaru)
- JavaScript enabled
- Tidak ada requirement khusus di backend

---

## 2. Arsitektur Sistem

### 2.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT                                   │
│    ┌─────────────────┐     ┌──────────────────┐                │
│    │  Admin Panel    │     │  Teknisi Panel   │                │
│    │  (Alpine.js)     │     │  (Alpine.js)     │                │
│    │  /admin/*       │     │  /technician/*   │                │
│    └────────┬────────┘     └────────┬─────────┘                │
│             │ HTTP API               │ HTTP API                 │
└─────────────┼───────────────────────┼───────────────────────────┘
              │                       │
              ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                     LARAVEL 12 BACKEND                           │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Controllers  │  │   Services   │  │    Jobs      │          │
│  │ (API Entry)  │  │ (Business)   │  │  (Async)     │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                 │                   │                   │
│  ┌──────┴─────────────────┴───────────────────┴──────┐        │
│  │                    MODELS (Eloquent)                  │        │
│  └──────────────────────────┬───────────────────────────┘        │
└─────────────────────────────┼─────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│    MySQL      │    │   Mikrotik   │    │   GenieACS   │
│   Database   │    │    Router    │    │  TR-069 ACS  │
│              │    │  (API Port)  │    │              │
└──────────────┘    └──────────────┘    └──────────────┘
```

### 2.2 Directory Structure

```
/var/www/feralix-billing/
├── app/
│   ├── Console/                    # Artisan commands
│   │   └── Commands/              # Scheduler commands
│   ├── Enums/                     # Enum definitions
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Admin/            # Admin API controllers
│   │   │   └── Technician/       # Technician API controllers
│   │   ├── Requests/             # Form request validators
│   │   │   └── [Module]/         # Organized by module
│   │   └── Resources/            # API Resources (transformers)
│   ├── Jobs/                      # Queue jobs
│   ├── Models/                    # Eloquent models
│   ├── Services/                  # Business logic
│   │   ├── Access/               # Role-based access control
│   │   ├── Audit/                # Activity logging
│   │   ├── Auth/                 # Authentication
│   │   ├── Billing/              # Invoice, payment logic
│   │   ├── Customer/            # Customer management
│   │   ├── Dashboard/          # Dashboard data
│   │   ├── FieldWork/           # Work order
│   │   ├── Finance/             # Cashflow
│   │   ├── Helpdesk/            # Tickets
│   │   ├── Hotspot/             # Hotspot management
│   │   ├── Inventory/           # Network inventory
│   │   ├── MasterData/          # Customer service
│   │   ├── Mikrotik/            # Mikrotik API integration
│   │   ├── Monitoring/          # PPPoE, ONT monitoring
│   │   ├── Network/             # Network management
│   │   └── Provisioning/        # Service provisioning
│   └── Contracts/               # Interface definitions
├── config/                       # Laravel config files
├── database/
│   ├── migrations/              # Database migrations
│   └── seeders/                # Database seeders
├── resources/
│   ├── js/                     # Alpine.js frontend
│   │   ├── admin.js            # Main admin panel
│   │   └── services/           # API service layer
│   └── views/                  # Blade templates
├── routes/                      # Route definitions
└── tests/                      # PHPUnit tests
```

### 2.3 Technology Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| Backend Framework | Laravel 12 | PHP framework |
| Database | MySQL | Primary data store |
| Authentication | Laravel Sanctum | API token auth |
| Frontend | Alpine.js + Vite | Reactive SPA |
| Queue | Database Driver | Async job processing |
| Mikrotik API | Socket API | Router communication |
| ONT Management | GenieACS | TR-069 ACS for ONT |
| PDF Generation | DomPDF | Invoice PDF export |

---

## 3. Database Schema

### 3.1 Entity Relationship Overview

```
┌─────────────┐         ┌─────────────┐
│   Router    │ 1──────>│    OLT      │
└─────────────┘ 1:N     └─────────────┘
      │                   │
      │                   │ 1:N
      │                   ▼
      │              ┌─────────────┐
      │              │    ONT      │
      │              └─────────────┘
      │
      │ 1:N         ┌─────────────┐
      ▼             │NetworkLoc   │
┌─────────────┐     └──────┬──────┘
│  Service    │            │ 1:N
└──────┬──────┘            ├────────┐
       │ 1:1              ▼        ▼
       ▼           ┌─────────┐ ┌───────┐
┌─────────────┐    │   ODC   │ │  ODP  │
│  Customer   │    └─────────┘ └───────┘
└─────────────┘
       │
       │ 1:N
       ▼
┌─────────────┐    ┌─────────────┐
│  Invoice    │───>│  Payment    │
└─────────────┘ 1:1└─────────────┘
```

### 3.2 Core Tables

#### customers
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| customer_code | varchar | Unique customer code (CUST-XXXXX) |
| full_name | varchar | Customer name |
| phone | varchar | Phone number |
| email | varchar | Email (nullable) |
| address | text | Address |
| network_location_id | bigint FK | Reference to network_locations |
| preferred_olt_id | bigint FK | Preferred OLT for installation |
| assigned_technician_id | bigint FK | Assigned technician |
| customer_type | enum | residential, business, internal |
| status | enum | active, inactive |
| monthly_price | decimal | Monthly billing amount |
| billing_day | int | Day of month for billing (1-28) |
| install_date | date | Installation date |
| latitude, longitude | decimal | GPS coordinates |
| created_at, updated_at | timestamp | Timestamps |

#### services
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| customer_id | bigint FK | Reference to customers |
| service_code | varchar | Unique service code (SVC-XXXXX) |
| package_id | bigint FK | Service package reference |
| router_id | bigint FK | Mikrotik router reference |
| olt_id | bigint FK | OLT reference |
| ont_id | bigint FK | ONT reference |
| vid_id | bigint FK | IP pool snapshot reference |
| access_mode | enum | vlan, pppoe, static |
| pppoe_username | varchar | PPPoE username |
| pppoe_password | varchar | PPPoE password (encrypted) |
| internet_vid | int | VLAN ID for internet |
| monitor_vid | int | VLAN ID for monitoring |
| subnet_cidr | varchar | Static IP subnet |
| isolation_method | enum | address_list, firewall_filter, ppp_profile, queue |
| billing_status | enum | pending, paid, overdue, suspended, closed |
| network_status | enum | provisioning, active, isolated, down, inactive |
| overall_status | enum | provisioning, active, down, suspended, isolated, inactive, terminated |
| activation_date | date | When service was activated |
| monthly_price | decimal | Monthly price (can override package price) |

#### invoices
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| customer_id | bigint FK | Reference to customers |
| service_id | bigint FK | Reference to services |
| invoice_number | varchar | Unique invoice number |
| billing_period | varchar | Period in YYYY-MM format |
| invoice_date | date | Date invoice was created |
| due_date | date | Payment due date |
| subtotal | decimal | Total before penalty |
| penalty_amount | decimal | Late payment penalty |
| total_amount | decimal | Final amount due |
| remaining_amount | decimal | Amount still owed |
| payment_status | enum | unpaid, issued, overdue, partially_paid, paid, canceled |
| issued_at | timestamp | When invoice was issued |
| paid_at | timestamp | When payment was received |
| overdue_marked_at | timestamp | When marked overdue |

#### payments
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| invoice_id | bigint FK | Reference to invoices |
| customer_id | bigint FK | Reference to customers |
| service_id | bigint FK | Reference to services |
| amount_paid | decimal | Amount paid |
| payment_method | varchar | Payment method used |
| paid_at | timestamp | Payment timestamp |
| reference_no | varchar | External reference number |

#### service_isolations
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| service_id | bigint FK | Reference to services |
| router_id | bigint FK | Router where isolation was applied |
| invoice_id | bigint FK | Related invoice (nullable) |
| isolation_type | enum | manual, auto |
| target_type | enum | subnet, pppoe, static |
| address_list_name | varchar | Mikrotik address-list name |
| target_subnet | varchar | Subnet to block |
| target_identifier | varchar | IP or username to block |
| status | enum | pending, applied, released, failed |
| isolated_at | timestamp | When isolation was applied |
| released_at | timestamp | When isolation was released |

### 3.3 Network Tables

#### routers
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| router_code | varchar | Router identifier code |
| router_name | varchar | Human-readable name |
| host | varchar | Mikrotik IP/hostname |
| api_port | int | API port (default 8728) |
| api_username | varchar | API username |
| api_password | text | Encrypted API password |
| acs_inform_url | varchar | GenieACS inform URL |
| acs_nbi_url | varchar | GenieACS NBI URL |
| acs_username | varchar | GenieACS username |
| acs_password | text | Encrypted GenieACS password |
| status | enum | active, inactive |

#### olts
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| code | varchar | OLT code |
| name | varchar | OLT name |
| host | varchar | OLT IP address |
| network_location_id | bigint FK | Location reference |
| router_id | bigint FK | Associated router |
| pon_ports | int | Number of PON ports |
| brand | varchar | OLT brand/vendor |
| model | varchar | OLT model |
| status | enum | active, inactive |

#### onts
| Column | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| olt_id | bigint FK | OLT reference |
| ont_sn | varchar | ONT serial number |
| ont_name | varchar | ONT name |
| pon_port | int | PON port number |
| onu_id | int | ONU ID |
| ssid_name | varchar | WiFi SSID |
| wifi_password | varchar | WiFi password |
| optical_status | varchar | Optical status |
| rx_power | decimal | RX power (dBm) |
| tx_power | decimal | TX power (dBm) |
| status | varchar | ONT status |
| last_seen_at | timestamp | Last seen by GenieACS |
| genieacs_device_id | varchar | GenieACS device ID |

### 3.4 Enums Reference

```php
// Invoice payment status
enum InvoicePaymentStatus: string
    'unpaid'        // Created, not yet issued
    'issued'        // Issued, before due date
    'overdue'       // Past due date
    'partially_paid' // Partial payment received
    'paid'          // Fully paid
    'canceled'      // Canceled by admin

// Service overall status
enum ServiceOverallStatus: string
    'provisioning'  // Newly created, awaiting first payment
    'active'        // Paid, PPPoE enabled
    'down'          // Connection problem
    'suspended'      // Admin suspended
    'isolated'      // Isolated due to overdue
    'inactive'      // Deactivated
    'terminated'    // Service ended

// Service billing status
enum ServiceBillingStatus: string
    'pending'       // Awaiting payment
    'paid'          // Paid
    'overdue'       // Overdue
    'suspended'     // Suspended
    'closed'        // Closed

// Service access mode
enum ServiceAccessMode: string
    'pppoe'         // PPPoE authentication
    'vlan'          // VLAN-based
    'static'        // Static IP

// Isolation method
enum ServiceIsolationMethod: string
    'address_list'  // Mikrotik address-list (default)
    'firewall_filter'
    'ppp_profile'
    'queue'

// User roles
enum UserRole: string
    'superadmin'    // Full access
    'admin'         // Router-scoped access
    'technician'    // Field technician
    'reseller'      // Hotspot reseller
```

---

## 4. Fitur & Module

### 4.1 Customer Management

| Fitur | Endpoint | Status |
|-------|----------|--------|
| List customers | GET /api/v1/admin/customers | ✅ |
| Create customer | POST /api/v1/admin/customers | ✅ |
| Show customer | GET /api/v1/admin/customers/{id} | ✅ |
| Update customer | PUT /api/v1/admin/customers/{id} | ✅ |
| Delete customer | DELETE /api/v1/admin/customers/{id} | ✅ |
| Terminate customer | DELETE /api/v1/admin/customers/{id}/terminate | ✅ |
| Bulk delete | POST /api/v1/admin/customers/bulk-delete | ✅ |
| Bulk disable | POST /api/v1/admin/customers/bulk-disable | ✅ |

### 4.2 Provisioning

| Fitur | Endpoint | Status |
|-------|----------|--------|
| Provision customer | POST /api/v1/admin/customers/provisioning | ✅ |
| Preview provisioning | POST /api/v1/admin/customers/provisioning-preview | ✅ |
| Onboard (full flow) | POST /api/v1/admin/customers/onboard | ✅ |

**Provisioning Flow:**
```
Admin input form
    ↓
Validate: customer data, package, router, OLT
    ↓
Generate PPPoE username/password
    ↓
Suggest available VID (atomic lock)
    ↓
Create Customer record
    ↓
Create Service record
    ↓
Create PPPoE secret in Mikrotik (disabled=true)
    ↓
Reserve VID in ip_pool_snapshots
    ↓
Generate first invoice
    ↓
Return customer & service details
```

### 4.3 Billing

| Fitur | Endpoint | Status |
|-------|----------|--------|
| List invoices | GET /api/v1/admin/invoices | ✅ |
| Create manual invoice | POST /api/v1/admin/invoices | ✅ |
| Generate monthly | POST /api/v1/admin/invoices/generate-monthly | ✅ |
| Mark paid | POST /api/v1/admin/invoices/{id}/mark-paid | ✅ |
| Mark overdue | POST /api/v1/admin/invoices/{id}/mark-overdue | ✅ |
| Send WhatsApp | POST /api/v1/admin/invoices/{id}/send-whatsapp | ✅ |
| Download PDF | GET /api/v1/admin/invoices/{id}/pdf | ✅ |
| Bulk action | POST /api/v1/admin/invoices/bulk-action | ✅ |
| Auto suspend | POST /api/v1/admin/invoices/auto-suspend | ✅ |

### 4.4 IP Pool & VID Management

| Fitur | Endpoint | Status |
|-------|----------|--------|
| List IP pools | GET /api/v1/admin/ip-pools | ✅ |
| Suggest VID | GET /api/v1/admin/ip-pools/suggest | ✅ |
| Preview live pools | GET /api/v1/admin/ip-pools/preview | ✅ |
| Save pool selection | POST /api/v1/admin/ip-pools/save-selection | ✅ |
| Sync pools | POST /api/v1/admin/ip-pools/sync | ✅ |
| Router pools | GET /api/v1/admin/routers/{id}/ip-pools | ✅ |

### 4.5 Network Inventory

| Fitur | Endpoint | Status |
|-------|----------|--------|
| Network Locations | CRUD /api/v1/admin/network-locations | ✅ |
| OLTs | CRUD /api/v1/admin/olts | ✅ |
| PON Ports | CRUD /api/v1/admin/olts/{id}/pon-ports | ✅ |
| ODCs | CRUD /api/v1/admin/odcs | ✅ |
| ODPs | CRUD /api/v1/admin/odps | ✅ |
| ONTs | CRUD /api/v1/admin/onts | ✅ |
| Fiber Map | GET /api/v1/admin/fiber-map | ✅ |

### 4.6 Service Isolation

| Fitur | Endpoint | Status |
|-------|----------|--------|
| List isolations | GET /api/v1/admin/service-isolations | ✅ |
| Suggestions | GET /api/v1/admin/service-isolations/suggestions | ✅ |
| Create isolation | POST /api/v1/admin/service-isolations | ✅ |
| Mark applied | PATCH /api/v1/admin/service-isolations/{id}/applied | ✅ |
| Release isolation | PATCH /api/v1/admin/service-isolations/{id}/release | ✅ |
| Manual isolir | POST /api/v1/admin/isolir/manual | ✅ |
| Isolir page | GET /isolir | ✅ |

### 4.7 Monitoring

| Fitur | Endpoint | Status |
|-------|----------|--------|
| PPPoE monitoring | GET /api/v1/admin/monitoring/pppoe | ✅ |
| ONT online | GET /api/v1/admin/onts/online | ✅ |
| ONT offline | GET /api/v1/admin/onts/offline | ✅ |
| Router sync | POST /api/v1/admin/router-sync | ✅ |
| PPPoE import | GET/POST /api/v1/admin/pppoe-import | ✅ |

### 4.8 Helpdesk

| Fitur | Endpoint | Status |
|-------|----------|--------|
| Tickets CRUD | /api/v1/admin/tickets | ✅ |
| Ticket replies | POST /api/v1/admin/tickets/{id}/replies | ✅ |
| Work orders CRUD | /api/v1/admin/work-orders | ✅ |
| Technician dashboard | GET /api/v1/admin/technician-dashboard | ✅ |

### 4.9 Finance

| Fitur | Endpoint | Status |
|-------|----------|--------|
| Cashflows | /api/v1/admin/cashflows | ✅ |
| Cashflow summary | GET /api/v1/admin/cashflows/summary | ✅ |
| Resellers | /api/v1/admin/resellers | ✅ |
| Hotspot profiles | /api/v1/admin/hotspot-profiles | ✅ |
| Voucher batches | /api/v1/admin/voucher-batches | ✅ |

---

## 5. Billing Engine

### 5.1 Invoice Generation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ SCHEDULER: billing:generate-monthly-invoices                    │
│ Runs: Tanggal 1 setiap bulan, 00:05                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ MonthlyInvoiceGenerationService::generate()                     │
│                                                                 │
│ 1. Get all active services (overall_status IN active,down,     │
│    suspended, isolated)                                         │
│ 2. For each service:                                            │
│    a. Check if invoice for billing period exists → skip        │
│    b. Calculate amount:                                         │
│       - service.monthly_price                                   │
│       - OR package.monthly_price                               │
│       - OR 0 (if both null)                                     │
│    c. Calculate due_date:                                       │
│       - If install_date exists: same day in current month        │
│       - Else: day 7 of current month                            │
│    d. Create invoice record                                     │
│    e. Update service billing_status = pending                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ SCHEDULER: billing:check-overdue-invoices                       │
│ Runs: Setiap hari, 00:20                                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ InvoiceOverdueService::markOverdue()                            │
│                                                                 │
│ 1. Find invoices where:                                         │
│    - payment_status IN (unpaid, issued)                        │
│    - due_date < today                                           │
│    - overdue_marked_at IS NULL                                  │
│ 2. For each invoice:                                           │
│    a. Update payment_status = overdue                          │
│    b. Set overdue_marked_at = now                              │
│    c. Update service billing_status = overdue                   │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Auto Isolation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ SCHEDULER: billing:create-overdue-isolations                    │
│ Runs: Setiap hari, 00:30                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ InvoiceAutoSuspendService::trigger()                            │
│                                                                 │
│ 1. Find invoices where:                                         │
│    - payment_status = overdue                                  │
│    - No existing open isolation for service                     │
│ 2. For each invoice:                                           │
│    a. Create service_isolation record (status = pending)       │
│    b. Dispatch Job → MikrotikAddressListService                │
│    c. Update isolation status = applied                        │
│    d. Update service overall_status = isolated                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ MikrotikAddressListService::addToIsolationList()                │
│                                                                 │
│ 1. Get service PPPoE IP from Mikrotik                          │
│ 2. Add IP to address-list "ISOLIR_CUSTOMER" in Mikrotik        │
│ 3. Log result to service_isolation record                      │
└─────────────────────────────────────────────────────────────────┘
```

### 5.3 Payment Release Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ API: POST /api/v1/admin/invoices/{id}/mark-paid                │
│ Triggered: Admin marks invoice as paid                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ PaymentService::settleInvoice()                                 │
│                                                                 │
│ 1. Create payment record                                        │
│ 2. Update invoice payment_status                               │
│ 3. Record to cashflow (CashflowIncomeService)                  │
│ 4. Trigger InvoiceIsolationAutomationService::syncForInvoice() │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ InvoiceIsolationAutomationService::syncForInvoice()             │
│                                                                 │
│ 1. Check if all service invoices are paid                      │
│ 2. If no more overdue invoices:                               │
│    a. Find open isolation record                               │
│    b. Dispatch job to release from Mikrotik address-list       │
│    c. Update isolation status = released                      │
│    d. Update service overall_status = active                  │
└─────────────────────────────────────────────────────────────────┘
```

### 5.4 Prepaid Activation Flow

```
Customer provisioned
    ↓
PPPoE secret created in Mikrotik with disabled=true
    ↓
First invoice created (status = issued)
    ↓
Customer pays invoice
    ↓
Payment marked as paid
    ↓
PPPoeSecretService::setSecretEnabled() called
    ↓
PPPoE secret enabled in Mikrotik
    ↓
Service overall_status updated to 'active'
```

---

## 6. Network & Provisioning

### 6.1 VID Assignment Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ API: GET /api/v1/admin/ip-pools/suggest?router_id=X            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ IpPoolService::suggestAvailableVid()                            │
│                                                                 │
│ 1. BEGIN TRANSACTION with row lock                              │
│ 2. Find pools where:                                           │
│    - router_id = X                                             │
│    - is_tracked = true                                         │
│    - is_available = true                                      │
│    - free_ips > 0                                             │
│    - Within router_scope vid_start-vid_end range               │
│ 3. Filter out pools with reserved_by_customer_id                │
│ 4. Select pool with lowest free_ips (fills up pools evenly)   │
│ 5. Return pool details                                         │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2 IP Pool Status Logic

| used_ips | free_ips | is_available | Status Badge |
|----------|----------|-------------|--------------|
| 0 | > 0 | true | **Available** — hijau |
| ≥ 1 | > 0 | true | **Used** — kuning |
| > 0 | 0 | false | **Full** — merah |
| 0 | 0 | false | **Empty** — abu-abu |

---

## 7. Mikrotik Integration

### 7.1 MikrotikApiClientFactory

```php
// Get API client for a router
$client = app(MikrotikApiClientFactory::class)->make($router);

// Methods available:
$client->connect();           // Establish connection
$client->disconnect();        // Close connection
$client->execute($command);   // Execute API command
```

### 7.2 Available Mikrotik Services

| Service | Class | Purpose |
|---------|-------|---------|
| PPPoE Secret | MikrotikPppoeSecretService | Create/Update/Delete PPPoE secrets |
| IP Pool | IpPoolSyncService | Sync IP pools from router |
| Address List | MikrotikAddressListService | Manage address lists (for isolation) |
| PPPoE Monitoring | PppoeMonitoringService | Sync PPPoE online status |
| VID Sync | SyncMikrotikVidCommand | Sync VID from router |
| Router Stats | RouterStatsController | Get router resource info |

### 7.3 PPPoE Secret Operations

```php
// Create PPPoE secret
$service = MikrotikPppoeSecretService::createSecret(
    router: $router,
    username: 'john_doe',
    password: 'secret123',
    profile: 'default',
    comment: 'Customer: John Doe | VID: 100',
    disabled: true  // For prepaid, start disabled
);

// Enable PPPoE (after payment)
MikrotikPppoeSecretService::setSecretEnabled(
    router: $router,
    username: 'john_doe',
    enabled: true
);

// Disable PPPoE (isolation)
MikrotikPppoeSecretService::setSecretEnabled(
    router: $router,
    username: 'john_doe',
    enabled: false
);

// Delete PPPoE secret (termination)
MikrotikPppoeSecretService::deleteSecret(
    router: $router,
    username: 'john_doe'
);
```

---

## 8. Scheduler & Automation

### 8.1 Schedule Configuration

**Location:** `app/Console/Kernel.php`

**Timezone:** Asia/Jakarta (configured in `config/automation.php`)

### 8.2 Cron Schedule

| Schedule | Command | Queue | Purpose |
|----------|---------|-------|---------|
| Monthly 1st, 00:05 | `billing:generate-monthly-invoices` | billing | Generate monthly invoices |
| Daily, 00:20 | `billing:check-overdue-invoices` | billing | Mark overdue invoices |
| Daily, 00:30 | `billing:create-overdue-isolations` | billing | Auto-isolate overdue |
| Every 15 min | `mikrotik:sync-vids` | provisioning | Sync VID from Mikrotik |
| Every 5 min | `ip-pools:sync` | provisioning | Sync IP pools |
| Every minute | `monitor:sync-pppoe` | default | Sync PPPoE status |
| Every 5 min | `genieacs:sync-onts` | default | Sync ONT from GenieACS |
| Every minute | `notifications:process-telegram` | notifications | Process Telegram queue |

### 8.3 Running the Scheduler

```bash
# In production, add this to crontab:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Run queue worker (separate process):
php artisan queue:work --queue=default,billing,provisioning,notifications --sleep=3 --tries=3
```

---

## 9. API Endpoints

### 9.1 Authentication

```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
```

### 9.2 Dashboard

```
GET    /api/v1/admin/dashboard
PATCH  /api/v1/admin/dashboard/router-switch
GET    /api/v1/admin/dashboard/scope
```

### 9.3 Customers

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

### 9.4 Services

```
GET    /api/v1/admin/services
POST   /api/v1/admin/services
GET    /api/v1/admin/services/{id}
PUT    /api/v1/admin/services/{id}
DELETE /api/v1/admin/services/{id}
```

### 9.5 Invoices

```
GET    /api/v1/admin/invoices
POST   /api/v1/admin/invoices
GET    /api/v1/admin/invoices/{id}
PUT    /api/v1/admin/invoices/{id}
DELETE /api/v1/admin/invoices/{id}
POST   /api/v1/admin/invoices/generate-monthly
POST   /api/v1/admin/invoices/{id}/mark-paid
POST   /api/v1/admin/invoices/{id}/mark-overdue
POST   /api/v1/admin/invoices/{id}/send-whatsapp
GET    /api/v1/admin/invoices/{id}/pdf
POST   /api/v1/admin/invoices/bulk-action
POST   /api/v1/admin/invoices/auto-suspend
GET    /api/v1/admin/invoices/overdue
GET    /api/v1/admin/invoices/paid
GET    /api/v1/admin/invoices/unpaid
```

### 9.6 IP Pools

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
```

### 9.7 Network

```
# Locations
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
PUT    /api/v1/admin/olts/{id}/pon-ports/{port}
DELETE /api/v1/admin/olts/{id}/pon-ports/{port}

# ODCs
GET    /api/v1/admin/odcs
POST   /api/v1/admin/odcs
GET    /api/v1/admin/odcs/{id}
PUT    /api/v1/admin/odcs/{id}
DELETE /api/v1/admin/odcs/{id}

# ODPs
GET    /api/v1/admin/odps
POST   /api/v1/admin/odps
GET    /api/v1/admin/odps/{id}
PUT    /api/v1/admin/odps/{id}
DELETE /api/v1/admin/odps/{id}
```

### 9.8 ONTs

```
GET    /api/v1/admin/onts
POST   /api/v1/admin/onts
GET    /api/v1/admin/onts/{id}
PUT    /api/v1/admin/onts/{id}
DELETE /api/v1/admin/onts/{id}
GET    /api/v1/admin/onts/online
GET    /api/v1/admin/onts/offline
```

### 9.9 Routers

```
GET    /api/v1/admin/routers
POST   /api/v1/admin/routers
GET    /api/v1/admin/routers/{id}
PUT    /api/v1/admin/routers/{id}
DELETE /api/v1/admin/routers/{id}
POST   /api/v1/admin/routers/{id}/test-connection
POST   /api/v1/admin/routers/{id}/test-acs
POST   /api/v1/admin/routers/{id}/sync-ont
GET    /api/v1/admin/router-scopes
POST   /api/v1/admin/router-scopes
PUT    /api/v1/admin/router-scopes/{id}
DELETE /api/v1/admin/router-scopes/{id}
```

### 9.10 Isolation

```
GET    /api/v1/admin/service-isolations
GET    /api/v1/admin/service-isolations/suggestions
POST   /api/v1/admin/service-isolations
PATCH  /api/v1/admin/service-isolations/{id}/applied
PATCH  /api/v1/admin/service-isolations/{id}/release
POST   /api/v1/admin/isolir/manual
POST   /api/v1/admin/isolir/release
GET    /isolir                              # Public page
```

### 9.11 Monitoring

```
GET    /api/v1/admin/monitoring/pppoe
POST   /api/v1/admin/router-sync
GET    /api/v1/admin/router-sync/logs
GET    /api/v1/admin/router-stats
GET    /api/v1/admin/pppoe-import
POST   /api/v1/admin/pppoe-import/import
GET    /api/v1/admin/fiber-map
```

### 9.12 Helpdesk

```
# Tickets
GET    /api/v1/admin/tickets
POST   /api/v1/admin/tickets
GET    /api/v1/admin/tickets/{id}
PATCH  /api/v1/admin/tickets/{id}/status
POST   /api/v1/admin/tickets/{id}/replies

# Work Orders
GET    /api/v1/admin/work-orders
POST   /api/v1/admin/work-orders
GET    /api/v1/admin/work-orders/{id}
PUT    /api/v1/admin/work-orders/{id}
DELETE /api/v1/admin/work-orders/{id}

# Technician Dashboard
GET    /api/v1/admin/technician-dashboard
```

### 9.13 Finance

```
GET    /api/v1/admin/cashflows
POST   /api/v1/admin/cashflows
GET    /api/v1/admin/cashflows/{id}
PUT    /api/v1/admin/cashflows/{id}
DELETE /api/v1/admin/cashflows/{id}
GET    /api/v1/admin/cashflows/summary
```

### 9.14 Hotspot

```
GET    /api/v1/admin/hotspot-profiles
POST   /api/v1/admin/hotspot-profiles
GET    /api/v1/admin/hotspot-profiles/{id}
PUT    /api/v1/admin/hotspot-profiles/{id}
GET    /api/v1/admin/resellers
POST   /api/v1/admin/resellers
GET    /api/v1/admin/resellers/{id}
PUT    /api/v1/admin/resellers/{id}
GET    /api/v1/admin/voucher-batches
POST   /api/v1/admin/voucher-batches
```

### 9.15 System

```
GET    /api/v1/admin/activity-logs
GET    /api/v1/admin/users
POST   /api/v1/admin/users
GET    /api/v1/admin/users/{id}
PUT    /api/v1/admin/users/{id}
DELETE /api/v1/admin/users/{id}
GET    /api/v1/admin/database-settings
PUT    /api/v1/admin/database-settings
GET    /api/v1/admin/packages
POST   /api/v1/admin/packages
GET    /api/v1/admin/packages/{id}
PUT    /api/v1/admin/packages/{id}
DELETE /api/v1/admin/packages/{id}
```

---

## 10. Router Scope Architecture

### 10.1 Overview

Router Scope adalah fitur yang membatasi visibilitas data berdasarkan router yang dipilih. Ini penting untuk multi-tenant ISP yang mengelola beberapa router/lokasi.

### 10.2 Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ Router Switcher (UI)                                            │
│                                                                 │
│ [ Semua Router ▼ ]  ← Switcher dropdown di header              │
│   ✓ Semua Router                                              │
│     CCR-Warnet                                                 │
│     OLT Kadus 1                                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ active_router_id
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ admin.js state                                                  │
│                                                                 │
│ routerSwitcher: {                                                │
│   enabled: true,                                                │
│   active_router_id: '103',     ← ID router yang dipilih        │
│   available_routers: [...]                                     │
│ }                                                              │
│                                                                 │
│ filters: {                                                      │
│   router_id: '103'            ← Sync ke filters global        │
│ }                                                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ router_id di params API
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ API Controllers                                                 │
│                                                                 │
│ GET /api/v1/admin/olts?router_id=103                            │
│                                                                 │
│ Each controller filters data based on router_id:                 │
│ - OltController: where('router_id', $routerId)                  │
│ - NetworkLocationController: whereHas('olts', ...)               │
│ - CustomerController: whereHas('services', ...)                 │
│ - InvoiceController: whereHas('service', ...)                   │
│ - TicketController: whereHas('service', ...)                    │
│ - OntController: whereHas('olt', ...)                           │
│ - OdpController: whereHas('olt', ...)                           │
│ - OdcController: whereHas('location.olts', ...)                 │
│ - WorkOrderController: where('router_id', $routerId)            │
│ - CashflowController: where('router_id', $routerId)             │
└─────────────────────────────────────────────────────────────────┘
```

### 10.3 Filter Pattern by Model

| Model | Filter Type | Query Pattern |
|-------|-------------|---------------|
| OLT | Direct | `where('router_id', $routerId)` |
| NetworkLocation | Through OLT | `whereHas('olts', ...)` |
| Customer | Through Service | `whereHas('services', ...)` |
| Service | Direct | `where('router_id', $routerId)` |
| Invoice | Through Service | `whereHas('service', ...)` |
| WorkOrder | Direct | `where('router_id', $routerId)` |
| ONT | Through OLT | `whereHas('olt', ...)` |
| ODP | Through OLT | `whereHas('olt', ...)` |
| ODC | Through Location→OLT | `whereHas('location.olts', ...)` |
| Ticket | Through Service | `whereHas('service', ...)` |
| Cashflow | Direct (nullable) | `where('router_id', $routerId)` |
| ServiceIsolation | Direct | `where('router_id', $routerId)` |
| Monitoring (PPPoE) | Direct | `where('router_id', $routerId)` |

---

## 11. Frontend Architecture

### 11.1 Stack

- **Framework**: Alpine.js (reactive JavaScript)
- **Build Tool**: Vite
- **API Layer**: Custom `api` service with axios
- **State Management**: Alpine.js reactive state

### 11.2 Main Modules (admin.js)

```javascript
// Main state
const state = {
    page: 'dashboard',
    items: [],
    pagination: {},
    filters: { search: '', page: 1, per_page: 15, router_id: null },

    // Router scope
    routerSwitcher: { enabled: false, active_router_id: '', available_routers: [] },

    // Master data modules
    masterLokasi: createMasterLokasiState(),
    masterOlt: createMasterOltState(),

    // Feature modules
    cashflow: { filters: {}, summary: {} },
    ipPools: { router_id: null, pools: [], selectedPools: [] },
    routerSync: { router_id: null, result: null },
    provisioning: { form: {}, packages: [], olts: [] },
    manualIsolir: { router_id: null },
    pppoeImport: { candidates: [], importing: false },
    acsConfig: { router_id: null },

    // References (dropdown options)
    references: { customers: [], services: [], routers: [], olts: [], ... }
};
```

### 11.3 Page Loading Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ User clicks sidebar menu                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ changePage(page)                                                │
│                                                                 │
│ 1. Set state.page = 'customers' (for example)                  │
│ 2. Reset filters                                                │
│ 3. Call loadPage()                                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ loadPage()                                                      │
│                                                                 │
│ switch(page):                                                   │
│ - 'master-lokasi' → masterLokasi.loadData()                     │
│ - 'master-olt'   → masterOlt.loadData()                         │
│ - 'dashboard'    → loadDashboard()                              │
│ - 'router-sync'  → loadRouterSyncPage()                         │
│ - default        → genericLoadItems() via buildCurrentParams()  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ buildCurrentParams(config)                                       │
│                                                                 │
│ Returns: {                                                      │
│   search: this.filters.search,                                  │
│   page: this.filters.page,                                      │
│   per_page: this.filters.per_page,                              │
│   router_id: this.filters.router_id   ← Added from routerSwitcher│
│ }                                                              │
│                                                                 │
│ API.get(config.endpoint, { params: ... })                       │
└─────────────────────────────────────────────────────────────────┘
```

### 11.4 Key Features

#### Router Scope Sync
```javascript
applyRouterScopeToModules(routerId) {
    // Sync to global filters
    this.filters.router_id = routerId ? String(routerId) : null;

    // Sync to feature-specific states
    this.ipPools.router_id = routerIdStr;
    this.routerSync.router_id = routerIdStr;
    this.manualIsolir.router_id = routerIdStr;
    this.provisioning.form.router_id = routerId;

    // Reset pagination
    this.filters.page = 1;
    this.items = [];
    this.pagination = {};
}
```

#### Switch Router Flow
```javascript
async switchRouter() {
    const routerId = this.routerSwitcher.active_router_id === ''
        ? null
        : Number(this.routerSwitcher.active_router_id);

    // Persist to URL
    const url = new URL(window.location);
    routerId ? url.searchParams.set('router_id', routerId)
             : url.searchParams.delete('router_id');
    window.history.replaceState({}, '', url);

    // Update server session
    await api.patch('/api/v1/admin/dashboard/router-switch', { router_id: routerId });

    // Apply scope to all modules
    this.applyRouterScopeToModules(routerId);

    // Reload current page
    await this.loadPage();
}
```

---

## 12. Setup & Deployment

### 12.1 Environment Variables

```env
# Application
APP_NAME="Feralix ISP Cloud"
APP_ENV=production
APP_URL=http://your-domain.com
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=feralix_billing
DB_USERNAME=feralix
DB_PASSWORD=xxxxxxxx

# Queue (using database driver)
QUEUE_CONNECTION=database

# Mikrotik API (default ports)
MIKROTIK_DEFAULT_PORT=8728
MIKROTIK_DEFAULT_SSL_PORT=8729

# GenieACS (for ONT management)
GENIEACS_URL=http://localhost:7557
GENIEACS_USERNAME=admin
GENIEACS_PASSWORD=your-password

# Timezone (Indonesia)
AUTOMATION_SCHEDULE_TIMEZONE=Asia/Jakarta

# WhatsApp (optional)
WHATSAPP_API_KEY=
WHATSAPP_DEVICE=

# Company Info (for PDF & emails)
COMPANY_NAME="Nama ISP Anda"
COMPANY_ADDRESS="Alamat Lengkap"
COMPANY_PHONE="08xxxxxxxxxx"
COMPANY_EMAIL=billing@isp.com
```

### 12.2 Installation

```bash
# Clone repository
git clone <repository-url> feralix-billing
cd feralix-billing

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed

# Build assets
npm run build

# Clear caches
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### 12.3 Queue Worker Setup

```bash
# Start queue worker with all queues
php artisan queue:work --queue=default,billing,provisioning,notifications --sleep=3 --tries=3

# For production, use Supervisor:
# /etc/supervisor/conf.d/feralix-worker.conf
[program:feralix-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/feralix-billing/artisan queue:work --queue=default,billing,provisioning,notifications --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/feralix-worker.log
stopwaitsecs=3600
```

### 12.4 Scheduler Setup (Cron)

```bash
# Add to crontab (crontab -e)
* * * * * cd /var/www/feralix-billing && php artisan schedule:run >> /dev/null 2>&1
```

### 12.5 Mikrotik Setup

```
# Enable API
/ip service enable api

# Set API port (optional, default 8728)
/ip service set api port=8728

# Create API user for Feralix
/user add name=feralix read write group=full address=0.0.0.0/0

# Enable proxy for isolir redirect
/ip proxy set enabled=yes port=8080 max-cache-size=none

# NAT rule for isolir redirect (add to firewall nat)
/ip firewall nat add chain=dstnat src-address-list=ISOLIR_CUSTOMER protocol=tcp dst-port=80 action=redirect to-ports=8080 comment="Feralix Isolir"

# Proxy access rule
/ip proxy access add dst-port=80 action=deny redirect-to=http://YOUR_SERVER_IP:6733/isolir
```

### 12.6 GenieACS Setup (Optional, for ONT Monitoring)

```bash
# Using Docker
docker run -d \
  --name genieacs \
  -p 7557:7547 \
  -p 7558:7548 \
  -p 7559:3000 \
  -v genieacs-db:/data/db \
  dparrelli/genieacs

# Configure router ACS URL
# Point router's ACS URL to: http://YOUR_SERVER_IP:7557
```

---

## 13. Common Tasks

### 13.1 Adding New Controller

```bash
# 1. Create request validator first
php artisan make:request ModuleName/StoreModuleNameRequest
php artisan make:request ModuleName/UpdateModuleNameRequest
php artisan make:request ModuleName/IndexModuleNameRequest

# 2. Create resource (optional)
php artisan make:resource ModuleNameResource

# 3. Create controller
php artisan make:controller Api/V1/Admin/ModuleNameController

# 4. Add routes to routes/api.php
# 5. Add router_id filter following existing pattern
```

### 13.2 Adding Router Filter to Controller

```php
// In your controller's index() method
public function index(IndexRequest $request): JsonResponse
{
    $filters = $request->validated();

    $query = Model::query()
        ->when(
            $filters['router_id'] ?? null,
            fn ($query, $routerId) => $query->where('router_id', (int) $routerId)
        )
        // ... rest of filters
        ->paginate($perPage);

    return $this->paginatedResponse($query, Resource::class, '...');
}
```

### 13.3 Running Manual Commands

```bash
# Generate invoices manually
php artisan billing:generate-monthly-invoices --queue

# Check overdue invoices
php artisan billing:check-overdue-invoices --queue

# Create isolations for overdue invoices
php artisan billing:create-overdue-isolations --queue

# Sync IP pools from Mikrotik
php artisan ip-pools:sync --router=103

# Sync VID from Mikrotik
php artisan mikrotik:sync-vids --router=103

# Sync ONT from GenieACS
php artisan genieacs:sync-onts --router=103

# Sync PPPoE status
php artisan monitor:sync-pppoe
```

### 13.4 Testing API Endpoints

```bash
# Get auth token
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'

# Use token for API calls
curl http://localhost/api/v1/admin/customers \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 14. Troubleshooting

### 14.1 Common Issues

#### Queue Jobs Not Running
```bash
# Check queue table
php artisan queue:failed
php artisan queue:retry all

# Restart queue worker
php artisan queue:restart
```

#### Mikrotik Connection Failed
```bash
# Test connection
php artisan tinker --execute="
    \$router = App\Models\Router::find(103);
    \$client = app(App\Contracts\Mikrotik\MikrotikApiClientFactory::class)->make(\$router);
    try {
        \$client->connect();
        echo 'Connected successfully';
    } catch (\Exception \$e) {
        echo 'Failed: ' . \$e->getMessage();
    }
"
```

#### Invoice Generation Issues
```bash
# Check services without price
php artisan tinker --execute="
    \$services = App\Models\Service::whereNull('monthly_price')
        ->whereDoesntHave('package')
        ->count();
    echo 'Services without price: ' . \$services;
"

# Check duplicate invoice prevention
php artisan tinker --execute="
    \$service = App\Models\Service::with('invoices')->first();
    print_r(\$service->invoices->pluck('billing_period')->toArray());
"
```

### 14.2 Log Locations

| Log | Location |
|-----|----------|
| Laravel log | `storage/logs/laravel.log` |
| Queue failures | Database (`failed_jobs` table) |
| Mikrotik sync | Database (`mikrotik_sync_vid_logs` table) |

### 14.3 Database Debug Queries

```sql
-- Find services without monthly_price
SELECT s.*, c.full_name as customer_name
FROM services s
JOIN customers c ON s.customer_id = c.id
WHERE s.monthly_price IS NULL
AND s.package_id IS NULL;

-- Find overdue invoices
SELECT i.*, c.full_name, s.pppoe_username
FROM invoices i
JOIN customers c ON i.customer_id = c.id
JOIN services s ON i.service_id = s.id
WHERE i.payment_status = 'overdue'
AND i.id NOT IN (
    SELECT service_id FROM service_isolations WHERE status = 'applied'
);

-- Find open isolations
SELECT si.*, c.full_name, s.pppoe_username
FROM service_isolations si
JOIN services s ON si.service_id = s.id
JOIN customers c ON s.customer_id = c.id
WHERE si.status = 'pending';
```

---

## Appendix A: File Index

### Controllers
| File | Purpose |
|------|---------|
| `CustomerController.php` | Customer CRUD, provisioning, onboard |
| `InvoiceController.php` | Invoice management, PDF, bulk actions |
| `ServiceController.php` | Service CRUD |
| `IpPoolController.php` | IP pool management, VID suggestion |
| `OltController.php` | OLT management, PON ports |
| `OntController.php` | ONT management, GenieACS sync |
| `RouterController.php` | Router management, connection testing |
| `MonitoringController.php` | PPPoE monitoring |
| `ServiceIsolationController.php` | Isolation history |
| `TicketController.php` | Helpdesk tickets |
| `WorkOrderController.php` | Work orders |
| `CashflowController.php` | Finance cashflow |
| `HotspotVoucherController.php` | Hotspot voucher management |

### Services
| Service | Purpose |
|---------|---------|
| `CustomerService.php` | Customer business logic |
| `InvoiceService.php` | Invoice CRUD operations |
| `MonthlyInvoiceGenerationService.php` | Monthly invoice generation |
| `PaymentService.php` | Payment processing |
| `ServiceBillingStatusService.php` | Sync billing status |
| `InvoiceAutoSuspendService.php` | Auto isolation trigger |
| `InvoiceIsolationAutomationService.php` | Isolation release on payment |
| `IpPoolService.php` | IP pool operations |
| `MikrotikPppoeSecretService.php` | PPPoE secret management |
| `ServiceProvisioningService.php` | Provisioning flow |
| `TicketService.php` | Ticket management |
| `WorkOrderService.php` | Work order management |
| `CashflowService.php` | Cashflow operations |

---

*Dokumentasi ini di-generate untuk developer onboarding Feralix ISP Cloud.*
*Generated: 2026-05-10*
*Last Updated: 2026-05-10*
