<?php
require __DIR__.'/config.php';
$lookupProvided = array_key_exists('lookup', $_GET) || array_key_exists('token', $_GET);
$lookup = trim((string)($_GET['lookup'] ?? $_GET['token'] ?? ''));
if (!$lookupProvided && $lookup === '') $lookup = rememberedParticipantToken() ?? '';
$participant = null;
$raffles = [];
$drawingWinners = [];
if (migrationTableExists('raffle_winners') && migrationTableExists('raffle_prizes')) {
    $drawingWinners = db()->query(
        'SELECT rp.prize_order,rp.prize_name,rn.raffle_number,p.tiktok_account,rw.drawn_at
         FROM raffle_winners rw
         JOIN raffle_prizes rp ON rp.id=rw.prize_id
         JOIN raffle_numbers rn ON rn.id=rw.raffle_number_id
         JOIN participants p ON p.id=rw.participant_id
         ORDER BY rp.prize_order,rw.id'
    )->fetchAll();
}
if ($lookup !== '') {
    $tokenCandidate = strtoupper($lookup);
    $whatsappCandidate = normalizeWhatsapp($lookup);
    $tiktokCandidate = mb_strtolower(ltrim($lookup, '@'));
    $st = db()->prepare('SELECT id,name,token,status,correction_message,correct_count,submitted_at,reviewed_at FROM participants WHERE token=? OR whatsapp=? OR tiktok_account=? LIMIT 1');
    $st->execute([$tokenCandidate, $whatsappCandidate, $tiktokCandidate]);
    $participant = $st->fetch();
    if ($participant && $participant['status'] === 'reviewed') {
        $winnerJoin = migrationTableExists('raffle_winners') && migrationTableExists('raffle_prizes');
        $r = db()->prepare($winnerJoin
            ? 'SELECT rn.raffle_number,rw.id AS winner_id,rp.prize_name FROM raffle_numbers rn LEFT JOIN raffle_winners rw ON rw.raffle_number_id=rn.id LEFT JOIN raffle_prizes rp ON rp.id=rw.prize_id WHERE rn.participant_id=? ORDER BY rn.id'
            : 'SELECT raffle_number,NULL AS winner_id,NULL AS prize_name FROM raffle_numbers WHERE participant_id=? ORDER BY id');
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
                            <?php foreach ($raffles as $row): ?><span class="<?=$row['winner_id']?'raffle-winning':''?>"><?php if($row['winner_id']):?><i class="fa-solid fa-trophy" aria-hidden="true"></i><?php endif;?><?=e($row['raffle_number'])?><?php if($row['winner_id']):?><small>Menang: <?=e((string)$row['prize_name'])?></small><?php endif;?></span><?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Belum memperoleh nomor undian.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($drawingWinners): ?>
            <section class="public-winner-section" aria-labelledby="public-winner-title">
                <div class="public-winner-heading">
                    <span class="public-winner-icon"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span>
                    <div>
                        <span class="card-label">HASIL PENGUNDIAN</span>
                        <h2 id="public-winner-title">Daftar Pemenang Undian</h2>
                        <p>Selamat kepada peserta yang telah mendapatkan hadiah.</p>
                    </div>
                </div>
                <div class="public-winner-list">
                    <?php foreach ($drawingWinners as $winner): ?>
                        <article class="public-winner-item">
                            <span class="public-winner-order"><?= (int)$winner['prize_order'] ?></span>
                            <div class="public-winner-detail">
                                <strong><?=e((string)$winner['prize_name'])?></strong>
                                <span class="public-winning-ticket"><i class="fa-solid fa-ticket" aria-hidden="true"></i> <?=e((string)$winner['raffle_number'])?></span>
                                <span><i class="fa-brands fa-tiktok" aria-hidden="true"></i> @<?=e(ltrim((string)$winner['tiktok_account'], '@'))?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <p><a href="index.php">Kembali ke formulir</a></p>
    </div>
</div>
</body>
</html>
