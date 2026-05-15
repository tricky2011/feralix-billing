# Feralix ISP Cloud — CLAUDE.md

Sistem manajemen billing & operasional ISP berbasis FTTH. Meliputi customer onboarding, provisioning PPPoE/GPON, billing otomatis, integrasi MikroTik API, GenieACS TR-069, FreeRADIUS Hotspot, dan monitoring real-time.

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 12, PHP 8.2+, Laravel Sanctum |
| Frontend | Alpine.js 3, Tailwind CSS 4, Vite 7 |
| Database | MySQL 8+ / MariaDB 10.6+ |
| Integrasi | MikroTik API (Socket + REST), GenieACS, FreeRADIUS SQL |
| Queue | Laravel Queue (database driver) |
| PDF | DomPDF |

## Struktur Direktori Penting

```
app/
  Http/Controllers/Api/V1/Admin/   ← semua controller API admin
  Http/Controllers/Api/V1/         ← auth, technician, hotspot-radius
  Models/                          ← Eloquent models
  Services/                        ← business logic (per domain)
    Billing/, Customer/, Hotspot/, Mikrotik/, Provisioning/, ...
  Observers/                       ← model observers
  Jobs/                            ← queued jobs
  Enums/                           ← PHP enums (status, type, dll)
  Data/                            ← DTO / Data classes
resources/
  js/admin.js                      ← SELURUH frontend logic (Alpine.js, ~4500 baris)
  views/admin/
    index.blade.php                ← single-page admin shell
    partials/module.blade.php      ← semua section/panel module
    partials/dashboard.blade.php   ← dashboard section
  views/components/
    modal.blade.php                ← generic CRUD modal
    sidebar.blade.php, topbar.blade.php
routes/
  api.php                          ← semua API routes
  web.php                          ← halaman admin + auth
database/migrations/               ← migrations berurutan
docs/TECHNICAL_DOCUMENTATION.md   ← dokumentasi lengkap
```

## Frontend Architecture

Seluruh admin UI adalah **satu Alpine.js component** (`adminPanel`) di `resources/js/admin.js`.

- **`modules`** object: config tiap module (endpoint, columns, fields, optionsRef)
- **`networkTabs`**: tabs untuk page `network` (routers, router-scopes, vids, olts, onts, dll)
- **`hotspotTabs`**: tabs untuk page `hotspot` (profiles, vouchers, batches, dll)
- **`sidebarGroups`**: navigasi sidebar
- **`references`**: data referensi global (customers, routers, available_vids, dll)
- **`items`**: data tabel generic module saat ini
- **`currentConfig()`**: mengembalikan config module aktif berdasarkan `page` + `activeTab`

Generic table di `module.blade.php` menggunakan `x-for="row in items"`.  
Modal form menggunakan `field.options` yang di-resolve dari `optionsRef` via `optionList(ref)`.

## API Pattern

Semua API di `/api/v1/admin/` mengikuti pola:

```php
// Response sukses
return $this->successResponse('Message', $data);
return $this->paginatedResponse($collection, ResourceClass::class, 'Message');
return $this->createdResponse('Message', $data);

// Auth: Bearer token via Sanctum
// Scope: route group dengan middleware auth:sanctum + role check
```

Referensi data untuk dropdown diambil via:
- `GET /api/v1/admin/customer-references` → returns: locations, olts, routers, packages, technicians, available_vids
- Terpisah: customers, services, hotspot_profiles, resellers, network_locations, network_odcs, telegram_bots

## Models Utama

| Model | Tabel | Keterangan |
|---|---|---|
| Customer | customers | Pelanggan ISP |
| Service | services | Layanan aktif (PPPoE + VID + ONT) |
| Vid | vids | VLAN ID dari MikroTik |
| Router | routers | MikroTik router |
| RouterScope | router_scopes | Scope VID per router |
| Invoice | invoices | Tagihan bulanan |
| Payment | payments | Pembayaran |
| HotspotVoucher | hotspot_vouchers | Voucher hotspot |
| HotspotService | hotspot_services | Hotspot aktif per router |
| Ont | onts | ONT/ONU perangkat fiber |
| Olt | olts | OLT perangkat fiber |
| WorkOrder | work_orders | Pekerjaan lapangan |
| Ticket | tickets | Helpdesk tiket |

## Roles & Authorization

| Role | Akses |
|---|---|
| `superadmin` | Full, semua router |
| `admin` | Dibatasi oleh `user_router` pivot table |
| `technician` | Hanya dashboard teknisi, WO, tiket |
| `reseller` | Hotspot voucher saja |

Router scope diterapkan via `RoleRouterScopeService::applyRouterScope()` di semua query yang butuh pembatasan.

## Workflow Wajib (Untuk Claude)

1. **Setelah setiap perubahan kode**: commit + push ke branch aktif
2. **Sebelum lapor selesai**: verifikasi koneksi backend ↔ frontend
   - Cek endpoint di `admin.js` ada di `routes/api.php`
   - Cek `optionsRef` ada di reference endpoints
3. **Output bersih**: tidak ada `console.log` debug tersisa di `admin.js`
4. **Build assets** setelah ubah JS/CSS: `npm run build`
5. **Branch aktif**: `claude/update-technical-docs-KsO26`
6. **Server production**: `/var/www/feralix-billing` — perlu `git pull` + `npm run build` setelah push

## Perintah Penting

```bash
# Build frontend
npm install && npm run build

# Cek routes
php artisan route:list --path=api/v1/admin

# Jalankan migrations
php artisan migrate

# Cache config (production)
php artisan config:cache && php artisan view:cache

# Queue worker
php artisan queue:work

# Scheduler (cron)
php artisan schedule:run
```

## Konvensi Kode

- **PHP**: PSR-12, readonly constructor properties, strict types via Enums
- **Services**: thin controllers, logika di service classes
- **JS**: tidak ada `console.log` di production code
- **Alpine**: semua state di `adminPanel`, tidak ada Alpine component terpisah
- **Migrations**: timestamp `YYYY_MM_DD_HHMMSS_nama.php`, foreign key dengan `cascadeOnDelete()`
- **API responses**: selalu gunakan helper methods dari `Controller` base class

## File Yang Sering Diubah

| Task | File |
|---|---|
| Tambah module/tab UI | `resources/js/admin.js` (tambah ke `modules` atau tab object) |
| Tambah section UI | `resources/views/admin/partials/module.blade.php` |
| Tambah API endpoint | `routes/api.php` + buat Controller + Request + Resource |
| Ubah business logic | `app/Services/[Domain]/[Name]Service.php` |
| Tambah kolom DB | `database/migrations/YYYY_MM_DD_...php` |
| Tambah navigasi | `sidebarGroups` di `admin.js` + route di `web.php` |

## Dokumentasi Lengkap

Lihat `docs/TECHNICAL_DOCUMENTATION.md` untuk detail lengkap: schema database, semua API endpoints, integrasi MikroTik, FreeRADIUS, GenieACS, deployment guide, dan troubleshooting.
