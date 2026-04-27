# Feature Audit Summary

Tanggal audit: 2026-04-24  
Tujuan: ringkasan fitur yang sudah dibangun agar bisa dicek apakah sudah sesuai dengan kebutuhan pemilik aplikasi.  
Sumber audit: struktur kode Laravel, route API/web, migration, service/job/query, test, dan UI admin saat ini.

## Cara membaca status

- `SELESAI`: kebutuhan inti sudah ada, punya route/service/data model, dan sudah terhubung ke alur utama.
- `SEBAGIAN`: fondasi sudah ada, tetapi masih ada gap penting terhadap ekspektasi akhir.
- `PLACEHOLDER UI`: menu/halaman sudah ada di panel, tetapi backend endpoint utama belum tersedia.
- `BELUM ADA`: belum ditemukan implementasi backend yang relevan.

## Ringkasan cepat

Core operasional ISP sudah cukup tebal: auth token Sanctum, dashboard, customer, service FTTH VLAN + VID, billing/payment, invoice automation, isolir manual/otomatis via service, tiket helpdesk dengan reply, work order, inventori router/OLT/ONT/VID, hotspot voucher + RADIUS, monitoring PPPoE, sync GenieACS, dan sync VID Mikrotik.

Bagian yang masih perlu diaudit ketat: user management penuh, audit log user, settings lengkap, cashflow manual, network map ODP/ODC, export PDF/Excel, PWA/mobile wrapper, provisioning legacy PPPoE, serta UI untuk beberapa modul yang backend-nya belum selesai.

## Fitur yang sudah dibangun

| Area | Status | Yang sudah ada | Bukti utama | Catatan audit |
| --- | --- | --- | --- | --- |
| Login/logout API | `SEBAGIAN` | Login username atau email, token Sanctum, `me`, logout/revoke token, role abilities. | `routes/api.php`, `app/Services/Auth/ApiTokenAuthService.php`, `tests/Feature/Api/ApiAuthenticationTest.php` | Field `is_active` sudah ada, tetapi login belum menolak user nonaktif. Belum ada CRUD user. |
| Role dan router scope | `SEBAGIAN` | Middleware role panel, router scoped binding, assignment router ke user, query scope di modul utama. | `app/Http/Middleware/EnsurePanelRole.php`, `app/Http/Middleware/AuthorizeRouterScopedBindings.php`, `app/Services/Access/RoleRouterScopeService.php` | Perlu audit final untuk memastikan semua endpoint sensitif sudah scope-aware dan teknisi punya akses operasional yang tepat. |
| Panel admin web | `SEBAGIAN` | Login page, layout admin, sidebar grouped, topbar, router switcher, CRUD generic UI, detail tiket/reply. | `routes/web.php`, `resources/views/admin/index.blade.php`, `resources/js/admin.js` | Banyak menu sudah tampil, tetapi sebagian masih placeholder menunggu backend. |
| Dashboard admin | `SELESAI` | KPI customer, invoice unpaid, income/expense/profit bulanan, instalasi, tiket, PPP active, chart revenue, PPP trend, revenue analytics, ranking teknisi, router switcher. | `app/Http/Controllers/Api/V1/Admin/DashboardController.php`, `app/Services/Dashboard/*` | Sudah sesuai kebutuhan dashboard inti. |
| Dashboard teknisi API | `SEBAGIAN` | KPI teknisi, ticket/WO assigned, list recent ticket/WO. | `app/Http/Controllers/Api/V1/Technician/DashboardController.php`, `app/Services/Dashboard/Queries/TechnicianDashboardQuery.php` | Belum ada target/point rule/export PDF. UI sidebar `technician-dashboard` masih placeholder, sedangkan endpoint `/api/v1/technician/dashboard` sudah ada. |
| Customer management | `SELESAI` | CRUD customer, pagination/search/filter, lokasi/OLT/teknisi, onboarding, bulk delete, bulk disable, bulk generate invoice, provisioning preview. | `app/Http/Controllers/Api/V1/Admin/CustomerController.php`, `app/Services/Customer/*`, `tests/Feature/Api/CustomerManagementEnhancementTest.php` | Cocok dengan arah FTTH VLAN-based. |
| Service FTTH + provisioning data | `SELESAI` | CRUD service, relasi customer/package/router/OLT/ONT/VID, subnet, DHCP pool, access mode VLAN/PPPoE/static, status billing/network/overall. | `app/Http/Controllers/Api/V1/Admin/ServiceController.php`, `app/Services/Provisioning/FtthServiceManager.php` | Legacy PPPoE create-secret ke Mikrotik belum ada. |
| VID inventory | `SELESAI` | CRUD VID, assignment invariant, router scope VID, sync VID dari Mikrotik provider, conflict detection/log. | `app/Services/Inventory/VidService.php`, `app/Services/Mikrotik/MikrotikVidSyncService.php`, `tests/Feature/Api/VidControllerTest.php` | Ada command audit `services:audit-vid-assignments`. |
| Router, router scope, OLT, ONT, package, lokasi | `SELESAI` | CRUD master network/reference, filter/search, relasi lokasi ke OLT/customer, ONT GenieACS fields. | `routes/api.php`, `app/Http/Controllers/Api/V1/Admin/*Controller.php` | Bulk update/delete master reference belum ada. Referential safety delete perlu audit. |
| Billing dan invoice | `SELESAI` | CRUD invoice, generate manual/bulanan, mark overdue/paid, bulk action, overdue/paid/unpaid listing, auto suspend, soft delete/history, invoice detail. | `app/Http/Controllers/Api/V1/Admin/InvoiceController.php`, `app/Services/Billing/*`, `tests/Feature/Api/BillingControllerTest.php` | Sudah mencakup kebutuhan billing utama. |
| Payment | `SELESAI` | Record payment, update payment status invoice, income cashflow otomatis, relasi invoice/customer/service. | `app/Http/Controllers/Api/V1/Admin/PaymentController.php`, `app/Services/Billing/PaymentService.php` | Cashflow manual belum lengkap. |
| WhatsApp invoice | `SEBAGIAN` | Contract gateway, stub provider, compose/send invoice WhatsApp dari endpoint invoice. | `app/Contracts/Billing/InvoiceWhatsAppGateway.php`, `app/Services/Billing/InvoiceWhatsappService.php` | Belum ada job queue khusus dan log database pengiriman WhatsApp. |
| Cashflow | `SEBAGIAN` | Tabel/model cashflow transaction, income otomatis dari payment, business expense untuk dashboard expense. | `app/Models/CashflowTransaction.php`, `app/Models/BusinessExpense.php`, `app/Services/Billing/CashflowIncomeService.php` | Belum ada endpoint CRUD cashflow, kategori, chart cashflow khusus, bulk action, review/change request. UI `cashflow` masih placeholder. |
| Isolir service | `SELESAI` | List/suggestion/create isolir, mark applied, release, target subnet/PPPoE/static, status operasi router, job eksekusi address-list Mikrotik. | `app/Http/Controllers/Api/V1/Admin/ServiceIsolationController.php`, `app/Services/Provisioning/ServiceIsolation*`, `tests/Feature/Api/ServiceIsolationControllerTest.php` | Sync PPP secret/ARP/queue/static binding belum lengkap. |
| Billing automation | `SELESAI` | Generate invoice bulanan, check overdue, create overdue isolation, sync invoice-isolation, job queue, scheduler. | `routes/console.php`, `app/Console/Commands/*Billing*`, `app/Jobs/*Invoice*` | Cocok untuk cron, perlu pastikan scheduler aktif di server produksi. |
| Work order | `SEBAGIAN` | CRUD WO, nomor otomatis, assignment teknisi, status transisi dasar, completion timestamp, onboarding bisa membuat WO. | `app/Http/Controllers/Api/V1/Admin/WorkOrderController.php`, `app/Services/FieldWork/WorkOrderService.php`, `tests/Feature/Api/WorkOrderControllerTest.php` | Belum ada filter bulan/tahun, tipe masih `new_install` bukan `installation`, Telegram untuk WO belum ada, policy teknisi khusus belum lengkap. |
| Helpdesk/ticketing | `SEBAGIAN` | List/create/show ticket, auto assign teknisi, update status, add reply, log/queue Telegram untuk create/status/reply. | `app/Http/Controllers/Api/V1/Admin/TicketController.php`, `app/Services/Helpdesk/TicketService.php`, `database/migrations/2026_04_24_020000_create_ticket_replies_table.php` | Belum ada attachment, delete ticket, SLA cron, dashboard/report/export helpdesk, detail PPP customer dari Mikrotik. |
| Telegram notification | `SEBAGIAN` | Tabel `telegram_logs`, queue processing, log-only delivery mode, job untuk ticket notification. | `app/Services/Helpdesk/TelegramNotificationService.php`, `app/Console/Commands/ProcessTelegramNotificationsCommand.php` | Settings multi bot/group dan test Telegram belum ada. |
| Hotspot voucher | `SELESAI` | Profile hotspot, reseller, voucher batch, voucher list, generate voucher, saldo reseller, activate voucher, mask password. | `app/Services/Hotspot/*`, `routes/api.php`, `tests/Feature/Api/HotspotVoucherModuleTest.php` | Delete profile/voucher dibatasi; perlu cek apakah aturan bisnis reseller sudah sesuai. |
| Hotspot RADIUS | `SELESAI` | Internal authorize/accounting endpoint, session tracking, event log, MAC lock, expiry time/data, reject reason. | `app/Http/Controllers/Api/V1/Internal/HotspotRadiusController.php`, `app/Services/Hotspot/Radius/HotspotRadiusService.php` | Endpoint internal belum terlihat memakai token/shared secret khusus; perlu audit keamanan integrasi RADIUS. |
| PPPoE monitor | `SELESAI` | Sync PPP active dari Mikrotik, status online/offline per service, log perubahan, update overall status. | `app/Services/Monitoring/PppoeMonitorSyncService.php`, `app/Console/Commands/SyncPppoeMonitorCommand.php` | Ini untuk monitoring, bukan provisioning PPPoE secret. |
| GenieACS ONT sync | `SELESAI` | Provider fake/NBI, client HTTP, mapper snapshot ONT, sync single/query, command/job scheduled. | `app/Services/GenieAcs/*`, `app/Console/Commands/SyncGenieAcsOntCommand.php` | UI config ACS masih placeholder. |
| Settings | `SEBAGIAN` | Tabel `app_settings`, seed app locale/timezone/demo mode. | `app/Models/AppSetting.php`, `database/seeders/AppSettingSeeder.php` | Belum ada service get/set, controller/route settings, test connection, multi bot/group Telegram. |
| Database health | `SELESAI` | Command health check dan halaman/error JSON database unavailable. | `app/Support/DatabaseHealthCheckService.php`, `app/Console/Commands/DatabaseHealthCheckCommand.php`, `resources/views/database-unavailable.blade.php` | Berguna untuk operasional dan audit availability. |
| API response standardization | `SELESAI` | Response success/error konsisten, exception JSON untuk auth/validation/404/500/database. | `app/Support/Api/ApiResponse.php`, `app/Http/Controllers/Concerns/InteractsWithApiResponses.php`, `bootstrap/app.php` | Sudah ada test standardisasi API. |
| Sensitive field masking | `SELESAI` | Masking untuk field sensitif di resource/API. | `app/Support/Security/SensitiveValueMasker.php`, `tests/Feature/Api/SensitiveFieldExposureTest.php` | Tetap perlu audit endpoint baru agar tidak expose password/token. |

## Menu UI yang sudah terhubung ke backend

- Dashboard: `/api/v1/admin/dashboard`
- Customers: `/api/v1/admin/customers`
- Services + VID: `/api/v1/admin/services`
- Billing/invoices: `/api/v1/admin/invoices`
- Manual isolir: `/api/v1/admin/service-isolations`
- Helpdesk tickets: `/api/v1/admin/tickets`
- Work orders: `/api/v1/admin/work-orders`
- Network tabs: routers, router scopes, VIDs, OLTs, ONTs, packages, locations
- Hotspot tabs: profiles, resellers, voucher batches, vouchers

## Menu UI yang masih placeholder

Menu berikut sudah muncul di sidebar/panel, tetapi kontennya masih daftar kebutuhan backend:

- Upgrade Paket
- Service Plan
- IP Pools
- Router Sync
- Cashflow
- Fiber Network Map
- Manajemen ODP/ODC
- Dashboard Teknisi page
- Monitoring
- ONT Online
- ONT Offline
- Config ACS
- User Management
- User Logs
- Settings Telegram
- Settings Database

Catatan: beberapa backend di balik menu placeholder sebenarnya sudah ada sebagian, misalnya PPPoE monitor, GenieACS sync, technician dashboard API, dan cashflow income otomatis. Yang belum ada adalah UI/endpoint operasional lengkap sesuai menu tersebut.

## Route penting untuk audit API

| Group | Route utama | Catatan |
| --- | --- | --- |
| Auth | `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout` | Sanctum token. |
| Admin | `/api/v1/admin/*` | Wajib `auth:sanctum`; sebagian besar juga `panel.role:superadmin,admin` dan `router.scope.bindings`. |
| Technician | `GET /api/v1/technician/dashboard` | Wajib role `technician`. |
| Internal | `POST /api/v1/internal/hotspot-radius/authorize`, `POST /api/v1/internal/hotspot-radius/accounting` | Untuk RADIUS/hotspot internal. Perlu validasi keamanan integrasi. |
| Web panel | `/login`, `/admin/{page}` | SPA-like admin panel berbasis Blade + Alpine. |

## Automation dan scheduler

| Command | Jadwal default | Fungsi |
| --- | --- | --- |
| `billing:generate-monthly-invoices --queue` | tanggal 1, `00:05` | Generate invoice bulanan. |
| `billing:check-overdue-invoices --queue` | harian `00:20` | Hitung status overdue/billing. |
| `billing:create-overdue-isolations --queue` | harian `00:30` | Membuat isolir otomatis untuk invoice overdue. |
| `mikrotik:sync-vids --queue` | setiap 15 menit | Sync inventory VID dari Mikrotik. |
| `monitor:sync-pppoe --queue` | tiap menit | Sync PPP active untuk monitoring ONT/service. |
| `genieacs:sync-onts --queue` | tiap 5 menit | Sync telemetry/status ONT dari GenieACS. |
| `notifications:process-telegram --queue` | tiap menit | Proses log notifikasi Telegram. |

## Hal yang perlu Anda cocokkan dengan ekspektasi

1. Apakah service utama memang finalnya FTTH VLAN-based, dengan PPPoE hanya untuk monitoring dan legacy? Kode saat ini mengikuti arah itu.
2. Apakah role teknisi cukup hanya dashboard, tiket, dan work order, atau harus punya endpoint list/update khusus teknisi? Saat ini banyak endpoint operasional masih di group admin.
3. Apakah user nonaktif wajib ditolak login? Field sudah ada, tetapi enforcement login belum ada.
4. Apakah RADIUS internal boleh terbuka di network internal saja, atau perlu shared secret/token di aplikasi?
5. Apakah invoice delete boleh soft delete untuk admin dan hard delete hanya superadmin? Soft delete invoice sudah ada, hard delete guard superadmin perlu dipastikan.
6. Apakah cashflow harus bisa input manual income/expense dari UI? Saat ini baru otomatis dari payment dan expense dipakai dashboard.
7. Apakah ODP/ODC dan fiber map wajib di fase ini? Saat ini belum ada model/backend.
8. Apakah export PDF/Excel wajib untuk audit/reporting? Dependency dan framework export belum ada.
9. Apakah notifikasi Telegram harus berlaku untuk WO juga? Saat ini baru ticket.
10. Apakah menu placeholder boleh tetap tampil sebagai roadmap, atau harus disembunyikan sampai backend selesai?

## Gap prioritas sebelum dianggap final

1. User management: CRUD user, reset password, validasi user nonaktif, audit log user, demo/read-only enforcement.
2. Security internal: proteksi endpoint RADIUS/internal dan audit masking credential semua integrasi.
3. Cashflow: endpoint/manual transaction/category/chart/review request.
4. Work order: filter bulan/tahun, tipe `installation` atau mapping dari `new_install`, notifikasi Telegram WO, policy teknisi.
5. Helpdesk: attachment, delete, SLA cron, dashboard/report/export, detail PPP customer dari Mikrotik.
6. Settings: service/controller/route, Telegram multi bot/group, test router/database/Telegram, penyimpanan credential aman.
7. Network topology: ODC/ODP/fiber link model, API topology, capacity/utilization.
8. Export: service reusable + dependency PDF/Excel.
9. PWA/mobile wrapper: manifest, service worker, offline fallback, dokumentasi Capacitor.
10. Hardening akhir: coverage role/scope, race condition billing/isolir, scheduler/queue deployment, dan test environment.

## Evidence test yang tersedia

Test sudah mencakup area besar:

- Auth API
- Dashboard admin
- Customer enhancement
- Billing
- Service/VID/router
- Role router scope policy
- Ticket
- Work order
- Hotspot voucher dan RADIUS
- Isolir service dan router operation job
- Billing automation console
- Sync Mikrotik VID, PPPoE monitor, GenieACS ONT
- API response standardization
- Sensitive field exposure
- Database health check

Untuk audit final, jalankan `php artisan test` di environment yang punya extension database test sesuai konfigurasi, lalu lampirkan hasilnya ke dokumen ini.

