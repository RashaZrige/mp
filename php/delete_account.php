<?php
// mp/php/delete_account.php
header('Content-Type: application/json; charset=utf-8');
session_start();

function jres($ok, $extra = []) {
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  jres(false, ['error'=>'unauthorized']);
}
$uid = (int)$_SESSION['user_id'];

// اتصال DB
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) {
  http_response_code(500);
  jres(false, ['error'=>'db_connect_failed', 'detail'=>$conn->connect_error]);
}
$conn->set_charset("utf8mb4");

// ابدأ معاملة
$conn->begin_transaction();
try {
  // 1) احذف رسائل التواصل
  $stmt = $conn->prepare("DELETE FROM contact_messages WHERE user_id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute(); $stmt->close();

  // 2) احذف الريفيوز كزبون
  $stmt = $conn->prepare("DELETE FROM service_reviews WHERE customer_id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute(); $stmt->close();

  // 3) احذف الريفيوز المرتبطة بخدمات هذا المستخدم (لو كان مزوّد)
  $stmt = $conn->prepare("
    DELETE sr FROM service_reviews sr
    JOIN services s ON s.id = sr.service_id
    WHERE s.provider_id = ?
  ");
  $stmt->bind_param("i", $uid);
  $stmt->execute(); $stmt->close();

  // 4) احذف الحجوزات كزبون أو كمزوّد
  $stmt = $conn->prepare("DELETE FROM bookings WHERE customer_id=? OR provider_id=?");
  $stmt->bind_param("ii", $uid, $uid);
  $stmt->execute(); $stmt->close();

  // 5) احذف الخدمات التي يملكها
  $stmt = $conn->prepare("DELETE FROM services WHERE provider_id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute(); $stmt->close();

  // 6) احذف بروفايل المزود
  $stmt = $conn->prepare("DELETE FROM provider_profiles WHERE user_id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute(); $stmt->close();

  // 7) أخيرًا احذف المستخدم نفسه
  $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute(); 
  $affected = $stmt->affected_rows;
  $stmt->close();

  if ($affected <= 0) {
    throw new Exception('user_not_found_or_already_deleted');
  }

  // نجاح
  $conn->commit();

  // انهي الجلسة
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
  session_destroy();

  jres(true);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  jres(false, ['error'=>'delete_failed', 'detail'=>$e->getMessage()]);
}