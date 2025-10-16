<?php
/* admin-customer-add-save.php — Save new customer */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* Helpers */
function back_with($page, $key, $msg){
  $q = http_build_query([$key => $msg]);
  header("Location: {$page}?{$q}");
  exit;
}
function gen_pwd($len = 12){
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%!';
  $out = '';
  for ($i=0; $i<$len; $i++) { $out .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
  return $out;
}



/* Read POST (اسماء الحقول لازم تطابق الفورم) */
$name   = trim($_POST['full_name'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$email  = trim($_POST['email'] ?? '');
$status = $_POST['status'] ?? 'active';
$pwd    = (string)($_POST['password']  ?? '');
$pwd2   = (string)($_POST['password2'] ?? '');

/* Validate أساسي */
if ($name === '') {
  back_with('admin-customer-add.php', 'err', 'Full name is required.');
}
if ($pwd !== '' && $pwd !== $pwd2) {
  back_with('admin-customer-add.php', 'err', 'Passwords do not match.');
}

/* خلي الهاتف/الإيميل NULL لو فاضين */
$phone = ($phone === '') ? null : $phone;
$email = ($email === '') ? null : $email;

/* Checks للتكرار فقط إذا القيمة موجودة */
if (!is_null($email)) {
  $st = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
  $st->bind_param("s", $email);
  $st->execute();
  if ($st->get_result()->fetch_row()) { $st->close(); back_with('admin-customer-add.php','err','Email already exists.'); }
  $st->close();
}
if (!is_null($phone)) {
  $st = $conn->prepare("SELECT 1 FROM users WHERE phone=? LIMIT 1");
  $st->bind_param("s", $phone);
  $st->execute();
  if ($st->get_result()->fetch_row()) { $st->close(); back_with('admin-customer-add.php','err','Phone already exists.'); }
  $st->close();
}

/* Password */
if ($pwd === '') { $pwd = gen_pwd(12); }
$hash = password_hash($pwd, PASSWORD_BCRYPT);

/* تأكد من أن أعمدة DB تسمح NULL للهاتف/الإيميل إن بدك تخليهم اختياريين.
   لو أعمدتك عليها NOT NULL لازم تعبّي قيمة بدل NULL. */

$role = 'customer';

/* نبني SQL ديناميكي حسب وجود الهاتف/الإيميل */
$sql = "INSERT INTO users (full_name, email, phone, role, password_hash, status, created_at)
        VALUES (?,?,?,?,?,?, NOW())";
$st = $conn->prepare($sql);
if (!$st) { back_with('admin-customer-add.php','err','Prepare failed: '.$conn->error); }

/* نوع البراميتر يظل "s" حتى مع NULL */
$st->bind_param("ssssss",
  $name,
  $email,   // ممكن تكون NULL
  $phone,   // ممكن تكون NULL
  $role,
  $hash,
  $status
);

if ($st->execute()) {
  $st->close();
  $conn->close();
  back_with('admin-customer-add.php', 'ok', 'Customer added successfully.');
} else {
  $err = $st->error;
  $st->close();
  $conn->close();
  back_with('admin-customer-add.php', 'err', 'Insert failed: '.$err);
}