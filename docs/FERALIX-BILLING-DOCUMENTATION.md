# Feralix Billing System - Dokumentasi Fitur

Dokumentasi ini menjelaskan semua fitur yang tersedia di sistem Feralix Billing, sebuah platform manajemen billing untuk ISP (Internet Service Provider) berbasis MikroTik.

## Daftar Isi
1. [Manajemen Pelanggan & Layanan](#1-manajemen-pelanggan--layanan)
2. [Billing & Invoice](#2-billing--invoice)
3. [Isolasi Layanan (Isolir)](#3-isolasi-layanan-isolir)
4. [Monitoring PPPoE](#4-monitoring-pppoe)
5. [Monitoring ONT (GenieACS)](#5-monitoring-ont-genieacs)
6. [Integrasi MikroTik](#6-integrasi-mikrotik)
7. [Hotspot & Voucher](#7-hotspot--voucher)
8. [Manajemen Keuangan (Cashflow)](#8-manajemen-keuangan-cashflow)
9. [Dashboard & Analytics](#9-dashboard--analytics)
10. [Helpdesk & Tiket](#10-helpdesk--tiket)
11. [Jadwal Otomatisasi](#11-jadwal-otomatisasi)

---

## 1. Manajemen Pelanggan & Layanan

### 1.1 Customer Management
- **Model**: `Customer`
- **Fitur**:
  - CRUD pelanggan dengan tipe (Individual/Company)
  - Bulk actions: delete, disable, generate invoice
  - Customer onboarding dengan provisioning preview
  - Customer reference management

### 1.2 Service Management
- **Model**: `Service`
- **Fitur**:
  - Layanan dengan multiple access modes: `PPPoE`, `Static`, `Hotspot`
  - Link ke VID (VLAN ID) untuk jaringan
  - Status tracking: `active`, `inactive`, `suspended`, `isolated`
  - Monitoring PPPoE username per service
  - Billing status tracking per service

### 1.3 Package Management
- **Model**: `Package`
- **Fitur**:
  - Paket layanan dengan harga dan bandwidth
  - Validity period management

### 1.4 VID (VLAN ID) Management
- **Model**: `Vid`
- **Fitur**:
  - Manajemen VLAN ID untuk jaringan FTTH
  - Tipe VID: `CUSTOMER`, `OLT`, `TRANSIT`, `INFRA`
  - Subnet CIDR assignment
  - VID Assignment audit trail

---

## 2. Billing & Invoice

### 2.1 Invoice Management
- **Model**: `Invoice`, `Payment`
- **Fitur**:
  - Generate invoice manual atau bulk
  - Monthly invoice generation (automasi)
  - Payment status tracking: `unpaid`, `partial`, `paid`, `overdue`
  - Due date management
  - WhatsApp notification untuk invoice (via gateway)
  - Bulk actions untuk invoice

### 2.2 Billing Automation
- **Service**: `BillingAutomationService`
- **Fitur**:
  - `syncOverdueStatuses()`: Sinkronisasi status overdue invoice
  - `createOverdueIsolationRecords()`: Buat record isolir untuk invoice overdue
  - Chunked processing untuk performa

### 2.3 Invoice Overdue Service
- **Service**: `InvoiceOverdueService`
- **Fitur**:
  - Mark invoice sebagai overdue berdasarkan due date
  - Bulk processing dengan chunk

### 2.4 Invoice Auto Suspend
- **Service**: `InvoiceAutoSuspendService`
- **Fitur**:
  - Trigger isolation untuk layanan dengan invoice overdue
  - Chunk-based processing
  - Skip jika sudah ada open isolation

### 2.5 Payment Service
- **Service**: `PaymentService`
- **Fitur**:
  - Record pembayaran
  - Auto-create cashflow entry saat payment dibuat
  - Payment observer untuk auto-sync status

### 2.6 Cashflow Income
- **Service**: `CashflowIncomeService`
- **Fitur**:
  - Generate income entries dari payments
  - Track system-generated income

---

## 3. Isolasi Layanan (Isolir)

### 3.1 Service Isolation Service
- **Service**: `ServiceIsolationService`
- **Fitur**:
  - Create isolation record untuk layanan
  - Mark applied/released isolation
  - Support multiple target types:
    - `pppoe_profile`: Ubah PPPoE profile
    - `queue`: Apply queue/bandwidth limit
    - `address_list`: Add ke address list MikroTik
  - Automatic status sync ke service

### 3.2 Isolation Target Resolver
- **Service**: `ServiceIsolationTargetResolver`
- **Fitur**:
  - Resolve target berdasarkan access mode (PPPoE/Static)
  - PPPoE: resolve username dan profile
  - Static: resolve IP address, MAC, queue name

### 3.3 Isolation Router Execution
- **Service**: `ServiceIsolationRouterExecutionService`
- **Fitur**:
  - Execute isolation di MikroTik router
  - PPPoE: disable/enable secret atau change profile
  - Queue: apply/remove queue
  - Address List: add/remove IP

### 3.4 Isolation Suggestion Service
- **Service**: `ServiceIsolationSuggestionService`
- **Fitur**:
  - Suggest layanan yang perlu diisolasi
  - Berdasarkan invoice overdue

### 3.5 Manual Isolir
- **Controller**: `ManualIsolirController`
- **Endpoint**:
  - `POST /v1/admin/isolir/manual` - Manual isolir
  - `POST /v1/admin/isolir/release` - Release manual isolir

---

## 4. Monitoring PPPoE

### 4.1 PPPoE Monitor Sync Service
- **Service**: `PppoeMonitorSyncService`
- **Fitur**:
  - Sync active PPPoE sessions dari MikroTik
  - Track online/offline status per service
  - Monitor berdasarkan `monitor_pppoe_username`
  - History logging untuk status changes
  - Auto-update overall service status

### 4.2 Models
- **ServiceMonitorPppoeStatus**: Snapshot status terakhir
- **ServiceMonitorPppoeLog**: History perubahan status

### 4.3 Overall State Manager
- **Service**: `ServiceOverallStateManager`
- **Fitur**:
  - Calculate desired status berdasarkan billing + network status
  - Calculate effective status berdasarkan monitor status
  - Priority: Isolated > Suspended > Offline > Active

---

## 5. Monitoring ONT (GenieACS)

### 5.1 GenieACS ONT Sync Service
- **Service**: `GenieAcsOntSyncService`
- **Fitur**:
  - Sync ONT data dari GenieACS ACS (Auto Configuration Server)
  - Fetch serial number, status, optical info
  - Support multiple data providers:
    - `NbiGenieAcsOntDataProvider`: Real GenieACS NBI API
    - `FakeGenieAcsOntDataProvider`: Mock untuk testing

### 5.2 ONT Data Provider
- **Interface**: `GenieAcsOntDataProvider`
- **Fitur**:
  - Fetch ONT snapshot berdasarkan device ID
  - Map ke standard attributes:
    - `status`: online/offline
    - `optical_status`: normal/warning/critical
    - `rx_power`, `tx_power`: optical power levels
    - `ssid_name`, `wifi_password`: WiFi configuration

### 5.3 ONT Model
- **Model**: `Ont`
- **Fitur**:
  - Track ONT devices (Optical Network Terminal)
  - Link ke OLT dan service
  - Store optical statistics
  - Track GenieACS device ID

### 5.4 Sync Jobs
- **Job**: `SyncGenieAcsOntJob`
- **Schedule**: Every 5 minutes (`*/5 * * * *`)

---

## 6. Integrasi MikroTik

### 6.1 MikroTik API Client
- **Service**: `SocketMikrotikApiClient`
- **Fitur**:
  - Socket-based MikroTik API connection
  - SSL support dengan self-signed certificate option
  - Configurable timeouts
  - Command execution dan response parsing

### 6.2 MikroTik Sync Service
- **Service**: `MikrotikVidSyncService`
- **Fitur**:
  - Sync VID (VLAN) information dari MikroTik
  - Map router VLAN interfaces ke VID records
  - Track subnet CIDR dan gateway IP
  - Sync logging untuk audit trail

### 6.3 IP Pool Service
- **Service**: `IpPoolService`
- **Fitur**:
  - Fetch IP pools dari MikroTik
  - Track pool usage dan availability
  - Suggest pools untuk VID assignment
  - Utilization reporting

### 6.4 IP Pool VID Assignment
- **Service**: `IpPoolVidAssignmentService`
- **Fitur**:
  - Assign IP pool ranges ke VID
  - Track available IPs per VID
  - Suggest pool berdasarkan availability

### 6.5 Address List Service
- **Service**: `MikrotikAddressListService`
- **Fitur**:
  - Manage MikroTik address lists
  - Add/remove IPs untuk isolation

### 6.6 Router Stats Service
- **Service**: `RouterStatsService`
- **Fitur**:
  - Get comprehensive router statistics:
    - PPPoE: total secrets, active users
    - DHCP: total leases, bound leases
    - IP Pool: total pools, total addresses
    - Interfaces: total, active, VLAN count
    - Addresses: total, active static

### 6.7 Router Sync Controller
- **Controller**: `RouterSyncController`
- **Endpoint**:
  - `POST /v1/admin/router-sync/pppoe` - Sync PPPoE
  - `POST /v1/admin/router-sync/static` - Sync Static
  - `POST /v1/admin/router-sync/address-list` - Sync Address List
  - `POST /v1/admin/router-sync/all` - Sync All

---

## 7. Hotspot & Voucher

### 7.1 Hotspot Profile
- **Model**: `HotspotProfile`
- **Fitur**:
  - Profile dengan validity mode:
    - `duration`: based on login time
    - `period`: based on calendar period
  - Data limit (bytes)
  - User lock option
  - Expired mode handling

### 7.2 Hotspot Voucher
- **Model**: `HotspotVoucher`
- **Fitur**:
  - Voucher dengan username/password
  - Batch generation
  - Status tracking: `active`, `used`, `expired`
  - MAC address binding
  - Reseller support

### 7.3 Voucher Batch
- **Model**: `VoucherBatch`
- **Fitur**:
  - Bulk voucher generation
  - Batch code untuk tracking
  - Quantity dan prefix configuration

### 7.4 Reseller Management
- **Model**: `Reseller`
- **Fitur**:
  - Reseller hotspot vouchers
  - Commission tracking
  - Status management

### 7.5 Hotspot Radius Service
- **Service**: `HotspotRadiusService`
- **Fitur**:
  - RADIUS authorization endpoint
  - RADIUS accounting endpoint
  - Stub provider untuk testing

### 7.6 Voucher State Service
- **Service**: `HotspotVoucherStateService`
- **Fitur**:
  - State machine untuk voucher lifecycle
  - MAC address validation
  - Expiration checking
  - Access preparation

---

## 8. Manajemen Keuangan (Cashflow)

### 8.1 Cashflow Model
- **Model**: `Cashflow`
- **Fitur**:
  - Income dan expense tracking
  - Category-based organization
  - System-generated (dari payments) atau manual entries
  - Date-based filtering

### 8.2 Cashflow Service
- **Service**: `CashflowService`
- **Fitur**:
  - CRUD operations
  - Auto-create dari payments
  - Summary dan analytics
  - Monthly breakdown
  - Category management

### 8.3 Cashflow Categories
- **Model**: `CashflowCategory`
- **Fitur**:
  - Pre-defined categories: "Payment", etc.
  - Income/expense type

### 8.4 Cashflow Transactions
- **Model**: `CashflowTransaction`
- **Fitur**:
  - Detailed transaction ledger
  - Reference ke invoices/payments

---

## 9. Dashboard & Analytics

### 9.1 Admin Dashboard
- **Controller**: `AdminDashboardController`
- **Fitur**:
  - Overview statistics
  - Revenue analytics
  - Service statistics
  - Router switching untuk scoped access

### 9.2 Dashboard Analytics Service
- **Service**: `DashboardAnalyticsService`
- **Queries**:
  - `DashboardOverviewQuery`: Overview metrics
  - `DashboardChartQuery`: Chart data
  - `DashboardRevenueAnalyticsQuery`: Revenue breakdown
  - `TechnicianDashboardQuery`: Technician metrics

### 9.3 Technician Dashboard
- **Controller**: `AdminTechnicianDashboardController`
- **Fitur**:
  - Technician ranking
  - Work order statistics
  - Performance metrics
  - PDF export

### 9.4 Dashboard Access Service
- **Service**: `DashboardAccessService`
- **Fitur**:
  - Role-based access control
  - Router scope filtering

---

## 10. Helpdesk & Tiket

### 10.1 Ticket System
- **Model**: `Ticket`
- **Fitur**:
  - Ticket creation dengan prioritas
  - Assignment mode: manual, auto, round-robin
  - Status tracking: `open`, `in_progress`, `resolved`, `closed`
  - Reply/thread support

### 10.2 Technician Auto Assignment
- **Service**: `TechnicianAutoAssignmentService`
- **Fitur**:
  - Round-robin assignment
  - Load-based assignment
  - Priority-based routing

### 10.3 Telegram Notifications
- **Service**: `TelegramNotificationService`
- **Fitur**:
  - Send ticket notifications ke Telegram
  - Support multiple bot dan group configurations
  - Batch processing

---

## 11. Jadwal Otomatisasi

### 11.1 Scheduled Commands

| Command | Schedule | Description |
|---------|----------|-------------|
| `generate:monthly-invoices` | 00:05 daily | Generate invoice bulanan |
| `check:overdue-invoices` | 00:20 daily | Cek dan mark overdue invoices |
| `create:overdue-isolations` | 00:30 daily | Buat isolir untuk overdue |
| `sync:mikrotik-vid` | */15 * * * * | Sync VID dari MikroTik |
| `sync:pppoe-monitor` | * * * * * | Sync PPPoE sessions |
| `sync:genieacs-ont` | */5 * * * * | Sync ONT dari GenieACS |
| `process:telegram` | * * * * * | Process Telegram notifications |

### 11.2 Queue Configuration

| Queue | Purpose |
|-------|---------|
| `billing` | Billing operations |
| `provisioning` | Router provisioning |
| `network` | Network sync jobs |
| `monitoring` | Monitoring sync |
| `notifications` | Telegram notifications |

### 11.3 Automation Configuration

**File**: `config/automation.php`

```php
'billing' => [
    'monthly_invoice' => [
        'due_in_days' => 10,
        'penalty_amount' => 0,
    ],
    'overdue' => [
        'chunk' => 200,
    ],
    'service_isolation' => [
        'chunk' => 100,
    ],
],
```

---

## 12. Network Infrastructure

### 12.1 OLT (Optical Line Terminal)
- **Model**: `Olt`
- **Fitur**:
  - OLT device management
  - Link ke router
  - GPON port tracking

### 12.2 ODC (Optical Distribution Cabinet)
- **Model**: `Odc`
- **Fitur**:
  - Distribution cabinet tracking
  - Fiber path management

### 12.3 ODP (Optical Distribution Point)
- **Model**: `Odp`
- **Fitur**:
  - Distribution point management
  - Port tracking

### 12.4 Fiber Map
- **Controller**: `FiberMapController`
- **Fitur**:
  - View network topology
  - OLT → ODC → ODP → ONT hierarchy

---

## 13. Role-Based Access Control

### 13.1 User Roles
- **Enum**: `UserRole`
- **Roles**:
  - `superadmin`: Full access
  - `admin`: Admin access
  - `technician`: Limited access
  - `reseller`: Hotspot reseller access

### 13.2 Router Scope
- **Service**: `RoleRouterScopeService`
- **Fitur**:
  - Role-based router access
  - Scope binding untuk data isolation

### 13.3 Middleware
- `panel.role`: Role checking
- `router.scope.bindings`: Router scope binding

---

## 14. API Endpoints Summary

### 14.1 Authentication
```
POST   /v1/auth/login
GET    /v1/auth/me
POST   /v1/auth/logout
```

### 14.2 Admin Routes
```
# Dashboard
GET    /v1/admin/dashboard
PATCH  /v1/admin/dashboard/router-switch

# Users
GET    /v1/admin/users
POST   /v1/admin/users
PATCH  /v1/admin/users/{user}
DELETE /v1/admin/users/{user}

# Customers
GET    /v1/admin/customers
POST   /v1/admin/customers
POST   /v1/admin/customers/onboard
POST   /v1/admin/customers/bulk-*

# Services
GET    /v1/admin/services
POST   /v1/admin/services
PATCH  /v1/admin/services/{service}
DELETE /v1/admin/services/{service}

# Routers
GET    /v1/admin/routers
POST   /v1/admin/routers
GET    /v1/admin/routers/{router}/stats
POST   /v1/admin/routers/{router}/test-connection

# Service Isolation
GET    /v1/admin/service-isolations
POST   /v1/admin/service-isolations
PATCH  /v1/admin/service-isolations/{id}/applied
PATCH  /v1/admin/service-isolations/{id}/release
POST   /v1/admin/isolir/manual
POST   /v1/admin/isolir/release

# Invoices
GET    /v1/admin/invoices
POST   /v1/admin/invoices
POST   /v1/admin/invoices/generate-monthly
PATCH  /v1/admin/invoices/{invoice}/mark-paid

# Payments
GET    /v1/admin/payments
POST   /v1/admin/payments

# Cashflow
GET    /v1/admin/cashflows
GET    /v1/admin/cashflows/summary

# Monitoring
GET    /v1/admin/monitoring/pppoe
GET    /v1/admin/onts/online
GET    /v1/admin/onts/offline

# Tickets
GET    /v1/admin/tickets
POST   /v1/admin/tickets
PATCH  /v1/admin/tickets/{ticket}/status

# Network
GET    /v1/admin/vids
GET    /v1/admin/ip-pools
GET    /v1/admin/olts
GET    /v1/admin/odcs
GET    /v1/admin/odps
GET    /v1/admin/fiber-map
```

### 14.3 Internal Routes
```
POST   /v1/internal/hotspot-radius/authorize
POST   /v1/internal/hotspot-radius/accounting
```

---

## 15. Configuration Files

| File | Purpose |
|------|---------|
| `config/billing.php` | Billing configuration |
| `config/automation.php` | Automation schedules & queues |
| `config/mikrotik.php` | MikroTik API settings |
| `config/genieacs.php` | GenieACS settings |
| `config/hotspot.php` | Hotspot/Radius settings |
| `config/dashboard.php` | Dashboard settings |
| `config/services.php` | External services |

---

## 16. Database Models

### Core Models
- `User`, `Customer`, `Service`, `Package`
- `Invoice`, `Payment`
- `Router`, `Vid`
- `ServiceIsolation`
- `HotspotProfile`, `HotspotVoucher`, `VoucherBatch`, `Reseller`
- `Cashflow`, `CashflowCategory`
- `Ticket`, `TicketReply`
- `WorkOrder`
- `Ont`, `Olt`, `Odc`, `Odp`
- `Location`, `NetworkLocation`

### Sync/Status Models
- `ServiceMonitorPppoeStatus`, `ServiceMonitorPppoeLog`
- `MikrotikSyncJob`, `MikrotikSyncVidLog`
- `ServiceRouterOperationStatus`, `ServiceRouterOperationLog`

### Supporting Models
- `ActivityLog`, `TelegramBot`, `TelegramGroup`, `TelegramLog`
- `AppSetting`, `SystemSetting`
- `RouterScope`
- `Technician`
