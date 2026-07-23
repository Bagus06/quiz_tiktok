<?php
declare(strict_types=1);

const APP_VERSION = '7.0.0';
const APP_TIMEZONE = 'Asia/Jakarta';
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;
const IMAGE_MAX_WIDTH = 1600;
const IMAGE_MAX_HEIGHT = 1600;
const IMAGE_JPEG_QUALITY = 75;
const IMAGE_MAX_PIXELS = 24000000;
const SUBMIT_COOLDOWN_SECONDS = 20;
const MAX_IP_SUBMISSIONS_PER_HOUR = 5;
const MAX_IP_ATTEMPTS_PER_DAY = 15;
const MAX_DEVICE_ATTEMPTS_PER_DAY = 3;
const MIN_FORM_FILL_SECONDS = 12;
const DEFAULT_DAILY_PARTICIPANT_QUOTA = 200;
const PRIVACY_POLICY_VERSION = '2026-07-23';
const PRIVACY_RETENTION_DAYS = 90;
const ORGANIZER_NAME = 'Affan Elektronik';
const ORGANIZER_CONTACT_LABEL = 'TikTok @affan.balap';
const ORGANIZER_CONTACT_URL = 'https://www.tiktok.com/@affan.balap';
const ADMIN_SESSION_TIMEOUT = 1800;
const ADMIN_SESSION_ABSOLUTE_TIMEOUT = 28800;
const ADMIN_SESSION_RENEWAL_SECONDS = 900;
const MAX_ADMIN_ATTEMPTS = 5;
const ADMIN_LOCK_MINUTES = 15;

$externalConfig = dirname(__DIR__).'/quiz_tiktok.config.php';
$localConfig = __DIR__.'/config.local.php';
$configPath = is_file($externalConfig) ? $externalConfig : $localConfig;
if (!is_file($configPath)) {
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}
require $configPath;
date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('expose_php', '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    session_name('QUIZTIKTOKSESSID');
    session_start();
}

header_remove('X-Powered-By');
if (!defined('CSP_NONCE')) define('CSP_NONCE', base64_encode(random_bytes(18)));
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-".CSP_NONCE."'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cspNonce(): string { return e((string)CSP_NONCE); }
function clientIp(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }
function appSecret(): string {
    if (defined('APP_KEY') && preg_match('/^[a-f0-9]{64}$/', (string)APP_KEY)) return (string)APP_KEY;
    return hash('sha256', DB_HOST."\0".DB_NAME."\0".DB_USER."\0".DB_PASS."\0quiz-tiktok-v7");
}
function secureCookie(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }
function setLongCookie(string $name, string $value): void {
    setcookie($name, $value, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => secureCookie(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $value;
}
function clearLongCookie(string $name): void {
    setcookie($name, '', ['expires'=>time()-3600,'path'=>'/','secure'=>secureCookie(),'httponly'=>true,'samesite'=>'Lax']);
    unset($_COOKIE[$name]);
}
function deviceHash(): string {
    $id = strtolower((string)($_COOKIE['quiz_device'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $id)) {
        $id = bin2hex(random_bytes(32));
        setLongCookie('quiz_device', $id);
    }
    return hash_hmac('sha256', $id, appSecret());
}
function signedValue(string $value): string {
    return $value.'.'.hash_hmac('sha256', $value, appSecret());
}
function verifySignedValue(string $signed): ?string {
    $separator = strrpos($signed, '.');
    if ($separator === false) return null;
    $value = substr($signed, 0, $separator);
    $signature = substr($signed, $separator + 1);
    return preg_match('/^[a-f0-9]{64}$/', $signature) && hash_equals(hash_hmac('sha256', $value, appSecret()), $signature) ? $value : null;
}
function rememberParticipantToken(string $token): void { setLongCookie('quiz_participant', signedValue($token)); }
function rememberedParticipantToken(): ?string {
    $token = verifySignedValue((string)($_COOKIE['quiz_participant'] ?? ''));
    return $token !== null && preg_match('/^TKN-[A-F0-9]{16}$/', $token) ? $token : null;
}
function formProof(): string { return signedValue((string)time()); }
function formAge(string $proof): ?int {
    $timestamp = verifySignedValue($proof);
    if ($timestamp === null || !ctype_digit($timestamp)) return null;
    $age = time() - (int)$timestamp;
    return $age >= 0 && $age <= 7200 ? $age : null;
}
function normalizeWhatsapp(string $raw): string {
    $number = preg_replace('/\D+/', '', $raw) ?? '';
    if (strpos($number, '08') === 0) $number = '62'.substr($number, 1);
    elseif (strpos($number, '8') === 0) $number = '62'.$number;
    return $number;
}
function isValidWhatsapp(string $number): bool {
    if (!preg_match('/^628[1-9][0-9]{7,11}$/', $number)) return false;
    $local = substr($number, 2);
    $subscriber = substr($number, 3);
    if (preg_match('/^(\d)\1{6,}$/', $subscriber)) return false;
    if (count(array_unique(str_split($subscriber))) < 3) return false;
    return !preg_match('/^(0123456789|1234567890|9876543210)/', $subscriber);
}
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}
function verifyCsrf(string $token): bool { return isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token); }
function rotateCsrf(): void { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function requireAdmin(): void {
    if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
    $last = (int)($_SESSION['admin_last_activity'] ?? 0);
    $started = (int)($_SESSION['admin_session_started'] ?? 0);
    if (!$started) {
        $started = time();
        $_SESSION['admin_session_started'] = $started;
    }
    if (($last && time() - $last > ADMIN_SESSION_TIMEOUT) || ($started && time() - $started > ADMIN_SESSION_ABSOLUTE_TIMEOUT)) {
        $_SESSION = []; session_regenerate_id(true); header('Location: login.php?expired=1'); exit;
    }
    $renewed = (int)($_SESSION['admin_session_renewed'] ?? 0);
    if (!$renewed || time() - $renewed > ADMIN_SESSION_RENEWAL_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['admin_session_renewed'] = time();
    }
    $_SESSION['admin_last_activity'] = time();
}
function cleanupSecurityLogs(): void {
    $today = date('Y-m-d');
    if (appSetting('security_log_cleanup_date') === $today) return;
    try {
        db()->beginTransaction();
        $stmt = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key='security_log_cleanup_date' FOR UPDATE");
        $stmt->execute();
        if ((string)$stmt->fetchColumn() !== $today) {
            db()->exec("DELETE FROM submission_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            db()->exec("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            setAppSetting('security_log_cleanup_date', $today);
        }
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
    }
}
function quizIsOpen(): bool {
    $settings = quizScheduleSettings();
    if ($settings['mode'] === 'forced_open') return true;
    if ($settings['mode'] === 'forced_closed') return false;
    if ($settings['start_at'] !== '' && $settings['end_at'] !== '') {
        $now = time();
        $start = strtotime($settings['start_at']);
        $end = strtotime($settings['end_at']);
        return $start !== false && $end !== false && $now >= $start && $now <= $end;
    }
    $s = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key='quiz_open' LIMIT 1");
    $s->execute();
    return $s->fetchColumn() === '1';
}
function quizPublicStatus(): string {
    $settings = quizScheduleSettings();
    if ($settings['mode'] === 'forced_open') return 'open';
    if ($settings['mode'] === 'forced_closed') return 'closed_by_admin';

    if ($settings['start_at'] !== '' && $settings['end_at'] !== '') {
        $start = strtotime($settings['start_at']);
        $end = strtotime($settings['end_at']);
        $now = time();
        if ($start !== false && $now < $start) return 'not_started';
        if ($end !== false && $now > $end) return 'ended';
        if ($start !== false && $end !== false) return 'open';
    }

    return quizIsOpen() ? 'open' : 'closed_by_admin';
}
function setQuizOpen(bool $open): void {
    $s = db()->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('quiz_open',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $s->execute([$open ? '1' : '0']);
    setAppSetting('quiz_mode', $open ? 'forced_open' : 'forced_closed');
}
function appSetting(string $key, string $default = ''): string {
    $s = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
    $s->execute([$key]);
    $value = $s->fetchColumn();
    return $value === false ? $default : (string)$value;
}
function setAppSetting(string $key, string $value): void {
    $s = db()->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $s->execute([$key, $value]);
}
function quizScheduleSettings(): array {
    $mode = appSetting('quiz_mode', 'auto');
    if (!in_array($mode, ['auto','forced_open','forced_closed'], true)) $mode = 'auto';
    return ['mode'=>$mode,'start_at'=>appSetting('quiz_start_at'),'end_at'=>appSetting('quiz_end_at')];
}
function setQuizSchedule(string $startAt, string $endAt): void {
    setAppSetting('quiz_start_at', $startAt);
    setAppSetting('quiz_end_at', $endAt);
    setAppSetting('quiz_mode', 'auto');
}
function setQuizAutomatic(): void { setAppSetting('quiz_mode', 'auto'); }
function dailyParticipantQuota(): int {
    $s = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key='daily_participant_quota' LIMIT 1");
    $s->execute();
    $quota = (int)$s->fetchColumn();
    return $quota > 0 ? min($quota, 100000) : DEFAULT_DAILY_PARTICIPANT_QUOTA;
}
function setDailyParticipantQuota(int $quota): void {
    $quota = max(1, min($quota, 100000));
    $s = db()->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('daily_participant_quota',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $s->execute([(string)$quota]);
}


/** Return true only when MySQL rejected a UNIQUE/PRIMARY KEY value. */
function isDuplicateKeyError(Throwable $error): bool {
    if (!$error instanceof PDOException) return false;
    $sqlState = (string)$error->getCode();
    $driverCode = isset($error->errorInfo[1]) ? (int)$error->errorInfo[1] : 0;
    return $sqlState === '23000' && $driverCode === 1062;
}

/** Generate a cryptographically secure participant token candidate. */
function participantTokenCandidate(): string {
    return 'TKN-'.strtoupper(bin2hex(random_bytes(8)));
}

/**
 * Allocate the next sequential raffle number while locking the sequence row.
 * Must be called inside an active database transaction.
 */
function nextSequentialRaffleNumber(PDO $pdo): string {
    $pdo->exec("INSERT IGNORE INTO raffle_sequence(id,next_number) VALUES(1,1)");
    $stmt = $pdo->query("SELECT next_number FROM raffle_sequence WHERE id=1 FOR UPDATE");
    $next = (int)$stmt->fetchColumn();
    if ($next < 1) $next = 1;
    $pdo->prepare("UPDATE raffle_sequence SET next_number=? WHERE id=1")->execute([$next + 1]);
    return 'UND-'.str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

function paginationUrl(array $changes): string {
    $params = array_merge($_GET, $changes);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 1 || $value === '1') unset($params[$key]);
    }
    $query = http_build_query($params);
    return 'index.php'.($query !== '' ? '?'.$query : '');
}
