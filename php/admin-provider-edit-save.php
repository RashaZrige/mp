<?php
/* admin-provider-edit-save.php */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { 
  header("Location:admin-provider-edit.php?err=".rawurlencode("DB connect failed")); 
  exit;
}
$conn->set_charset("utf8mb4");

/* Helpers */
function clean_phone($s){
  $s = (string)$s;
  $s = preg_replace('/[^0-9+\- ]+/', '', $s);
  return $s === null ? '' : $s;
}
function back_to($id,$type,$msg){
  $q = $type==='ok'?'ok':'err';
  header("Location:admin-provider-edit.php?id=".$id."&$q=".rawurlencode($msg));
  exit;
}

$uid         = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$full_name   = trim($_POST['full_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = clean_phone($_POST['phone'] ?? '');
$national_id = trim($_POST['national_id'] ?? '');
$status_in   = strtolower(trim($_POST['status'] ?? 'active'));
$is_available= isset($_POST['is_available']) ? 1 : 0;

if ($uid<=0){ header("Location:admin-providers.php?msg=Invalid+id"); exit; }
if ($full_name==='' || $email===''){ back_to($uid,'err','Full name and email are required'); }
if (!filter_var($email,FILTER_VALIDATE_EMAIL)){ back_to($uid,'err','Invalid email'); }
$status = in_array($status_in,['active','suspended'],true) ? $status_in : 'active';

/* تأكد إنّه مزود */
$st = $conn->prepare("SELECT id FROM users WHERE id=? AND role='provider' AND is_deleted=0");
$st->bind_param("i",$uid);
$st->execute();
$exists = $st->get_result()->num_rows>0;
$st->close();
if(!$exists){ header("Location:admin-providers.php?msg=Provider+not+found"); exit; }

/* ارفع الصورة (اختياري) */
$avatar_path = null;
if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
  $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
  $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$BASE.'/uploads/avatars';
  if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
  $fname = 'prov_'.date('Ymd_His').'_'.mt_rand(1000,9999).'.'.$ext;
  $dest  = $uploadDir.'/'.$fname;
  if (@move_uploaded_file($_FILES['avatar']['tmp_name'],$dest)) {
    $avatar_path = 'uploads/avatars/'.$fname;
  }
}

$conn->begin_transaction();
try{
  /* users */
  $sqlU = "UPDATE users SET full_name=?, email=?, phone=?, status=? WHERE id=?";
  $st = $conn->prepare($sqlU);
  if(!$st){ throw new Exception("Update users prepare: ".$conn->error); }
  if ($phone === null) $phone = '';
  $st->bind_param("ssssi", $full_name, $email, $phone, $status, $uid);
  if(!$st->execute()){ throw new Exception("Update users failed: ".$st->error); }
  $st->close();

  /* provider_profiles موجود؟ */
  $sqlHas = "SELECT user_id FROM provider_profiles WHERE user_id=? LIMIT 1";
  $st = $conn->prepare($sqlHas);
  $st->bind_param("i",$uid);
  $st->execute();
  $has = $st->get_result()->num_rows>0;
  $st->close();

  if ($has){
    $sqlP = "UPDATE provider_profiles
             SET full_name=?, phone=?, email=?, national_id=?, is_available=?, updated_at=NOW()".($avatar_path!==null?", avatar_path=?":"")."
             WHERE user_id=?";
    if ($avatar_path!==null){
      $st = $conn->prepare($sqlP);
      $st->bind_param("ssssisi", $full_name,$phone,$email,$national_id,$is_available,$avatar_path,$uid);
    }else{
      $sqlP = "UPDATE provider_profiles
               SET full_name=?, phone=?, email=?, national_id=?, is_available=?, updated_at=NOW()
               WHERE user_id=?";
      $st = $conn->prepare($sqlP);
      $st->bind_param("ssssii", $full_name,$phone,$email,$national_id,$is_available,$uid);
    }
    if(!$st->execute()){ throw new Exception("Update profile failed: ".$st->error); }
    $st->close();
  }else{
    $sqlP = "INSERT INTO provider_profiles (user_id, full_name, phone, email, national_id, avatar_path, is_available, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?, NOW(), NOW())";
    $st = $conn->prepare($sqlP);
    $ap = $avatar_path ?? '';
    $st->bind_param("isssssi", $uid, $full_name,$phone,$email,$national_id,$ap,$is_available);
    if(!$st->execute()){ throw new Exception("Insert profile failed: ".$st->error); }
    $st->close();
  }

  $conn->commit();
  back_to($uid,'ok','Provider updated');

}catch(Throwable $e){
  $conn->rollback();
  back_to($uid,'err','DB error: '.$e->getMessage());
}