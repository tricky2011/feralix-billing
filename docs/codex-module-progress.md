# Codex Module Progress

Tanggal audit: 2026-04-22  
Sumber utama: `feralix-billing-codex-prompts-tambahan.md`  
Mode: audit + implementasi bertahap, saat ini modul `P` sudah mulai di-hardening

## Metode audit
- Membaca seluruh prompt tambahan dan urutan eksekusi yang disarankan.
- Scan codebase pada `routes`, `app`, `database`, `tests`, dan `config`.
- Menandai modul sebagai `DONE`, `PARTIAL`, atau `TODO` berdasarkan implementasi nyata di repo, bukan hanya nama file.

## Legend
- `DONE`: kebutuhan inti modul sudah ada dan terhubung.
- `PARTIAL`: sudah ada fondasi/fitur inti, tetapi masih ada gap penting terhadap prompt.
- `TODO`: belum ditemukan implementasi modul yang relevan.

## Catatan verifikasi
- `php artisan test` belum bisa dipakai sebagai bukti fungsional penuh di environment ini.
- Penyebab utama: PHP hanya memuat `PDO` dan `pdo_mysql`; `pdo_sqlite` tidak tersedia.
- Dampaknya: mayoritas test yang memakai SQLite in-memory gagal di bootstrap dengan error `could not find driver`.
- Baseline saat audit ini: `8 passed`, `56 failed`, tetapi kegagalan didominasi masalah environment test, bukan assertion bisnis.

## Modul audit menurut urutan eksekusi

| Urutan | Kode | Modul | Status | Ringkasan audit |
| --- | --- | --- | --- | --- |
| 1 | `P` | Hak akses dan router scope policy | `PARTIAL` | Wave awal enforcement sudah masuk: middleware role admin/teknisi, route binding router-scope guard, request authorize inti, dan query scope lintas customer/billing/helpdesk/WO/settings prioritas. Sisa lanjutan masih terkait penyempurnaan coverage dan dependensi modul user/auth. |
| 2 | `L` | User management, role, audit, router scope assignment | `PARTIAL` | Model user, enum role, relasi technician/router assignment sudah ada, tetapi CRUD user, login/logout, audit log, demo mode, dan validasi user nonaktif belum ada. |
| 3 | `B` | Dashboard dan ringkasan bisnis | `DONE` | Dashboard admin, dashboard teknisi redirect, KPI, chart, analytics revenue/ARPU, ranking teknisi, router switcher, dan cache sudah tersedia. |
| 4 | `S` | Analytics dan reporting layer | `PARTIAL` | Query layer reusable untuk dashboard admin sudah ada, tetapi belum menjadi layer lintas helpdesk/cashflow/export, dan belum mencakup statistik SLA/helpdesk/cashflow. |
| 5 | `C` | Customer management enhancement | `DONE` | CRUD customer, onboarding, bulk action, filter router/lokasi/OLT, reference lokasi/OLT, dan provisioning preview sudah tersedia. |
| 6 | `D` | Billing enhancement lengkap | `DONE` | Invoice lifecycle baru, bulk action, generate bulanan/manual, payment posting, auto suspend trigger, invoice detail, WhatsApp trigger, dan arsip via soft delete sudah tersedia. |
| 7 | `F` | Cashflow module | `PARTIAL` | `cashflow_transactions` dan income otomatis dari payment invoice sudah ada, tetapi modul cashflow manual penuh belum ada. |
| 8 | `E` | Manual isolir dan sync operasional router | `PARTIAL` | Manual isolate/release, suggestion target, PPPoE/Static abstraction, dan status operasi router sudah ada, tetapi sync PPP secret/ARP/queue/static binding dan migrasi legacy belum ada. |
| 9 | `G` | Work order enhancement | `PARTIAL` | CRUD WO, nomor otomatis, onboarding ke WO, assignment teknisi, dan status dasar sudah ada, tetapi filter bulan/tahun, notifikasi status berubah, policy teknisi, dan beberapa tipe belum lengkap. |
| 10 | `H` | Helpdesk dan ticketing enhancement | `PARTIAL` | Ticket create/list/show, auto assign teknisi, dan Telegram queued notification sudah ada, tetapi reply, attachment, update status penuh, SLA, dashboard, report, dan export belum ada. |
| 11 | `I` | Dashboard teknisi | `PARTIAL` | Endpoint dan KPI dasar teknisi sudah ada, tetapi target, point rule, ranking, filter periode/teknisi, dan export PDF belum ada. |
| 12 | `K` | Master references enhancement | `PARTIAL` | Master lokasi dan OLT CRUD sudah ada, tetapi bulk update/delete aman dan referential safety belum lengkap. |
| 13 | `M` | Settings system lengkap | `TODO` | Belum ditemukan tabel settings, service get/set settings, endpoint test connection, maupun multi bot/group Telegram management. |
| 14 | `Q` | WhatsApp integration layer | `PARTIAL` | Sudah ada gateway abstraction + stub + invoice compose/send, tetapi belum ada queue job generik dan log delivery tersimpan di database. |
| 15 | `N` | Provisioning API enhancement | `PARTIAL` | Sudah ada onboarding/provisioning preview/FTTH service manager, tetapi belum ada endpoint provisioning internal khusus pending customer + layer legacy PPPoE secret. |
| 16 | `J` | Network map dan topologi fiber | `TODO` | Belum ditemukan model/migration/controller untuk ODC, ODP, fiber link, atau payload topologi. |
| 17 | `R` | Export PDF dan Excel framework | `TODO` | Belum ada service export reusable, endpoint export, maupun dependency PDF/Excel di `composer.json`. |
| 18 | `O` | PWA dan mobile wrapper support | `TODO` | Belum ada manifest PWA, service worker, offline fallback route, atau dokumentasi Capacitor. |
| 19 | `T` | Hardening review setelah fitur tambahan | `TODO` | Belum waktunya dikerjakan; dilakukan setelah modul tambahan yang masih `PARTIAL/TODO` selesai. |

## Modul yang bisa di-skip saat lanjut implementasi
- `B` Dashboard dan ringkasan bisnis
- `C` Customer management enhancement
- `D` Billing enhancement lengkap

## Antrian lanjutan setelah audit
Lanjut sesuai urutan prompt, dengan modul `DONE` dilewati:

1. `P` Hak akses dan router scope policy
2. `L` User management, role, audit, router scope assignment
3. `S` Analytics dan reporting layer
4. `F` Cashflow module
5. `E` Manual isolir dan sync operasional router
6. `G` Work order enhancement
7. `H` Helpdesk dan ticketing enhancement
8. `I` Dashboard teknisi
9. `K` Master references enhancement
10. `M` Settings system lengkap
11. `Q` WhatsApp integration layer
12. `N` Provisioning API enhancement
13. `J` Network map dan topologi fiber
14. `R` Export PDF dan Excel framework
15. `O` PWA dan mobile wrapper support
16. `T` Hardening review setelah fitur tambahan

## Evidence inti per modul

### `P` Hak akses dan router scope policy
- Evidence:
  - `app/Enums/UserRole.php`
  - `app/Models/User.php`
  - `database/migrations/2026_04_22_090000_add_dashboard_access_fields_to_users_table.php`
  - `database/migrations/2026_04_22_090100_create_user_router_assignments_table.php`
  - `app/Services/Dashboard/DashboardAccessService.php`
  - `app/Services/Access/RoleRouterScopeService.php`
  - `app/Http/Middleware/EnsurePanelRole.php`
  - `app/Http/Middleware/AuthorizeRouterScopedBindings.php`
  - `app/Http/Requests/AdminPanelRequest.php`
  - `routes/api.php`
  - `tests/Feature/Api/RoleRouterScopePolicyTest.php`
- Update implementasi:
  - Request admin inti pada modul prioritas tidak lagi `authorize() = true`, tetapi memakai base request `AdminPanelRequest`.
  - Endpoint admin prioritas sekarang memakai middleware `panel.role` dan `router.scope.bindings`.
  - Query router-aware sudah diterapkan di customer, billing, helpdesk, work order, provisioning service, customer references, router, router scope, dan VID.
  - Payload sensitif berbasis `router_id/service_id/vid_id/invoice_id` sekarang divalidasi melalui service akses-scope terpusat agar tetap backward-compatible.
- Gap tersisa:
  - Backward-compatible guest bypass masih dipertahankan sampai modul `L` menyediakan login/logout dan enforcement auth penuh.
  - Coverage scope belum diperluas ke seluruh modul nonprioritas di luar area yang disebut audit.

### `L` User management, role, audit, router scope assignment
- Evidence:
  - `app/Models/User.php`
  - `database/factories/UserFactory.php`
  - `routes/api.php` belum memiliki endpoint user/auth management
- Gap utama:
  - Belum ada controller/service CRUD user.
  - Belum ada login/logout flow aplikasi.
  - Belum ada audit/activity log.
  - Belum ada demo mode/read-only indicator.

### `B` Dashboard dan ringkasan bisnis
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/DashboardController.php`
  - `app/Services/Dashboard/DashboardAnalyticsService.php`
  - `app/Services/Dashboard/Queries/DashboardOverviewQuery.php`
  - `app/Services/Dashboard/Queries/DashboardChartQuery.php`
  - `app/Services/Dashboard/Queries/DashboardRevenueAnalyticsQuery.php`
  - `app/Services/Dashboard/Queries/DashboardTechnicianRankingQuery.php`
  - `app/Http/Controllers/Api/V1/Technician/DashboardController.php`
  - `tests/Feature/Api/DashboardControllerTest.php`
- Catatan:
  - Modul ini sudah memenuhi intent utama prompt tambahan dashboard admin.

### `S` Analytics dan reporting layer
- Evidence:
  - `app/Services/Dashboard/Queries/AbstractDashboardQuery.php`
  - `app/Services/Dashboard/Queries/DashboardOverviewQuery.php`
  - `app/Services/Dashboard/Queries/DashboardChartQuery.php`
  - `app/Services/Dashboard/Queries/DashboardRevenueAnalyticsQuery.php`
  - `app/Services/Dashboard/DashboardAnalyticsService.php`
- Gap utama:
  - Belum ada layer reporting bersama untuk helpdesk, cashflow, dan export.
  - Belum ada statistik tiket per kategori/channel/teknisi.
  - Belum ada `recent SLA breached` dan `cashflow summary`.

### `C` Customer management enhancement
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/CustomerController.php`
  - `app/Services/MasterData/CustomerService.php`
  - `app/Services/Customer/CustomerOnboardingService.php`
  - `app/Services/Customer/CustomerBulkActionService.php`
  - `app/Services/Customer/CustomerProvisioningPreviewService.php`
  - `app/Services/Customer/CustomerReferenceService.php`
  - `database/migrations/2026_04_22_100000_create_locations_table.php`
  - `database/migrations/2026_04_22_100100_add_customer_management_fields.php`
  - `tests/Feature/Api/CustomerManagementEnhancementTest.php`
- Catatan:
  - Modul inti enhancement customer sudah tersedia dan terhubung.

### `D` Billing enhancement lengkap
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/InvoiceController.php`
  - `app/Http/Controllers/Api/V1/Admin/PaymentController.php`
  - `app/Services/Billing/InvoiceService.php`
  - `app/Services/Billing/MonthlyInvoiceGenerationService.php`
  - `app/Services/Billing/PaymentService.php`
  - `app/Services/Billing/InvoiceBulkActionService.php`
  - `app/Services/Billing/InvoiceWhatsappService.php`
  - `app/Services/Billing/BillingAutomationService.php`
  - `database/migrations/2026_04_22_110000_upgrade_invoice_billing_lifecycle.php`
  - `database/migrations/2026_04_22_110100_create_cashflow_transactions_table.php`
  - `tests/Feature/Api/BillingControllerTest.php`
- Catatan:
  - Status invoice baru, bulk action, WhatsApp send, cashflow income otomatis, dan cron command billing sudah ada.

### `F` Cashflow module
- Evidence:
  - `app/Models/CashflowTransaction.php`
  - `app/Models/BusinessExpense.php`
  - `database/migrations/2026_04_22_110100_create_cashflow_transactions_table.php`
  - `database/migrations/2026_04_22_090200_create_business_expenses_table.php`
  - `app/Services/Billing/CashflowIncomeService.php`
- Gap utama:
  - Belum ada controller/route cashflow.
  - Belum ada manual income/expense CRUD.
  - Belum ada category table terpisah.
  - Belum ada bulk action, review/change request, chart endpoint, dan role guard cashflow.

### `E` Manual isolir dan sync operasional router
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/ServiceIsolationController.php`
  - `app/Services/Provisioning/ServiceIsolationService.php`
  - `app/Services/Provisioning/ServiceIsolationSuggestionService.php`
  - `app/Services/Provisioning/ServiceIsolationTargetResolver.php`
  - `app/Models/ServiceRouterOperationStatus.php`
  - `database/migrations/2026_04_22_120000_add_legacy_operational_fields_to_services_table.php`
  - `database/migrations/2026_04_22_120100_create_service_router_operation_statuses_table.php`
  - `database/migrations/2026_04_22_120200_add_target_fields_to_service_isolations_table.php`
  - `app/Console/Commands/CreateOverdueServiceIsolationCommand.php`
- Gap utama:
  - Belum ada command/service sync PPP secret/PPP active/profile.
  - Belum ada sync static binding/ARP/queue/address-list.
  - Belum ada migrator legacy secret yang nyata, baru field penanda.
  - Belum ada cron khusus check static isolir/ARP/queue.

### `G` Work order enhancement
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/WorkOrderController.php`
  - `app/Services/FieldWork/WorkOrderService.php`
  - `app/Models/WorkOrder.php`
  - `tests/Feature/Api/WorkOrderControllerTest.php`
  - `app/Services/Customer/CustomerOnboardingService.php`
- Gap utama:
  - Belum ada filter bulan/tahun.
  - Tipe `maintenance` belum ada; enum saat ini masih `new_install/relocation/termination/ont_replacement/other`.
  - Belum ada notifikasi Telegram saat WO berubah/selesai.
  - Belum ada policy teknisi berdasar scope/assignment.

### `H` Helpdesk dan ticketing enhancement
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/TicketController.php`
  - `app/Services/Helpdesk/TicketService.php`
  - `app/Services/Helpdesk/TechnicianAutoAssignmentService.php`
  - `app/Services/Helpdesk/TelegramNotificationService.php`
  - `app/Jobs/SendTicketCreatedTelegramNotificationJob.php`
  - `app/Console/Commands/ProcessTelegramNotificationsCommand.php`
  - `tests/Feature/Api/TicketControllerTest.php`
- Gap utama:
  - Belum ada update status/reply/delete.
  - Belum ada replies table dan attachments table.
  - Belum ada SLA checker.
  - Belum ada helpdesk dashboard/report/export.
  - Belum ada integration abstraction untuk detail PPP customer dari MikroTik.

### `I` Dashboard teknisi
- Evidence:
  - `app/Http/Controllers/Api/V1/Technician/DashboardController.php`
  - `app/Services/Dashboard/Queries/TechnicianDashboardQuery.php`
  - `app/Services/Dashboard/DashboardAnalyticsService.php`
- Gap utama:
  - Belum ada target instalasi/target tiket.
  - Belum ada point rule dan ranking teknisi khusus dashboard teknisi.
  - Belum ada filter periode/teknisi.
  - Belum ada export PDF.

### `K` Master references enhancement
- Evidence:
  - `app/Http/Controllers/Api/V1/Admin/LocationController.php`
  - `app/Http/Controllers/Api/V1/Admin/OltController.php`
  - `app/Services/MasterData/LocationService.php`
  - `app/Services/Customer/CustomerReferenceService.php`
  - `app/Models/Location.php`
  - `app/Models/Olt.php`
- Gap utama:
  - Belum ada bulk update/delete untuk lokasi dan OLT.
  - Belum ada guard referential safety saat reference masih dipakai customer/service/work order/ticket.
  - Service layer OLT masih inline di controller.

### `M` Settings system lengkap
- Evidence:
  - Tidak ditemukan model/controller/service/settings terpusat.
  - `composer.json` dan `routes/api.php` belum menunjukkan modul settings.
- Gap utama:
  - Tabel settings, grouped settings, test connection endpoint, multi bot/group Telegram, dan security handling credential belum ada.

### `Q` WhatsApp integration layer
- Evidence:
  - `app/Contracts/Billing/InvoiceWhatsAppGateway.php`
  - `app/Services/Billing/Gateways/StubInvoiceWhatsAppGateway.php`
  - `app/Services/Billing/InvoiceWhatsappService.php`
  - `app/Providers/AppServiceProvider.php`
- Gap utama:
  - Belum ada job queue WhatsApp khusus.
  - Belum ada persistence/log table pengiriman WhatsApp.
  - Kontrak masih sangat invoice-centric, belum siap penuh untuk notifikasi generik lain.

### `N` Provisioning API enhancement
- Evidence:
  - `app/Services/Customer/CustomerOnboardingService.php`
  - `app/Services/Customer/CustomerProvisioningPreviewService.php`
  - `app/Services/Provisioning/FtthServiceManager.php`
- Gap utama:
  - Belum ada endpoint provisioning internal khusus.
  - Belum ada status pending customer khusus provisioning.
  - Belum ada generator username/password legacy PPPoE.
  - Belum ada abstraction create PPP secret ke MikroTik.
  - Belum ada job Telegram assignment dari provisioning.

### `J` Network map dan topologi fiber
- Evidence:
  - Tidak ditemukan model/migration `odcs`, `odps`, `fiber_links`, atau API topologi.
- Gap utama:
  - Modul masih kosong.

### `R` Export PDF dan Excel framework
- Evidence:
  - Tidak ditemukan service/controller export.
  - `composer.json` belum memuat dependency PDF/Excel.
- Gap utama:
  - Modul masih kosong.

### `O` PWA dan mobile wrapper support
- Evidence:
  - Tidak ditemukan `manifest.webmanifest`, service worker, offline route khusus, atau dokumentasi Capacitor.
- Gap utama:
  - Modul masih kosong.

### `T` Hardening review setelah fitur tambahan
- Catatan:
  - Dikerjakan setelah backlog `PARTIAL/TODO` selesai.
  - Saat ini baseline hardening utama yang sudah terlihat adalah gap authorization lintas modul dan gap verifikasi test environment.

## Kesimpulan audit
- Tiga modul tambahan sudah cukup matang untuk di-skip pada fase lanjutan: `B`, `C`, dan `D`.
- Wave awal modul `P` sudah masuk untuk endpoint prioritas; langkah berikutnya paling logis adalah lanjut ke `L` agar enforcement role/scope bisa ditutup dengan auth flow dan audit log yang lengkap.
- Setelah itu, modul paling berdampak untuk melengkapi fondasi bisnis adalah `S`, `F`, `E`, `G`, dan `H`.
