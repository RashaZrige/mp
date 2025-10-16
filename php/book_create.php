<?php
// mp/php/book_create.php
session_start();

// تأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.html");
  exit;
}
$customer_id = (int)$_SESSION['user_id'];

try {
  $pdo = new PDO(
    "mysql:host=localhost;dbname=fixora;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (Throwable $e) {
  http_response_code(500);
  die("DB Error: ".$e->getMessage());
}

// استلام القيم من النموذج
$service_id  = (int)($_POST['service_id'] ?? 0);
$phone       = trim($_POST['phone'] ?? '');
$address     = trim($_POST['address'] ?? '');
$date        = trim($_POST['date'] ?? '');
$time        = trim($_POST['time'] ?? '');
$problem     = trim($_POST['problem'] ?? '');

// تحقق أساسي
$errors = [];
if ($service_id <= 0)                  $errors[] = "Invalid service.";
if ($phone === '')                     $errors[] = "Phone is required.";
if ($address === '')                   $errors[] = "Address is required.";
if ($date === '' || $time === '')      $errors[] = "Date & time required.";

if ($errors) {
  $_SESSION['book_err'] = implode(" | ", $errors);
  header("Location: service.php?id=".$service_id);
  exit;
}

// جلب المزوّد الحقيقي والتأكد من أنه مزوّد فعّال والخدمة فعالة
$sql = "
  SELECT s.provider_id
  FROM services s
  JOIN users u ON u.id = s.provider_id
  WHERE s.id = :sid
    AND u.role = 'provider'
    AND u.status = 'active'
    AND s.is_active = 1
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':sid' => $service_id]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  $_SESSION['book_err'] = "This service is unavailable or the provider is suspended.";
  header("Location: service.php?id=".$service_id);
  exit;
}
$provider_id = (int)$row['provider_id'];

// صياغة التاريخ والوقت
$scheduled_at_str = $date.' '.$time.':00';
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_at_str);
if (!$dt) {
  $_SESSION['book_err'] = "Invalid datetime format.";
  header("Location: service.php?id=".$service_id);
  exit;
}
$scheduled_at = $dt->format('Y-m-d H:i:s');

// اختيار status أولي صالح حسب ENUM الجدول
$status = 'pending';
$col = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
if ($col && preg_match("~^enum\\((.*)\\)$~i", $col['Type'], $m)) {
  $vals = str_getcsv($m[1], ',', "'");
  if (!in_array($status, $vals, true)) $status = $vals[0];
}

require_once 'notify.php';

// إدخال الحجز
$ins = $pdo->prepare("
  INSERT INTO bookings
    (customer_id, provider_id, service_id, phone, address, scheduled_at, problem_text, status, created_at)
  VALUES
    (:cid, :pid, :sid, :ph, :addr, :dt, :prob, :st, NOW())
");
$ins->execute([
  ':cid'  => $customer_id,
  ':pid'  => $provider_id,
  ':sid'  => $service_id,
  ':ph'   => $phone,
  ':addr' => $address,
  ':dt'   => $scheduled_at,
  ':prob' => $problem,
  ':st'   => $status,
]);
$booking_id = (int)$pdo->lastInsertId();

/* ===== إشعار للمزوّد فقط: new_booking ===== */
$service_stmt = $pdo->prepare("SELECT title FROM services WHERE id = ? LIMIT 1");
$service_stmt->execute([$service_id]);
$service_name = $service_stmt->fetchColumn() ?: 'الخدمة';

$display_stmt = $pdo->prepare("SELECT COALESCE(full_name, email, phone) FROM users WHERE id = ? LIMIT 1");
$display_stmt->execute([$customer_id]);
$customer_name = $display_stmt->fetchColumn() ?: ('User #'.$customer_id);

// يمكنك استخدام الاختصار إن أردت:
// notify_provider_new_booking($pdo, $provider_id, $booking_id, $service_name, $customer_name);

notify_pdo(
  $pdo,
  $provider_id,              // المستقبِل = المزوّد
  'new_booking',             // مطابق لقيم ENUM في notifications.type
  'حجز جديد 🎉',
  "تم حجز خدمتك '{$service_name}' بواسطة {$customer_name}. رقم الحجز #{$booking_id}",
  $booking_id
);


notify_pdo(
  $pdo,
  $customer_id,
  'booking_confirmed',  // ✅ نوع إشعار العميل الصحيح
  'تم الحجز بنجاح ✅',
  "تم حجز خدمة '{$service_name}' بنجاح. رقم حجزك #{$booking_id} - سنتواصل معك قريباً",
  $booking_id
);


/* إعادة التوجيه لصفحة حجوزات العميل */
header("Location: /mp/php/my_booking.php?tab=upcoming&success=1&booking_id=".$booking_id);
exit;



