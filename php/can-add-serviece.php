<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}
$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db']); exit; }
$conn->set_charset('utf8mb4');

/* كم خدمة (غير محذوفة) عند هذا المزود؟ */
$svc_count = 0;
if ($st = $conn->prepare("SELECT COUNT(*) FROM services WHERE provider_id=? AND is_deleted=0")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($svc_count); $st->fetch(); $st->close();
}

/* أول خدمة مجانًا */
if ((int)$svc_count === 0) {
  $conn->close();
  echo json_encode(['ok'=>true, 'reason'=>'first_free']); exit;
}

/* تحقق من وجود دفعة مؤكدة لهذا المزود */
$has_confirmed = 0;
if ($st = $conn->prepare("SELECT COUNT(*) FROM payments WHERE provider_id=? AND purpose='extra_service' AND status='confirmed'")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($has_confirmed); $st->fetch(); $st->close();
}

if ((int)$has_confirmed > 0) {
  $conn->close();
  echo json_encode(['ok'=>true, 'reason'=>'paid_confirmed']); exit;
}

/* هل عنده طلب دفع معلّق؟ */
$pending_id = null;
if ($st = $conn->prepare("SELECT id FROM payments WHERE provider_id=? AND purpose='extra_service' AND status='pending' ORDER BY id DESC LIMIT 1")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($pending_id); $st->fetch(); $st->close();
}

/* إذا ما في pending، أنشئ pending جديد (اختياري) */
if (!$pending_id) {
  if ($st = $conn->prepare("INSERT INTO payments (provider_id, purpose, status, amount) VALUES (?, 'extra_service', 'pending', 0.00)")) {
    $st->bind_param("i", $uid);
    $st->execute();
    $pending_id = $st->insert_id ?: null;
    $st->close();
  }
}

$conn->close();
echo json_encode([
  'ok'=>false,
  'state'=> 'pending',
  'payment_id'=> (int)$pending_id,
  'message'=> 'Payment is required before adding more services.'
]);