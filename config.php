<?php
declare(strict_types=1);

const APP_VERSION = '4.0.0';
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;
const IMAGE_MAX_WIDTH = 1600;
const IMAGE_MAX_HEIGHT = 1600;
const IMAGE_JPEG_QUALITY = 75;
const IMAGE_MAX_PIXELS = 24000000;
const SUBMIT_COOLDOWN_SECONDS = 20;
const MAX_IP_SUBMISSIONS_PER_HOUR = 5;
const ADMIN_SESSION_TIMEOUT = 1800;
const MAX_ADMIN_ATTEMPTS = 5;
const ADMIN_LOCK_MINUTES = 15;

if (!is_file(__DIR__.'/config.local.php')) {
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}
require __DIR__.'/config.local.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    session_name('QUIZTIKTOKSESSID');
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://api.qrserver.com; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

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
function clientIp(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }
function normalizeWhatsapp(string $raw): string {
    $number = preg_replace('/\D+/', '', $raw) ?? '';
    if (strpos($number, '08') === 0) $number = '62'.substr($number, 1);
    elseif (strpos($number, '8') === 0) $number = '62'.$number;
    return $number;
}
function isValidWhatsapp(string $number): bool {
    if (!preg_match('/^628[1-9][0-9]{7,11}$/', $number)) return false;
    $local = substr($number, 2);
    if (preg_match('/^(\d)\1{7,}$/', $local)) return false;
    return !preg_match('/^(0123456789|1234567890|9876543210)/', $local);
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
    if ($last && time() - $last > ADMIN_SESSION_TIMEOUT) {
        $_SESSION = []; session_regenerate_id(true); header('Location: login.php?expired=1'); exit;
    }
    $_SESSION['admin_last_activity'] = time();
}
function quizIsOpen(): bool {
    $s = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key='quiz_open' LIMIT 1");
    $s->execute();
    return $s->fetchColumn() === '1';
}
function setQuizOpen(bool $open): void {
    $s = db()->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('quiz_open',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $s->execute([$open ? '1' : '0']);
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
