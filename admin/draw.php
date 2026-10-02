<?php
require dirname(__DIR__).'/config.php';
requireAdmin();
if (!empty($_SESSION['must_change_password'])) { header('Location: password.php'); exit; }

function drawJson(array $payload, int $status=200): void {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
if (!migrationTableExists('raffle_prizes') || !migrationTableExists('raffle_winners')) {
    if ($_SERVER['REQUEST_METHOD']==='POST') drawJson(['ok'=>false,'message'=>'Database pengundian belum dimigrasikan.'],503);
    header('Location: drawing.php'); exit;
}
$prizeId=max(0,(int)($_GET['prize_id']??$_POST['prize_id']??0));
$stmt=db()->prepare('SELECT id,prize_order,prize_name,description FROM raffle_prizes WHERE id=? LIMIT 1');$stmt->execute([$prizeId]);$prize=$stmt->fetch();
if(!$prize){if($_SERVER['REQUEST_METHOD']==='POST')drawJson(['ok'=>false,'message'=>'Hadiah tidak ditemukan.'],404);http_response_code(404);exit('Hadiah tidak ditemukan.');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verifyCsrf((string)($_POST['csrf_token']??'')))drawJson(['ok'=>false,'message'=>'Sesi admin tidak valid. Muat ulang halaman.'],403);
    if((string)($_POST['action']??'')!=='draw_winner')drawJson(['ok'=>false,'message'=>'Aksi pengundian tidak dikenali.'],400);
    $pdo=db();
    try{
        $pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT id FROM raffle_prizes WHERE id=? FOR UPDATE');$lock->execute([$prizeId]);if(!$lock->fetchColumn())throw new RuntimeException('Hadiah tidak ditemukan.');
        $existing=$pdo->prepare('SELECT rn.raffle_number,p.name,p.whatsapp,p.tiktok_account,rw.drawn_at FROM raffle_winners rw JOIN raffle_numbers rn ON rn.id=rw.raffle_number_id JOIN participants p ON p.id=rw.participant_id WHERE rw.prize_id=? LIMIT 1');$existing->execute([$prizeId]);$winner=$existing->fetch();
        if(!$winner){
            $eligible=(int)$pdo->query('SELECT COUNT(*) FROM raffle_numbers rn LEFT JOIN raffle_winners rw ON rw.raffle_number_id=rn.id WHERE rw.id IS NULL')->fetchColumn();
            if($eligible<1)throw new DomainException('Tidak ada nomor undian yang tersedia.');
            $offset=random_int(0,$eligible-1);
            $winner=$pdo->query("SELECT rn.id raffle_number_id,rn.participant_id,rn.raffle_number,p.name,p.whatsapp,p.tiktok_account FROM raffle_numbers rn JOIN participants p ON p.id=rn.participant_id LEFT JOIN raffle_winners rw ON rw.raffle_number_id=rn.id WHERE rw.id IS NULL ORDER BY rn.id LIMIT 1 OFFSET {$offset}")->fetch();
            if(!$winner)throw new RuntimeException('Kandidat pemenang tidak ditemukan.');
            $insert=$pdo->prepare('INSERT INTO raffle_winners(prize_id,raffle_number_id,participant_id) VALUES(?,?,?)');$insert->execute([$prizeId,(int)$winner['raffle_number_id'],(int)$winner['participant_id']]);$winner['drawn_at']=date('Y-m-d H:i:s');
        }
        $pdo->commit();
        $numeric=preg_replace('/\D+/','',(string)$winner['raffle_number'])?:'0';$digits=substr(str_pad($numeric,4,'0',STR_PAD_LEFT),-4);
        drawJson(['ok'=>true,'winner'=>['digits'=>$digits,'raffle_number'=>(string)$winner['raffle_number'],'name'=>(string)$winner['name'],'whatsapp'=>(string)$winner['whatsapp'],'tiktok_account'=>(string)$winner['tiktok_account'],'drawn_at'=>(string)$winner['drawn_at']]]);
    }catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();drawJson(['ok'=>false,'message'=>$e->getMessage()],409);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[quiz_tiktok][draw] '.$e->getMessage());drawJson(['ok'=>false,'message'=>'Pengundian gagal diproses. Periksa database dan coba kembali.'],500);}
}

$winnerStmt=db()->prepare('SELECT rn.raffle_number,p.name,p.whatsapp,p.tiktok_account,rw.drawn_at FROM raffle_winners rw JOIN raffle_numbers rn ON rn.id=rw.raffle_number_id JOIN participants p ON p.id=rw.participant_id WHERE rw.prize_id=? LIMIT 1');$winnerStmt->execute([$prizeId]);$savedWinner=$winnerStmt->fetch();
$eligibleCount=(int)db()->query('SELECT COUNT(*) FROM raffle_numbers rn LEFT JOIN raffle_winners rw ON rw.raffle_number_id=rn.id WHERE rw.id IS NULL')->fetchColumn();$initialDigits='0000';
if($savedWinner){$savedNumeric=preg_replace('/\D+/','',(string)$savedWinner['raffle_number'])?:'0';$initialDigits=substr(str_pad($savedNumeric,4,'0',STR_PAD_LEFT),-4);}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Undi Hadiah - Affan Elektronik</title><link rel="icon" href="../assets/favicon.png"><link rel="stylesheet" href="../assets/style.css"></head><body>
<canvas class="draw-confetti" id="drawConfetti" aria-hidden="true"></canvas><div class="container draw-container"><div class="card draw-card"><div class="admin-brand"><img src="../assets/affan-logo.png" alt="Affan Elektronik"><span>Panel Kuis Affan Elektronik</span></div><p><a href="drawing.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali ke daftar hadiah</a></p>
<section class="draw-selected-prize"><span class="prize-order-badge"><?=(int)$prize['prize_order']?></span><div><span class="card-label">HADIAH YANG DIUNDI</span><h1><?=e((string)$prize['prize_name'])?></h1><?php if((string)$prize['description']!==''):?><p><?=e((string)$prize['description'])?></p><?php endif;?></div></section>
<section class="draw-machine" aria-labelledby="draw-machine-title"><div class="draw-machine-heading"><span class="card-label">NOMOR PEMENANG</span><h2 id="draw-machine-title"><?=$savedWinner?'Hasil Pengundian':'Siap Diundi'?></h2></div>
<button type="button" class="draw-digit-display" id="drawDigitDisplay" aria-label="Nomor undian. Klik untuk mulai." <?=$savedWinner?'disabled':''?>><?php foreach(str_split($initialDigits) as $index=>$digit):?><span class="draw-digit" data-index="<?=$index?>"><?=e($digit)?></span><?php endforeach;?></button>
<p class="draw-status" id="drawStatus" aria-live="polite"><?=$savedWinner?'Pengundian hadiah ini telah selesai.':'Tekan tombol mulai. Angka akan diacak dari kanan ke kiri.'?></p>
<form id="drawForm" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="draw_winner"><input type="hidden" name="prize_id" value="<?=$prizeId?>"><button type="submit" class="draw-start-button" id="drawStartButton" <?=$savedWinner||$eligibleCount<1?'disabled':''?>><i class="fa-solid fa-play" aria-hidden="true"></i> <?=$savedWinner?'Sudah Diundi':'Mulai Undian'?></button></form><small>Setiap digit berputar sekitar 10 detik dan berhenti satu per satu dari kanan. Pastikan volume perangkat aktif untuk mendengar efek suara.</small></section>
<div class="draw-readiness-card"><i class="fa-solid fa-ticket" aria-hidden="true"></i><div><span>Nomor undian yang belum menang</span><strong><?=number_format($eligibleCount,0,',','.')?></strong></div></div>
<section class="draw-winner-card" id="drawWinnerCard" <?=$savedWinner?'':'hidden'?>><span class="winner-crown"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span><div><span class="card-label">SELAMAT KEPADA PEMENANG</span><h2 id="winnerName"><?=e((string)($savedWinner['name']??''))?></h2><p class="winner-prize-line">Mendapatkan hadiah <strong id="winnerPrize"><?=e((string)$prize['prize_name'])?></strong></p><p>Nomor undian: <strong id="winnerRaffle"><?=e((string)($savedWinner['raffle_number']??''))?></strong></p><p>TikTok: <strong id="winnerTikTok"><?=e((string)($savedWinner['tiktok_account']??''))?></strong></p></div></section>
<?php if(!$savedWinner&&$eligibleCount<1):?><div class="alert">Belum ada nomor undian yang dapat dipilih.</div><?php endif;?></div></div>
<script src="../assets/draw-confetti.js" defer></script>
<script nonce="<?=cspNonce()?>">(()=>{const form=document.getElementById('drawForm'),start=document.getElementById('drawStartButton'),display=document.getElementById('drawDigitDisplay'),digits=Array.from(document.querySelectorAll('.draw-digit')),status=document.getElementById('drawStatus'),winnerCard=document.getElementById('drawWinnerCard');if(!form||!start||!display||!digits.length)return;let running=false;const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));async function animateWinner(winner){const finalDigits=String(winner.digits||'0000').padStart(4,'0').slice(-4).split(''),intervals=digits.map((digit,index)=>setInterval(()=>{if(!digit.classList.contains('locked'))digit.textContent=String(Math.floor(Math.random()*10))},65+index*7));display.classList.add('is-spinning');for(let index=digits.length-1;index>=0;index--){status.textContent='Mengacak digit ke-'+(digits.length-index)+' dari 4...';await wait(10000);clearInterval(intervals[index]);digits[index].textContent=finalDigits[index];digits[index].classList.add('locked');await wait(500)}intervals.forEach(clearInterval);display.classList.remove('is-spinning');status.textContent='Pengundian selesai. Selamat kepada pemenang!';document.getElementById('winnerName').textContent=winner.name;document.getElementById('winnerRaffle').textContent=winner.raffle_number;document.getElementById('winnerTikTok').textContent=winner.tiktok_account;winnerCard.hidden=false;start.innerHTML='<i class="fa-solid fa-circle-check" aria-hidden="true"></i> Sudah Diundi';winnerCard.scrollIntoView({behavior:'smooth',block:'center'})}async function beginDraw(event){if(event)event.preventDefault();if(running||start.disabled)return;if(!window.confirm('Mulai pengundian hadiah ini? Hasil pemenang akan langsung dikunci.'))return;running=true;start.disabled=true;display.disabled=true;status.textContent='Memilih pemenang secara aman di server...';try{const response=await fetch('draw.php?prize_id=<?=$prizeId?>',{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},cache:'no-store'}),data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Pengundian gagal.');await animateWinner(data.winner)}catch(error){status.textContent=error.message||'Pengundian gagal.';start.disabled=false;display.disabled=false;running=false;return}running=false}form.addEventListener('submit',beginDraw);display.addEventListener('click',beginDraw)})();</script></body></html>
