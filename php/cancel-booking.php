<?php
/** Fixora — Cancel Booking (AJAX JSON) */
session_start();

// ✅ خلي الاستجابة دائمًا JSON
header('Content-Type: application/json; charset=utf-8');
// امسح أي مخرجات سابقة (لو فيه BOM/مسافات بالخطأ)
if (function_exists('ob_get_level')) { while (ob_get_level()) ob_end_clean(); }

// دالة ردّ سريع
function jres($ok, $error=''){
  echo json_encode(['ok'=>$ok, 'error'=>$error], JSON_UNESCAPED_UNICODE);
  exit;
}

// ✅ تحقق من تسجيل الدخول
if (empty($_SESSION['user_id'])) {
  jres(false, 'Not authenticated.');
}
$uid = (int)$_SESSION['user_id'];

// ✅ تحقق من باراميتر الحجز
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
if ($bookingId <= 0) {
  jres(false, 'Invalid booking id.');
}

// ✅ اتصال قاعدة البيانات
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
  jres(false, 'DB connection failed.');
}
$conn->set_charset("utf8mb4");

// ✅ تأكد إن الحجز يخص هالمستخدم
$sqlCheck = "SELECT id, COALESCE(status,'') AS status FROM bookings WHERE id=? AND customer_id=? LIMIT 1";
if (!$st = $conn->prepare($sqlCheck)) {
  jres(false, 'SQL prepare failed (check).');
}
$st->bind_param("ii", $bookingId, $uid);
$st->execute();
$rs = $st->get_result();
$booking = $rs ? $rs->fetch_assoc() : null;
$st->close();

if (!$booking) {
  $conn->close();
  jres(false, 'Booking not found.');
}

// ✅ نفّذ الإلغاء (تحديث الحالة لإلغاء)
// لو حاب “تمسح السطر” فعلاً بدل التحديث، بدّل الكويري بـ DELETE (معلّق تحت)
$sqlCancel = "UPDATE bookings SET status='cancelled' WHERE id=? AND customer_id=? AND (status IS NULL OR status <> 'cancelled')";
if (!$st2 = $conn->prepare($sqlCancel)) {
  $conn->close();
  jres(false, 'SQL prepare failed (cancel).');
}
$st2->bind_param("ii", $bookingId, $uid);
$st2->execute();
$affected = $st2->affected_rows;
$st2->close();
$conn->close();

// ✅ تم الإلغاء (أو كان مُلغى من قبل)
if ($affected > 0 || strtolower($booking['status']) === 'cancelled') {
  jres(true);
}

// إذا وصلنا هنا، ما صار تغيير (ربما مشكلة صلاحيات/حالة)
jres(false, 'Could not cancel the booking.');

/* 
// بديل “حذف نهائي” بدل تحديث الحالة:
$sqlDel = "DELETE FROM bookings WHERE id=? AND customer_id=?";
...
*/