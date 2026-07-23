KUIS TIKTOK - VERSI SHARED HOSTING
===================================

KEBUTUHAN:
- PHP 7.4 sampai PHP 8.3
- MySQL/MariaDB
- Ekstensi: pdo_mysql, mbstring, fileinfo, gd
- HTTPS sangat disarankan

CARA INSTALASI:
1. Buat database dan user database melalui cPanel.
2. Berikan ALL PRIVILEGES user tersebut ke database.
3. Upload seluruh ISI folder ini ke public_html atau subfolder domain.
4. Pastikan folder utama dan folder uploads memiliki permission 755; file 644.
5. Buka alamat website. Sistem otomatis diarahkan ke install.php.
6. Isi koneksi database dan akun admin, lalu klik Install Sekarang.
7. Setelah berhasil, hapus atau rename install.php.
8. Login admin melalui /admin/login.php.

CATATAN:
- Tidak memakai Composer.
- Tidak membutuhkan import SQL manual.
- Tidak membutuhkan rewrite URL.
- Tidak menyertakan .htaccess agar tidak memicu 403 pada shared hosting tertentu.
- Jika config.local.php gagal dibuat, ubah permission folder sementara agar PHP dapat menulis, lalu kembalikan ke 755 setelah instalasi.
- Foto otomatis dikompres menjadi JPEG maksimal 1600x1600, kualitas 75%.

KEAMANAN:
- Prepared statement PDO
- CSRF token
- Rate limit login admin
- Rate limit submit peserta
- Session timeout dan regenerate ID
- Validasi MIME dan isi gambar
- Password hash Argon2id/bcrypt
- Nama dan nomor WhatsApp unik

Tidak ada aplikasi yang dapat dijamin cocok 100% pada semua penyedia hosting. Paket ini dibuat tanpa dependensi dan tanpa aturan server khusus agar kompatibilitas shared hosting setinggi mungkin.
