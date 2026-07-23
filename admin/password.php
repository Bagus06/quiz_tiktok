<?php
require dirname(__DIR__).'/config.php'; requireAdmin();
$msg=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verifyCsrf((string)($_POST['csrf_token']??''))) $error='Sesi tidak valid.';
 else{
  $old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
  $s=db()->prepare('SELECT password_hash FROM admins WHERE id=?');$s->execute([$_SESSION['admin_id']]);$hash=(string)$s->fetchColumn();
  if(!password_verify($old,$hash))$error='Password lama salah.';
  elseif(strlen($new)<12||!preg_match('/[A-Z]/',$new)||!preg_match('/[a-z]/',$new)||!preg_match('/\d/',$new)||!preg_match('/[^A-Za-z0-9]/',$new))$error='Password baru minimal 12 karakter dan harus berisi huruf besar, huruf kecil, angka, serta simbol.';
  elseif($new!==$confirm)$error='Konfirmasi password tidak sama.';
  elseif(password_verify($new,$hash))$error='Password baru tidak boleh sama dengan password lama.';
  else{db()->prepare('UPDATE admins SET password_hash=?,must_change_password=0 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$_SESSION['admin_id']]);$_SESSION['must_change_password']=0;rotateCsrf();$msg='Password berhasil diganti.';}
 }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ganti Password - Affan Elektronik</title><link rel="icon" type="image/png" href="../assets/favicon.png"><link rel="shortcut icon" href="../assets/favicon.ico"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="container"><div class="card"><img src="../assets/affan-logo.png" alt="Affan Elektronik" class="login-logo"><h1>Ganti Password Admin</h1><p>Password awal wajib diganti sebelum mengelola kuis.</p><?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?><?php if($msg):?><div class="success-alert"><?=e($msg)?></div><p><a class="small-button" href="index.php">Masuk Dashboard</a></p><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><div class="field"><label>Password lama</label><input type="password" name="old_password" required></div><div class="field"><label>Password baru</label><input type="password" name="new_password" minlength="12" required></div><div class="field"><label>Ulangi password baru</label><input type="password" name="confirm_password" minlength="12" required></div><button>Simpan Password</button></form><?php endif;?></div></div></body></html>
