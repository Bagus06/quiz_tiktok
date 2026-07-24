<?php
require dirname(__DIR__).'/config.php';
ob_start(static function (string $html): string {
    $html = preg_replace('/\s+onerror="[^"]*"/', '', $html) ?? $html;
    return str_replace('<script>', '<script nonce="'.cspNonce().'">', $html);
});
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

function normalizeReviewedAnswer(string $answer): string {
    $answer = mb_strtolower(trim($answer));
    return preg_replace('/\s+/u', ' ', $answer) ?? $answer;
}
function aiWritingSuggestion(string $answer): array {
    $text = trim($answer);
    if ($text === '' || $text[0] === '[') return ['level'=>'neutral','label'=>'Tidak dianalisis','score'=>0,'reasons'=>['Jawaban berupa gambar atau tidak memiliki teks yang dapat dianalisis.']];
    $length = mb_strlen($text);
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $wordCount = count($words);
    if ($length < 45 || $wordCount < 8) {
        return ['level'=>'neutral','label'=>'Teks terlalu singkat untuk dianalisis','score'=>0,'reasons'=>['Diperlukan jawaban yang lebih panjang agar pola penulisan dapat dinilai dengan wajar.']];
    }

    $score = 0;
    $reasons = [];
    $independentSignals = 0;
    $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $sentenceCount = count($sentences);

    // Panjang hanya menjadi sinyal pendukung dan tidak cukup untuk menandai AI.
    if ($length >= 160) { $score += 12; $reasons[] = 'Jawaban lebih panjang dari kebanyakan jawaban kuis singkat.'; }
    if ($length >= 350) $score += 6;

    $transitionCount = preg_match_all('/\b(secara umum|dengan demikian|oleh karena itu|dapat disimpulkan|berdasarkan (?:informasi|penjelasan|uraian)|pada dasarnya|selain itu|lebih lanjut|di sisi lain|sebagai kesimpulan)\b/iu', $text);
    if ($transitionCount >= 1) {
        $score += min(26, 14 + (($transitionCount - 1) * 4));
        $independentSignals++;
        $reasons[] = $transitionCount >= 2
            ? 'Menggunakan beberapa frasa transisi formal secara berulang.'
            : 'Menggunakan frasa transisi formal yang umum pada teks generatif.';
    }

    $genericFramingCount = preg_match_all('/\b(penting untuk (?:dicatat|dipahami|diketahui)|perlu (?:dicatat|dipahami|diketahui)|hal ini (?:menunjukkan|mencerminkan|menggambarkan)|secara keseluruhan|jawaban ini dapat|dalam konteks ini)\b/iu', $text);
    if ($genericFramingCount >= 1) {
        $score += min(22, 14 + (($genericFramingCount - 1) * 4));
        $independentSignals++;
        $reasons[] = 'Memuat pola pembuka atau kesimpulan yang bersifat generik.';
    }

    $listItems = preg_match_all('/(?:^|\n)\s*(?:[-*•]|\d+[.)])\s+\S+/u', $text);
    if ($listItems >= 2) {
        $score += 16;
        $independentSignals++;
        $reasons[] = 'Jawaban disusun sebagai daftar dengan struktur yang konsisten.';
    }

    if (preg_match('/\b(?:pertama|kedua|ketiga)\b.*\b(?:kesimpulan|dapat disimpulkan|secara keseluruhan)\b/isu', $text)) {
        $score += 18;
        $independentSignals++;
        $reasons[] = 'Memakai pola uraian bertahap yang ditutup dengan kesimpulan.';
    }

    $connectorCount = preg_match_all('/\b(karena|sehingga|namun|selain itu|sementara itu|oleh sebab itu|dengan demikian|serta)\b/iu', $text);
    if ($sentenceCount >= 3 && $connectorCount >= 3) {
        $averageWords = $wordCount / max(1, $sentenceCount);
        if ($averageWords >= 10 && $averageWords <= 35) {
            $score += 14;
            $independentSignals++;
            $reasons[] = 'Banyak kalimat memiliki susunan dan penghubung yang sangat konsisten.';
        }
    }

    if (preg_match('/\b(sebagai (?:sebuah )?AI|sebagai model bahasa|saya tidak memiliki akses|batas pengetahuan saya|saya tidak dapat memverifikasi)\b/iu', $text)) {
        $score += 80;
        $independentSignals += 2;
        $reasons[] = 'Terdapat ungkapan yang secara langsung merujuk pada respons model AI.';
    }

    // Satu sinyal tetap dapat memicu pemeriksaan, tetapi tidak boleh langsung dianggap kuat.
    if ($independentSignals < 2) $score = min($score, 44);
    $score = min(100, $score);
    if (!$reasons) $reasons[] = 'Tidak ditemukan pola bahasa generatif yang kuat.';
    if ($score >= 55) return ['level'=>'high','label'=>'Indikasi AI cukup kuat','score'=>$score,'reasons'=>$reasons];
    if ($score >= 30) return ['level'=>'medium','label'=>'Perlu pemeriksaan manual','score'=>$score,'reasons'=>$reasons];
    return ['level'=>'low','label'=>'Indikasi AI rendah','score'=>$score,'reasons'=>$reasons];
}

$historyStmt = db()->prepare("SELECT pa.question_id,pa.answer_text FROM participant_answers pa JOIN participants p ON p.id=pa.participant_id WHERE pa.is_correct=1 AND p.status='reviewed' AND pa.participant_id<>? AND pa.answer_text<>''");
$historyStmt->execute([$id]);
$answerHistory = [];
foreach ($historyStmt->fetchAll() as $historyRow) {
    $questionId = (int)$historyRow['question_id'];
    $original = trim((string)$historyRow['answer_text']);
    if ($original === '' || $original[0] === '[') continue;
    $normalized = normalizeReviewedAnswer($original);
    if (!isset($answerHistory[$questionId][$normalized])) $answerHistory[$questionId][$normalized] = ['answer'=>$original,'count'=>0];
    $answerHistory[$questionId][$normalized]['count']++;
}
$answerInsights = [];
foreach ($answers as $answerRow) {
    $questionId = (int)$answerRow['question_id'];
    $historyOptions = array_values($answerHistory[$questionId] ?? []);
    usort($historyOptions, static function (array $a, array $b): int { return $b['count'] <=> $a['count']; });
    $answerInsights[(int)$answerRow['id']] = [
        'ai' => aiWritingSuggestion((string)$answerRow['answer_text']),
        'history' => $historyOptions[0] ?? null,
        'history_total' => array_sum(array_column($historyOptions, 'count')),
    ];
}

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
<a class="secondary-button profile-popup-link" target="tiktokProfilePopup" rel="noopener noreferrer" href="<?=e($p['tiktok_profile_url'])?>"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Buka Profile TikTok</a>
<script nonce="<?=cspNonce()?>">document.querySelector('.profile-popup-link').addEventListener('click',function(event){event.preventDefault();const link=event.currentTarget,width=Math.min(560,screen.availWidth||560),height=Math.min(780,screen.availHeight||780),left=Math.max(0,Math.round(((screen.availWidth||width)-width)/2)),top=Math.max(0,Math.round(((screen.availHeight||height)-height)/2));const popup=window.open(link.href,'tiktokProfilePopup','popup=yes,width='+width+',height='+height+',left='+left+',top='+top+',resizable=yes,scrollbars=yes');if(popup){popup.opener=null;popup.focus()}else window.open(link.href,'_blank','noopener,noreferrer')});</script>
<script nonce="<?=cspNonce()?>">document.addEventListener('DOMContentLoaded',function(){const insights=<?=json_encode(array_values($answerInsights),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;document.querySelectorAll('.review-item').forEach(function(card,index){const data=insights[index];if(!data)return;const aside=document.createElement('aside');aside.className='answer-review-insights';aside.innerHTML='<div class="ai-writing-insight"><div class="insight-heading"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span><small>Analisis Pola Jawaban</small><strong class="ai-label"></strong></span><b class="ai-score"></b></div><ul class="ai-reasons"></ul><p>Indikator ini hanya sugesti pola penulisan, bukan bukti penggunaan AI.</p></div><div class="history-answer-insight"><div class="insight-heading"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span><small>Sugesti dari Histori Koreksi</small><strong class="history-label"></strong></span></div><blockquote class="history-answer" hidden></blockquote><p class="history-description"></p></div>';const aiBox=aside.querySelector('.ai-writing-insight');aiBox.classList.add(data.ai.level);aside.querySelector('.ai-label').textContent=data.ai.label;aside.querySelector('.ai-score').textContent=data.ai.score+'%';const reasons=aside.querySelector('.ai-reasons');data.ai.reasons.forEach(function(reason){const item=document.createElement('li');item.textContent=reason;reasons.appendChild(item)});const historyLabel=aside.querySelector('.history-label'),historyAnswer=aside.querySelector('.history-answer'),historyDescription=aside.querySelector('.history-description');if(data.history){historyLabel.textContent='Jawaban yang paling sering benar';historyAnswer.hidden=false;historyAnswer.textContent=data.history.answer;historyDescription.textContent='Digunakan pada '+data.history.count+' dari '+data.history_total+' jawaban benar sebelumnya.'}else{historyLabel.textContent='Belum ada referensi';historyDescription.textContent='Sugesti akan muncul setelah tersedia histori koreksi jawaban benar untuk soal ini.'}const heading=card.querySelector('h3');if(heading)heading.insertAdjacentElement('afterend',aside);else card.prepend(aside)})});</script>
</div>
</div>
</div><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="participant_id" value="<?=$id?>"><?php foreach($answers as $a):?><div class="review-item"><h3>Nomor <?=(int)$a['question_number']?></h3><?php if((int)$a['question_number']===10):?><div class="answer-image-grid"><?php foreach($answerImages[(int)$a['id']]??[] as $img):?><button type="button" class="image-thumb photo-button" data-src="photo.php?type=answer&amp;image_id=<?=(int)$img['id']?>" data-title="Jawaban Nomor 10 - Gambar <?=(int)$img['sort_order']?>"><img src="photo.php?type=answer&amp;image_id=<?=(int)$img['id']?>" alt="Jawaban nomor 10 - Gambar <?=(int)$img['sort_order']?>" loading="lazy" onerror="this.parentElement.classList.add('image-unavailable');this.remove();"><span class="image-placeholder">Gambar tidak tersedia</span></button><?php endforeach;?></div><?php else:?><div class="answer-box"><?=nl2br(e($a['answer_text']))?></div><?php endif;?><label class="check-label"><input type="checkbox" name="correct[<?=(int)$a['id']?>]" value="1" <?=$a['is_correct']==1?'checked':''?>> Jawaban benar</label><div class="field"><label>Catatan jawaban (opsional)</label><input name="note[<?=(int)$a['id']?>]" maxlength="500" value="<?=e((string)$a['correction_note'])?>"></div></div><?php endforeach;?><div class="field correction-message-field"><label for="correction_message">Pesan koreksi untuk peserta</label><div class="message-template-actions"><span>Template opsional:</span><button type="button" class="template-button" data-template="diskualifikasi">Diskualifikasi</button><button type="button" class="template-button done-template" data-template="done">Done</button><button type="button" class="template-button clear-template" data-template="clear">Kosongkan</button></div><textarea id="correction_message" name="correction_message" rows="5" maxlength="3000" required><?=e((string)$p['correction_message'])?></textarea><small>Template hanya membantu mengisi pesan dan masih dapat diedit sebelum disimpan.</small></div><button>Simpan Hasil Koreksi</button></form></div></div><div id="photoModal" class="modal" hidden><div class="modal-card"><div class="modal-header"><h2 id="modalTitle">Bukti Foto</h2><button type="button" id="closeModal" class="modal-close">×</button></div><div class="modal-body"><img id="modalImage" alt="Bukti peserta"></div></div></div><script>const modal=document.getElementById('photoModal'),img=document.getElementById('modalImage'),title=document.getElementById('modalTitle');document.querySelectorAll('.photo-button').forEach(b=>b.addEventListener('click',()=>{img.src=b.dataset.src;title.textContent=b.dataset.title;modal.hidden=false;document.body.classList.add('modal-open')}));function closeModal(){modal.hidden=true;img.removeAttribute('src');document.body.classList.remove('modal-open')}document.getElementById('closeModal').addEventListener('click',closeModal);modal.addEventListener('click',e=>{if(e.target===modal)closeModal()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)closeModal()});const correctionMessage=document.getElementById('correction_message');const messageTemplates={diskualifikasi:'Diskualifikasi: Peserta dinyatakan diskualifikasi karena data, bukti, atau ketentuan kuis tidak terpenuhi. Silakan membaca kembali ketentuan yang berlaku.',done:'Done: Jawaban telah selesai dikoreksi. Terima kasih sudah mengikuti Kuis TikTok Affan Elektronik. Silakan cek jumlah jawaban benar dan nomor undian Anda.'};document.querySelectorAll('.template-button').forEach(button=>button.addEventListener('click',()=>{const type=button.dataset.template;if(type==='clear'){correctionMessage.value='';}else if(messageTemplates[type]){correctionMessage.value=messageTemplates[type];}correctionMessage.focus();correctionMessage.dispatchEvent(new Event('input',{bubbles:true}));}));</script></body></html>
