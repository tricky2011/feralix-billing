# Feralix Billing — CLAUDE.md

## Project Overview

Feralix adalah sistem billing & manajemen operasional untuk ISP berbasis FTTH (Fiber-to-the-Home).
Stack: **Laravel 12 · PHP 8.2+ · MySQL · Alpine.js · Vite · Sanctum Auth**
Timezone: **Asia/Jakarta** (semua scheduler menggunakan ini)

---

## Tech Stack & Conventions

### Backend
- **Framework**: Laravel 12, PHP 8.2+
- **Auth**: Laravel Sanctum (API token)
- **Queue**: Database driver — queues: `default`, `billing`, `provisioning`, `notifications`
- **PDF**: barryvdh/laravel-dompdf
- **Mikrotik**: Custom Socket API client (app/Services/Mikrotik/Clients/)
- **ONT/GenieACS**: TR-069 via HTTP NBI API

### Frontend
- **Framework**: Alpine.js (SPA pattern, semua state di `resources/js/admin.js`)
- **Build**: Vite
- **API layer**: custom `api` service (axios wrapper) di `resources/js/services/`

### Coding Conventions
- Controllers hanya di `app/Http/Controllers/Api/V1/Admin/` atau `.../Technician/`
- Business logic wajib di Service class, bukan di Controller
- Semua input divalidasi via FormRequest (`app/Http/Requests/[Module]/`)
- Response menggunakan helper `$this->successResponse()`, `$this->paginatedResponse()`, `$this->createdResponse()` dari base Controller
- Enum wajib digunakan untuk semua status field — lihat `app/Enums/`
- Jangan pakai string literal untuk status, selalu pakai Enum `->value`

---

## Architecture Penting

### Router Scope (KRITIS)
Semua data ter-isolasi per-router. Setiap user melihat data sesuai router yang dipilih.

**Pattern filter di Controller:**
```php
// Direct router_id on model
->when($filters['router_id'] ?? null, fn($q, $id) => $q->where('router_id', (int)$id))

// Via relasi OLT
->when($filters['router_id'] ?? null, fn($q, $id) => $q->whereHas('olt', fn($oq) => $oq->where('router_id', $id)))

// Via relasi location (setelah migration add router_id to network_locations)
->when($filters['router_id'] ?? null, fn($q, $id) => $q->whereHas('location', fn($lq) => $lq->where('router_id', $id)))
```

**Tabel & cara filter-nya:**
| Model | Filter |
|-------|--------|
| NetworkLocation | direct `router_id` |
| OLT | direct `router_id` |
| Service | direct `router_id` |
| WorkOrder | direct `router_id` |
| Cashflow | direct `router_id` |
| ServiceIsolation | direct `router_id` |
| ODP | via `olt.router_id` |
| ONT | via `olt.router_id` |
| ODC | via `location.router_id` |
| Customer | via `services.router_id` |
| Invoice | via `service.router_id` |
| Ticket | via `service.router_id` |

**Frontend:** `applyRouterScopeToModules(routerId)` di `admin.js` harus diupdate setiap ada modul baru yang punya scope router.

Master data modules (`masterLokasi`, `masterOlt`) menyimpan `currentRouterId` internal — gunakan `setRouterFilter(routerId)` untuk update scope, bukan hanya passing parameter ke `loadData()`.

### Mikrotik ROSv6 vs ROSv7
Semua operasi Mikrotik harus version-aware:

```php
$client = $this->clientFactory->forRouter($router);
$version = $this->versionDetector->detect($router, $client); // returns 6 or 7
```

- `ros_version` di-cache di kolom `routers.ros_version`
- ROSv7 tidak ada `session-id` field di `/ppp/active`
- Auth ROSv7: plain password; Auth ROSv6: MD5 challenge-response (handled otomatis di SocketMikrotikApiClient)
- Path API sama untuk ROSv6 dan ROSv7 (`/ppp/secret`, `/ppp/active`, `/ip/firewall/address-list`, dll)
- Jika ada perbedaan behavior antar versi, gunakan `$version >= 7` untuk branching

**Inject `RouterOsVersionDetector` di service yang perlu version-awareness:**
```php
public function __construct(
    private readonly MikrotikApiClientFactory $clientFactory,
    private readonly RouterOsVersionDetector $versionDetector,
) {}
```

### Billing Engine
```
billing:generate-monthly-invoices  → Tanggal 1, 00:05 (billing queue)
billing:check-overdue-invoices     → Daily, 00:20 (billing queue)
billing:create-overdue-isolations  → Daily, 00:30 (billing queue)
```

Payment flow: mark-paid → `PaymentService::settleInvoice()` → `InvoiceIsolationAutomationService::syncForInvoice()` → release isolation jika semua invoice lunas.

### PPPoE Monitor Sync
```
monitor:sync-pppoe  → Every minute (default queue)
```
Command otomatis memilih router yang punya services dengan `pppoe_username` ATAU `monitor_pppoe_username`.
Service: `PppoeMonitorSyncService::syncRouter()` — jika fetch ke Mikrotik gagal, semua service di router tersebut dianggap offline (tidak throw exception).

---

## Database Schema Penting

### services (tabel inti)
- `access_mode`: enum `pppoe|vlan|static`
- `isolation_method`: enum `address_list|firewall_filter|ppp_profile|queue`
- `billing_status`: enum `pending|paid|overdue|suspended|closed`
- `network_status`: enum `provisioning|active|isolated|down|inactive`
- `overall_status`: enum `provisioning|active|down|suspended|isolated|inactive|terminated`

### routers
- `ros_version`: `'6'` atau `'7'` (nullable, auto-detect & cache)
- `api_port`: default 8728
- `api_password` & `acs_password`: encrypted cast

### network_locations
- `router_id` FK ke `routers` (nullable) — menentukan router scope lokasi ini

---

## Common Tasks

### Tambah Controller Baru
```bash
php artisan make:request ModuleName/IndexModuleNameRequest
php artisan make:request ModuleName/StoreModuleNameRequest
php artisan make:request ModuleName/UpdateModuleNameRequest
php artisan make:resource ModuleNameResource
php artisan make:controller Api/V1/Admin/ModuleNameController
```
Selalu tambah `router_id` filter di `index()` mengikuti pattern yang sudah ada.

### Tambah Mikrotik Operation
1. Buat/extend service di `app/Services/Mikrotik/`
2. Inject `MikrotikApiClientFactory` dan `RouterOsVersionDetector`
3. Selalu `disconnect()` di blok `finally`
4. Handle exception — jangan biarkan Mikrotik error merusak request user

### Tambah Scheduler
Daftarkan di `app/Console/Kernel.php` dengan timezone `config('automation.schedule_timezone')`.

### Running Dev
```bash
php artisan serve          # backend
npm run dev               # frontend (Vite)
php artisan queue:work --queue=default,billing,provisioning,notifications
php artisan schedule:work  # untuk development
```

### Running Manual Commands
```bash
php artisan billing:generate-monthly-invoices --queue
php artisan billing:check-overdue-invoices
php artisan billing:create-overdue-isolations --queue
php artisan monitor:sync-pppoe
php artisan ip-pools:sync --router=ID
php artisan mikrotik:sync-vids --router=ID
php artisan genieacs:sync-onts --router=ID
php artisan monitor:detect-ros-version --router=ID  # detect & cache ros_version
```

---

## File Penting

| Path | Fungsi |
|------|--------|
| `app/Services/Mikrotik/Clients/SocketMikrotikApiClient.php` | Implementasi protokol binary Mikrotik API |
| `app/Services/Mikrotik/RouterOsVersionDetector.php` | Detect ROSv6/v7 |
| `app/Services/Mikrotik/MikrotikAddressListService.php` | Manage address-list untuk isolasi |
| `app/Services/Mikrotik/MikrotikPppoeSecretService.php` | CRUD PPPoE secret |
| `app/Services/Monitoring/PppoeMonitorSyncService.php` | Sync status PPPoE aktif |
| `app/Services/Billing/MonthlyInvoiceGenerationService.php` | Generate invoice bulanan |
| `app/Services/Billing/PaymentService.php` | Proses pembayaran |
| `app/Services/Billing/InvoiceIsolationAutomationService.php` | Auto-release isolasi saat lunas |
| `app/Services/Access/RoleRouterScopeService.php` | RBAC + router scope enforcement |
| `app/Services/Provisioning/ServiceProvisioningService.php` | Full provisioning flow |
| `resources/js/admin.js` | Seluruh SPA state & logic frontend |
| `routes/api.php` | Semua route API |
| `app/Console/Kernel.php` | Scheduler definitions |

---

## Rules & Constraints

1. **Jangan** ubah migration yang sudah ada — selalu buat migration baru
2. **Selalu** gunakan `$router->ros_version` dari DB sebelum operasi Mikrotik; detect jika null
3. **Router scope wajib** di semua endpoint yang mengembalikan data per-router
4. **Frontend master data** (`masterLokasi`, `masterOlt`, dll) harus punya `currentRouterId` state internal dan method `setRouterFilter()` — jangan hanya passing ke `loadData()` karena pagination akan kehilangan filter
5. Enkripsi password router dengan Laravel encrypted cast — **jangan** simpan plaintext
6. Semua job Mikrotik berjalan di queue, **jangan** blocking di request HTTP
7. `paginatedResponse()` untuk list, `successResponse()` untuk detail/action, `createdResponse()` untuk create
