<?php
require __DIR__.'/config.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Privasi - Kuis TikTok Affan Elektronik</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="container privacy-page">
    <article class="card">
        <button class="privacy-back-link privacy-close-button" type="button" data-close-privacy><i class="fa-solid fa-xmark" aria-hidden="true"></i> Tutup Kebijakan Privasi</button>
        <header class="privacy-page-header">
            <span class="privacy-version">Versi <?=e(PRIVACY_POLICY_VERSION)?></span>
            <h1>Kebijakan Privasi Peserta</h1>
            <p>Kebijakan ini menjelaskan cara <?=e(ORGANIZER_NAME)?> mengumpulkan, menggunakan, menyimpan, dan melindungi data peserta Kuis TikTok.</p>
        </header>

        <section>
            <h2>1. Pengendali dan kontak data</h2>
            <p>Pengendali data dalam kegiatan ini adalah <strong><?=e(ORGANIZER_NAME)?></strong>. Pertanyaan, keberatan, atau permintaan mengenai data pribadi dapat disampaikan melalui <a href="<?=e(ORGANIZER_CONTACT_URL)?>" target="_blank" rel="noopener noreferrer"><?=e(ORGANIZER_CONTACT_LABEL)?></a>. Panitia akan melakukan verifikasi identitas sebelum memproses permintaan untuk mencegah data diberikan kepada pihak yang salah.</p>
        </section>

        <section>
            <h2>2. Data yang diproses</h2>
            <ul>
                <li>Nama, nomor WhatsApp, username dan tautan profil TikTok.</li>
                <li>Foto profil/bukti komentar, gambar jawaban, dan jawaban kuis.</li>
                <li>Token peserta, hasil koreksi, status, dan nomor undian.</li>
                <li>Alamat IP, cookie identitas perangkat, waktu pengiriman, dan catatan keamanan untuk mencegah pengiriman berulang atau penyalahgunaan.</li>
            </ul>
            <p>Peserta diminta tidak mengunggah kata sandi, kode OTP, dokumen identitas, atau data pihak lain yang tidak diperlukan. Jika foto memuat pihak lain, peserta wajib memiliki hak atau izin untuk mengunggahnya.</p>
        </section>

        <section>
            <h2>3. Tujuan dan dasar pemrosesan</h2>
            <p>Data diproses berdasarkan persetujuan peserta untuk pendaftaran, verifikasi persyaratan, koreksi jawaban, pencegahan kecurangan, penerbitan nomor undian, penghubungan pemenang, penyerahan hadiah, penanganan keluhan, keamanan sistem, dan pemenuhan kewajiban hukum penyelenggara.</p>
            <p>Persetujuan ini <strong>tidak mencakup pemasaran</strong>. Data tidak dijual. Pesan promosi hanya boleh dikirim jika di kemudian hari peserta memberikan persetujuan terpisah dan opsional.</p>
        </section>

        <section>
            <h2>4. Penerima data</h2>
            <p>Akses dibatasi kepada panitia yang berwenang. Jika diperlukan, data yang relevan dapat diberikan secara terbatas kepada penyedia hosting/teknologi, penyedia pengiriman hadiah, sponsor yang terlibat langsung dalam penyerahan hadiah, atau instansi pemerintah berdasarkan kewajiban hukum. Setiap pihak hanya menerima data yang diperlukan untuk tugasnya.</p>
        </section>

        <section>
            <h2>5. Penyimpanan dan penghapusan</h2>
            <p>Data peserta disimpan selama kuis, proses koreksi, penetapan pemenang, penyerahan hadiah, dan penyelesaian keberatan. Setelah seluruh rangkaian selesai, data operasional akan dihapus paling lambat <strong><?=PRIVACY_RETENTION_DAYS?> hari</strong>, kecuali sebagian data wajib disimpan lebih lama berdasarkan peraturan atau diperlukan untuk penyelesaian sengketa. Cadangan sistem akan ikut terhapus sesuai siklus pencadangan penyedia hosting.</p>
        </section>

        <section>
            <h2>6. Hak peserta</h2>
            <p>Peserta dapat meminta informasi dan salinan data, perbaikan data yang keliru, penghentian atau pembatasan pemrosesan, penarikan persetujuan, serta penghapusan data sejauh tidak bertentangan dengan kewajiban hukum. Peserta juga dapat menyampaikan keberatan atau keluhan melalui kontak resmi di atas.</p>
            <p>Penarikan persetujuan sebelum proses kuis selesai dapat mengakibatkan pendaftaran dibatalkan karena panitia tidak lagi dapat memverifikasi keikutsertaan. Penarikan tidak membatalkan pemrosesan yang telah dilakukan secara sah sebelum permintaan diterima.</p>
        </section>

        <section>
            <h2>7. Peserta dan keamanan akun</h2>
            <p>Kuis ini ditujukan bagi peserta berusia sekurang-kurangnya 18 tahun. Peserta di bawah usia tersebut tidak diperkenankan mengirim formulir tanpa mekanisme persetujuan orang tua/wali yang secara khusus disediakan penyelenggara.</p>
            <p>Panitia menerapkan pembatasan akses, validasi server, perlindungan unggahan, pencatatan persetujuan, dan pengendalian penyalahgunaan. Namun, tidak ada sistem elektronik yang sepenuhnya bebas risiko. Peserta harus segera menghubungi penyelenggara jika menduga terjadi penyalahgunaan data.</p>
        </section>

        <footer class="privacy-page-footer">
            <p>Terakhir diperbarui: 23 Juli 2026</p>
            <button class="secondary-button" type="button" data-close-privacy>Tutup dan Kembali ke Formulir</button>
        </footer>
    </article>
</main>
<script nonce="<?=cspNonce()?>">
document.querySelectorAll('[data-close-privacy]').forEach(function (button) {
    button.addEventListener('click', function () {
        window.close();
        if (!window.closed) {
            if (document.referrer && new URL(document.referrer).origin === window.location.origin) {
                history.back();
            } else {
                window.location.href = 'index.php';
            }
        }
    });
});
</script>
</body>
</html>
