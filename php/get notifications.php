<?php
// mp/php/get_notifications.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'login_required']);
  exit;
}

$uid  = (int)$_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'] ?? 'customer')); // customer | provider

$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
  echo json_encode(['success'=>false, 'message'=>'db_failed: '.$conn->connect_error]);
  exit;
}
$conn->set_charset('utf8mb4');

/* (اختياري) وضع تصحيح سهل */
if (isset($_GET['debug'])) {
  echo json_encode(['uid'=>$uid, 'role'=>$role, 'session'=>$_SESSION], JSON_UNESCAPED_UNICODE);
  $conn->close();
  exit;
}

/* limit */
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;
if ($limit < 1)   $limit = 1;
if ($limit > 100) $limit = 100;

if ($role === 'provider') {
  // ======= المــزود =======
  // LIST
  $sql = "
    SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at
    FROM notifications n
    JOIN bookings b ON b.id = n.booking_id
    WHERE n.user_id = ?
      AND n.is_active = 1
      AND n.type = 'new_booking'
      AND n.booking_id IS NOT NULL
      AND n.user_id = b.provider_id
    ORDER BY n.created_at DESC, n.id DESC
    LIMIT $limit
  ";
  $st = $conn->prepare($sql);
  if (!$st) { echo json_encode(['success'=>false,'message'=>'prepare_failed: '.$conn->error]); $conn->close(); exit; }
  $st->bind_param('i', $uid);
  $st->execute();
  $res = $st->get_result();

  $list = [];
  while ($r = $res->fetch_assoc()) {
    $r['id']      = (int)$r['id'];
    $r['is_read'] = (int)$r['is_read'];
    $list[] = $r;
  }
  $st->close();

  // COUNT unread
  $cntSql = "
    SELECT COUNT(*) AS c
    FROM notifications n
    JOIN bookings b ON b.id = n.booking_id
    WHERE n.user_id = ?
      AND n.is_active = 1
      AND n.is_read = 0
      AND n.type = 'new_booking'
      AND n.booking_id IS NOT NULL
      AND n.user_id = b.provider_id
  ";
  $st2 = $conn->prepare($cntSql);
  if (!$st2) { echo json_encode(['success'=>false,'message'=>'cnt_prepare_failed: '.$conn->error]); $conn->close(); exit; }
  $st2->bind_param('i', $uid);
  $st2->execute();
  $cntRow = $st2->get_result()->fetch_assoc();
  $cnt    = (int)($cntRow['c'] ?? 0);
  $st2->close();

} else {
  // ======= العــميل - كود مبسط وآمن =======
  
  // LIST
  $sql = "
    SELECT id, title, message, type, is_read, created_at
    FROM notifications 
    WHERE user_id = ?
      AND is_active = 1
      AND type IN ('booking_confirmed','job_started','job_completed','booking_cancelled')
    ORDER BY created_at DESC
    LIMIT $limit
  ";
  
  $st = $conn->prepare($sql);
  $st->bind_param('i', $uid);
  $st->execute();
  $res = $st->get_result();

  $list = [];
  while ($r = $res->fetch_assoc()) {
    $r['id'] = (int)$r['id'];
    $r['is_read'] = (int)$r['is_read'];
    $list[] = $r;
  }
  $st->close();

  // COUNT
  $cntSql = "
    SELECT COUNT(*) AS c 
    FROM notifications 
    WHERE user_id = ? 
      AND is_active = 1 
      AND is_read = 0 
      AND type IN ('booking_confirmed','job_started','job_completed','booking_cancelled')
  ";
  $st2 = $conn->prepare($cntSql);
  $st2->bind_param('i', $uid);
  $st2->execute();
  $cntRow = $st2->get_result()->fetch_assoc();
  $cnt = (int)($cntRow['c'] ?? 0);
  $st2->close();
}
echo json_encode([
  'success'       => true,
  'unread_count'  => $cnt,
  'notifications' => $list
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>