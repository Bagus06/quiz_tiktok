<?php
require __DIR__ . '/config.php';

$token = $_SESSION['success_token'] ?? null;
$name = $_SESSION['success_name'] ?? null;
unset($_SESSION['success_token'], $_SESSION['success_name']);

if (!$token || !$name) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bolone Affan | Hasil Submit</title>
    <link rel="icon" type="image/png" href="assets/bolone-favicon.png">
    <link rel="shortcut icon" href="assets/bolone-favicon.ico">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container success-wrap">
    <div class="card branded-card success-card bolone-page-card">
        <header class="bolone-page-header">
            <img src="assets/sponsor-bolone-affan.png" alt="Bolone Affan" class="bolone-page-logo">
        </header>
        <div class="check">✓</div>
        <h1>Jawaban Berhasil Dikirim</h1>
        <p>Terima kasih, <strong><?= e($name) ?></strong>.</p>

        <div class="token-warning" role="alert">
            <strong>⚠ WAJIB SIMPAN TOKEN INI ⚠</strong>
            <span>Token diperlukan untuk melihat hasil koreksi, pesan admin, dan nomor undian. Simpan dengan menyalin atau screenshot halaman ini.</span>
        </div>

        <p class="token-caption">Token peserta Anda:</p>
        <div class="token" id="token"><?= e($token) ?></div>
        <div class="success-actions">
            <button type="button" id="copyTokenButton">Salin Token</button>
            <button type="button" class="qr-toggle-button" id="toggleQrButton">Tampilkan QR (Opsional)</button>
            <a class="secondary" href="check.php?token=<?= rawurlencode($token) ?>">Cek Status Token</a>
        </div>
        <p class="copy-status" id="copyStatus" aria-live="polite"></p>

        <div class="optional-qr" id="optionalQr" hidden>
            <h2>QR Token</h2>
            <p>QR ini bersifat opsional. Simpan sebagai screenshot bila diperlukan.</p>
            <img id="qrImage" alt="QR untuk mengecek token" width="220" height="220">
            <p class="qr-error" id="qrError" hidden>QR gagal dimuat. Gunakan tombol Cek Status Token atau salin token secara manual.</p>
            <small>QR dibuat saat tombol ditampilkan dan memerlukan koneksi internet.</small>
        </div>

        <p class="success-back"><a href="index.php">Kembali ke Form</a></p>
    </div>
</div>
<script>
(function () {
    const tokenElement = document.getElementById('token');
    const copyButton = document.getElementById('copyTokenButton');
    const copyStatus = document.getElementById('copyStatus');
    const toggleQrButton = document.getElementById('toggleQrButton');
    const optionalQr = document.getElementById('optionalQr');
    const qrImage = document.getElementById('qrImage');
    const qrError = document.getElementById('qrError');
    let tokenSaved = false;

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const success = document.execCommand('copy');
        document.body.removeChild(textarea);
        return success;
    }

    copyButton.addEventListener('click', async function () {
        const token = tokenElement.textContent.trim();
        let success = false;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(token);
                success = true;
            } else {
                success = fallbackCopy(token);
            }
        } catch (error) {
            success = fallbackCopy(token);
        }
        if (success) {
            tokenSaved = true;
            copyStatus.textContent = '✓ Token berhasil disalin. Simpan di tempat yang aman.';
            copyButton.textContent = 'Token Sudah Disalin';
        } else {
            copyStatus.textContent = 'Salin manual token di atas atau ambil screenshot halaman ini.';
        }
    });

    toggleQrButton.addEventListener('click', function () {
        const willShow = optionalQr.hidden;
        optionalQr.hidden = !willShow;
        toggleQrButton.textContent = willShow ? 'Sembunyikan QR' : 'Tampilkan QR (Opsional)';
        if (willShow && !qrImage.src) {
            const token = tokenElement.textContent.trim();
            const checkUrl = new URL('check.php', window.location.href);
            checkUrl.searchParams.set('token', token);
            qrError.hidden = true;
            qrImage.hidden = false;
            qrImage.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&format=png&data=' + encodeURIComponent(checkUrl.toString());
        }
    });

    qrImage.addEventListener('error', function () {
        qrImage.hidden = true;
        qrError.hidden = false;
    });

    qrImage.addEventListener('load', function () {
        qrImage.hidden = false;
        qrError.hidden = true;
    });

    window.addEventListener('beforeunload', function (event) {
        if (tokenSaved) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
</script>
</body>
</html>
