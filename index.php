<?php
require __DIR__ . '/config.php';

$quizOpen = quizIsOpen();
$questions = db()->query('SELECT id, question_number FROM questions WHERE is_active = 1 ORDER BY question_number ASC')->fetchAll();
$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Affan Elektronik - Kuis TikTok</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="shortcut icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <div class="card branded-card">
        <header class="brand-header">
            <div class="primary-brand-row">
                <div class="primary-brand-item main-brand-item">
                    <span class="brand-role">Penyelenggara Utama</span>
                    <img src="assets/affan-logo.png" alt="Affan Elektronik" class="brand-logo main-brand-logo">
                </div>
                <div class="primary-brand-item partner-brand-item">
                    <span class="brand-role">Didukung Oleh</span>
                    <img src="assets/phaselab-logo.png" alt="Phaselab High Quality and Performance" class="partner-logo">
                </div>
            </div>
            <div class="sponsor-strip" aria-label="Sponsor acara">
                <span class="sponsor-title">Sponsor</span>
                <div class="sponsor-logos">
                    <button type="button" class="sponsor-logo-button" data-sponsor-src="assets/sponsor-naura.png" data-sponsor-name="Naura Electronic" aria-label="Lihat logo Naura Electronic">
                        <img src="assets/sponsor-naura.png" alt="Naura Electronic" class="sponsor-logo sponsor-naura">
                    </button>
                    <button type="button" class="sponsor-logo-button" data-sponsor-src="assets/sponsor-bolone-affan.png" data-sponsor-name="Bolone Affan" aria-label="Lihat logo Bolone Affan">
                        <img src="assets/sponsor-bolone-affan.png" alt="Bolone Affan" class="sponsor-logo sponsor-bolone">
                    </button>
                    <button type="button" class="sponsor-logo-button" data-sponsor-src="assets/sponsor-invy.png" data-sponsor-name="INVY — Infinity Visionary" aria-label="Lihat logo INVY">
                        <img src="assets/sponsor-invy.png" alt="INVY Infinity Visionary" class="sponsor-logo sponsor-invy">
                    </button>
                </div>
                <small class="sponsor-hint">Klik logo sponsor untuk melihat ukuran lebih besar.</small>
            </div>
            <h1>Kuis TikTok Affan Elektronik</h1>
            <p class="subtitle">Pertanyaan kuis dibagikan melalui video TikTok Affan Elektronik 2. Tuliskan jawaban sesuai nomor soal, lalu unggah bukti yang diminta.</p>
            <a class="organizer-link" href="https://www.tiktok.com/@affan.balap?is_from_webapp=1&amp;sender_device=pc" target="_blank" rel="noopener noreferrer">Buka TikTok Penyelenggara: @affan.balap</a>
        </header>

        <?php if (!$quizOpen): ?>
            <div class="alert"><strong>Sesi kuis telah ditutup.</strong> Formulir tidak menerima jawaban baru. Peserta tetap dapat memeriksa hasil melalui menu Cek Token.</div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert">
                <strong>Data belum dapat disimpan:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($quizOpen): ?>
        <form action="submit.php" method="post" enctype="multipart/form-data" id="quizForm">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="grid">
                <div class="field">
                    <label for="name">Nama lengkap</label>
                    <input type="text" id="name" name="name" maxlength="100" required value="<?= e($old['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="whatsapp">Nomor WhatsApp</label>
                    <input type="tel" id="whatsapp" name="whatsapp" maxlength="20" placeholder="Contoh: 081958443004" required value="<?= e($old['whatsapp'] ?? '') ?>">
                </div>
            </div>

            <div class="grid">
                <div class="field">
                    <label for="tiktok_account">Akun TikTok / Username TikTok</label>
                    <input type="text" id="tiktok_account" name="tiktok_account" maxlength="50" placeholder="Contoh: affan.balap" required value="<?= e($old['tiktok_account'] ?? '') ?>" autocomplete="off" autocapitalize="none" spellcheck="false">
                    <small>Masukkan username saja. Tanda <b>@</b> boleh ditulis atau tidak.</small>
                    <details class="input-guide">
                        <summary>Cara melihat username TikTok</summary>
                        <ol>
                            <li>Buka aplikasi TikTok dan pilih menu <b>Profil</b>.</li>
                            <li>Lihat tulisan yang diawali tanda <b>@</b> di bawah nama profil.</li>
                            <li>Contoh: <b>@affan.balap</b>. Anda cukup mengisi <b>affan.balap</b>.</li>
                        </ol>
                    </details>
                </div>
                <div class="field">
                    <label for="tiktok_profile_url">Link Profile</label>
                    <input type="text" inputmode="url" id="tiktok_profile_url" name="tiktok_profile_url" maxlength="500" placeholder="https://www.tiktok.com/@username" required value="<?= e($old['tiktok_profile_url'] ?? '') ?>" autocapitalize="none" spellcheck="false">
                    <small>Tempel tautan profil TikTok Anda, bukan tautan video.</small>
                    <details class="input-guide">
                        <summary>Cara menyalin link profil TikTok</summary>
                        <ol>
                            <li>Buka aplikasi TikTok dan masuk ke menu <b>Profil</b>.</li>
                            <li>Tekan tombol <b>Bagikan Profil</b> atau ikon bagikan.</li>
                            <li>Pilih <b>Salin tautan</b>, kemudian tempel pada kolom ini.</li>
                        </ol>
                    </details>
                </div>
            </div>

            <div class="grid">
                <div class="field">
                    <label for="subscriber_photo">Foto Profile TikTok</label>
                    <input type="file" id="subscriber_photo" name="subscriber_photo" accept="image/jpeg,image/png,image/webp" required>
                    <small>Screenshot profile akun TikTok peserta. Maksimal 5 MB. Format JPG, PNG, atau WEBP.</small>
                </div>
                <div class="field">
                    <label for="comment_photo">Foto bukti komentar TikTok</label>
                    <input type="file" id="comment_photo" name="comment_photo" accept="image/jpeg,image/png,image/webp" required>
                    <small>Maksimal 5 MB. Format JPG, PNG, atau WEBP.</small>
                </div>
            </div>

            <hr>
            <h2>Soal Esai</h2>

            <?php foreach ($questions as $index => $question): ?>
                <?php if ((int)$question['question_number'] === 10): ?>
                    <div class="field question">
                        <label>Jawaban Nomor 10 — Upload 3 Gambar</label>
                        <div class="grid three-columns">
                            <?php for ($imageNo = 1; $imageNo <= 3; $imageNo++): ?>
                                <div>
                                    <label for="answer10_image_<?=$imageNo?>">Gambar <?=$imageNo?></label>
                                    <input type="file" id="answer10_image_<?=$imageNo?>" name="answer10_images[]" accept="image/jpeg,image/png,image/webp" required>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <small>Masing-masing maksimal 5 MB. Format JPG, PNG, atau WEBP.</small>
                    </div>
                <?php else: ?>
                    <div class="field question">
                        <label for="answer_<?= (int) $question['id'] ?>">
                            Jawaban Nomor <?= (int) $question['question_number'] ?>
                        </label>
                        <textarea id="answer_<?= (int) $question['id'] ?>" name="answers[<?= (int) $question['id'] ?>]" rows="4" required><?= e($old['answers'][$question['id']] ?? '') ?></textarea>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <button type="submit" id="submitButton">Kirim Jawaban</button>
        </form>
        <?php endif; ?>
        <div class="check-box"><h2>Cek Hasil Koreksi</h2><p>Masukkan token untuk melihat status, pesan admin, dan nomor undian.</p><a class="secondary-button" href="check.php">Cek Token</a></div>
    </div>
</div>

<div class="modal sponsor-modal" id="sponsorModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="sponsorModalTitle">
    <div class="modal-card sponsor-modal-card">
        <div class="modal-header">
            <h2 id="sponsorModalTitle">Sponsor</h2>
            <button type="button" class="modal-close" id="closeSponsorModal" aria-label="Tutup popup">×</button>
        </div>
        <div class="modal-body sponsor-modal-body">
            <img id="sponsorModalImage" src="" alt="Logo sponsor">
        </div>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('quizForm');
    const button = document.getElementById('submitButton');
    if (!form || !button) return;

    let submitting = false;
    const originalText = button.textContent;

    window.addEventListener('pageshow', function () {
        submitting = false;
        button.disabled = false;
        button.textContent = originalText;
    });

    function fileToCompressedJpeg(file) {
        return new Promise(function (resolve) {
            if (!file || !file.type.startsWith('image/')) return resolve(file);
            const reader = new FileReader();
            reader.onerror = function () { resolve(file); };
            reader.onload = function (event) {
                const image = new Image();
                image.onerror = function () { resolve(file); };
                image.onload = function () {
                    const maxSide = 1600;
                    let width = image.naturalWidth;
                    let height = image.naturalHeight;
                    const ratio = Math.min(1, maxSide / width, maxSide / height);
                    width = Math.max(1, Math.round(width * ratio));
                    height = Math.max(1, Math.round(height * ratio));
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const context = canvas.getContext('2d');
                    if (!context) return resolve(file);
                    context.fillStyle = '#fff';
                    context.fillRect(0, 0, width, height);
                    context.drawImage(image, 0, 0, width, height);
                    canvas.toBlob(function (blob) {
                        if (!blob) return resolve(file);
                        const base = file.name.replace(/\.[^.]+$/, '') || 'foto';
                        resolve(new File([blob], base + '.jpg', {type: 'image/jpeg', lastModified: Date.now()}));
                    }, 'image/jpeg', 0.75);
                };
                image.src = String(event.target.result || '');
            };
            reader.readAsDataURL(file);
        });
    }

    async function compressSelectedImages() {
        const inputs = Array.from(form.querySelectorAll('input[type="file"][accept*="image"]'));
        for (const input of inputs) {
            if (!input.files || input.files.length === 0 || typeof DataTransfer === 'undefined') continue;
            const transfer = new DataTransfer();
            for (const file of Array.from(input.files)) {
                transfer.items.add(await fileToCompressedJpeg(file));
            }
            input.files = transfer.files;
        }
    }

    form.addEventListener('submit', async function (event) {
        if (submitting) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        submitting = true;
        button.disabled = true;
        button.textContent = 'Menyiapkan foto...';
        try {
            await compressSelectedImages();
            button.textContent = 'Mengunggah dan menyimpan...';
            setTimeout(function () {
                if (submitting) button.textContent = 'Masih mengunggah, jangan tutup halaman...';
            }, 12000);
            form.submit();
        } catch (error) {
            submitting = false;
            button.disabled = false;
            button.textContent = originalText;
            alert('Foto gagal diproses. Silakan coba kembali atau gunakan gambar dengan ukuran lebih kecil.');
        }
    });
})();
</script>

<script>
(function () {
    const modal = document.getElementById('sponsorModal');
    const modalImage = document.getElementById('sponsorModalImage');
    const modalTitle = document.getElementById('sponsorModalTitle');
    const closeButton = document.getElementById('closeSponsorModal');
    if (!modal || !modalImage || !modalTitle || !closeButton) return;

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modalImage.removeAttribute('src');
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-sponsor-src]').forEach(function (button) {
        button.addEventListener('click', function () {
            modalImage.src = button.getAttribute('data-sponsor-src') || '';
            modalImage.alt = button.getAttribute('data-sponsor-name') || 'Logo sponsor';
            modalTitle.textContent = button.getAttribute('data-sponsor-name') || 'Sponsor';
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            closeButton.focus();
        });
    });

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>
</body>
</html>
