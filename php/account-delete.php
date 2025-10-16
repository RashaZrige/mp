<?php
// /mp/account-delete.php
session_start();

header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  header("Location: login.html");
  exit;
}

$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) {
  http_response_code(500);
  die("DB failed: ".$conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ✅ Soft Delete الحساب */
if ($st = $conn->prepare("UPDATE users SET is_deleted=1, is_active=0 WHERE id=? LIMIT 1")) {
  $st->bind_param("i", $uid);
  $st->execute();
  $st->close();
}

$conn->close();

/* ✅ تدمير الجلسة بعد الحذف */
$_SESSION = [];
session_destroy();

/* ✅ تحويل لصفحة وداع */
header("Location:../goodbye.html");
exit;