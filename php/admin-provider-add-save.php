<?php
/* admin-provider-add-save.php */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { 
  header("Location:admin-provider-add.php?err=".rawurlencode("DB connect failed")); 
  exit;
}
$conn->set_charset("utf8mb4");

/* Helpers */
function clean_phone($s){
  // مهم: استخدم delimiters صحيحة عشان ما يرجّع NULL
  $s = (string)$s;
  $s = preg_replace('/[^0-9+\- ]+/', '', $s);
  return $s === null ? '' : $s;
}
function back_with($type,$msg){
  $q = $type === 'ok' ? 'ok' : 'err';
  header("Location:admin-provider-add.php?$q=".rawurlencode($msg));
  exit;
}

/* --- اقرأ المدخلات --- */
$full_name   = trim($_POST['full_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = clean_phone($_POST['phone'] ?? '');
$national_id = trim($_POST['national_id'] ?? '');
$category    = trim($_POST['category'] ?? '');
$status_in   = strtolower(trim($_POST['status'] ?? 'active'));
$is_available= isset($_POST['is_available']) ? 1 : 0;

$status = in_array($status_in, ['active','suspended'], true) ? $status_in : 'active';

/* تحقق أساسي */
if ($full_name === '' || $email === '') {
  back_with('err', 'Full name and email are required');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_with('err', 'Invalid email');
}

/* جهّز كلمة مرور مؤقتة */
$plain_pw = 'provider123'; /* عدّلها لو حاب */
$password_hash = password_hash($plain_pw, PASSWORD_BCRYPT);

/* ارفع الصورة (اختياري) - بدون _DIR_ */
$avatar_path = '';
if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
  $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';

  // مسار الرفع الفعلي على السيرفر
  $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$BASE.'/uploads/avatars';
  if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

  $fname = 'prov_'.date('Ymd_His').'_'.mt_rand(1000,9999).'.'.$ext;
  $dest  = $uploadDir.'/'.$fname;

  if (@move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
    // المسار الذي نخزّنه في الداتابيس لا يبدأ بجذر السيرفر
    $avatar_path = 'uploads/avatars/'.$fname;
  }
}

/* تأكد من عدم تكرار الإيميل */
$st = $conn->prepare("SELECT COUNT(*) FROM users WHERE email=? LIMIT 1");
if(!$st){ back_with('err', "DB error: ".$conn->error); }
$st->bind_param("s", $email);
$st->execute();
$exists = (int)$st->get_result()->fetch_row()[0];
$st->close();

if ($exists > 0) { back_with('err', 'Email already exists'); }

/* تنفيذ الإدخال */
$conn->begin_transaction();

try {
  // users: انتبه لعدد الـ placeholders (5)
  $sqlU = "INSERT INTO users
           (full_name, email, phone, role, password_hash, is_verified, created_at, status, is_deleted)
           VALUES (?,?,?,'provider',?,0,NOW(),?,0)";
  $st = $conn->prepare($sqlU);
  if(!$st){ throw new Exception("Insert users prepare: ".$conn->error); }

  // phone إلزامي Not NULL في جدولك، فلو فاضي نخزّن سترنغ فاضي بدل NULL
  if ($phone === null) $phone = '';

  $st->bind_param("sssss", $full_name, $email, $phone, $password_hash, $status);
  if(!$st->execute()){
    throw new Exception("Insert users failed: ".$st->error);
  }
  $uid = (int)$st->insert_id;
  $st->close();

  // provider_profiles
  $sqlP = "INSERT INTO provider_profiles
          (user_id, full_name, phone, email, national_id, avatar_path, is_available, created_at, updated_at)
           VALUES (?,?,?,?,?,?,?, NOW(), NOW())";
  $st = $conn->prepare($sqlP);
  if(!$st){ throw new Exception("Insert profile prepare: ".$conn->error); }
  $st->bind_param("isssssi", $uid, $full_name, $phone, $email, $national_id, $avatar_path, $is_available);
  if(!$st->execute()){
    throw new Exception("Insert profile failed: ".$st->error);
  }
  $st->close();

  // (اختياري) ممكن تخزّن $category في مكانك المفضل لاحقاً

  $conn->commit();
  header("Location:admin-providers.php?msg=".rawurlencode("Provider created"));
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  back_with('err', "DB error: ".$e->getMessage());
}