# Rencana Implementasi Ulang RadBill

> Dokumen ini adalah panduan untuk membangun ulang fitur-fitur RadBill
> ke dalam project baru dari nol. Bukan untuk di-copy paste langsung,
> melainkan sebagai blueprint pengembangan.

---

## Fase 1: Foundation (Minggu 1-2)

### 1.1 Setup Project
- [ ] Buat project Laravel baru (atau framework pilihan)
- [ ] Setup database (MySQL/PostgreSQL)
- [ ] Setup authentication multi-role (Admin, Reseller, Kasir, Client)
- [ ] Setup Nginx reverse proxy + SSL (lihat `docs/NGINX_SSL_SETUP.md`)

### 1.2 Database Schema Dasar
```sql
-- Tabel utama yang dibutuhkan:
- users (id, name, email, role, password)
- customers (id, name, address, phone, status)
- packages (id, name, speed_up, speed_down, price, duration)
- customer_packages (customer_id, package_id, start_date, end_date)
```

---

## Fase 2: Core Billing (Minggu 3-4)

### 2.1 Manajemen Pelanggan
- [ ] CRUD pelanggan
- [ ] Assign paket ke pelanggan
- [ ] Status management (aktif/suspend/nonaktif)

### 2.2 Invoice System
- [ ] Generate invoice otomatis (artisan command / scheduler)
- [ ] Pembayaran penuh
- [ ] Pembayaran parsial
- [ ] Download invoice (PDF)
- [ ] Riwayat pembayaran

### 2.3 Voucher System
- [ ] Buat voucher (batch/individual)
- [ ] Tukar voucher
- [ ] Revenue dicatat saat penukaran, bukan pembuatan

---

## Fase 3: Network Integration (Minggu 5-6)

### 3.1 Manajemen NAS
- [ ] CRUD NAS/Router
- [ ] Status tracking (ping/heartbeat)
- [ ] Koneksi ke MikroTik API

### 3.2 PPPoE Management
- [ ] Create/delete PPPoE user di MikroTik
- [ ] Monitor sesi PPPoE aktif
- [ ] Sync status dengan database

### 3.3 Hotspot Management
- [ ] Create/delete Hotspot user
- [ ] Monitor sesi Hotspot aktif
- [ ] Filter pencarian by tanggal

### 3.4 Suspend/Isolir
- [ ] Auto-suspend saat jatuh tempo
- [ ] Halaman redirect isolir
- [ ] Reaktivasi setelah bayar

---

## Fase 4: RADIUS Integration (Minggu 7-8)

### 4.1 FreeRADIUS Setup
- [ ] Install dan konfigurasi FreeRADIUS
- [ ] Integrasi database RadBill dengan RADIUS
- [ ] Sync user PPPoE/Hotspot ke RADIUS

### 4.2 Expiration Management
- [ ] Set expiration date di RADIUS
- [ ] Update expiration hanya setelah login sukses
- [ ] Disconnect sesi saat suspend

---

## Fase 5: Multi-Role Portal (Minggu 9-10)

### 5.1 Admin Portal
- [ ] Dashboard statistik lengkap
- [ ] Semua fitur manajemen
- [ ] Manajemen user system

### 5.2 Reseller Portal
- [ ] Dashboard reseller
- [ ] Kelola sub-pelanggan
- [ ] Laporan komisi

### 5.3 Kasir Portal
- [ ] Dashboard kasir (optimized, simple)
- [ ] Input pembayaran cepat
- [ ] Cetak kwitansi

### 5.4 Client Portal
- [ ] Lihat tagihan
- [ ] Download invoice
- [ ] Riwayat pembayaran
- [ ] Info paket aktif

---

## Fase 6: Integrasi Gateway (Minggu 11-12)

### 6.1 Payment Gateway
- [ ] Pilih provider (Midtrans/Xendit/dll)
- [ ] Setup webhook handler
- [ ] Auto-update status invoice
- [ ] Rekonsiliasi

### 6.2 WhatsApp Gateway
- [ ] Pilih provider (Fonnte/WaBlas/dll)
- [ ] Template pesan tagihan
- [ ] Template pengingat jatuh tempo
- [ ] Template konfirmasi bayar

---

## Fase 7: Advanced Features (Minggu 13-14)

### 7.1 Laporan & Monitoring
- [ ] Laporan keuangan (harian/bulanan/tahunan)
- [ ] Export ke PDF/Excel
- [ ] Monitoring sesi real-time
- [ ] Network map/visualisasi

### 7.2 VPN Management
- [ ] Setup WireGuard
- [ ] Setup L2TP
- [ ] Assign VPN ke NAS tertentu

### 7.3 TR-069 / ACS (Opsional)
- [ ] Integrasi GenieACS
- [ ] Provisioning CPE otomatis

---

## Database Schema Lengkap (Estimasi)

```sql
-- Core Tables
customers
packages
nas_devices
invoices
invoice_items
payments
vouchers
voucher_redemptions

-- Auth
users (multi-role)
resellers
cashiers

-- Network
pppoe_users
hotspot_users
radius_users
active_sessions

-- Finance
transactions
revenue_reports

-- Config
payment_gateways
whatsapp_settings
radius_settings
system_settings
```

---

## Tech Stack Rekomendasi

| Layer | Pilihan |
|-------|---------|
| Backend | Laravel 11 / Go (Fiber) |
| Frontend | Livewire + Alpine.js / Inertia + Vue |
| Database | MySQL 8 / PostgreSQL 16 |
| Queue | Redis + Laravel Horizon |
| Scheduler | Laravel Scheduler / Cron |
| Web Server | Nginx |
| SSL | Let's Encrypt (Certbot) |
| MikroTik | routeros-api PHP / go-routeros |
| RADIUS | FreeRADIUS + daloRADIUS |
| PDF | DomPDF / mPDF |
| WhatsApp | Fonnte / WaBlas / Wuzapi |
| Payment | Midtrans / Xendit |

---

## Prioritas Fitur (MVP)

Untuk MVP (Minimum Viable Product), fokus di:

1. **Manajemen pelanggan** — CRUD dasar
2. **Invoice otomatis** — generate dan bayar
3. **NAS + PPPoE/Hotspot** — integrasi MikroTik
4. **Suspend/Isolir** — auto suspend jatuh tempo
5. **Multi-role** — Admin + Kasir + Client

Sisanya bisa ditambahkan iteratif.
