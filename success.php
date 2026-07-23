<?php
require __DIR__ . '/config.php';

$token = $_SESSION['success_token'] ?? null;
$name = $_SESSION['success_name'] ?? null;
unset($_SESSION['success_token'], $_SESSION['success_name']);

if (!$token || !$name) {
    header('Location: index.php');
    exit;
}
rememberParticipantToken((string)$token);
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
        <div class="check"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
        <h1>Jawaban Berhasil Dikirim</h1>
        <p>Terima kasih, <strong><?= e($name) ?></strong>.</p>

        <div class="token-warning" role="alert">
            <strong><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> WAJIB SIMPAN TOKEN INI</strong>
            <span>Token diperlukan untuk melihat hasil koreksi, pesan admin, dan nomor undian. Simpan dengan menyalin atau screenshot halaman ini.</span>
        </div>

        <p class="token-caption">Token peserta Anda:</p>
        <div class="token" id="token"><?= e($token) ?></div>
        <div class="success-actions">
            <button type="button" id="copyTokenButton">Salin Token</button>
            <a class="secondary" href="check.php?token=<?= rawurlencode($token) ?>">Cek Status Token</a>
        </div>
        <p class="copy-status" id="copyStatus" aria-live="polite"></p>

        <p class="success-back"><a href="index.php">Kembali ke Form</a></p>
    </div>
</div>
<script nonce="<?=cspNonce()?>">
(function () {
    const tokenElement = document.getElementById('token');
    const copyButton = document.getElementById('copyTokenButton');
    const copyStatus = document.getElementById('copyStatus');
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
            copyStatus.textContent = 'Token berhasil disalin. Simpan di tempat yang aman.';
            copyButton.textContent = 'Token Sudah Disalin';
        } else {
            copyStatus.textContent = 'Salin manual token di atas atau ambil screenshot halaman ini.';
        }
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
