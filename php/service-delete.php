<?php
// /mp/provider/service-delete.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$uid = (int)$_SESSION['user_id'];
$id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
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

/* 1) تأكيد الملكية + (اختياري) جلب الصورة */
$img_path = null;
if ($st = $conn->prepare("SELECT img_path FROM services WHERE id=? AND provider_id=? LIMIT 1")) {
  $st->bind_param("ii", $id, $uid);
  $st->execute();
  $st->bind_result($img_path);
  if (!$st->fetch()) {
    $st->close(); $conn->close();
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
  }
  $st->close();
}

/* 2) Soft Delete: عيّن is_deleted=1 واطفِ الخدمة */
/* ملاحظة: أضفنا AND is_deleted=0 لنتجنب إعادة التحديث */
if ($st = $conn->prepare("UPDATE services SET is_deleted=1, is_active=0 WHERE id=? AND provider_id=? AND is_deleted=0 LIMIT 1")) {
  $st->bind_param("ii", $id, $uid);
  if (!$st->execute()) {
    $st->close(); $conn->close();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'delete_failed']); exit;
  }
  $st->close();
} else {
  $conn->close();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'prep_failed']); exit;
}

/* 3) (اختياري) حذف الصورة فعليًا — عادةً بالـSoft Delete نتركها */
// إذا حابة تحذفي الملف فعليًا، فكي الكومنت أدناه وعدّلي المسار حسب مشروعك.
// if ($img_path && !preg_match('~^https?://~i', $img_path)) {
//   $full = rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/mp/'.ltrim(str_replace('\\','/',$img_path), '/');
//   if (is_file($full)) { @unlink($full); }
// }

/* 4) إحصائيات مُحدّثة (بدون المحذوفة) */
$active_count = 0;
$inactive_count = 0;
$avg_price = 0.0;

if ($st = $conn->prepare("SELECT SUM(is_active=1), SUM(is_active=0) FROM services WHERE provider_id=? AND is_deleted=0")) {
  $st->bind_param("i", $uid);
  $st->execute();
  $st->bind_result($active_count, $inactive_count);
  $st->fetch();
  $st->close();
}
if ($st = $conn->prepare("SELECT COALESCE(AVG((price_from+price_to)/2),0) FROM services WHERE provider_id=? AND is_active=1 AND is_deleted=0")) {
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
  'deleted_id'     => $id,
  'active_count'   => (int)$active_count,
  'inactive_count' => (int)$inactive_count,
  'pct_active'     => $pct,
  'avg_price'      => (float)$avg_price
]);