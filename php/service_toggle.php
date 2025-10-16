<?php
// /mp/service_toggle.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// (اختياري للتشخيص فقط): السماح بـGET لتتأكد إن الملف موجود
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo json_encode(['ok'=>true,'hint'=>'service_toggle.php reachable (GET)']);
  exit;
}

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$uid       = (int)$_SESSION['user_id'];
$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : -1;

if ($id <= 0 || ($is_active !== 0 && $is_active !== 1)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_conn']); exit;
}
$conn->set_charset('utf8mb4');

// تأكيد ملكية الخدمة
if ($st = $conn->prepare("SELECT id FROM services WHERE id=? AND provider_id=? LIMIT 1")) {
  $st->bind_param("ii", $id, $uid);
  $st->execute();
  $own = $st->get_result()->fetch_row();
  $st->close();
  if (!$own) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); $conn->close(); exit; }
} else {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'prep_failed']); $conn->close(); exit;
}

// التحديث
if ($st = $conn->prepare("UPDATE services SET is_active=? WHERE id=? AND provider_id=? LIMIT 1")) {
  $st->bind_param("iii", $is_active, $id, $uid);
  if (!$st->execute()) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'update_failed']); $st->close(); $conn->close(); exit;
  }
  // ممكن نفحص عدد الصفوف المتأثرة (للتشخيص فقط)
  // $changed = $conn->affected_rows;
  $st->close();
} else {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'prep_failed']); $conn->close(); exit;
}

// إحصائيات جديدة للمزوّد الحالي
$active_count = $inactive_count = 0;
$avg_price = 0.0;

if ($st = $conn->prepare("SELECT SUM(is_active=1), SUM(is_active=0) FROM services WHERE provider_id=?")) {
  $st->bind_param("i", $uid);
  $st->execute();
  $st->bind_result($active_count,$inactive_count);
  $st->fetch();
  $st->close();
}

// متوسط السعر للخدمات النشطة فقط
if ($st = $conn->prepare("SELECT COALESCE(AVG((price_from+price_to)/2),0) FROM services WHERE provider_id=? AND is_active=1")) {
  $st->bind_param("i", $uid);
  $st->execute();
  $st->bind_result($avg_price);
  $st->fetch();
  $st->close();
}

$conn->close();

$total = max(1, (int)$active_count + (int)$inactive_count);
$pct   = (int)round(((int)$active_count / $total) * 100);

echo json_encode([
  'ok'             => true,
  'service_id'     => $id,
  'is_active'      => (int)$is_active,     // برجع الحالة الجديدة (للتشخيص/الاستخدام)
  'active_count'   => (int)$active_count,  // عدد النشطة
  'inactive_count' => (int)$inactive_count,// عدد غير النشطة
  'pct_active'     => $pct,                // نسبة النشطة
  'avg_price'      => (float)$avg_price    // متوسط أسعار الخدمات النشطة فقط
]);