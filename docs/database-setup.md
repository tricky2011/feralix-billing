# Database Setup

Project ini disiapkan untuk MySQL 8.0+ atau MariaDB 10.6+ pada Ubuntu server. Default yang direkomendasikan:

- `DB_CONNECTION=mysql` untuk MySQL
- `DB_CONNECTION=mariadb` untuk MariaDB
- `DB_CHARSET=utf8mb4`
- `DB_COLLATION=utf8mb4_unicode_ci`

`utf8mb4_unicode_ci` dipilih sebagai default karena paling aman lintas MySQL dan MariaDB. Jika server Anda murni MySQL 8, `utf8mb4_0900_ai_ci` bisa dipakai, tetapi kolasi itu tidak portable ke MariaDB.

## Ubuntu server

### MySQL

```bash
sudo apt update
sudo apt install -y mysql-server php8.3-mysql
sudo mysql_secure_installation
```

### MariaDB

```bash
sudo apt update
sudo apt install -y mariadb-server php8.3-mysql
sudo mysql_secure_installation
```

## Buat database dan user

Masuk ke shell SQL:

```bash
sudo mysql
```

Lalu buat database dan user aplikasi:

```sql
CREATE DATABASE feralix_billing
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'feralix_user'@'127.0.0.1' IDENTIFIED BY 'ganti-dengan-password-kuat';
GRANT ALL PRIVILEGES ON feralix_billing.* TO 'feralix_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Jika Anda memakai socket lokal Ubuntu, Anda juga bisa menyetel `DB_SOCKET=/run/mysqld/mysqld.sock`.

## Konfigurasi environment

Salin `.env.example` ke `.env`, lalu set minimal:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=feralix_billing
DB_USERNAME=feralix_user
DB_PASSWORD=...
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
APP_SUPERADMIN_NAME=Superadmin
APP_SUPERADMIN_USERNAME=arya
APP_SUPERADMIN_EMAIL=
APP_SUPERADMIN_PASSWORD=strong-password
APP_SEED_SAMPLE_DATA=false
```

Untuk MariaDB, cukup ubah `DB_CONNECTION=mariadb`.

## Menjalankan setup Laravel

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan config:clear
php artisan db:health-check
php artisan migrate --seed
```

Jika koneksi database belum siap, `php artisan db:health-check` akan memberi alasan yang lebih jelas sebelum migrasi dijalankan.

## Setelah migrasi

Untuk production, Anda bisa beralih ke driver database-backed bila dibutuhkan:

```dotenv
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Setelah mengubah tiga nilai di atas, jalankan:

```bash
php artisan config:clear
php artisan migrate
```

Catatan:

- Seeder foundation akan membuat `app_settings` minimal.
- Superadmin awal dibuat dengan `APP_SUPERADMIN_USERNAME`; default username adalah `arya`.
- Jika `APP_SUPERADMIN_PASSWORD` kosong, seeder memakai password default project. Ganti nilai ini sebelum production.
