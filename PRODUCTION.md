# Deployment Production — Quiz TikTok

## 1. Siapkan hosting

- Gunakan PHP 8.3 atau 8.4.
- Aktifkan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, dan `exif`.
- Aktifkan HTTPS/SSL sebelum membuka aplikasi untuk peserta.
- Buat database dan user database khusus aplikasi. Jangan gunakan user `root`.

Pengaturan PHP yang disarankan:

```ini
upload_max_filesize = 6M
post_max_size = 32M
max_file_uploads = 10
memory_limit = 256M
display_errors = Off
log_errors = On
expose_php = Off
date.timezone = Asia/Jakarta
```

## 2. Backup sebelum memperbarui

1. Export database production melalui phpMyAdmin.
2. Backup folder upload production.
3. Jangan menimpa `config.local.php` dan folder `uploads` dengan data lokal.

## 3. Upload kode

Upload atau tarik kode terbaru ke document root situs. Pastikan file berikut ikut:

- `.htaccess`
- `uploads/.htaccess`
- `admin/traffic-data.php`
- folder `assets/fontawesome`
- `assets/sweetalert2.all.min.js`

Folder `DB`, `config.local.php`, dan isi upload peserta tidak ikut Git.

## 4. Konfigurasi rahasia

Pilihan paling aman adalah membuat file `quiz_tiktok.config.php` satu tingkat di
atas document root. Contoh bila aplikasi berada di `public_html`, simpan file di:

```text
/home/USERNAME/quiz_tiktok.config.php
```

Isi file:

```php
<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'nama_database';
const DB_USER = 'user_database';
const DB_PASS = 'password_database_yang_kuat';
const APP_KEY = '64_karakter_hex_acak';
```

`APP_KEY` harus berupa 64 karakter heksadesimal dan berbeda antara lokal dengan
production. Contoh membuatnya dari terminal hosting:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Jika file di luar document root tidak memungkinkan, gunakan `config.local.php`
di folder aplikasi dan atur permission `600` atau `640`. File tersebut sudah
diblokir oleh `.htaccess`, tetapi penyimpanan di luar document root tetap lebih
aman.

## 5. Perbarui database

Untuk database production yang sudah ada:

1. Buka phpMyAdmin.
2. Pilih database aplikasi.
3. Import `DB/2026-07-23.sql` dari komputer lokal.
4. Pastikan proses selesai tanpa error.

Untuk pemasangan baru, akses installer hanya saat konfigurasi belum tersedia.
Begitu konfigurasi terbentuk, `install.php` otomatis memberikan respons 404.
Hapus `install.php` dari hosting setelah instalasi berhasil.

## 6. Permission folder

Folder berikut harus dapat ditulis oleh PHP:

```text
uploads/subscriber
uploads/comment
uploads/answer10
```

Mulai dengan permission folder `755`. Jika hosting mengharuskan group write,
gunakan `775`. Jangan gunakan `777`.

## 7. Perlindungan web server

Pada Apache/LiteSpeed, `.htaccess` project sudah:

- menolak directory listing;
- memblokir `.git`, `DB`, konfigurasi, SQL, key, backup, dan log;
- menolak akses langsung ke seluruh foto peserta.

Pastikan hosting mengizinkan `.htaccess`/`AllowOverride`.

Jika menggunakan Nginx, tambahkan aturan setara:

```nginx
location ^~ /uploads/ { deny all; }
location ~* ^/(DB|\.git|\.agents|\.codex)(/|$) { deny all; }
location ~* /(config\.local\.php|quiz_tiktok\.config\.php|\.env|.*\.(sql|log|bak|key|pem))$ { deny all; }
```

Tambahkan rate limit hosting/WAF terutama untuk:

- `/admin/login.php`
- `/submit.php`
- `/check.php`

## 8. Konfigurasi melalui admin

1. Login menggunakan password admin yang kuat dan unik.
2. Buka **Konfigurasi**.
3. Ubah kuota harian dari nilai pengujian ke nilai production.
4. Atur tanggal mulai dan berakhir kuis dalam WIB.
5. Pilih **Gunakan Jadwal Otomatis**.
6. Jangan menekan reset atau hapus data setelah peserta mulai masuk.

## 9. Pemeriksaan setelah deployment

- Halaman utama dapat dibuka melalui HTTPS.
- `install.php` menghasilkan 404.
- `/uploads/index.html` menghasilkan 403.
- `/config.local.php`, `/DB/`, dan `/.git/` menghasilkan 403/404.
- Dashboard admin tanpa login dialihkan ke login.
- Font Awesome dan SweetAlert tampil.
- Coba satu submit menggunakan data pengujian yang valid.
- Pastikan foto hanya dapat dilihat setelah login admin.
- Periksa token, koreksi, nomor undian, export, grafik, kuota, dan jadwal.
- Hapus data pengujian melalui konfigurasi sebelum kuis resmi dimulai.

