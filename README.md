# Kuis TikTok — PHP 7.4 + MySQL

## Fitur
- Form 10 jawaban esai berdasarkan nomor soal.
- Upload Foto Profile TikTok dan bukti komentar.
- Nama serta nomor WhatsApp unik.
- Token peserta dan halaman cek hasil.
- Koreksi benar/salah per jawaban, catatan, dan pesan admin.
- Satu jawaban benar menghasilkan satu nomor undian unik.
- Export CSV nomor undian beserta nama, WhatsApp, token, jumlah benar, dan waktu koreksi.
- Admin dapat membuka atau menutup sesi kuis.
- Bukti foto tampil dalam popup dan file hanya disajikan melalui endpoint admin terautentikasi.

## Instalasi baru
1. Salin folder `quiz_tiktok` ke `htdocs` atau `www`.
2. Import `database.sql`.
3. Sesuaikan koneksi pada `config.php`.
4. Buka `/quiz_tiktok/`.
5. Admin: `/quiz_tiktok/admin/`.
6. Login awal: `admin` / `Admin123!`, lalu sistem mewajibkan penggantian password minimal 12 karakter.

## Upgrade dari versi sebelumnya
Backup database dan folder upload terlebih dahulu, lalu import `database_update_v2.sql`. Jangan menjalankan `database.sql` pada database berisi data karena file tersebut ditujukan untuk instalasi baru.

## Keamanan yang diterapkan
- PDO native prepared statements.
- CSRF pada form penting.
- Regenerasi ID sesi setelah login dan timeout admin 30 menit.
- Cookie HttpOnly, SameSite Strict, dan Secure saat HTTPS aktif.
- Maksimal 5 login gagal per IP atau username selama 15 menit.
- Respons login gagal diberi jeda acak untuk memperlambat brute force.
- Password di-hash menggunakan `password_hash()` dan password awal wajib diganti.
- Security headers: CSP, anti-frame, nosniff, referrer policy, dan permissions policy.
- Validasi MIME serta isi gambar, nama file acak, dan eksekusi PHP diblokir di folder upload.
- Foto bukti tidak ditautkan langsung dari dashboard; hanya dapat dibaca setelah sesi admin tervalidasi.
- Export CSV dilindungi sesi admin dan mitigasi formula injection spreadsheet.
- Validasi buka/tutup kuis dilakukan pada tampilan dan endpoint submit.

## Rekomendasi produksi
Gunakan HTTPS, password database khusus dengan hak minimum, nonaktifkan display_errors, rutin update PHP/web server, backup berkala, dan bila tersedia tambahkan rate limiting di Cloudflare/Nginx/Apache serta autentikasi dua faktor di depan halaman admin.

## Kompresi foto upload
Foto Profile TikTok dan bukti komentar diproses otomatis sebelum disimpan:
- Format hasil: JPEG
- Dimensi maksimal: 1600 x 1600 piksel
- Kualitas JPEG: 75%
- Batas gambar: 24 megapiksel
- Orientasi foto JPEG diperbaiki otomatis bila ekstensi EXIF tersedia
- File asli tidak disimpan

Server wajib mengaktifkan ekstensi PHP GD. Pada XAMPP, buka `php.ini`, aktifkan `extension=gd`, lalu restart Apache.
Pengaturan dapat diubah pada `config.php` melalui `IMAGE_MAX_WIDTH`, `IMAGE_MAX_HEIGHT`, `IMAGE_JPEG_QUALITY`, dan `IMAGE_MAX_PIXELS`.


## Update versi 4
- Nomor undian sekarang berurutan: UND-000001, UND-000002, dan seterusnya.
- Sequence menggunakan transaction + SELECT FOR UPDATE sehingga aman dari nomor ganda saat koreksi bersamaan.
- Daftar peserta dan nomor undian memakai pencarian serta pagination server-side (15 data per halaman).
- Soal nomor 10 berupa upload tepat 3 gambar dan tampil sebagai thumbnail/popup pada halaman koreksi.
- Untuk instalasi lama, import `database_update_v4.sql`, lalu buat folder `uploads/answer10` dengan permission 755.

## Update V5 — Affan Elektronik
Untuk instalasi lama, import `database_update_v5.sql` satu kali. Update ini menambahkan kolom Akun TikTok dan menomori ulang seluruh nomor undian mulai dari `UND-000001` tanpa duplikasi. Setelah import, upload/replace seluruh file aplikasi dari paket terbaru.


## Perbaikan Android
- Foto dikompres di browser sebelum dikirim jika browser mendukung Canvas dan DataTransfer.
- Tombol menampilkan status proses agar peserta tidak mengirim dua kali.
- Tombol otomatis aktif kembali ketika halaman dipulihkan oleh browser Android.
- Server memberi pesan khusus bila total upload melewati batas `post_max_size`.


## Update V6 — Panduan TikTok
- Label diubah menjadi Akun TikTok / Username TikTok.
- Ditambahkan panduan melihat username dan menyalin Link Profile.
- Username dinormalisasi tanpa tanda @ dan menjadi huruf kecil.
- Link Profile divalidasi sebagai tautan profil TikTok; username yang ditempel pada kolom link otomatis diubah menjadi URL profil.
- Tautan penyelenggara diubah ke @affan.balap.
- Informasi video diubah menjadi Affan Elektronik 2.
Tidak memerlukan perubahan database.

## Update branding sponsor dan template koreksi
- Logo utama: Affan Elektronik.
- Logo pendamping paling atas: Phaselab.
- Sponsor: Naura Electronic dan Bolone Affan.
- Halaman koreksi menyediakan template pesan opsional `Diskualifikasi`, `Done`, dan `Kosongkan`.
- Tidak memerlukan perubahan database.

## Update sponsor dan token
- Sponsor INVY ditambahkan pada halaman form.
- Logo sponsor tampil kecil dan dapat diklik untuk membuka popup ukuran besar.
- Halaman sukses menampilkan peringatan merah berkedip agar peserta menyimpan token.
- Tombol salin token memakai Clipboard API dengan fallback browser lama.
- QR token tersedia secara opsional dan baru dimuat ketika pengguna menekan tombol tampilkan QR.
