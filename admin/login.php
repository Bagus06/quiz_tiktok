<?php
require dirname(__DIR__).'/config.php';
if (!empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
$error = '';
$ip = clientIp();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mb_strtolower(trim((string)($_POST['username'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!verifyCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $minutes = (int)ADMIN_LOCK_MINUTES;
        $limit = db()->prepare("SELECT COUNT(*) FROM admin_login_attempts WHERE was_successful=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL {$minutes} MINUTE) AND (ip_address=? OR username=?)");
        $limit->execute([$ip, $username]);
        if ((int)$limit->fetchColumn() >= MAX_ADMIN_ATTEMPTS) {
            usleep(random_int(500000, 900000));
            $error = 'Terlalu banyak percobaan. Coba lagi setelah '.ADMIN_LOCK_MINUTES.' menit.';
        } else {
            $s = db()->prepare('SELECT id,username,password_hash,must_change_password FROM admins WHERE username=? LIMIT 1');
            $s->execute([$username]);
            $admin = $s->fetch();
            $valid = $admin && password_verify($password, $admin['password_hash']);
            db()->prepare('INSERT INTO admin_login_attempts(username,ip_address,was_successful) VALUES(?,?,?)')->execute([$username ?: '-', $ip, $valid ? 1 : 0]);
            if ($valid) {
                session_regenerate_id(true); rotateCsrf();
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = (string)$admin['username'];
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['must_change_password'] = (int)$admin['must_change_password'];
                header('Location: '.((int)$admin['must_change_password'] === 1 ? 'password.php' : 'index.php')); exit;
            }
            usleep(random_int(500000, 900000));
            $error = 'Username atau password salah.';
        }
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login Admin - Affan Elektronik</title><link rel="icon" type="image/png" href="../assets/favicon.png"><link rel="shortcut icon" href="../assets/favicon.ico"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="container"><div class="card"><img src="../assets/affan-logo.png" alt="Affan Elektronik" class="login-logo"><h1>Login Admin</h1><?php if(isset($_GET['expired'])):?><div class="alert">Sesi berakhir. Silakan login kembali.</div><?php endif;?><?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?><form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><div class="field"><label>Username</label><input name="username" maxlength="50" required autocomplete="username"></div><div class="field"><label>Password</label><input type="password" name="password" minlength="8" maxlength="200" required autocomplete="current-password"></div><button type="submit">Login</button></form></div></div></body></html>
