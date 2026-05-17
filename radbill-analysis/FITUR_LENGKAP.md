# Daftar Fitur Lengkap RadBill

Versi analisis: v2.0.0.1-E21

---

## 1. Manajemen Pelanggan

- Pendaftaran dan pengelolaan data pelanggan (nama, alamat, kontak)
- Pengaturan profil layanan per pelanggan (paket internet)
- Status aktif/nonaktif/suspend pelanggan
- Riwayat layanan dan perpindahan paket
- Pencarian pelanggan berdasarkan berbagai parameter

## 2. Billing & Invoice

- **Pembuatan invoice otomatis** berdasarkan siklus tagihan
- Riwayat pembayaran per pelanggan
- Manajemen produk/paket harga (kecepatan, harga, masa aktif)
- **Pembayaran parsial** — invoice bisa dibayar sebagian
- Pembayaran penuh untuk invoice dengan status partial
- **Download invoice** oleh pelanggan dari portal client
- Tanggal suspend otomatis saat invoice jatuh tempo belum dibayar
- Perbaikan update tanggal suspend saat invoice dibayar
- **Voucher system**:
  - Voucher dibuat admin
  - Pendapatan dicatat saat voucher **ditukar**, bukan saat dibuat
  - Pengelolaan voucher individual dan massal

## 3. Manajemen NAS / Router MikroTik

- Registrasi perangkat NAS (Network Access Server)
- Tracking status perangkat (online/offline)
- **Modifikasi NAS individual atau massal (bulk)**
- Tampilan NAS di antarmuka hotspot user
- Pembagian/sharing koneksi NAS antar sistem
- VPN access ke router:
  - **WireGuard**
  - **L2TP**

## 4. PPPoE & Hotspot

- Setup dan manajemen user PPPoE
- Setup dan manajemen user Hotspot
- **Isolasi jaringan** per pelanggan
- Pengelompokan profil PPPoE/Hotspot
- Pencarian hotspot user berdasarkan tanggal pembuatan
- Tombol clear/reset filter pencarian

## 5. Multi-Role Portal

### Admin
- Dashboard lengkap semua data
- Kelola pelanggan, paket, NAS, laporan
- Manajemen lisensi dari dalam aplikasi (integrasi langsung)

### Reseller
- Portal reseller terpisah
- Kelola pelanggan di bawah reseller
- Komisi dan laporan reseller

### Kasir
- **Dashboard kasir yang dioptimasi** (performa ditingkatkan di v2.0.0.1-E21)
- Input pembayaran pelanggan
- Cetak/download kwitansi

### Client / Pelanggan
- Portal self-service pelanggan
- Lihat tagihan dan status layanan
- Download invoice
- Riwayat pembayaran

## 6. Payment Gateway

- Integrasi dengan payment gateway eksternal
- Pembayaran online oleh pelanggan
- Rekonsiliasi otomatis ke sistem billing
- Notifikasi pembayaran real-time

## 7. WhatsApp Gateway

- Notifikasi tagihan via WhatsApp
- Kirim invoice otomatis ke pelanggan
- Pengingat jatuh tempo
- Konfirmasi pembayaran

## 8. Monitoring Sesi Aktif

- Melihat sesi PPPoE/Hotspot yang sedang aktif real-time
- Detail koneksi: IP, MAC, durasi, usage
- Visualisasi jaringan (network map)
- Status perangkat NAS secara real-time

## 9. Laporan Keuangan

- Laporan transaksi harian/bulanan/tahunan
- Laporan pendapatan per paket
- Laporan per kasir/reseller
- Export laporan
- Log aktivitas sistem

## 10. Radius Server

- Integrasi RADIUS untuk autentikasi PPPoE/Hotspot
- Pengelolaan expiration date radius
  - **Expiration hanya berlaku setelah login sukses** (fix di v2.0.0.1-E21)
- Sinkronisasi data pelanggan ke RADIUS

## 11. TR-069 / ACS Support

- Integrasi **GenieACS** (Auto Configuration Server)
- Integrasi **GoACS**
- Provisioning perangkat CPE (router pelanggan) otomatis
- Remote configuration modem/router pelanggan

## 12. Suspend / Isolir

- Suspend otomatis saat tagihan belum dibayar
- Halaman isolir khusus (port 8087) untuk pelanggan yang di-suspend
- Reaktivasi otomatis setelah pembayaran dikonfirmasi
- Update tanggal suspend yang tepat saat invoice dibayar

## 13. Manajemen Lisensi

- Pengelolaan lisensi terintegrasi langsung di dalam aplikasi RadBill
- Tidak perlu keluar aplikasi untuk kelola lisensi

---

## Changelog v2.0.0.1-E21 (Fitur Baru & Bug Fix)

| Tipe | Deskripsi |
|------|-----------|
| Fix | Voucher yang dibuat admin tidak langsung jadi revenue; dicatat saat ditukar |
| Fix | Bug update tanggal suspend saat invoice dibayar |
| Improve | Optimasi dashboard kasir admin |
| Fix | Bug pembayaran parsial tidak update invoice dan tanggal suspend |
| Change | Mekanisme sharing koneksi NAS antar sistem diubah |
| Fix | Pembayaran penuh untuk invoice dengan status partial |
| New | Download invoice di portal client |
| Fix | Radius expiration date hanya berlaku setelah login sukses |
| New | Tampilan NAS di antarmuka hotspot user |
| New | Modifikasi NAS individual atau bulk |
| New | Pencarian hotspot user berdasarkan tanggal pembuatan |
| New | Tombol clear untuk field pencarian |
| New | Manajemen lisensi terintegrasi dalam aplikasi |
