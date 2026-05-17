# RadBill — Analisis & Breakdown Lengkap

> **Folder ini HANYA untuk analisis dan referensi.**
> Tidak ada satu pun kode di sini yang diimplementasikan ke feralix-billing.
> Tujuan: mempelajari arsitektur RadBill untuk dijiplak fitur-fiturnya ke project sendiri.

---

## Tentang RadBill

RadBill adalah aplikasi **billing dan manajemen ISP** buatan Indonesia. Digunakan oleh penyedia layanan internet untuk mengelola pelanggan, tagihan, perangkat NAS/router, serta integrasi payment gateway dan WhatsApp.

- **Repo asli**: https://github.com/radbill/radbill
- **Versi terbaru**: v2.0.0.1-E21 (1 Mei 2026)
- **Bahasa utama**: Shell (installer) + binary compiled (Go/backend)
- **Stars**: 14 | **Forks**: 13

---

## Struktur Folder Analisis Ini

```
radbill-analysis/
├── README.md                    ← File ini
├── FITUR_LENGKAP.md             ← Daftar lengkap semua fitur RadBill
├── ARSITEKTUR.md                ← Arsitektur sistem & stack teknologi
├── RENCANA_IMPLEMENTASI.md      ← Panduan untuk mengimplementasi ulang
├── scripts/
│   ├── install.sh               ← Script instalasi asli
│   ├── update.sh                ← Script update asli
│   └── migrate.sh               ← Script migrasi dari gonet ke radbill
├── docs/
│   └── NGINX_SSL_SETUP.md       ← Panduan setup Nginx + SSL
└── fitur/
    ├── 01-manajemen-pelanggan.md
    ├── 02-billing-invoice.md
    ├── 03-nas-router.md
    ├── 04-hotspot-pppoe.md
    ├── 05-multi-role-portal.md
    ├── 06-payment-gateway.md
    ├── 07-whatsapp-gateway.md
    ├── 08-monitoring-sesi.md
    ├── 09-laporan-keuangan.md
    └── 10-vpn-manajemen.md
```

---

## Quick Overview Fitur Utama

| No | Fitur | Status Analisis |
|----|-------|-----------------|
| 1 | Manajemen Pelanggan | Selesai |
| 2 | Billing & Invoice Otomatis | Selesai |
| 3 | Manajemen NAS/Router MikroTik | Selesai |
| 4 | PPPoE & Hotspot | Selesai |
| 5 | Multi-Role Portal (Admin/Reseller/Kasir/Client) | Selesai |
| 6 | Payment Gateway | Selesai |
| 7 | WhatsApp Gateway | Selesai |
| 8 | Monitoring Sesi Aktif | Selesai |
| 9 | Laporan Keuangan & Transaksi | Selesai |
| 10 | VPN (WireGuard/L2TP) | Selesai |
| 11 | TR-069 / GenieACS / GoACS | Selesai |
| 12 | Voucher System | Selesai |
| 13 | Radius Server | Selesai |
| 14 | Isolir/Suspend | Selesai |

---

## Cara Pakai Analisis Ini

1. Baca `FITUR_LENGKAP.md` untuk gambaran besar semua fitur
2. Baca `ARSITEKTUR.md` untuk memahami stack teknologi yang digunakan
3. Masuk folder `fitur/` untuk breakdown detail per modul
4. Gunakan `RENCANA_IMPLEMENTASI.md` sebagai panduan saat mau coding ulang
5. Script di `scripts/` bisa dijadikan referensi deployment automation
