<?php
require __DIR__.'/config.php';
cleanupSecurityLogs();

// Jika total upload melampaui post_max_size, PHP dapat mengosongkan seluruh POST/FILES.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $_SESSION['errors'] = ['Total ukuran foto terlalu besar untuk server. Gunakan foto yang lebih kecil, lalu kirim kembali.'];
    header('Location:index.php');
    exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location:index.php');exit;}
if(!quizIsOpen()){$_SESSION['errors']=['Sesi kuis telah ditutup dan tidak menerima jawaban baru.'];header('Location:index.php');exit;}
if(!participantSubmissionSchemaReady()){$_SESSION['errors']=['Sistem pendaftaran sedang dalam proses pembaruan. Silakan hubungi administrator dan coba kembali setelah database diperbarui.'];header('Location:index.php');exit;}
$dailyQuota = dailyParticipantQuota();
$dailyParticipants = (int)db()->query("SELECT COUNT(*) FROM participants WHERE submitted_at >= CURDATE() AND submitted_at < CURDATE() + INTERVAL 1 DAY")->fetchColumn();
if($dailyParticipants >= $dailyQuota){$_SESSION['errors']=['Mohon maaf, kuota peserta untuk hari ini telah terpenuhi. Silakan mencoba kembali besok.'];header('Location:index.php');exit;}
$errors=[];$name=trim((string)($_POST['name']??''));$nameNormalized=mb_strtolower(preg_replace('/\s+/u',' ',$name)??$name);$waRaw=trim((string)($_POST['whatsapp']??''));$wa=normalizeWhatsapp($waRaw);$tiktokAccountRaw=trim((string)($_POST['tiktok_account']??''));$tiktokAccount=mb_strtolower(ltrim($tiktokAccountRaw,'@'));$urlRaw=trim((string)($_POST['tiktok_profile_url']??''));$url=$urlRaw;$answers=is_array($_POST['answers']??null)?$_POST['answers']:[];$privacyConsent=(string)($_POST['privacy_consent']??'')==='1';$ageConfirmation=(string)($_POST['age_confirmation']??'')==='1';$ip=clientIp();$deviceHash=deviceHash();$riskScore=0;$riskReasons=[];
$_SESSION['old']=['name'=>$name,'whatsapp'=>$waRaw,'tiktok_account'=>$tiktokAccountRaw,'tiktok_profile_url'=>$urlRaw,'answers'=>$answers,'privacy_consent'=>$privacyConsent,'age_confirmation'=>$ageConfirmation];
if(!verifyCsrf((string)($_POST['csrf_token']??'')))$errors[]='Sesi formulir tidak valid. Muat ulang halaman.';
if(trim((string)($_POST['website']??''))!=='')$errors[]='Permintaan terdeteksi sebagai spam.';
if(!$privacyConsent)$errors[]='Persetujuan penggunaan data pribadi wajib diberikan sebelum mengirim jawaban.';
if(!$ageConfirmation)$errors[]='Pernyataan usia dan hak atas data/foto wajib dikonfirmasi sebelum mengirim jawaban.';
if($name===''||mb_strlen($name)<3||mb_strlen($name)>100)$errors[]='Nama harus berisi 3 sampai 100 karakter.';
if($name!==''&&!preg_match("/^[\\p{L}][\\p{L} .'-]*[\\p{L}.]$/u",$name))$errors[]='Nama hanya boleh berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
if(preg_match('/(.)\\1{4,}/iu',$name)||preg_match('/^(test|testing|asdf|qwerty|admin|user|nama|anonymous|anonim)$/iu',$nameNormalized))$errors[]='Nama terdeteksi tidak wajar. Masukkan nama asli.';
if(!isValidWhatsapp($wa))$errors[]='Nomor WhatsApp Indonesia tidak valid atau terdeteksi sebagai nomor palsu.';
if($tiktokAccount===''||mb_strlen($tiktokAccount)>50||!preg_match('/^[a-z0-9._]{2,50}$/',$tiktokAccount))$errors[]='Akun TikTok / username TikTok tidak valid. Gunakan huruf, angka, titik, atau garis bawah.';
// Izinkan peserta menempel username pada kolom Link Profile; sistem mengubahnya menjadi URL profil.
if($url!==''&&!preg_match('~^https?://~i',$url)){
    $profileUsername=mb_strtolower(ltrim($url,'@'));
    if(preg_match('/^[a-z0-9._]{2,50}$/',$profileUsername))$url='https://www.tiktok.com/@'.$profileUsername;
}
$urlHost=strtolower((string)parse_url($url,PHP_URL_HOST));
$urlPath=(string)parse_url($url,PHP_URL_PATH);
if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array($urlHost,['tiktok.com','www.tiktok.com','m.tiktok.com'],true)||!preg_match('~^/@[A-Za-z0-9._]{2,50}/?$~',$urlPath))$errors[]='Link Profile tidak valid. Gunakan tautan profil seperti https://www.tiktok.com/@username, bukan tautan video.';
$profilePathUsername=mb_strtolower(ltrim(trim($urlPath,'/'),'@'));
if($tiktokAccount!==''&&$profilePathUsername!==''&&!hash_equals($tiktokAccount,$profilePathUsername))$errors[]='Username TikTok harus sama dengan username pada Link Profile.';
$age=formAge((string)($_POST['form_proof']??''));
if($age===null)$errors[]='Formulir telah kedaluwarsa atau tidak valid. Muat ulang halaman.';
elseif($age<MIN_FORM_FILL_SECONDS)$errors[]='Formulir dikirim terlalu cepat. Periksa kembali data sebelum mengirim.';
if(isset($_SESSION['last_submit_time']) && time()-(int)$_SESSION['last_submit_time']<SUBMIT_COOLDOWN_SECONDS)$errors[]='Pengiriman terlalu cepat. Silakan tunggu beberapa saat.';
$rate=db()->prepare('SELECT COUNT(*) FROM submission_attempts WHERE ip_address=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');$rate->execute([$ip]);if((int)$rate->fetchColumn()>=MAX_IP_SUBMISSIONS_PER_HOUR)$errors[]='Batas pengiriman dari jaringan ini telah tercapai. Coba lagi satu jam kemudian.';
$dailyRate=db()->prepare('SELECT COUNT(*) FROM submission_attempts WHERE ip_address=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)');$dailyRate->execute([$ip]);$dailyIpAttempts=(int)$dailyRate->fetchColumn();if($dailyIpAttempts>=MAX_IP_ATTEMPTS_PER_DAY)$errors[]='Batas pengiriman harian dari jaringan ini telah tercapai.';elseif($dailyIpAttempts>=3){$riskScore+=20;$riskReasons[]='Beberapa percobaan dari IP yang sama';}
$deviceRate=db()->prepare('SELECT COUNT(*) FROM submission_attempts WHERE device_hash=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)');$deviceRate->execute([$deviceHash]);if((int)$deviceRate->fetchColumn()>=MAX_DEVICE_ATTEMPTS_PER_DAY)$errors[]='Perangkat ini terlalu banyak melakukan percobaan. Coba lagi besok.';
$qrows=db()->query('SELECT id,question_number FROM questions WHERE is_active=1 ORDER BY question_number')->fetchAll();
if(count($qrows)!==10)$errors[]='Jumlah soal aktif harus tepat 10.';
foreach($qrows as $q){$qid=(int)$q['id'];$number=(int)$q['question_number'];if($number===10){continue;}if(trim((string)($answers[$qid]??''))===''){$errors[]='Semua jawaban teks wajib diisi.';break;}}
function imageUploadFromFile(array $f,string $label,array &$errors):?array{
    if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){$errors[]="$label wajib diunggah.";return null;}
    if((int)($f['size']??0)>MAX_UPLOAD_SIZE){$errors[]="$label maksimal 5 MB.";return null;}
    $tmp=(string)($f['tmp_name']??'');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$allowed=['image/jpeg','image/png','image/webp'];$info=@getimagesize($tmp);
    if(!in_array($mime,$allowed,true)||$info===false){$errors[]="$label harus JPG, PNG, atau WEBP yang valid.";return null;}
    $width=(int)$info[0];$height=(int)$info[1];if($width<1||$height<1||($width*$height)>IMAGE_MAX_PIXELS){$errors[]="$label memiliki dimensi yang tidak diizinkan.";return null;}
    if(!extension_loaded('gd')){$errors[]='Ekstensi PHP GD wajib aktif untuk memproses foto.';return null;}
    return['tmp'=>$tmp,'mime'=>$mime,'width'=>$width,'height'=>$height,'hash'=>hash_file('sha256',$tmp)];
}
function imageUpload(string $field,string $label,array &$errors):?array{if(!isset($_FILES[$field])){$errors[]="$label wajib diunggah.";return null;}return imageUploadFromFile($_FILES[$field],$label,$errors);}
function compressImage(array $image,string $destination):bool{
    $source=null;if($image['mime']==='image/jpeg')$source=@imagecreatefromjpeg($image['tmp']);elseif($image['mime']==='image/png')$source=@imagecreatefrompng($image['tmp']);elseif($image['mime']==='image/webp'&&function_exists('imagecreatefromwebp'))$source=@imagecreatefromwebp($image['tmp']);if(!$source)return false;
    $width=imagesx($source);$height=imagesy($source);if($image['mime']==='image/jpeg'&&function_exists('exif_read_data')){$exif=@exif_read_data($image['tmp']);$orientation=(int)($exif['Orientation']??1);if($orientation===3)$source=imagerotate($source,180,0);elseif($orientation===6)$source=imagerotate($source,-90,0);elseif($orientation===8)$source=imagerotate($source,90,0);$width=imagesx($source);$height=imagesy($source);}
    $ratio=min(1,IMAGE_MAX_WIDTH/$width,IMAGE_MAX_HEIGHT/$height);$newWidth=max(1,(int)round($width*$ratio));$newHeight=max(1,(int)round($height*$ratio));$canvas=imagecreatetruecolor($newWidth,$newHeight);if(!$canvas){imagedestroy($source);return false;}$white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);$ok=imagecopyresampled($canvas,$source,0,0,0,0,$newWidth,$newHeight,$width,$height)&&imagejpeg($canvas,$destination,IMAGE_JPEG_QUALITY);imagedestroy($canvas);imagedestroy($source);if(!$ok||!is_file($destination)||filesize($destination)===0){@unlink($destination);return false;}@chmod($destination,0644);return true;
}
$sub=imageUpload('subscriber_photo','Foto Profile TikTok',$errors);$com=imageUpload('comment_photo','Foto komentar',$errors);
$q10Images=[];$files=$_FILES['answer10_images']??null;
if($files&&is_array($files['name']??null)){if(count($files['name'])>3)$errors[]='Gambar jawaban nomor 10 maksimal 3 file.';else{for($i=0;$i<count($files['name']);$i++){$file=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$img=imageUploadFromFile($file,'Gambar jawaban nomor 10 ke-'.($i+1),$errors);if($img)$q10Images[]=$img;}}}}
if(!$errors){$c=db()->prepare('SELECT id FROM participants WHERE name_normalized=? OR whatsapp=? OR tiktok_account=? OR device_hash=? LIMIT 1');$c->execute([$nameNormalized,$wa,$tiktokAccount,$deviceHash]);if($c->fetch())$errors[]='Nama, nomor WhatsApp, akun TikTok, atau perangkat ini sudah pernah digunakan.';}
if(!$errors){if(hash_equals((string)$sub['hash'],(string)$com['hash']))$errors[]='Foto profile TikTok dan foto komentar harus menggunakan dua bukti yang berbeda.';else{$duplicateProof=db()->prepare('SELECT 1 FROM participants WHERE subscriber_image_hash=? OR comment_image_hash=? LIMIT 1');$duplicateProof->execute([(string)$sub['hash'],(string)$sub['hash']]);if($duplicateProof->fetchColumn())$errors[]='Foto profile TikTok ini sudah pernah digunakan oleh peserta lain.';$duplicateProof->execute([(string)$com['hash'],(string)$com['hash']]);if($duplicateProof->fetchColumn())$errors[]='Foto komentar TikTok ini sudah pernah digunakan oleh peserta lain.';}}
$log=db()->prepare('INSERT INTO submission_attempts(ip_address,whatsapp,tiktok_account,device_hash,was_successful) VALUES(?,?,?,?,0)');$log->execute([$ip,$wa?:null,$tiktokAccount?:null,$deviceHash]);$attemptId=(int)db()->lastInsertId();
if($errors){$_SESSION['errors']=array_values(array_unique($errors));header('Location:index.php');exit;}
$stored=[];
function storeCompressed(array $image,string $folder,array &$stored):string{$relative=$folder.'/'.bin2hex(random_bytes(16)).'.jpg';$full=__DIR__.'/'.$relative;if(!compressImage($image,$full))throw new RuntimeException('Foto gagal dikompres atau disimpan.');$stored[]=$full;return $relative;}
try{$sp=storeCompressed($sub,'uploads/subscriber',$stored);$cp=storeCompressed($com,'uploads/comment',$stored);$q10Paths=[];foreach($q10Images as $image)$q10Paths[]=storeCompressed($image,'uploads/answer10',$stored);
$pdo=db();$pdo->beginTransaction();$quotaLock=$pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key='daily_participant_quota' FOR UPDATE");$quotaLock->execute();$lockedQuota=(int)$quotaLock->fetchColumn();if($lockedQuota<1)$lockedQuota=DEFAULT_DAILY_PARTICIPANT_QUOTA;$lockedDailyCount=(int)$pdo->query("SELECT COUNT(*) FROM participants WHERE submitted_at >= CURDATE() AND submitted_at < CURDATE() + INTERVAL 1 DAY")->fetchColumn();if($lockedDailyCount>=$lockedQuota)throw new UnexpectedValueException('DAILY_QUOTA_FULL');$identityLock=$pdo->prepare('INSERT INTO participant_identity_locks(identity_type,identity_value) VALUES(?,?)');$identityLock->execute(['whatsapp',$wa]);$identityLock->execute(['tiktok',$tiktokAccount]);$identityLock->execute(['device',$deviceHash]);$riskStatus=$riskScore>0?'flagged':'clear';$s=$pdo->prepare('INSERT INTO participants(name,name_normalized,whatsapp,tiktok_account,tiktok_profile_url,subscriber_photo,comment_photo,token,submit_ip,device_hash,subscriber_image_hash,comment_image_hash,risk_status,risk_score,risk_reasons,privacy_consent_at,privacy_policy_version,age_confirmed_at,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');$token='';$inserted=false;for($tokenAttempt=0;$tokenAttempt<25;$tokenAttempt++){$token=participantTokenCandidate();try{$confirmedAt=date('Y-m-d H:i:s');$s->execute([$name,$nameNormalized,$wa,$tiktokAccount,$url,$sp,$cp,$token,$ip,$deviceHash,$sub['hash'],$com['hash'],$riskStatus,$riskScore,implode('; ',$riskReasons),$confirmedAt,PRIVACY_POLICY_VERSION,$confirmedAt]);$inserted=true;break;}catch(PDOException $insertError){if(!isDuplicateKeyError($insertError)||stripos($insertError->getMessage(),'token')===false)throw $insertError;}}if(!$inserted)throw new RuntimeException('Gagal membuat token unik.');
$pid=(int)$pdo->lastInsertId();$a=$pdo->prepare('INSERT INTO participant_answers(participant_id,question_id,answer_text) VALUES(?,?,?)');$imgIns=$pdo->prepare('INSERT INTO participant_answer_images(participant_answer_id,image_path,image_hash,sort_order) VALUES(?,?,?,?)');foreach($qrows as $q){$qid=(int)$q['id'];$number=(int)$q['question_number'];$text=$number===10?($q10Images?'['.count($q10Images).' gambar diunggah]':'[Tidak ada gambar]'):trim((string)$answers[$qid]);$a->execute([$pid,$qid,$text]);$answerId=(int)$pdo->lastInsertId();if($number===10){foreach($q10Paths as $i=>$path)$imgIns->execute([$answerId,$path,$q10Images[$i]['hash'],$i+1]);}}
$pdo->prepare('UPDATE submission_attempts SET was_successful=1 WHERE id=?')->execute([$attemptId]);$pdo->commit();rememberParticipantToken($token);$_SESSION['last_submit_time']=time();unset($_SESSION['old']);$_SESSION['success_token']=$token;$_SESSION['success_name']=$name;header('Location:success.php');exit;
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();foreach($stored as $file)@unlink($file);if($e instanceof UnexpectedValueException&&$e->getMessage()==='DAILY_QUOTA_FULL'){$_SESSION['errors']=['Mohon maaf, kuota peserta untuk hari ini baru saja terpenuhi. Silakan mencoba kembali besok.'];}else{logSubmissionFailure($e);$reference=(string)($_SESSION['submission_error_reference']??'');$_SESSION['errors']=['Data belum dapat disimpan karena terjadi gangguan pada sistem. Silakan coba kembali. Jika masalah berulang, sampaikan kode '.($reference!==''?$reference:'ERROR').' kepada administrator.'];}header('Location:index.php');exit;}
