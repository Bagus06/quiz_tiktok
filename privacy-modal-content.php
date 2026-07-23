<?php if (!defined('APP_VERSION')) { http_response_code(404); exit; } ?>
<div class="privacy-modal-intro">
    <span class="privacy-version">Versi <?=e(PRIVACY_POLICY_VERSION)?></span>
    <p>Kebijakan ini menjelaskan cara <?=e(ORGANIZER_NAME)?> mengumpulkan, menggunakan, menyimpan, dan melindungi data peserta Kuis TikTok.</p>
</div>
<section>
    <h3>1. Pengendali dan kontak data</h3>
    <p>Pengendali data adalah <strong><?=e(ORGANIZER_NAME)?></strong>. Pertanyaan, keberatan, dan permintaan mengenai data pribadi dapat disampaikan melalui <a href="<?=e(ORGANIZER_CONTACT_URL)?>" target="_blank" rel="noopener noreferrer"><?=e(ORGANIZER_CONTACT_LABEL)?></a>. Identitas pemohon akan diverifikasi sebelum permintaan diproses.</p>
</section>
<section>
    <h3>2. Data yang diproses</h3>
    <ul>
        <li>Nama, WhatsApp, username dan tautan profil TikTok.</li>
        <li>Foto bukti, gambar jawaban, serta jawaban kuis.</li>
        <li>Token, hasil koreksi, status, dan nomor undian.</li>
        <li>Alamat IP, cookie identitas perangkat, waktu pengiriman, serta catatan keamanan.</li>
    </ul>
    <p>Jangan mengunggah kata sandi, kode OTP, dokumen identitas, atau data pihak lain yang tidak diperlukan. Peserta wajib memiliki hak atau izin atas setiap foto yang diunggah.</p>
</section>
<section>
    <h3>3. Tujuan pemrosesan</h3>
    <p>Data digunakan untuk pendaftaran, verifikasi, koreksi jawaban, pencegahan kecurangan, penerbitan nomor undian, penghubungan pemenang, penyerahan hadiah, keamanan sistem, penanganan keluhan, dan kewajiban hukum.</p>
    <p>Data tidak dijual dan persetujuan ini <strong>tidak mencakup pemasaran</strong>. Pemasaran memerlukan persetujuan terpisah dan opsional.</p>
</section>
<section>
    <h3>4. Penerima data</h3>
    <p>Akses dibatasi kepada panitia berwenang. Data yang relevan dapat diberikan secara terbatas kepada penyedia hosting/teknologi, pengiriman hadiah, sponsor yang terlibat langsung dalam penyerahan hadiah, atau instansi pemerintah berdasarkan kewajiban hukum.</p>
</section>
<section>
    <h3>5. Penyimpanan dan penghapusan</h3>
    <p>Data disimpan selama rangkaian kuis dan akan dihapus paling lambat <strong><?=PRIVACY_RETENTION_DAYS?> hari</strong> setelah seluruh rangkaian selesai, kecuali wajib disimpan lebih lama berdasarkan hukum atau untuk penyelesaian sengketa.</p>
</section>
<section>
    <h3>6. Hak peserta</h3>
    <p>Peserta dapat meminta akses, salinan, perbaikan, pembatasan, penarikan persetujuan, atau penghapusan data sejauh tidak bertentangan dengan kewajiban hukum. Penarikan sebelum proses selesai dapat mengakibatkan pendaftaran dibatalkan karena panitia tidak lagi dapat melakukan verifikasi.</p>
</section>
<section>
    <h3>7. Usia dan keamanan</h3>
    <p>Kuis ditujukan bagi peserta berusia sekurang-kurangnya 18 tahun. Panitia menerapkan pembatasan akses, validasi server, perlindungan unggahan, pencatatan persetujuan, dan pengendalian penyalahgunaan.</p>
</section>
<p class="privacy-modal-updated">Terakhir diperbarui: 23 Juli 2026</p>
