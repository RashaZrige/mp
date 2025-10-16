<?php
// mp/php/contact_submit.php
header('Content-Type: application/json; charset=utf-8');
session_start();

$ADMIN_EMAIL = 'admin@example.com'; // غيّريها
function jres($ok, $extra = []) {
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); jres(false, ['error'=>'db_connect_failed']); }
$conn->set_charset("utf8mb4");

// مدخلات
$uid     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$email   = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); jres(false, ['error'=>'invalid_email']);
}
if ($message === '' || mb_strlen($message) < 3) {
  http_response_code(422); jres(false, ['error'=>'message_too_short']);
}
if (mb_strlen($message) > 5000) {
  http_response_code(422); jres(false, ['error'=>'message_too_long']);
}

// إدخال DB
$sql = "INSERT INTO contact_messages (user_id, email, message) VALUES (?, ?, ?)";
$st  = $conn->prepare($sql);
if (!$st) { http_response_code(500); jres(false, ['error'=>'prepare_failed']); }
$st->bind_param("iss", $uid, $email, $message);
$ok = $st->execute();
$st->close();
if (!$ok) { http_response_code(500); jres(false, ['error'=>'insert_failed']); }

// (اختياري) إشعار إيميل
$subject = "New contact message";
$body    = "From: {$email}\nUser ID: ".($uid ?? 'guest')."\n\nMessage:\n{$message}";
@mail($ADMIN_EMAIL, $subject, $body, "From: no-reply@yourdomain.test");

// نجاح
jres(true, ['msg'=>'saved']);