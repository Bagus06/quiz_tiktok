<?php
require dirname(__DIR__).'/config.php';
requireAdmin();
if (!empty($_SESSION['must_change_password'])) { header('Location: password.php'); exit; }

$schemaReady = migrationTableExists('raffle_prizes') && migrationTableExists('raffle_winners');
$message = (string)($_SESSION['drawing_message'] ?? '');
$error = (string)($_SESSION['drawing_error'] ?? '');
unset($_SESSION['drawing_message'], $_SESSION['drawing_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $_SESSION['drawing_error'] = 'Sesi tidak valid. Muat ulang halaman dan coba kembali.';
    } elseif (!$schemaReady) {
        $_SESSION['drawing_error'] = 'Tabel hadiah belum tersedia. Jalankan Migrasi Database Penuh dari halaman Konfigurasi.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save_prize') {
                $id = max(0, (int)($_POST['prize_id'] ?? 0));
                $orderRaw = trim((string)($_POST['prize_order'] ?? ''));
                $order = $orderRaw === ''
                    ? (int)db()->query('SELECT COALESCE(MAX(prize_order),0)+1 FROM raffle_prizes')->fetchColumn()
                    : (int)$orderRaw;
                $name = trim((string)($_POST['prize_name'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                if ($order < 1 || $order > 999) throw new InvalidArgumentException('Nomor urut hadiah harus antara 1 sampai 999.');
                if (mb_strlen($name) < 2 || mb_strlen($name) > 150) throw new InvalidArgumentException('Nama hadiah harus berisi 2 sampai 150 karakter.');
                if (mb_strlen($description) > 500) throw new InvalidArgumentException('Keterangan hadiah maksimal 500 karakter.');

                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE raffle_prizes SET prize_order=?,prize_name=?,description=? WHERE id=?');
                    $stmt->execute([$order, $name, $description !== '' ? $description : null, $id]);
                    if ($stmt->rowCount() === 0) {
                        $exists = db()->prepare('SELECT 1 FROM raffle_prizes WHERE id=?');
                        $exists->execute([$id]);
                        if (!$exists->fetchColumn()) throw new InvalidArgumentException('Data hadiah tidak ditemukan.');
                    }
                    $_SESSION['drawing_message'] = 'Hadiah urutan ke-'.$order.' berhasil diperbarui.';
                } else {
                    $stmt = db()->prepare('INSERT INTO raffle_prizes(prize_order,prize_name,description) VALUES(?,?,?)');
                    $stmt->execute([$order, $name, $description !== '' ? $description : null]);
                    $_SESSION['drawing_message'] = 'Hadiah urutan ke-'.$order.' berhasil ditambahkan.';
                }
            } elseif ($action === 'delete_prize') {
                $id = max(1, (int)($_POST['prize_id'] ?? 0));
                $stmt = db()->prepare('DELETE FROM raffle_prizes WHERE id=?');
                $stmt->execute([$id]);
                $_SESSION['drawing_message'] = $stmt->rowCount() ? 'Hadiah berhasil dihapus.' : 'Hadiah sudah tidak tersedia.';
            } else {
                throw new InvalidArgumentException('Aksi pengundian tidak dikenali.');
            }
            rotateCsrf();
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                $_SESSION['drawing_error'] = $action === 'delete_prize'
                    ? 'Hadiah yang sudah memiliki pemenang tidak dapat dihapus.'
                    : 'Nomor urut hadiah tersebut sudah digunakan. Gunakan nomor urut lain.';
            } else {
                error_log('[quiz_tiktok][drawing] '.$exception->getMessage());
                $_SESSION['drawing_error'] = 'Hadiah gagal disimpan. Periksa struktur database dan log server.';
            }
        } catch (InvalidArgumentException $exception) {
            $_SESSION['drawing_error'] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('[quiz_tiktok][drawing] '.$exception->getMessage());
            $_SESSION['drawing_error'] = 'Operasi hadiah gagal diproses.';
        }
    }
    header('Location: drawing.php');
    exit;
}

$prizes = $schemaReady ? db()->query('SELECT rp.id,rp.prize_order,rp.prize_name,rp.description,rp.created_at,rp.updated_at,rw.id AS winner_id FROM raffle_prizes rp LEFT JOIN raffle_winners rw ON rw.prize_id=rp.id ORDER BY rp.prize_order,rp.id')->fetchAll() : [];
$nextPrizeOrder = $schemaReady ? (int)db()->query('SELECT COALESCE(MAX(prize_order),0)+1 FROM raffle_prizes')->fetchColumn() : 1;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pengundian - Affan Elektronik</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link rel="shortcut icon" href="../assets/favicon.ico">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container wide">
    <div class="card">
        <div class="admin-brand"><img src="../assets/affan-logo.png" alt="Affan Elektronik"><span>Panel Kuis Affan Elektronik</span></div>
        <div class="drawing-page-heading">
            <div>
                <a href="index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali ke dashboard</a>
                <h1>Pengundian</h1>
                <p class="muted">Susun daftar hadiah berdasarkan urutan pelaksanaan undian.</p>
            </div>
            <span class="drawing-total"><i class="fa-solid fa-gift" aria-hidden="true"></i> <?=count($prizes)?> hadiah</span>
        </div>

        <?php if ($message !== ''): ?><div class="success-alert"><?=e($message)?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert"><?=e($error)?></div><?php endif; ?>

        <?php if (!$schemaReady): ?>
            <div class="alert drawing-migration-alert">
                <strong>Database pengundian belum siap.</strong>
                <span>Jalankan migrasi penuh terlebih dahulu. Proses migrasi hanya menambahkan struktur dan tidak menghapus data target.</span>
                <a class="small-button" href="configuration.php">Buka Konfigurasi</a>
            </div>
        <?php else: ?>
            <section class="drawing-prize-form" aria-labelledby="prize-form-title">
                <div>
                    <span class="card-label">KONFIGURASI HADIAH</span>
                    <h2 id="prize-form-title">Masukkan Hadiah Undian</h2>
                    <p class="muted">Nomor urut menentukan hadiah mana yang diundi terlebih dahulu.</p>
                </div>
                <form method="post" id="prizeForm">
                    <input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>">
                    <input type="hidden" name="action" value="save_prize">
                    <input type="hidden" name="prize_id" id="prizeId" value="0">
                    <div class="drawing-form-grid">
                        <div class="field">
                            <label for="prizeOrder">Nomor Urut Hadiah</label>
                            <input type="number" id="prizeOrder" name="prize_order" min="1" max="999" inputmode="numeric" value="<?=$nextPrizeOrder?>" data-next-order="<?=$nextPrizeOrder?>" required>
                            <small>Terisi otomatis dari nomor terakhir dan tetap dapat diganti.</small>
                        </div>
                        <div class="field">
                            <label for="prizeName">Nama Hadiah</label>
                            <input type="text" id="prizeName" name="prize_name" minlength="2" maxlength="150" placeholder="Contoh: Televisi 32 inci" required>
                        </div>
                        <div class="field drawing-description-field">
                            <label for="prizeDescription">Keterangan <span class="muted">(opsional)</span></label>
                            <textarea id="prizeDescription" name="description" rows="3" maxlength="500" placeholder="Contoh: Hadiah utama dari Affan Elektronik"></textarea>
                        </div>
                    </div>
                    <div class="drawing-form-actions">
                        <button type="submit" id="savePrizeButton"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Hadiah</button>
                        <button type="button" class="secondary-button" id="cancelPrizeEdit" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i> Batal Mengubah</button>
                    </div>
                </form>
            </section>

            <section class="drawing-prize-list" aria-labelledby="prize-list-title">
                <div class="section-heading">
                    <div><h2 id="prize-list-title">Daftar Hadiah</h2><p class="muted">Hadiah ditampilkan sesuai urutan pengundian.</p></div>
                </div>
                <?php if (!$prizes): ?>
                    <div class="drawing-empty"><i class="fa-solid fa-gift" aria-hidden="true"></i><strong>Belum ada hadiah</strong><span>Tambahkan hadiah pertama melalui formulir di atas.</span></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Urutan</th><th>Nama Hadiah</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach ($prizes as $prize): ?>
                                <tr>
                                    <td><span class="prize-order-badge"><?= (int)$prize['prize_order'] ?></span></td>
                                    <td><strong><?=e((string)$prize['prize_name'])?></strong></td>
                                    <td class="prize-description"><?=e((string)($prize['description'] ?: '—'))?></td>
                                    <td>
                                        <div class="drawing-row-actions">
                                            <a class="small-button draw-prize-button" href="draw.php?prize_id=<?=(int)$prize['id']?>"><i class="fa-solid <?=$prize['winner_id'] ? 'fa-trophy' : 'fa-shuffle'?>" aria-hidden="true"></i> <?=$prize['winner_id'] ? 'Lihat Hasil' : 'Undi'?></a>
                                            <button type="button" class="small-button edit-prize" data-id="<?=(int)$prize['id']?>" data-order="<?=(int)$prize['prize_order']?>" data-name="<?=e((string)$prize['prize_name'])?>" data-description="<?=e((string)$prize['description'])?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Ubah</button>
                                            <form method="post" class="delete-prize-form">
                                                <input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>">
                                                <input type="hidden" name="action" value="delete_prize">
                                                <input type="hidden" name="prize_id" value="<?=(int)$prize['id']?>">
                                                <button type="submit" class="small-button danger-button"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<script nonce="<?=cspNonce()?>">
(() => {
    const form = document.getElementById('prizeForm');
    if (!form) return;
    const id = document.getElementById('prizeId');
    const order = document.getElementById('prizeOrder');
    const name = document.getElementById('prizeName');
    const description = document.getElementById('prizeDescription');
    const save = document.getElementById('savePrizeButton');
    const cancel = document.getElementById('cancelPrizeEdit');
    const reset = () => {
        form.reset(); id.value = '0'; order.value = order.dataset.nextOrder || '1'; cancel.hidden = true;
        save.innerHTML = '<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Hadiah';
    };
    document.querySelectorAll('.edit-prize').forEach(button => button.addEventListener('click', () => {
        id.value = button.dataset.id || '0';
        order.value = button.dataset.order || '';
        name.value = button.dataset.name || '';
        description.value = button.dataset.description || '';
        cancel.hidden = false;
        save.innerHTML = '<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Perubahan';
        form.scrollIntoView({behavior: 'smooth', block: 'center'});
        name.focus();
    }));
    cancel.addEventListener('click', reset);
    document.querySelectorAll('.delete-prize-form').forEach(deleteForm => deleteForm.addEventListener('submit', event => {
        if (!window.confirm('Hapus hadiah ini dari daftar pengundian?')) event.preventDefault();
    }));
})();
</script>
</body>
</html>
