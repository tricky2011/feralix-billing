# Arsitektur Sistem RadBill

---

## Stack Teknologi (Analisis)

Berdasarkan installer binary dan struktur deployment:

| Komponen | Teknologi |
|----------|-----------|
| Backend | Binary compiled (kemungkinan **Go/Golang**) |
| Frontend | Web-based (diakses via browser) |
| Database | Kemungkinan **MySQL/PostgreSQL** |
| Web Server | **Nginx** (reverse proxy) |
| Auth Protocol | **RADIUS** (FreeRADIUS/Radiusd) |
| VPN | **WireGuard** dan **L2TP** |
| ACS | **GenieACS** / **GoACS** (TR-069) |
| OS Support | **Ubuntu/Debian** (amd64 & arm64) |

---

## Arsitektur Deployment

```
Internet
    │
    ▼
[Nginx - Reverse Proxy + SSL]
    │
    ├──► my.domain:8080      ─────► [RadBill Backend - Admin Portal]
    ├──► client.domain:8080  ─────► [RadBill Backend - Client Portal]
    └──► isolir.domain:8087  ─────► [Halaman Isolir/Suspend]

[RadBill Backend]
    │
    ├──► [Database - MySQL/PostgreSQL]
    ├──► [RADIUS Server]
    ├──► [MikroTik NAS via API/SNMP]
    ├──► [Payment Gateway API]
    ├──► [WhatsApp Gateway API]
    └──► [GenieACS/GoACS - TR-069]
```

---

## Port yang Digunakan

| Port | Layanan |
|------|---------|
| 80 | Nginx HTTP (redirect ke HTTPS) |
| 443 | Nginx HTTPS |
| 8080 | RadBill backend (admin + client) |
| 8087 | Halaman isolir pelanggan suspend |

---

## Struktur Layanan Sistem (Systemd)

Dari script `migrate.sh`, terlihat layanan yang dikelola:

### Layanan Legacy (gonet — dihapus saat migrasi)
- `gonet-api` — API backend lama
- `gonet-acs` — ACS service lama
- `gonet-radius` — RADIUS service lama
- `gonet-worker` — Worker/queue service lama

### Layanan RadBill (baru)
- Installer biner langsung set up layanan-layanan baru
- Diduga ada: `radbill-api`, `radbill-radius`, `radbill-worker`

---

## Struktur Direktori di Server

```
/opt/gonet/     ← direktori legacy (dihapus saat migrasi)
/opt/radbill/   ← kemungkinan direktori instalasi baru (estimasi)
/etc/nginx/     ← konfigurasi Nginx
/etc/systemd/system/  ← service files
```

---

## Arsitektur Multi-Role

```
┌─────────────────────────────────────────────┐
│              RadBill Backend                │
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │  Admin   │  │Reseller  │  │  Kasir   │  │
│  │  Portal  │  │  Portal  │  │  Portal  │  │
│  └──────────┘  └──────────┘  └──────────┘  │
│                                             │
│              ┌──────────┐                  │
│              │  Client  │                  │
│              │  Portal  │                  │
│              └──────────┘                  │
└─────────────────────────────────────────────┘
         │                  │
         ▼                  ▼
  [MikroTik API]    [RADIUS Server]
         │
         ▼
  [PPPoE/Hotspot Users]
```

---

## Alur Billing

```
1. Pelanggan terdaftar dengan paket
         │
         ▼
2. Invoice otomatis dibuat (cycle billing)
         │
         ├─► Notifikasi WhatsApp dikirim
         │
         ▼
3. Pelanggan bayar (via Payment Gateway / kasir)
         │
         ├─► Status invoice → PAID
         ├─► Tanggal suspend di-reset
         └─► RADIUS expiration diperbarui
                  │
                  ▼
4. Jika tidak bayar sampai jatuh tempo:
         │
         ▼
5. Status pelanggan → SUSPEND
         │
         ├─► Redirect ke halaman isolir (port 8087)
         └─► RADIUS session di-disconnect
```

---

## Alur Voucher

```
Admin buat voucher
         │
         ▼
Voucher tersimpan (belum jadi revenue)
         │
         ▼
Pelanggan/kasir tukar voucher
         │
         ▼
Revenue dicatat → invoice dibuat/lunas
```

---

## Integrasi Eksternal

### Payment Gateway
- Terima callback/webhook pembayaran
- Update status invoice otomatis
- Support multiple gateway (perlu dikonfirmasi gateway mana saja)

### WhatsApp Gateway
- Kirim pesan via API WhatsApp Business atau unofficial gateway
- Template pesan: tagihan, jatuh tempo, konfirmasi bayar

### MikroTik API
- Koneksi ke router via MikroTik API (port 8728/8729)
- Manage PPPoE secret dan Hotspot user
- Monitor sesi aktif

### RADIUS
- FreeRADIUS atau equivalent
- Autentikasi PPPoE dan Hotspot
- Accounting (catat usage)

### GenieACS / GoACS (TR-069)
- Remote provisioning CPE (modem/router pelanggan)
- Auto-konfigurasi perangkat baru
