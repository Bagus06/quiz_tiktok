<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','0');

$externalConfig = dirname(__DIR__).'/quiz_tiktok.config.php';
$installed = is_file($externalConfig) || is_file(__DIR__.'/config.local.php');
if ($installed && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit('Halaman tidak ditemukan.');
}
$errors=[];$success=false;
if ($_SERVER['REQUEST_METHOD']==='POST' && !$installed) {
    $host=trim((string)($_POST['db_host']??'localhost'));
    $port=(int)($_POST['db_port']??3306);
    $name=trim((string)($_POST['db_name']??''));
    $user=trim((string)($_POST['db_user']??''));
    $pass=(string)($_POST['db_pass']??'');
    $adminUser=strtolower(trim((string)($_POST['admin_user']??'admin')));
    $adminPass=(string)($_POST['admin_pass']??'');
    if($host===''||$name===''||$user==='')$errors[]='Data database wajib diisi.';
    if(!preg_match('/^[A-Za-z0-9_\-\.]+$/',$host))$errors[]='Host database tidak valid.';
    if($port<1||$port>65535)$errors[]='Port database tidak valid.';
    if(!preg_match('/^[A-Za-z0-9_]+$/',$name))$errors[]='Nama database hanya boleh huruf, angka, dan underscore.';
    if(!preg_match('/^[a-z0-9_.-]{3,50}$/',$adminUser))$errors[]='Username admin tidak valid.';
    if(strlen($adminPass)<12||!preg_match('/[A-Z]/',$adminPass)||!preg_match('/[a-z]/',$adminPass)||!preg_match('/\d/',$adminPass)||!preg_match('/[^A-Za-z0-9]/',$adminPass))$errors[]='Password admin minimal 12 karakter dan wajib memiliki huruf besar, kecil, angka, serta simbol.';
    if(!extension_loaded('pdo_mysql'))$errors[]='Ekstensi PDO MySQL belum aktif.';
    if(!extension_loaded('mbstring'))$errors[]='Ekstensi mbstring belum aktif.';
    if(!extension_loaded('fileinfo'))$errors[]='Ekstensi fileinfo belum aktif.';
    if(!extension_loaded('gd'))$errors[]='Ekstensi GD belum aktif.';
    if(!$errors){
      try{
        $pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $schema=<<<'SQL'
CREATE TABLE IF NOT EXISTS questions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 question_number TINYINT UNSIGNED NOT NULL UNIQUE,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS participants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 name_normalized VARCHAR(100) NOT NULL,
 whatsapp VARCHAR(20) NOT NULL,
 tiktok_account VARCHAR(100) NOT NULL,
 tiktok_profile_url VARCHAR(500) NOT NULL,
 subscriber_photo VARCHAR(255) NOT NULL,
 comment_photo VARCHAR(255) NOT NULL,
 token VARCHAR(32) NOT NULL,
 submit_ip VARCHAR(45) NULL,
 device_hash CHAR(64) NOT NULL,
 subscriber_image_hash CHAR(64) NOT NULL,
 comment_image_hash CHAR(64) NOT NULL,
 risk_status ENUM('clear','flagged') NOT NULL DEFAULT 'clear',
 risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 risk_reasons VARCHAR(1000) NULL,
 status ENUM('pending','reviewed') NOT NULL DEFAULT 'pending',
 correction_message TEXT NULL,
 correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 submitted_at DATETIME NOT NULL,
 reviewed_at DATETIME NULL,
 UNIQUE KEY uq_name_normalized(name_normalized),
 UNIQUE KEY uq_whatsapp(whatsapp),
 UNIQUE KEY uq_tiktok_account(tiktok_account),
 UNIQUE KEY uq_device_hash(device_hash),
 UNIQUE KEY uq_token(token),
 KEY idx_status(status),
 KEY idx_subscriber_hash(subscriber_image_hash),
 KEY idx_comment_hash(comment_image_hash),
 KEY idx_risk_status(risk_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS participant_answers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 participant_id BIGINT UNSIGNED NOT NULL,
 question_id INT UNSIGNED NOT NULL,
 answer_text TEXT NOT NULL,
 is_correct TINYINT(1) NULL,
 correction_note VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_participant_question(participant_id,question_id),
 CONSTRAINT fk_answer_participant FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE,
 CONSTRAINT fk_answer_question FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS participant_identity_locks (
 identity_type ENUM('whatsapp','tiktok','device') NOT NULL,
 identity_value VARCHAR(191) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(identity_type,identity_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS participant_answer_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 participant_answer_id BIGINT UNSIGNED NOT NULL,
 image_path VARCHAR(255) NOT NULL,
 image_hash CHAR(64) NOT NULL,
 sort_order TINYINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_answer_image_order(participant_answer_id,sort_order),
 KEY idx_answer_image_answer(participant_answer_id),
 KEY idx_answer_image_hash(image_hash),
 CONSTRAINT fk_answer_image_answer FOREIGN KEY(participant_answer_id) REFERENCES participant_answers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS raffle_sequence (
 id TINYINT UNSIGNED PRIMARY KEY,
 next_number BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS raffle_numbers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 participant_id BIGINT UNSIGNED NOT NULL,
 raffle_number VARCHAR(40) NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_raffle_participant(participant_id),
 CONSTRAINT fk_raffle_participant FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(50) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS submission_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ip_address VARCHAR(45) NOT NULL,
 whatsapp VARCHAR(20) NULL,
 tiktok_account VARCHAR(100) NULL,
 device_hash CHAR(64) NULL,
 was_successful TINYINT(1) NOT NULL DEFAULT 0,
 attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_submit_ip_time(ip_address,attempted_at),
 KEY idx_submit_device_time(device_hash,attempted_at),
 KEY idx_submit_tiktok_time(tiktok_account,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admin_login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(50) NOT NULL,
 ip_address VARCHAR(45) NOT NULL,
 was_successful TINYINT(1) NOT NULL DEFAULT 0,
 attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_admin_attempt_ip_time(ip_address,attempted_at),
 KEY idx_admin_attempt_user_time(username,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS app_settings (
 setting_key VARCHAR(50) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$schema))) as $sql){$pdo->exec($sql);}
        $pdo->beginTransaction();
        $q=$pdo->prepare('INSERT IGNORE INTO questions(question_number,is_active) VALUES(?,1)');for($i=1;$i<=10;$i++)$q->execute([$i]);
        $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('quiz_open','1') ON DUPLICATE KEY UPDATE setting_value=setting_value")->execute();
        $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('daily_participant_quota',?) ON DUPLICATE KEY UPDATE setting_value=setting_value")->execute(['200']);
        $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('quiz_mode','auto') ON DUPLICATE KEY UPDATE setting_value=setting_value")->execute();
        $pdo->exec("INSERT IGNORE INTO raffle_sequence(id,next_number) VALUES(1,1)");
        $hash=password_hash($adminPass,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
        $a=$pdo->prepare('INSERT INTO admins(username,password_hash,must_change_password) VALUES(?,?,0) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),must_change_password=0');$a->execute([$adminUser,$hash]);
        $pdo->commit();
        $config="<?php\ndeclare(strict_types=1);\nconst DB_HOST = ".var_export($host,true).";\nconst DB_PORT = ".$port.";\nconst DB_NAME = ".var_export($name,true).";\nconst DB_USER = ".var_export($user,true).";\nconst DB_PASS = ".var_export($pass,true).";\nconst APP_KEY = ".var_export(bin2hex(random_bytes(32)),true).";\n";
        if(file_put_contents(__DIR__.'/config.local.php',$config,LOCK_EX)===false)throw new RuntimeException('Gagal menulis config.local.php. Pastikan folder dapat ditulis sementara.');
        @chmod(__DIR__.'/config.local.php',0600);
        foreach(['uploads','uploads/subscriber','uploads/comment','uploads/answer10'] as $dir){$p=__DIR__.'/'.$dir;if(!is_dir($p)&&!mkdir($p,0755,true))throw new RuntimeException('Gagal membuat folder '.$dir);@file_put_contents($p.'/index.html','');}
        $success=true;$installed=true;
      }catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$errors[]='Instalasi gagal: '.$e->getMessage();}
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installer Kuis TikTok Affan Elektronik</title><style>body{font-family:Arial,sans-serif;background:#f3f6f4;margin:0;color:#173b2b}.wrap{max-width:720px;margin:40px auto;padding:20px}.card{background:#fff;padding:28px;border-radius:16px;box-shadow:0 8px 30px #0001}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin:14px 0}label{display:block;font-weight:700;margin-bottom:7px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ccd7d0;border-radius:9px}button,a.btn{display:inline-block;background:#198754;color:#fff;border:0;padding:12px 18px;border-radius:9px;text-decoration:none;font-weight:700}.alert{background:#ffe4e4;padding:12px;border-radius:9px;margin:12px 0}.ok{background:#def7e7;padding:16px;border-radius:9px}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style></head><body><div class="wrap"><div class="card"><img src="assets/affan-logo.png" alt="Affan Elektronik" style="max-width:340px;width:100%;height:auto"><h1>Installer Kuis TikTok Affan Elektronik</h1><?php if($success):?><div class="ok"><b>Instalasi berhasil.</b><p>Hapus atau ubah nama <code>install.php</code> setelah memastikan aplikasi berjalan.</p><a class="btn" href="index.php">Buka Kuis</a> <a class="btn" href="admin/login.php">Login Admin</a></div><?php elseif($installed):?><div class="ok">Aplikasi sudah terinstal. <a href="index.php">Buka aplikasi</a>.</div><?php else:?><?php if($errors):?><div class="alert"><ul><?php foreach($errors as $e):?><li><?=htmlspecialchars($e,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></div><?php endif;?><p>Isi data database dari cPanel. Database harus sudah dibuat dan user database harus memiliki ALL PRIVILEGES.</p><form method="post"><div class="grid"><div class="field"><label>Host Database</label><input name="db_host" value="<?=htmlspecialchars((string)($_POST['db_host']??'localhost'))?>" required></div><div class="field"><label>Port</label><input type="number" name="db_port" value="<?=htmlspecialchars((string)($_POST['db_port']??'3306'))?>" required></div><div class="field"><label>Nama Database</label><input name="db_name" value="<?=htmlspecialchars((string)($_POST['db_name']??''))?>" required></div><div class="field"><label>User Database</label><input name="db_user" value="<?=htmlspecialchars((string)($_POST['db_user']??''))?>" required></div></div><div class="field"><label>Password Database</label><input type="password" name="db_pass"></div><hr><div class="grid"><div class="field"><label>Username Admin</label><input name="admin_user" value="<?=htmlspecialchars((string)($_POST['admin_user']??'admin'))?>" required></div><div class="field"><label>Password Admin</label><input type="password" name="admin_pass" minlength="12" required></div></div><p><small>Password minimal 12 karakter, berisi huruf besar, huruf kecil, angka, dan simbol.</small></p><button>Install Sekarang</button></form><?php endif;?></div></div></body></html>
