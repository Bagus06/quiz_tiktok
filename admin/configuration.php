<?php
require dirname(__DIR__).'/config.php';
ob_start(static function (string $html): string {
    $html = preg_replace('/\s+onsubmit="return confirm\(\'([^\']*)\'\);"/', ' data-confirm="$1"', $html) ?? $html;
    $script = '<script nonce="'.cspNonce().'">document.querySelectorAll("form[data-confirm]").forEach(function(form){form.addEventListener("submit",function(event){if(!window.confirm(form.dataset.confirm||"Lanjutkan?"))event.preventDefault();});});</script>';
    return str_replace('</body>', $script.'</body>', $html);
});
requireAdmin();
if (!empty($_SESSION['must_change_password'])) { header('Location: password.php'); exit; }

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            $pdo = db();
            if ($action === 'set_schedule') {
                $startInput = trim((string)($_POST['quiz_start_at'] ?? ''));
                $endInput = trim((string)($_POST['quiz_end_at'] ?? ''));
                $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startInput);
                $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endInput);
                if (!$start || !$end || $end <= $start) throw new InvalidArgumentException('Periode kuis tidak valid. Waktu berakhir harus setelah waktu mulai.');
                setQuizSchedule($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                rotateCsrf();
                $message = 'Periode kuis berhasil disimpan dan mode jadwal otomatis telah diaktifkan.';
            } elseif ($action === 'set_auto_mode') {
                setQuizAutomatic();
                rotateCsrf();
                $message = 'Mode jadwal otomatis telah diaktifkan.';
            } elseif ($action === 'set_quota') {
                $quota = (int)($_POST['daily_quota'] ?? 0);
                if ($quota < 1 || $quota > 100000) throw new InvalidArgumentException('Kuota harus antara 1 sampai 100000 peserta.');
                setDailyParticipantQuota($quota);
                rotateCsrf();
                $message = 'Kuota peserta harian berhasil diperbarui menjadi '.$quota.' peserta.';
            } elseif ($action === 'upgrade_submission_schema') {
                $addedColumns = upgradeParticipantSubmissionSchema();
                rotateCsrf();
                $message = $addedColumns
                    ? 'Database berhasil diperbarui. Kolom yang ditambahkan: '.implode(', ', $addedColumns).'.'
                    : 'Struktur database pendaftaran sudah menggunakan versi terbaru.';
            } elseif ($action === 'reset_devices') {
                $pdo->beginTransaction();
                $pdo->exec('DELETE FROM participant_identity_locks WHERE identity_type=\'device\'');
                $pdo->exec('DELETE FROM submission_attempts');
                $rows = $pdo->query('SELECT id FROM participants ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
                $up = $pdo->prepare('UPDATE participants SET device_hash=? WHERE id=?');
                foreach ($rows as $id) $up->execute([hash('sha256', random_bytes(32)), (int)$id]);
                $pdo->commit();
                rotateCsrf();
                $message = 'Pembatas perangkat dan riwayat percobaan sudah direset. Setiap perangkat dapat mengirim satu pendaftaran baru.';
            } elseif ($action === 'clear_data' && (string)($_POST['confirm_text'] ?? '') === 'HAPUS SEMUA DATA') {
                $pdo->beginTransaction();
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach (['participant_answer_images','participant_answers','raffle_numbers','participants','participant_identity_locks','submission_attempts'] as $table) {
                    $pdo->exec('TRUNCATE TABLE `'.$table.'`');
                }
                $pdo->exec('UPDATE raffle_sequence SET next_number=1 WHERE id=1');
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                $pdo->commit();
                foreach (['uploads/subscriber','uploads/comment','uploads/answer10'] as $folder) {
                    foreach (glob(dirname(__DIR__).'/'.$folder.'/*.jpg') ?: [] as $file) @unlink($file);
                }
                rotateCsrf();
                $message = 'Seluruh data peserta, jawaban, nomor undian, percobaan, dan foto upload sudah dihapus. Nomor undian kembali mulai dari UND-000001.';
            } elseif ($action === 'clear_data') {
                $error = 'Ketik HAPUS SEMUA DATA untuk mengonfirmasi penghapusan.';
            } else {
                $error = 'Aksi konfigurasi tidak dikenal.';
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $ignored) {} }
            $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Operasi gagal. Periksa migration database V7 dan izin folder upload.';
        }
    }
}
$schedule = quizScheduleSettings();
$scheduleStartValue = $schedule['start_at'] !== '' ? date('Y-m-d\TH:i', strtotime($schedule['start_at'])) : '';
$scheduleEndValue = $schedule['end_at'] !== '' ? date('Y-m-d\TH:i', strtotime($schedule['end_at'])) : '';
$modeLabels = ['auto'=>'Otomatis','forced_open'=>'Paksa dibuka','forced_closed'=>'Paksa ditutup'];
$missingSubmissionColumns = participantSubmissionMissingColumns();
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Konfigurasi Admin - Affan Elektronik</title><link rel="icon" href="../assets/favicon.png"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="container"><div class="card"><div class="admin-brand"><img src="../assets/affan-logo.png" alt="Affan Elektronik"><span>Panel Kuis Affan Elektronik</span></div><p><a href="index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali ke dashboard</a></p><h1>Konfigurasi</h1><div class="schedule-config"><div class="schedule-config-heading"><div><h2>Periode Kuis</h2><p class="muted">Atur jadwal otomatis dalam zona waktu WIB. Mode saat ini: <strong><?=e($modeLabels[$schedule['mode']]??'Otomatis')?></strong>.</p></div><span class="status-badge <?=quizIsOpen()?'open':'closed'?>"><?=quizIsOpen()?'BERJALAN':'TIDAK BERJALAN'?></span></div><form method="post" class="schedule-form"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="set_schedule"><div class="field"><label>Mulai</label><input type="datetime-local" name="quiz_start_at" value="<?=e($scheduleStartValue)?>" required></div><div class="field"><label>Berakhir</label><input type="datetime-local" name="quiz_end_at" value="<?=e($scheduleEndValue)?>" required></div><button type="submit">Simpan & Aktifkan Otomatis</button></form><form method="post" class="auto-mode-form"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="set_auto_mode"><button type="submit" class="secondary-button">Gunakan Jadwal Otomatis</button></form></div><div class="session-box"><div><h2>Struktur database pendaftaran</h2><?php if(!$missingSubmissionColumns):?><p class="muted">Database sudah menggunakan struktur terbaru dan siap menerima persetujuan data peserta.</p><?php else:?><p class="muted">Pembaruan diperlukan. Kolom belum tersedia: <strong><?=e(implode(', ',$missingSubmissionColumns))?></strong>.</p><?php endif;?></div><form class="configuration-action-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="upgrade_submission_schema"><button type="submit" class="<?=$missingSubmissionColumns?'':'secondary-button'?>"><i class="fa-solid fa-database" aria-hidden="true"></i> <?=$missingSubmissionColumns?'Perbarui Database':'Periksa Database'?></button></form></div><div class="session-box"><div><h2>Kuota peserta harian</h2><p class="muted">Batas peserta baru yang dapat tersimpan setiap hari. Default: 200 peserta.</p></div><form class="configuration-action-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="set_quota"><input type="number" name="daily_quota" min="1" max="100000" value="<?=dailyParticipantQuota()?>" required><button type="submit">Simpan Kuota</button></form></div><?php if($message):?><div class="success-alert"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?><div class="session-box"><div><h2>Reset perangkat</h2><p class="muted">Menghapus riwayat percobaan dan membebaskan kembali pembatas perangkat. Data peserta yang sudah tersimpan tetap ada.</p></div><form class="configuration-action-form" method="post" onsubmit="return confirm('Reset pembatas semua perangkat dan riwayat percobaan?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="reset_devices"><button type="submit">Reset Perangkat</button></form></div><div class="session-box"><div><h2>Hapus seluruh data percobaan</h2><p class="muted">Menghapus peserta, jawaban, nomor undian, riwayat percobaan, identity lock, serta semua foto upload. Nomor undian kembali ke UND-000001.</p></div><form class="configuration-action-form" method="post" onsubmit="return confirm('Tindakan ini tidak dapat dibatalkan. Lanjutkan?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="clear_data"><input name="confirm_text" placeholder="Ketik HAPUS SEMUA DATA" autocomplete="off" required><button type="submit" class="danger-button">Hapus Semua Data</button></form></div></div></div></body></html>
