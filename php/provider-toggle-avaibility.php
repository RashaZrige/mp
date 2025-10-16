<?php
// /mp/php/provider-toggle-availability.php (أو بجانب صفحتك—عدّل المسار في JS لو لزم)
session_start();
header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) {
  echo json_encode(["success"=>false, "error"=>"DB failed"]); exit;
}

$uid  = (int)($_SESSION['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');

if ($uid <= 0 || $role !== 'provider') {
  echo json_encode(["success"=>false, "error"=>"not_authorized"]); exit;
}

// هل المزوّد موقّف من الإدارة؟ لو نعم نمنع التبديل
$st = $conn->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$st->bind_param("i", $uid);
$st->execute();
$adminSuspended = (strtolower((string)$st->get_result()->fetch_row()[0]) === 'suspended');
$st->close();

if ($adminSuspended) {
  echo json_encode(["success"=>false, "error"=>"account_suspended"]); exit;
}

$new = (isset($_POST['status']) && $_POST['status'] == '1') ? 1 : 0;

// ضمن أن السطر موجود، وإلا أنشئه
$st = $conn->prepare("INSERT INTO provider_profiles (user_id, is_available)
                      VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE is_available=VALUES(is_available)");
$st->bind_param("ii", $uid, $new);
$ok = $st->execute();
$st->close();

echo json_encode(["success"=>$ok, "new_status"=>$new]);