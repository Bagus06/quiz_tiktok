<?php
require __DIR__.'/config.php';
$lookupProvided = array_key_exists('lookup', $_GET) || array_key_exists('token', $_GET);
$lookup = trim((string)($_GET['lookup'] ?? $_GET['token'] ?? ''));
if (!$lookupProvided && $lookup === '') $lookup = rememberedParticipantToken() ?? '';
$participant = null;
$raffles = [];
if ($lookup !== '') {
    $tokenCandidate = strtoupper($lookup);
    $whatsappCandidate = normalizeWhatsapp($lookup);
    $tiktokCandidate = mb_strtolower(ltrim($lookup, '@'));
    $st = db()->prepare('SELECT id,name,token,status,correction_message,correct_count,submitted_at,reviewed_at FROM participants WHERE token=? OR whatsapp=? OR tiktok_account=? LIMIT 1');
    $st->execute([$tokenCandidate, $whatsappCandidate, $tiktokCandidate]);
    $participant = $st->fetch();
    if ($participant && $participant['status'] === 'reviewed') {
        $r = db()->prepare('SELECT raffle_number FROM raffle_numbers WHERE participant_id=? ORDER BY id');
        $r->execute([(int)$participant['id']]);
        $raffles = $r->fetchAll();
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bolone Affan | Cek Hasil Peserta</title>
    <link rel="icon" type="image/png" href="assets/bolone-favicon.png">
    <link rel="shortcut icon" href="assets/bolone-favicon.ico">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <div class="card branded-card bolone-page-card">
        <header class="bolone-page-header">
            <img src="assets/sponsor-bolone-affan.png" alt="Bolone Affan" class="bolone-page-logo">
        </header>
        <h1>Cek Hasil Peserta</h1>
        <form method="get">
            <div class="field">
                <label>Token, Nomor WhatsApp, atau Username TikTok</label>
                <input name="lookup" value="<?=e($lookup)?>" maxlength="100" placeholder="Contoh: TKN-..., 0812..., atau @username" required autocomplete="off">
                <small>Masukkan salah satu data yang digunakan saat mendaftar.</small>
            </div>
            <button type="submit">Cek Hasil</button>
        </form>
        <?php if ($lookup !== '' && !$participant): ?>
            <div class="alert">Data peserta tidak ditemukan. Periksa kembali token, nomor WhatsApp, atau username TikTok Anda.</div>
        <?php endif; ?>
        <?php if ($participant): ?>
            <div class="result">
                <h2><?=e($participant['name'])?></h2>
                <p><b>Status:</b> <?=$participant['status']==='pending'?'Menunggu koreksi admin':'Sudah dikoreksi'?></p>
                <?php if ($participant['status'] === 'reviewed'): ?>
                    <p><b>Jawaban benar:</b> <?= (int)$participant['correct_count'] ?> dari 10</p>
                    <p><b>Pesan koreksi:</b><br><?=nl2br(e((string)$participant['correction_message']))?></p>
                    <h3>Nomor Undian</h3>
                    <?php if ($raffles): ?>
                        <div class="raffles">
                            <?php foreach ($raffles as $row): ?><span><?=e($row['raffle_number'])?></span><?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Belum memperoleh nomor undian.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p><a href="index.php">Kembali ke formulir</a></p>
    </div>
</div>
</body>
</html>
