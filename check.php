<?php
require __DIR__.'/config.php';
$token = strtoupper(trim((string)($_GET['token'] ?? '')));
$participant = null;
$raffles = [];
if ($token !== '') {
    $st = db()->prepare('SELECT name,token,status,correction_message,correct_count,submitted_at,reviewed_at FROM participants WHERE token=? LIMIT 1');
    $st->execute([$token]);
    $participant = $st->fetch();
    if ($participant && $participant['status'] === 'reviewed') {
        $r = db()->prepare('SELECT raffle_number FROM raffle_numbers WHERE participant_id=(SELECT id FROM participants WHERE token=?) ORDER BY id');
        $r->execute([$token]);
        $raffles = $r->fetchAll();
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bolone Affan | Cek Token</title>
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
        <h1>Cek Token</h1>
        <form method="get">
            <div class="field">
                <label>Token peserta</label>
                <input name="token" value="<?=e($token)?>" maxlength="40" required autocomplete="off">
            </div>
            <button type="submit">Cek Hasil</button>
        </form>
        <?php if ($token !== '' && !$participant): ?>
            <div class="alert">Token tidak ditemukan.</div>
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
