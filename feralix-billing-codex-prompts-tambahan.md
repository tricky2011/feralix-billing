# feralix-billing — Prompt Tambahan Codex

Framework: **Laravel 12**  
PHP: **8.3+**

Dokumen ini berisi prompt tambahan untuk Codex **tanpa merevisi prompt sebelumnya**. Semua prompt lama dianggap tetap berlaku, lalu prompt di bawah ini dipakai untuk **menambah fitur secara inkremental**.

---

## A. Prompt konteks tambahan global

```text
Gunakan konteks project `feralix-billing` yang sebelumnya sudah dibangun dengan Laravel 12 dan PHP 8.3+.

Penting:
- Jangan merevisi prompt lama.
- Perlakukan semua prompt sebelumnya sebagai fondasi yang sudah ada.
- Tugas kali ini adalah MENAMBAHKAN fitur baru dan menyempurnakan modul yang sudah ada tanpa merombak total struktur yang sudah dibangun.
- Jika ada bagian yang belum ada, tambahkan dengan cara yang kompatibel dan minim breaking changes.
- Jika ada desain yang lebih baik, lakukan secara inkremental dan backward-compatible.
- Gunakan best practice Laravel 12.
- Gunakan migration baru untuk perubahan schema.
- Hindari menghapus kolom/tabel lama kecuali benar-benar diperlukan, dan jika perlu jelaskan risikonya.
- Business logic tetap diletakkan pada service class / action / job yang rapi, bukan di controller.
- Jika suatu modul butuh dashboard, gunakan struktur controller/service/query class yang mudah dioptimasi.
- Untuk analytics, prioritaskan query yang efisien dan siap di-cache.
- Untuk fitur export, siapkan struktur yang mudah dihubungkan ke PDF/Excel library.
- Fokus hanya pada modul yang diminta pada prompt ini.

Saat menjawab:
1. jelaskan singkat pendekatan implementasi,
2. sebutkan file yang dibuat/diubah,
3. tampilkan kode lengkap yang relevan,
4. sebutkan migration/artisan command bila ada,
5. sebutkan dependency eksternal bila diperlukan.
```

---

## B. Prompt tambahan: Dashboard dan ringkasan bisnis

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan modul Dashboard dan Ringkasan Bisnis tanpa merombak modul lama.

Saya ingin dashboard utama dengan kemampuan berikut:
- KPI total customer
- customer aktif / suspend
- total invoice unpaid
- income bulanan
- expense bulanan
- profit bulanan
- ringkasan instalasi bulan berjalan
- jumlah tiket bulan berjalan
- ringkasan PPP active
- chart revenue bulanan
- chart tren PPP active
- analytics revenue, ARPU, dan revenue by invoice
- ranking pencapaian teknisi
- router switcher untuk superadmin

Tugas:
1. Buat service/query layer khusus untuk dashboard analytics.
2. Tambahkan endpoint/controller dashboard utama.
3. Tambahkan logika role-aware:
   - superadmin bisa melihat semua data dan switch router aktif
   - admin hanya melihat data sesuai router scope
   - teknisi diarahkan ke dashboard teknisi, bukan dashboard admin
4. Tambahkan struktur response yang cocok untuk widget KPI dan chart.
5. Jika perlu, tambahkan tabel/kolom pendukung minimal untuk menyimpan router switch state atau preferensi user.
6. Siapkan caching yang aman untuk query dashboard yang berat.
7. Jangan implementasi frontend kompleks; fokus backend dan struktur data response yang clean.

Output:
- file yang dibuat/diubah
- service/query class dashboard
- controller
- route
- contoh response JSON/dashboard payload
- penjelasan logika router switcher untuk superadmin
```

---

## C. Prompt tambahan: Customer management enhancement

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement untuk modul Customer Management yang sudah ada, tanpa menghapus implementasi lama.

Tambahkan fitur berikut:
- daftar customer dengan pagination dan pencarian yang lebih lengkap
- create, edit, update, delete customer
- bulk action:
  - bulk delete
  - bulk disable
  - bulk generate invoice
- dukungan data referensi lokasi dan OLT
- assign teknisi saat onboarding
- create invoice awal saat customer dibuat
- preview dan suggestion remote IP
- data pelanggan router-aware

Catatan penting:
- Walaupun kebutuhan lama sempat menyebut PPPoE, pada sistem final ini service utama tetap FTTH VLAN-based dan PPPoE digunakan untuk monitoring ONT.
- Jadi enhancement customer harus tetap kompatibel dengan model `services` yang sudah dibuat.

Tugas:
1. Tambahkan master reference lokasi bila belum ada.
2. Tambahkan relasi customer ke lokasi/OLT jika memang cocok.
3. Tambahkan service onboarding customer yang:
   - assign teknisi
   - bisa memicu invoice awal
   - menyiapkan struktur service awal
4. Tambahkan bulk actions yang aman dan transactional.
5. Tambahkan suggestion remote IP / preview alokasi IP berdasarkan VID/service jika memang arsitektur saat ini memungkinkan.
6. Tambahkan filter berdasarkan router, lokasi, status, OLT.
7. Pertahankan backward compatibility.

Output:
- file yang dibuat/diubah
- migration tambahan jika ada
- controller/service/request tambahan
- contoh alur onboarding customer
- contoh bulk action implementation
```

---

## D. Prompt tambahan: Billing enhancement lengkap

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement modul billing tanpa mengubah prompt lama.

Saya ingin fitur billing berikut:
- daftar invoice
- generate invoice bulanan
- manual generate invoice
- auto suspend
- record payment
- mark paid
- mark overdue
- edit invoice
- update invoice
- delete invoice
- bulk action invoice
- invoice detail view
- tombol kirim invoice ke WhatsApp
- cashflow income otomatis saat pembayaran tercatat

Karakter billing engine:
- invoice historis dipertahankan sebagai arsip
- status invoice mendukung:
  - unpaid
  - issued
  - overdue
  - paid
  - partially_paid
- billing router-aware
- bisa dipicu lewat UI maupun cron

Tugas:
1. Upgrade modul invoice/payment yang sudah ada agar mendukung status baru di atas.
2. Tambahkan service class untuk:
   - generate invoice bulanan
   - manual invoice
   - mark overdue
   - mark paid / partially paid
   - auto suspend trigger
3. Tambahkan bulk action invoice.
4. Tambahkan invoice detail query/resource.
5. Tambahkan stub/integration layer untuk kirim invoice ke WhatsApp, belum perlu provider final jika belum ada.
6. Saat payment tercatat, otomatis buat transaksi cashflow income.
7. Pastikan histori invoice tidak hilang.
8. Jika delete invoice perlu dibatasi, buat soft delete atau policy yang aman.

Output:
- file yang dibuat/diubah
- migration tambahan bila perlu
- enum/status handling
- service class
- controller/action tambahan
- contoh flow cron billing dan payment posting
```

---

## E. Prompt tambahan: Manual isolir dan sync operasional router

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement pada modul isolir dan sync operasional router.

Fitur yang ingin didukung:
- manual isolir user
- manual release user
- suggest user/target isolir
- mendukung isolir PPPoE maupun Static
- PPPoE isolir via profile / address-list
- Static isolir via simple queue / address-list

Fitur router sync tambahan:
- PPPoE sync
- migrasi secret PPP lama ke struktur sekarang
- static IP sync
- check static isolir
- cron sync ARP / queue

Catatan:
- sistem final tetap FTTH VLAN-based, tetapi modul ini harus cukup fleksibel untuk menangani mode PPPoE dan Static jika memang ada data lama/legacy.

Tugas:
1. Tambahkan abstraction yang membedakan target isolir:
   - PPPoE
   - Static
2. Tambahkan service class untuk suggest target isolir berdasarkan customer/service/pppoe/static binding.
3. Tambahkan sync operasional router untuk:
   - PPP secret / PPP active / PPP profile
   - static binding / ARP / queue / address-list
4. Tambahkan mekanisme migrasi data secret lama ke struktur sekarang secara aman.
5. Buat command terjadwal untuk sync ARP/queue/check isolir.
6. Jangan merusak flow isolir address-list yang sebelumnya sudah dibuat.

Output:
- file yang dibuat/diubah
- migration tambahan bila perlu
- service class / abstraction
- command sync tambahan
- penjelasan desain untuk mode PPPoE dan Static
```

---

## F. Prompt tambahan: Cashflow module

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan modul cashflow.

Fitur yang dibutuhkan:
- daftar transaksi cashflow
- add income manual
- add expense manual
- update transaksi
- delete transaksi
- bulk action
- ringkasan income / expense / net
- chart cashflow
- kategori cashflow
- review request / change request pada transaksi tertentu
- pengelompokan income internet, instalasi, dan expense

Tugas:
1. Buat migration, model, relasi, controller, request validation, service class untuk:
   - cashflow_transactions
   - cashflow_categories
   - cashflow_change_requests atau nama yang lebih baik jika perlu
2. Integrasikan cashflow income otomatis dari pembayaran invoice.
3. Dukung transaksi manual income dan expense.
4. Dukung ringkasan bulanan.
5. Dukung bulk actions.
6. Tambahkan struktur review request/change request untuk transaksi tertentu.
7. Siapkan data source untuk chart cashflow.
8. Gunakan desain yang role-aware agar teknisi tidak mengakses cashflow sensitif.

Output:
- migration
- model
- relasi
- service class
- controller
- route
- contoh flow posting payment ke cashflow
```

---

## G. Prompt tambahan: Work order enhancement

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement modul Work Order.

Fitur yang diinginkan:
- daftar WO dengan filter bulan dan tahun
- create WO manual
- tipe WO:
  - installation
  - maintenance
  - relocation
  - termination
  - other
- assign teknisi
- mark done
- delete WO
- notifikasi Telegram saat WO dibuat atau status berubah
- penomoran WO otomatis

Selain input manual, alur customer onboarding juga harus bisa mengarah ke operasional WO pemasangan.

Tugas:
1. Upgrade modul work order yang sudah ada agar mendukung filter bulan/tahun dan tipe WO lengkap.
2. Tambahkan generator nomor WO otomatis yang konsisten.
3. Tambahkan integrasi ke onboarding customer agar bisa membuat WO installation.
4. Tambahkan trigger notifikasi Telegram saat:
   - WO dibuat
   - status berubah
   - WO selesai
5. Buat struktur status transisi yang aman.
6. Buat policy agar teknisi hanya melihat WO sesuai scope atau assignment-nya.

Output:
- file yang dibuat/diubah
- migration tambahan bila ada
- service class
- controller/filter query
- job notifikasi Telegram
- penjelasan alur onboarding ke WO
```

---

## H. Prompt tambahan: Helpdesk dan ticketing enhancement

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement modul Helpdesk dan Ticketing.

Fitur yang dibutuhkan:
- daftar tiket
- filter periode, status, prioritas, OLT, teknisi
- create tiket gangguan
- detail tiket
- update status tiket
- add reply
- upload attachment
- mark done
- delete tiket
- lihat detail PPP customer langsung dari MikroTik
- dashboard helpdesk
- report helpdesk
- export PDF
- export Excel pada dashboard helpdesk
- SLA check via cron
- notifikasi Telegram saat tiket dibuat / berubah

Kemampuan analytics:
- channel statistik tiket
- category statistik
- technician statistik
- recent SLA breached

Tugas:
1. Upgrade modul ticket yang sudah ada.
2. Tambahkan tabel replies dan attachments bila belum ada.
3. Tambahkan SLA logic dan cron checker.
4. Tambahkan helpdesk dashboard query layer.
5. Tambahkan report source untuk PDF/Excel export.
6. Tambahkan integration abstraction untuk membaca detail PPP customer dari MikroTik.
7. Tambahkan Telegram notification job untuk event tiket.
8. Pastikan role teknisi hanya melihat tiket sesuai scope atau assignment yang relevan.

Output:
- migration tambahan
- model dan relasi
- service class
- dashboard/report query class
- export structure
- cron SLA
- integrasi abstraction MikroTik PPP detail
```

---

## I. Prompt tambahan: Dashboard teknisi

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan modul Dashboard Teknisi.

Fitur:
- KPI teknisi
- target instalasi dan target tiket
- work order rows
- ticket rows
- ranking teknisi
- point rule
- filter teknisi
- export PDF

Role `teknisi` harus diarahkan ke dashboard ini saat membuka dashboard utama.

Tugas:
1. Buat controller/service/query layer dashboard teknisi.
2. Tambahkan perhitungan KPI:
   - jumlah WO assigned
   - WO selesai
   - tiket assigned
   - tiket selesai
   - target instalasi
   - target tiket
   - point/ranking
3. Tambahkan struktur point rule yang mudah diubah.
4. Tambahkan filter teknisi dan periode.
5. Tambahkan source untuk export PDF.
6. Tambahkan redirect logic role `teknisi` dari dashboard utama ke dashboard teknisi.

Output:
- file yang dibuat/diubah
- migration tambahan jika perlu untuk target/point rule
- service/query class
- controller
- route
- penjelasan logika ranking teknisi
```

---

## J. Prompt tambahan: Network map dan topologi fiber

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan modul Network Map dan Topologi Fiber.

Fitur yang ingin didukung:
- Fiber Network Map
- visualisasi topologi:
  - Router
  - OLT
  - ODC
  - ODP
  - Customer
- filter router
- marker clustering
- popup metadata node
- status utilisasi ODP/ODC berdasarkan kapasitas
- manual line drawing di peta
- manajemen node ODC/ODP
- API create/update/delete router, OLT, ODC, ODP

Tugas:
1. Tambahkan model data jaringan fiber bila belum ada:
   - odcs
   - odps
   - fiber_links atau nama yang cocok
2. Simpan koordinat dan metadata node.
3. Tambahkan API backend untuk create/update/delete node dan line/link.
4. Tambahkan service/query untuk menghasilkan payload peta/topologi.
5. Tambahkan perhitungan utilisasi ODP/ODC berdasarkan kapasitas dan keterisian customer/link.
6. Pertahankan router dan OLT yang sudah ada.
7. Fokus backend/API dan struktur data, frontend map cukup disiapkan payload-nya.

Output:
- migration
- model
- relasi
- controller/api resource
- payload contoh untuk map
- penjelasan struktur node dan link
```

---

## K. Prompt tambahan: Master references enhancement

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement Master References.

Fitur:
- Master Lokasi
- Master OLT
- bulk update
- bulk delete

Tugas:
1. Tambahkan modul master_locations jika belum ada.
2. Rapikan master OLT agar bisa dipakai sebagai reference management.
3. Tambahkan bulk update dan bulk delete yang aman.
4. Tambahkan validasi agar reference yang masih dipakai tidak dihapus sembarangan.
5. Tambahkan service class untuk bulk operations.
6. Pastikan modul ini kompatibel dengan customer, router, work order, dan ticket.

Output:
- migration tambahan
- model
- service class
- controller
- route
- penjelasan validasi referential safety
```

---

## L. Prompt tambahan: User management, role, audit, router scope assignment

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement modul User Management dan Audit.

Fitur:
- login / logout
- user management
- create user
- edit user
- update user
- delete user
- role management
- router scope assignment ke user
- validasi status user aktif
- user activity log
- user logs view
- demo mode banner / read only indicator

Tugas:
1. Upgrade modul user/role yang sudah ada.
2. Tambahkan mapping router scope ke user.
3. Tambahkan audit log aktivitas user untuk event penting.
4. Tambahkan validasi bahwa user nonaktif tidak bisa login/mengakses sistem.
5. Tambahkan mekanisme demo mode / read only indicator via config/settings.
6. Tambahkan viewer untuk logs user.
7. Buat policy/authorization yang rapi.

Output:
- migration tambahan
- model/pivot/relasi
- middleware/policy
- audit logging implementation
- demo mode config approach
```

---

## M. Prompt tambahan: Settings system lengkap

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan modul Settings.

Area settings yang harus didukung:
- Settings Router
- Settings Router ACS
- Settings Telegram
- Settings Database
- Settings MikroTik legacy
- Settings PPPoE Sync
- test koneksi router
- test koneksi database
- test Telegram
- konfigurasi multi bot Telegram
- konfigurasi multi group Telegram
- dispatch group berdasarkan type:
  - teknisi
  - admin
  - owner
  - alert

Tugas:
1. Buat arsitektur settings yang fleksibel, misalnya tabel key-value terstruktur atau grouped settings.
2. Tambahkan service class untuk get/set settings.
3. Tambahkan settings groups sesuai kebutuhan di atas.
4. Tambahkan endpoint/action test connection:
   - router
   - database
   - telegram
5. Tambahkan support multi bot dan multi group Telegram.
6. Tambahkan pemetaan group Telegram berdasarkan type dispatch.
7. Pastikan penyimpanan credential sensitif aman.

Output:
- migration
- model
- service class
- controller
- route
- penjelasan penyimpanan credential dan security notes
```

---

## N. Prompt tambahan: Provisioning API enhancement

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement Provisioning API.

Kebutuhan backend provisioning:
- create customer pending
- generate username/password
- simpan ke customers
- simpan ke pppoe_secrets atau struktur legacy yang kompatibel
- create PPP secret ke MikroTik
- assign teknisi otomatis
- kirim Telegram ke teknisi

Catatan:
- sistem final utama adalah FTTH VLAN-based, tetapi provisioning API harus tetap bisa menangani alur legacy PPPoE dengan aman.

Tugas:
1. Buat endpoint provisioning internal/API.
2. Buat service provisioning yang transactional.
3. Tambahkan status customer pending bila perlu.
4. Tambahkan generator username/password.
5. Tambahkan compatibility layer untuk `pppoe_secrets` legacy jika memang diperlukan.
6. Tambahkan integrasi abstraction untuk create PPP secret ke Mikrotik.
7. Tambahkan auto assign teknisi.
8. Tambahkan job notifikasi Telegram ke teknisi.
9. Jangan merusak model service FTTH yang sudah ada.

Output:
- migration tambahan bila perlu
- model/service/controller/api route
- contoh request/response provisioning
- penjelasan compatibility layer legacy PPPoE
```

---

## O. Prompt tambahan: PWA dan mobile wrapper support

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan dukungan PWA dan mobile wrapper untuk aplikasi ini.

Fitur yang diinginkan:
- installable PWA
- cache asset penting
- offline fallback page
- wrapper Android via Capacitor
- membuka aplikasi web langsung dari URL server
- integrasi pemilih kontak pada browser/PWA/Capacitor

Tugas:
1. Siapkan struktur PWA dasar untuk Laravel app:
   - manifest
   - service worker
   - offline fallback
2. Siapkan strategi caching asset penting.
3. Tambahkan route/page offline fallback.
4. Tambahkan dokumentasi/struktur untuk wrapper Android via Capacitor.
5. Tambahkan abstraction/helper untuk integrasi contact picker jika environment mendukung.
6. Fokus pada struktur integrasi dan kesiapan deployment, bukan UI penuh.

Output:
- file yang dibuat/diubah
- struktur manifest/service worker
- route offline fallback
- catatan integrasi Capacitor
- catatan compatibility contact picker
```

---

## P. Prompt tambahan: Hak akses dan router scope policy

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan enhancement role dan hak akses agar sesuai kebutuhan akhir sistem.

Role:
- Superadmin
  - akses semua modul
  - dapat switch router aktif
  - dapat hard delete pada modul yang mengizinkan
  - dapat mengelola settings sistem
  - dapat melihat semua distribusi/router
- Admin
  - fokus operasional harian
  - akses customer, billing, cashflow, WO, helpdesk, monitoring, router management
  - umumnya dibatasi pada router scope
  - pada beberapa modul delete dilakukan soft delete / nonaktif
  - tidak ditujukan untuk membuat atau mengelola akses superadmin
- Teknisi
  - fokus ke work order, helpdesk, dashboard teknisi, dan ONT monitoring internal
  - tidak mengakses billing sensitif, cashflow, dan settings inti
  - biasanya dibatasi ke router scope tertentu

Tugas:
1. Rapikan role dan permission yang sudah ada agar cocok dengan kebutuhan ini.
2. Tambahkan policy dan middleware yang memeriksa router scope.
3. Tambahkan helper/query scope untuk membatasi data berdasarkan router scope user.
4. Tambahkan hard delete guard khusus superadmin.
5. Tambahkan pembatasan menu/endpoint untuk teknisi.
6. Pastikan role admin tidak bisa menaikkan privilege menjadi superadmin.

Output:
- file yang dibuat/diubah
- policy/middleware
- contoh query scope router-aware
- penjelasan permission matrix singkat
```

---

## Q. Prompt tambahan: WhatsApp integration layer

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan integration layer untuk WhatsApp.

Kebutuhan saat ini:
- tombol kirim invoice ke WhatsApp
- ke depan bisa dipakai juga untuk notifikasi lain

Tugas:
1. Buat abstraction/provider layer untuk WhatsApp.
2. Buat stub/fake provider untuk development.
3. Buat service class untuk compose pesan invoice.
4. Tambahkan queue/job pengiriman WhatsApp.
5. Tambahkan logging pengiriman.
6. Belum perlu provider final tertentu, tapi arsitektur harus mudah disambungkan ke API WhatsApp gateway mana pun.

Output:
- migration tambahan bila perlu untuk log
- service/provider abstraction
- job queue
- contoh payload invoice message
- penjelasan strategi integrasi provider
```

---

## R. Prompt tambahan: Export PDF dan Excel framework

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang tambahkan pondasi export PDF dan Excel yang reusable.

Kebutuhan:
- helpdesk dashboard bisa export PDF/Excel
- dashboard teknisi bisa export PDF
- modul lain ke depan bisa memanfaatkan struktur export yang sama

Tugas:
1. Buat abstraction/service export yang reusable.
2. Pisahkan source data report dari format output.
3. Siapkan integrasi library PDF dan Excel yang cocok untuk Laravel 12.
4. Buat contoh implementasi untuk:
   - helpdesk dashboard export PDF
   - helpdesk dashboard export Excel
   - dashboard teknisi export PDF
5. Pastikan authorization dicek sebelum export.

Output:
- service/export structure
- dependency/library yang dipakai
- controller/action
- contoh implementasi
- catatan best practice export
```

---

## S. Prompt tambahan: Analytics dan reporting layer

```text
Gunakan konteks tambahan project `feralix-billing`.

Sekarang buat layer analytics dan reporting yang reusable untuk beberapa dashboard.

Kebutuhan analytics:
- revenue bulanan
- ARPU
- revenue by invoice
- tren PPP active
- ranking teknisi
- statistik ticket per kategori/channel/teknisi
- recent SLA breached
- cashflow summary

Tugas:
1. Buat query service/reporting layer yang reusable.
2. Pisahkan antara:
   - raw aggregation query
   - formatter untuk chart/table
3. Tambahkan caching per periode/router scope.
4. Buat struktur yang bisa dipakai dashboard admin, helpdesk, dan teknisi.
5. Hindari query N+1 dan query yang terlalu berat.

Output:
- file yang dibuat/diubah
- reporting/query classes
- contoh pemakaian di beberapa dashboard
- strategi caching dan invalidation singkat
```

---

## T. Prompt tambahan: Review hardening setelah fitur tambahan

```text
Sekarang review hasil implementasi modul tambahan ini.

Tolong:
1. cek kompatibilitas dengan modul lama
2. cek backward compatibility
3. cek migration baru aman atau tidak
4. cek potensi bug dan race condition
5. cek validasi request
6. cek policy dan pembatasan role
7. cek apakah query sudah router-aware
8. cek apakah logic berat sudah dipindah ke service/query class
9. cek apakah ada bagian yang perlu cache atau queue
10. rapikan agar konsisten dengan Laravel 12 best practice

Jangan menambah fitur baru. Fokus hanya hardening dan perapihan modul yang sudah dibuat.
```

---

## U. Urutan eksekusi prompt tambahan yang disarankan

1. Prompt konteks tambahan global  
2. Role & router scope policy  
3. User management & audit  
4. Dashboard & ringkasan bisnis  
5. Analytics & reporting layer  
6. Customer management enhancement  
7. Billing enhancement  
8. Cashflow  
9. Manual isolir & sync operasional router  
10. Work order enhancement  
11. Helpdesk enhancement  
12. Dashboard teknisi  
13. Master references enhancement  
14. Settings  
15. WhatsApp integration layer  
16. Provisioning API enhancement  
17. Network map & topologi fiber  
18. Export PDF/Excel  
19. PWA & mobile wrapper  
20. Hardening review per modul

---

## V. Prompt ringkasan implementasi tambahan

```text
Tolong ringkas hasil implementasi modul tambahan ini menjadi:
1. file yang dibuat/diubah
2. migration baru
3. route baru/yang berubah
4. service/query/job/provider yang ditambahkan
5. business rule yang sudah tercakup
6. dampaknya ke modul lama
7. hal yang belum dikerjakan

Jangan ubah kode, hanya buat ringkasan implementasi.
```
