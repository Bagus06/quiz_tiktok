<?php
require __DIR__ . '/config.php';

$deviceHash = deviceHash();
$rememberedToken = rememberedParticipantToken();
if ($rememberedToken !== null) {
    $remembered = db()->prepare('SELECT 1 FROM participants WHERE token=? LIMIT 1');
    $remembered->execute([$rememberedToken]);
    if ($remembered->fetchColumn()) {
        header('Location: check.php?token='.rawurlencode($rememberedToken));
        exit;
    }
    clearLongCookie('quiz_participant');
}

$quizStatus = quizPublicStatus();
$quizOpen = $quizStatus === 'open';
$dailyQuota = 0;
$dailyQuotaRemaining = 0;
if ($quizOpen) {
    $dailyQuota = dailyParticipantQuota();
    $dailyParticipantCount = (int)db()->query("SELECT COUNT(*) FROM participants WHERE submitted_at >= CURDATE() AND submitted_at < CURDATE() + INTERVAL 1 DAY")->fetchColumn();
    $dailyQuotaRemaining = max(0, $dailyQuota - $dailyParticipantCount);
}
$quizSchedule = quizScheduleSettings();
$quizPeriodStart = $quizSchedule['start_at'] !== '' ? date('d/m/Y H:i', strtotime($quizSchedule['start_at'])) : '';
$quizPeriodEnd = $quizSchedule['end_at'] !== '' ? date('d/m/Y H:i', strtotime($quizSchedule['end_at'])) : '';
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
    <script src="assets/sweetalert2.all.min.js" defer></script>
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

        <?php if ($quizStatus === 'not_started'): ?>
            <div class="alert"><strong>Kuis belum dimulai.</strong> Formulir akan dibuka secara otomatis sesuai jadwal yang tertera. Silakan kembali setelah periode kuis dimulai.</div>
        <?php elseif ($quizStatus === 'ended'): ?>
            <div class="alert"><strong>Periode kuis telah berakhir.</strong> Formulir sudah tidak menerima jawaban baru. Peserta tetap dapat memeriksa hasil melalui menu Cek Token.</div>
        <?php elseif ($quizStatus === 'closed_by_admin'): ?>
            <div class="alert"><strong>Sesi kuis sedang ditutup oleh administrator.</strong> Formulir untuk sementara tidak menerima jawaban baru. Peserta tetap dapat memeriksa hasil melalui menu Cek Token.</div>
        <?php endif; ?>

        <?php if ($quizOpen): ?>
        <div class="daily-quota-card <?=$dailyQuotaRemaining > 0 ? 'available' : 'full'?>" aria-live="polite">
            <div class="daily-quota-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
            <div class="daily-quota-info">
                <strong>Kuota Peserta Hari Ini</strong>
                <span><?=$dailyQuotaRemaining > 0 ? 'Masih tersedia '.number_format($dailyQuotaRemaining, 0, ',', '.').' dari '.number_format($dailyQuota, 0, ',', '.').' kuota.' : 'Kuota peserta hari ini telah habis.'?></span>
            </div>
            <div class="daily-quota-number"><?=number_format($dailyQuotaRemaining, 0, ',', '.')?></div>
        </div>
        <?php endif; ?>

        <?php if ($quizPeriodStart !== '' && $quizPeriodEnd !== ''): ?>
        <div class="quiz-period-card">
            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
            <div><strong>Periode Kuis</strong><span><?=$quizPeriodStart?> WIB — <?=$quizPeriodEnd?> WIB</span></div>
        </div>
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
        <div class="participation-summary">
            <div><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><span><strong>Satu perangkat</strong><small>Hanya satu kali pengiriman</small></span></div>
            <div><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span><strong>WhatsApp aktif</strong><small>Digunakan untuk konfirmasi</small></span></div>
            <div><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong>Data terlindungi</strong><small>Periksa sebelum mengirim</small></span></div>
        </div>
        <form action="submit.php" method="post" enctype="multipart/form-data" id="quizForm">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="form_proof" value="<?= e(formProof()) ?>">

            <section class="form-section participant-section">
                <div class="form-section-heading">
                    <span class="section-number">1</span>
                    <div><h2>Identitas Peserta</h2><p>Lengkapi identitas yang valid agar panitia dapat menghubungi Anda.</p></div>
                </div>
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
                    <small>Masukkan username yang tampil setelah tanda <b>@</b>. Kolom link akan terisi otomatis.</small>
                    <details class="input-guide">
                        <summary>Cara melihat username TikTok</summary>
                        <ol>
                            <li>Buka aplikasi TikTok, lalu ketuk <b>Profil</b> di bagian bawah.</li>
                            <li>Lihat nama pengguna yang diawali tanda <b>@</b> pada halaman profil.</li>
                            <li>Contoh <b>@affan.balap</b>; isi username tanpa tanda <b>@</b>.</li>
                            <li>Jangan gunakan Nama/Nickname karena berbeda dengan Username.</li>
                        </ol>
                    </details>
                </div>
                <div class="field">
                    <label for="tiktok_profile_url">Link Profile</label>
                    <input type="text" inputmode="url" id="tiktok_profile_url" name="tiktok_profile_url" maxlength="500" placeholder="https://www.tiktok.com/@username" required value="<?= e($old['tiktok_profile_url'] ?? '') ?>" autocapitalize="none" spellcheck="false">
                    <small>Tempel link profil TikTok. Jika username sudah diisi, link dibuat otomatis.</small>
                    <details class="input-guide">
                        <summary>Cara menyalin link profil TikTok</summary>
                        <ol>
                            <li>Buka aplikasi TikTok, lalu ketuk <b>Profil</b> di bagian bawah.</li>
                            <li>Di halaman profil, ketuk tombol <b>Bagikan profil</b>.</li>
                            <li>Pilih <b>Salin tautan</b>, lalu kembali ke formulir dan tempel link tersebut.</li>
                            <li>Link yang benar berbentuk <b>tiktok.com/@username</b>, bukan link video.</li>
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
            </section>

            <section class="form-section answer-section">
                <div class="form-section-heading">
                    <span class="section-number">2</span>
                    <div><h2>Jawaban Kuis</h2><p>Jawab seluruh pertanyaan dengan teliti berdasarkan video kuis.</p></div>
                </div>

            <?php foreach ($questions as $index => $question): ?>
                <?php if ((int)$question['question_number'] === 10): ?>
                    <div class="field question">
                        <label>Jawaban Nomor 10 — Upload hingga 3 Gambar (opsional)</label>
                        <div class="grid three-columns">
                            <?php for ($imageNo = 1; $imageNo <= 3; $imageNo++): ?>
                                <div>
                                    <label for="answer10_image_<?=$imageNo?>">Gambar <?=$imageNo?></label>
                                    <input type="file" id="answer10_image_<?=$imageNo?>" name="answer10_images[]" accept="image/jpeg,image/png,image/webp">
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
            </section>

            <div class="submit-panel">
                <div><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p><strong>Pastikan seluruh data sudah benar.</strong><span>Jawaban yang telah dikirim tidak dapat diubah kembali.</span></p></div>
                <button type="submit" id="submitButton">Kirim Jawaban</button>
            </div>
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
<script nonce="<?=cspNonce()?>">
(function () {
    const form = document.getElementById('quizForm');
    const button = document.getElementById('submitButton');
    if (!form || !button) return;

    let submitting = false;
    const originalText = button.textContent;
    const accountInput = document.getElementById('tiktok_account');
    const profileInput = document.getElementById('tiktok_profile_url');
    let syncingTikTok = false;

    function usernameFromProfile(value) {
        try {
            const url = new URL(value.trim());
            if (!['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'].includes(url.hostname.toLowerCase())) return '';
            const match = url.pathname.match(/^\/@([a-z0-9._]{2,50})\/?$/i);
            return match ? match[1].toLowerCase() : '';
        } catch (error) { return ''; }
    }
    function normalizeUsername(value) {
        return value.trim().replace(/^@+/, '').toLowerCase().replace(/[^a-z0-9._]/g, '').slice(0, 50);
    }
    if (accountInput && profileInput) {
        accountInput.addEventListener('input', function () {
            if (syncingTikTok) return;
            const username = normalizeUsername(accountInput.value);
            if (accountInput.value !== username) accountInput.value = username;
            if (username) profileInput.value = 'https://www.tiktok.com/@' + username;
        });
        profileInput.addEventListener('input', function () {
            if (syncingTikTok) return;
            const username = usernameFromProfile(profileInput.value);
            if (username) {
                syncingTikTok = true;
                accountInput.value = username;
                profileInput.value = 'https://www.tiktok.com/@' + username;
                syncingTikTok = false;
            }
        });
        profileInput.addEventListener('paste', function () {
            setTimeout(function () { profileInput.dispatchEvent(new Event('input', {bubbles: true})); }, 0);
        });
        if (!accountInput.value && profileInput.value) profileInput.dispatchEvent(new Event('input', {bubbles: true}));
    }

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
        try {
            const confirmation = await Swal.fire({
                icon: 'warning',
                title: 'Pastikan data sudah benar',
                html: '<div class="submit-notice"><p><strong>1 perangkat hanya dapat melakukan 1 kali submit.</strong></p><p>Mohon kirimkan nomor WhatsApp yang aktif digunakan, karena konfirmasi pemenang akan dilakukan melalui WhatsApp.</p><p>Data yang sudah dikirim tidak dapat diubah.</p></div>',
                showCancelButton: true,
                confirmButtonText: 'Ya, Kirim Sekarang',
                cancelButtonText: 'Periksa Kembali',
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'affan-swal-popup',
                    title: 'affan-swal-title',
                    htmlContainer: 'affan-swal-content',
                    confirmButton: 'affan-swal-confirm',
                    cancelButton: 'affan-swal-cancel'
                },
                buttonsStyling: false,
                allowOutsideClick: false
            });
            if (!confirmation.isConfirmed) {
                submitting = false;
                button.disabled = false;
                return;
            }
            button.textContent = 'Menyiapkan foto...';
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

<script nonce="<?=cspNonce()?>">
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
