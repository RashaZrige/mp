<?php
// mp/php/review_create.php
// يرجّع JSON فقط، بدون أي echo/HTML
header('Content-Type: application/json; charset=utf-8');

session_start();
$BASE = '/mp';

function jres($ok, $extra = []) {
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  jres(false, ['error' => 'unauthorized']);
}

$uid = (int)$_SESSION['user_id'];

// استلام القيم من POST
$booking_id  = isset($_POST['booking_id'])  ? (int)$_POST['booking_id']  : 0;
$service_id  = isset($_POST['service_id'])  ? (int)$_POST['service_id']  : 0;
$provider_id = isset($_POST['provider_id']) ? (int)$_POST['provider_id'] : 0;
$rating      = isset($_POST['rating'])      ? (int)$_POST['rating']      : 0;
$comment     = isset($_POST['comment'])     ? trim((string)$_POST['comment']) : '';

if ($booking_id <= 0 || $service_id <= 0 || $provider_id <= 0) {
  http_response_code(400);
  jres(false, ['error' => 'missing_parameters']);
}
if ($rating < 1 || $rating > 5) {
  http_response_code(400);
  jres(false, ['error' => 'invalid_rating']);
}

// اتصال DB
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
  http_response_code(500);
  jres(false, ['error' => 'db_connect_failed', 'detail'=>$conn->connect_error]);
}
$conn->set_charset("utf8mb4");

// تحقق: الحجز فعلاً يخص هذا المستخدم وتفاصيله تطابق المرسل
$sqlChk = "
  SELECT b.id
  FROM bookings b
  WHERE b.id=? AND b.customer_id=? AND b.service_id=? AND b.provider_id=?
  LIMIT 1
";
if (!($st = $conn->prepare($sqlChk))) {
  http_response_code(500);
  jres(false, ['error'=>'prepare_check_failed','detail'=>$conn->error]);
}
$st->bind_param("iiii", $booking_id, $uid, $service_id, $provider_id);
$st->execute();
$has = $st->get_result()->fetch_row();
$st->close();

if (!$has) {
  http_response_code(404);
  jres(false, ['error' => 'booking_not_found']);
}

// هل فيه ريفيو سابق لنفس الحجز؟
$sqlHas = "SELECT id FROM service_reviews WHERE booking_id=? AND customer_id=? LIMIT 1";
if (!($st = $conn->prepare($sqlHas))) {
  http_response_code(500);
  jres(false, ['error'=>'prepare_has_failed','detail'=>$conn->error]);
}
$st->bind_param("ii", $booking_id, $uid);
$st->execute();
$hasReview = $st->get_result()->fetch_assoc();
$st->close();

// إدخال/تحديث
if ($hasReview) {
  $sqlUpd = "
    UPDATE service_reviews
    SET rating=?, comment=?, created_at = NOW()
    WHERE id=?
  ";
  if (!($st = $conn->prepare($sqlUpd))) {
    http_response_code(500);
    jres(false, ['error'=>'prepare_update_failed','detail'=>$conn->error]);
  }
  $cid = (int)$hasReview['id'];
  $st->bind_param("isi", $rating, $comment, $cid);
  $ok = $st->execute();
  $st->close();
  if (!$ok) { jres(false, ['error'=>'update_failed','detail'=>$conn->error]); }
} else {
  $sqlIns = "
    INSERT INTO service_reviews
      (booking_id, customer_id, provider_id, service_id, rating, comment, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ";
  if (!($st = $conn->prepare($sqlIns))) {
    http_response_code(500);
    jres(false, ['error'=>'prepare_insert_failed','detail'=>$conn->error]);
  }
  $st->bind_param("iiiiis", $booking_id, $uid, $provider_id, $service_id, $rating, $comment);
  $ok = $st->execute();
  $st->close();
  if (!$ok) { jres(false, ['error'=>'insert_failed','detail'=>$conn->error]); }
}

/* ====== إعادة احتساب متوسط التقييم وتحديث جدول services.rating ====== */
$avg = 0.0;

// احسب المتوسط من جدول service_reviews
if ($rs = $conn->query("
    SELECT COALESCE(AVG(rating),0) AS a
    FROM service_reviews
    WHERE service_id = ".(int)$service_id
)) {
  if ($row = $rs->fetch_assoc()) {
    $avg = round((float)$row['a'], 1); // رقم عشري واحد
  }
  $rs->close();
}

// حدّث services.rating
if ($upd = $conn->prepare("UPDATE services SET rating=? WHERE id=?")) {
  $upd->bind_param("di", $avg, $service_id); // d: double, i: int
  $upd->execute();
  $upd->close();
}
/* ====== نهاية إعادة الاحتساب ====== */
$conn->close();
jres(true);