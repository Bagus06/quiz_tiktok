<?php
require dirname(__DIR__).'/config.php';
requireAdmin();
if (!empty($_SESSION['must_change_password'])) { header('Location: password.php'); exit; }

$id = (int)($_GET['id'] ?? $_POST['participant_id'] ?? 0);
$pst = db()->prepare('SELECT * FROM participants WHERE id=?');
$pst->execute([$id]);
$p = $pst->fetch();
if (!$p) { http_response_code(404); exit('Peserta tidak ditemukan.'); }

$ast = db()->prepare('SELECT pa.*,q.question_number FROM participant_answers pa JOIN questions q ON q.id=pa.question_id WHERE pa.participant_id=? ORDER BY q.question_number');
$ast->execute([$id]);
$answers = $ast->fetchAll();

$imageStmt = db()->prepare('SELECT pai.id,pai.participant_answer_id,pai.sort_order FROM participant_answer_images pai JOIN participant_answers pa ON pa.id=pai.participant_answer_id WHERE pa.participant_id=? ORDER BY pai.sort_order');
$imageStmt->execute([$id]);
$answerImages = [];
foreach ($imageStmt->fetchAll() as $imgRow) {
    $answerImages[(int)$imgRow['participant_answer_id']][] = $imgRow;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Sesi tidak valid.';
    } else {
        $correct = is_array($_POST['correct'] ?? null) ? $_POST['correct'] : [];
        $notes = is_array($_POST['note'] ?? null) ? $_POST['note'] : [];
        $general = trim((string)($_POST['correction_message'] ?? ''));
        $pdo = db();

        if (mb_strlen($general) > 3000) {
            $message = 'Pesan koreksi terlalu panjang.';
        } else {
            try {
                $pdo->beginTransaction();
                $up = $pdo->prepare('UPDATE participant_answers SET is_correct=?,correction_note=? WHERE id=? AND participant_id=?');
                $count = 0;
                foreach ($answers as $a) {
                    $ok = isset($correct[$a['id']]) ? 1 : 0;
                    if ($ok) $count++;
                    $note = trim((string)($notes[$a['id']] ?? ''));
                    if (mb_strlen($note) > 500) $note = mb_substr($note, 0, 500);
                    $up->execute([$ok, $note, $a['id'], $id]);
                }

                $currentStmt = $pdo->prepare('SELECT id FROM raffle_numbers WHERE participant_id=? ORDER BY id ASC FOR UPDATE');
                $currentStmt->execute([$id]);
                $current = $currentStmt->fetchAll(PDO::FETCH_COLUMN);
                $currentCount = count($current);

                if ($currentCount > $count) {
                    $remove = array_slice($current, $count);
                    $placeholders = implode(',', array_fill(0, count($remove), '?'));
                    $pdo->prepare("DELETE FROM raffle_numbers WHERE id IN ($placeholders)")->execute($remove);
                } elseif ($currentCount < $count) {
                    $ins = $pdo->prepare('INSERT INTO raffle_numbers(participant_id,raffle_number) VALUES(?,?)');
                    for ($i = $currentCount; $i < $count; $i++) {
                        $ins->execute([$id, nextSequentialRaffleNumber($pdo)]);
                    }
                }

                $pdo->prepare("UPDATE participants SET status='reviewed',correction_message=?,correct_count=?,reviewed_at=NOW() WHERE id=?")
                    ->execute([$general, $count, $id]);
                $pdo->commit();
                rotateCsrf();
                header('Location: review.php?id='.$id.'&saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = 'Koreksi gagal disimpan: '.$e->getMessage();
            }
        }
    }
}
if (isset($_GET['saved'])) $message = 'Koreksi berhasil disimpan dan nomor undian telah diperbarui.';
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Koreksi Jawaban - Affan Elektronik</title><link rel="icon" type="image/png" href="../assets/favicon.png"><link rel="shortcut icon" href="../assets/favicon.ico"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="container wide"><div class="card"><div class="admin-brand"><img src="../assets/affan-logo.png" alt="Affan Elektronik"><span>Panel Kuis Affan Elektronik</span></div><p><a href="index.php">← Data peserta</a></p><h1>Koreksi: <?=e($p['name'])?></h1><p>Token: <b><?=e($p['token'])?></b> · WA: <?=e($p['whatsapp'])?> · Akun / Username TikTok: <b><?=e((string)$p['tiktok_account'])?></b></p><?php if($message):?><div class="success-alert"><?=e($message)?></div><?php endif;?><div class="proof-section">
<h2>Bukti Peserta</h2>
<div class="proof-thumbnail-grid">
<div class="proof-photo-card">
<h3>Foto Profile TikTok</h3>
<button type="button" class="proof-thumbnail photo-button" data-src="photo.php?id=<?=$id?>&amp;type=subscriber" data-title="Foto Profile TikTok">
<img src="photo.php?id=<?=$id?>&amp;type=subscriber" alt="Foto Profile TikTok" loading="lazy" onerror="this.parentElement.classList.add('image-unavailable');this.remove();">
<span class="image-placeholder">Gambar tidak tersedia</span>
</button>
</div>
<div class="proof-photo-card">
<h3>Foto Komentar TikTok</h3>
<button type="button" class="proof-thumbnail photo-button" data-src="photo.php?id=<?=$id?>&amp;type=comment" data-title="Foto Komentar TikTok">
<img src="photo.php?id=<?=$id?>&amp;type=comment" alt="Foto Komentar TikTok" loading="lazy" onerror="this.parentElement.classList.add('image-unavailable');this.remove();">
<span class="image-placeholder">Gambar tidak tersedia</span>
</button>
</div>
<div class="proof-profile-link">
<h3>Profile TikTok</h3>
<a class="secondary-button" target="_blank" rel="noopener noreferrer" href="<?=e($p['tiktok_profile_url'])?>">Buka Profile TikTok</a>
</div>
</div>
</div><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="participant_id" value="<?=$id?>"><?php foreach($answers as $a):?><div class="review-item"><h3>Nomor <?=(int)$a['question_number']?></h3><?php if((int)$a['question_number']===10):?><div class="answer-image-grid"><?php foreach($answerImages[(int)$a['id']]??[] as $img):?><button type="button" class="image-thumb photo-button" data-src="photo.php?type=answer&amp;image_id=<?=(int)$img['id']?>" data-title="Jawaban Nomor 10 - Gambar <?=(int)$img['sort_order']?>"><img src="photo.php?type=answer&amp;image_id=<?=(int)$img['id']?>" alt="Jawaban nomor 10 - Gambar <?=(int)$img['sort_order']?>" loading="lazy" onerror="this.parentElement.classList.add('image-unavailable');this.remove();"><span class="image-placeholder">Gambar tidak tersedia</span></button><?php endforeach;?></div><?php else:?><div class="answer-box"><?=nl2br(e($a['answer_text']))?></div><?php endif;?><label class="check-label"><input type="checkbox" name="correct[<?=(int)$a['id']?>]" value="1" <?=$a['is_correct']==1?'checked':''?>> Jawaban benar</label><div class="field"><label>Catatan jawaban (opsional)</label><input name="note[<?=(int)$a['id']?>]" maxlength="500" value="<?=e((string)$a['correction_note'])?>"></div></div><?php endforeach;?><div class="field correction-message-field"><label for="correction_message">Pesan koreksi untuk peserta</label><div class="message-template-actions"><span>Template opsional:</span><button type="button" class="template-button" data-template="diskualifikasi">Diskualifikasi</button><button type="button" class="template-button done-template" data-template="done">Done</button><button type="button" class="template-button clear-template" data-template="clear">Kosongkan</button></div><textarea id="correction_message" name="correction_message" rows="5" maxlength="3000" required><?=e((string)$p['correction_message'])?></textarea><small>Template hanya membantu mengisi pesan dan masih dapat diedit sebelum disimpan.</small></div><button>Simpan Hasil Koreksi</button></form></div></div><div id="photoModal" class="modal" hidden><div class="modal-card"><div class="modal-header"><h2 id="modalTitle">Bukti Foto</h2><button type="button" id="closeModal" class="modal-close">×</button></div><div class="modal-body"><img id="modalImage" alt="Bukti peserta"></div></div></div><script>const modal=document.getElementById('photoModal'),img=document.getElementById('modalImage'),title=document.getElementById('modalTitle');document.querySelectorAll('.photo-button').forEach(b=>b.addEventListener('click',()=>{img.src=b.dataset.src;title.textContent=b.dataset.title;modal.hidden=false;document.body.classList.add('modal-open')}));function closeModal(){modal.hidden=true;img.removeAttribute('src');document.body.classList.remove('modal-open')}document.getElementById('closeModal').addEventListener('click',closeModal);modal.addEventListener('click',e=>{if(e.target===modal)closeModal()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)closeModal()});const correctionMessage=document.getElementById('correction_message');const messageTemplates={diskualifikasi:'Diskualifikasi: Peserta dinyatakan diskualifikasi karena data, bukti, atau ketentuan kuis tidak terpenuhi. Silakan membaca kembali ketentuan yang berlaku.',done:'Done: Jawaban telah selesai dikoreksi. Terima kasih sudah mengikuti Kuis TikTok Affan Elektronik. Silakan cek jumlah jawaban benar dan nomor undian Anda.'};document.querySelectorAll('.template-button').forEach(button=>button.addEventListener('click',()=>{const type=button.dataset.template;if(type==='clear'){correctionMessage.value='';}else if(messageTemplates[type]){correctionMessage.value=messageTemplates[type];}correctionMessage.focus();correctionMessage.dispatchEvent(new Event('input',{bubbles:true}));}));</script></body></html>
