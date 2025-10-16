<?php
// mp/php/mark_notifications_read.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success'=>false, 'message'=>'login_required']);
  exit;
}

$uid  = (int)$_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'] ?? 'customer')); // customer | provider

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false, 'message'=>'method_not_allowed']);
  exit;
}

$mysqli = new mysqli("localhost", "root", "", "fixora");
if ($mysqli->connect_error) {
  echo json_encode(['success'=>false, 'message'=>'db_failed: '.$mysqli->connect_error]);
  exit;
}
$mysqli->set_charset('utf8mb4');

// اختياري: تعليم إشعار واحد عبر POST id
$notif_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

/*
  قواعد التعليم:
  - المزود: يعلّم فقط إشعارات new_booking الخاصة بحجوزاته (n.user_id=b.provider_id AND n.type='new_booking')
  - العميل: يعلّم فقط booking_confirmed / job_started / job_completed / booking_cancelled الخاصة بحجوزه (n.user_id=b.customer_id)
*/

if ($role === 'provider') {
  // ======= المزوّد =======
  if ($notif_id > 0) {
    $sql = "
      UPDATE notifications n
      JOIN bookings b ON b.id = n.booking_id
      SET n.is_read = 1
      WHERE n.id = ?
        AND n.user_id = ?
        AND n.is_active = 1
        AND n.is_read = 0
        AND n.type = 'new_booking'
        AND n.user_id = b.provider_id
    ";
    $st = $mysqli->prepare($sql);
    $st->bind_param("ii", $notif_id, $uid);
  } else {
    $sql = "
      UPDATE notifications n
      JOIN bookings b ON b.id = n.booking_id
      SET n.is_read = 1
      WHERE n.user_id = ?
        AND n.is_active = 1
        AND n.is_read = 0
        AND n.type = 'new_booking'
        AND n.user_id = b.provider_id
    ";
    $st = $mysqli->prepare($sql);
    $st->bind_param("i", $uid);
  }
} else {
  // ======= العميل =======
  // الأنواع المسموحة للعميل
  $allowed = ['booking_confirmed','job_started','job_completed','booking_cancelled'];
  $inPlace = implode(',', array_fill(0, count($allowed), '?'));
  $typesBind = str_repeat('s', count($allowed));

  if ($notif_id > 0) {
    $sql = "
      UPDATE notifications n
      JOIN bookings b ON b.id = n.booking_id
      SET n.is_read = 1
      WHERE n.id = ?
        AND n.user_id = ?
        AND n.is_active = 1
        AND n.is_read = 0
        AND n.type IN ($inPlace)
        AND n.type <> 'new_booking'
        AND n.user_id = b.customer_id
    ";
    $st = $mysqli->prepare($sql);
    // bind: id, uid, types...
    $bindTypes = "ii".$typesBind;
    $bindVals  = array_merge([$notif_id, $uid], $allowed);
    $args = array_merge([$bindTypes], $bindVals);
    // call_user_func_array helper
    $refs = [];
    foreach ($args as $k=>$v){ $refs[$k]=&$args[$k]; }
    call_user_func_array([$st,'bind_param'], $refs);
  } else {
    $sql = "
      UPDATE notifications n
      JOIN bookings b ON b.id = n.booking_id
      SET n.is_read = 1
      WHERE n.user_id = ?
        AND n.is_active = 1
        AND n.is_read = 0
        AND n.type IN ($inPlace)
        AND n.type <> 'new_booking'
        AND n.user_id = b.customer_id
    ";
    $st = $mysqli->prepare($sql);
    $bindTypes = "i".$typesBind;
    $bindVals  = array_merge([$uid], $allowed);
    $args = array_merge([$bindTypes], $bindVals);
    $refs = [];
    foreach ($args as $k=>$v){ $refs[$k]=&$args[$k]; }
    call_user_func_array([$st,'bind_param'], $refs);
  }
}

$ok = $st && $st->execute();
$affected = $ok ? $st->affected_rows : 0;
if ($st) $st->close();

/* رجّع العداد الجديد بعد التعليم بنفس القيود */
if ($role === 'provider') {
  $cntSql = "
    SELECT COUNT(*) AS c
    FROM notifications n
    JOIN bookings b ON b.id = n.booking_id
    WHERE n.user_id = ?
      AND n.is_active = 1
      AND n.is_read = 0
      AND n.type = 'new_booking'
      AND n.user_id = b.provider_id
  ";
  $st2 = $mysqli->prepare($cntSql);
  $st2->bind_param("i", $uid);
} else {
  $allowed = ['booking_confirmed','job_started','job_completed','booking_cancelled'];
  $inPlace = implode(',', array_fill(0, count($allowed), '?'));
  $typesBind = str_repeat('s', count($allowed));

  $cntSql = "
    SELECT COUNT(*) AS c
    FROM notifications n
    JOIN bookings b ON b.id = n.booking_id
    WHERE n.user_id = ?
      AND n.is_active = 1
      AND n.is_read = 0
      AND n.type IN ($inPlace)
      AND n.type <> 'new_booking'
      AND n.user_id = b.customer_id
  ";
  $st2 = $mysqli->prepare($cntSql);
  $bindTypes = "i".$typesBind;
  $bindVals  = array_merge([$uid], $allowed);
  $args = array_merge([$bindTypes], $bindVals);
  $refs = [];
  foreach ($args as $k=>$v){ $refs[$k]=&$args[$k]; }
  call_user_func_array([$st2,'bind_param'], $refs);
}

$st2->execute();
$cnt = (int)($st2->get_result()->fetch_assoc()['c'] ?? 0);
$st2->close();

echo json_encode([
  'success'      => (bool)$ok,
  'updated'      => $affected,
  'unread_count' => $cnt
], JSON_UNESCAPED_UNICODE);

$mysqli->close();