<?php
// mp/php/reschedule.php
header('Content-Type: application/json; charset=utf-8');

session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'Auth required']); exit;
}
$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { echo json_encode(['ok'=>false,'error'=>'DB failed']); exit; }
$conn->set_charset("utf8mb4");

// التحقق من المدخلات
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$new_dt_raw = trim($_POST['new_datetime'] ?? '');

if ($booking_id <= 0 || $new_dt_raw === '') {
  echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit;
}

// تحويل datetime-local إلى صيغة MySQL: "YYYY-MM-DD HH:MM:SS"
$new_dt = str_replace('T', ' ', $new_dt_raw) . ':00';

// تأكد أن الحجز لهذا المستخدم
$chk = $conn->prepare("SELECT id FROM bookings WHERE id=? AND customer_id=? LIMIT 1");
$chk->bind_param("ii", $booking_id, $uid);
$chk->execute();
$has = $chk->get_result()->num_rows > 0;
$chk->close();

if (!$has) {
  echo json_encode(['ok'=>false,'error'=>'Booking not found']); exit;
}

// نفّذ التحديث
$up = $conn->prepare("UPDATE bookings SET scheduled_at=?, status='upcoming' WHERE id=?");
$up->bind_param("si", $new_dt, $booking_id);
$ok = $up->execute();
$up->close();
$conn->close();

if ($ok) {
  echo json_encode(['ok'=>true]);
} else {
  echo json_encode(['ok'=>false,'error'=>'Update failed']);
}