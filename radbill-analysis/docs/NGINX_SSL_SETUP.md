# Setup Nginx + SSL (Let's Encrypt) untuk RadBill

> Source: https://github.com/radbill/radbill/blob/main/NGINX_SSL_SETUP.md

---

## Konfigurasi Target

RadBill menggunakan 3 subdomain yang di-proxy ke backend:

| Domain | Port Backend | Keterangan |
|--------|-------------|------------|
| `my.domain.com` | 8080 | Portal Admin |
| `client.domain.com` | 8080 | Portal Client |
| `isolir.domain.com` | 8087 | Halaman Suspend/Isolir |

---

## Langkah 1: Install Nginx

```bash
sudo apt update
sudo apt install nginx -y
sudo systemctl enable nginx
sudo systemctl start nginx
```

---

## Langkah 2: Konfigurasi HTTP Awal (Sebelum SSL)

Buat konfigurasi untuk verifikasi domain sebelum SSL diaktifkan.

### File: `/etc/nginx/sites-available/radbill`

```nginx
# Portal Admin
server {
    listen 80;
    server_name my.domain.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Portal Client
server {
    listen 80;
    server_name client.domain.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Halaman Isolir
server {
    listen 80;
    server_name isolir.domain.com;

    location / {
        proxy_pass http://127.0.0.1:8087;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Aktifkan konfigurasi:

```bash
sudo ln -s /etc/nginx/sites-available/radbill /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Langkah 3: Install Certbot & Generate SSL

```bash
sudo apt install certbot python3-certbot-nginx -y

sudo certbot --nginx -d my.domain.com -d client.domain.com -d isolir.domain.com
```

### Saat Certbot bertanya tentang redirect:
> Pilih **opsi 2 (Redirect)** agar semua traffic HTTP otomatis diarahkan ke HTTPS.

```
Please choose whether or not to redirect HTTP traffic to HTTPS, removing HTTP access.
- - - - - - - - - - - - - - - - - - - - - - - - - - - -
1: No redirect - Make no further changes to the webserver configuration.
2: Redirect - Make all requests redirect to secure HTTPS access.
- - - - - - - - - - - - - - - - - - - - - - - - - - - -
Select the appropriate number [1-2] then [enter]: 2  ← Pilih ini
```

---

## Langkah 4: Konfigurasi Final Nginx (Setelah SSL)

Certbot otomatis memodifikasi konfigurasi. Hasil akhirnya seperti ini:

```nginx
# Portal Admin
server {
    listen 80;
    server_name my.domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name my.domain.com;

    ssl_certificate /etc/letsencrypt/live/my.domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/my.domain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Portal Client
server {
    listen 80;
    server_name client.domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name client.domain.com;

    ssl_certificate /etc/letsencrypt/live/client.domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/client.domain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Halaman Isolir
server {
    listen 80;
    server_name isolir.domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name isolir.domain.com;

    ssl_certificate /etc/letsencrypt/live/isolir.domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/isolir.domain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass http://127.0.0.1:8087;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## Langkah 5: Auto-Renewal SSL

Certbot otomatis set renewal via systemd timer. Cek dengan:

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
```

---

## Troubleshooting

### Error 502 Bad Gateway
Backend RadBill tidak berjalan di port 8080/8087.
```bash
sudo systemctl status radbill
sudo journalctl -u radbill -n 50
```

### Port 80/443 Blocked
Buka firewall:
```bash
sudo ufw allow 80
sudo ufw allow 443
sudo ufw reload
```

### Certificate Domain Mismatch
Pastikan DNS record sudah pointing ke IP server sebelum generate certificate.
```bash
dig my.domain.com
dig client.domain.com
dig isolir.domain.com
```
