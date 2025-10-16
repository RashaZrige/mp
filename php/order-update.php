<?php
/* order_update.php — Save admin order edits safely */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* Helpers */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

/* Inputs */
$id           = (int) post('id', 0);
$provider_id  = (int) post('provider_id', 0);
$service_id   = (int) post('service_id', 0);
$phone        = post('phone', '');
$status       = post('status', '');
$notes        = post('notes', '');
$scheduled_in = post('scheduled_at', ''); // قد تكون من input datetime-local

if ($id <= 0) { header("Location: {$BASE}/php/admin-order.php?err=norecord"); exit; }

/* Allowed statuses (عدّل لو عندك حالات إضافية) */
$allowedStatuses = ['pending','confirmed','in_progress','completed','cancelled'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
  header("Location: {$BASE}/php/order_edit.php?id={$id}&err=bad_status");
  exit;
}

/* Normalize scheduled_at إلى صيغة MySQL DATETIME لو مُدخلة */
$scheduled_at = null;
if ($scheduled_in !== '') {
  // يقبل "YYYY-MM-DDTHH:MM" أو "YYYY-MM-DD HH:MM[:SS]"
  $scheduled_in = str_replace('T',' ',$scheduled_in);
  $ts = strtotime($scheduled_in);
  if ($ts !== false) {
    $scheduled_at = date('Y-m-d H:i:s', $ts);
  }
}

/* جيب أعمدة جدول bookings عشان نحدّث بس الموجود */
$cols = [];
if ($res = $conn->query("SHOW COLUMNS FROM bookings")) {
  while($c = $res->fetch_assoc()){ $cols[$c['Field']] = true; }
  $res->free();
}

/* ابني SET ديناميكي حسب الأعمدة المتاحة */
$set = [];
$types = '';
$bind  = [];

/* provider_id */
if (isset($cols['provider_id']) && $provider_id > 0) {
  $set[]   = "provider_id = ?";
  $types  .= "i";
  $bind[]  = $provider_id;
}

/* service_id */
if (isset($cols['service_id']) && $service_id > 0) {
  $set[]   = "service_id = ?";
  $types  .= "i";
  $bind[]  = $service_id;
}

/* phone */
if (isset($cols['phone']) && $phone !== '') {
  $set[]   = "phone = ?";
  $types  .= "s";
  $bind[]  = $phone;
}

/* scheduled_at */
if (isset($cols['scheduled_at']) && $scheduled_at !== null) {
  $set[]   = "scheduled_at = ?";
  $types  .= "s";
  $bind[]  = $scheduled_at;
}

/* status */
if (isset($cols['status']) && $status !== '') {
  $set[]   = "status = ?";
  $types  .= "s";
  $bind[]  = $status;
}

/* notes (اختياري) */
if (isset($cols['notes']) && $notes !== '') {
  $set[]   = "notes = ?";
  $types  .= "s";
  $bind[]  = $notes;
}

/* updated_at لو موجود */
if (isset($cols['updated_at'])) {
  $set[] = "updated_at = NOW()";
}

/* إذا ما في ولا حقل للتحديث */
if (!$set) {
  header("Location: {$BASE}/php/order_edit.php?id={$id}&ok=0&msg=nothing_to_update");
  exit;
}

/* استثناء المحذوفين لو فيه soft delete */
$where = "WHERE id = ?";
$types .= "i";
$bind[]  = $id;
if (isset($cols['is_deleted'])) {
  $where .= " AND COALESCE(is_deleted,0) = 0";
}

/* نفّذ التحديث */
$sql = "UPDATE bookings SET ".implode(", ", $set)." {$where}";
$st  = $conn->prepare($sql);
if (!$st) { die("Prepare failed: ".$conn->error); }

/* ربط ديناميكي */
$bind_params = [];
$bind_params[] = &$types;
foreach ($bind as $k => $v) { $bind_params[] = &$bind[$k]; }
call_user_func_array([$st, 'bind_param'], $bind_params);

$ok = $st->execute();
$err= $st->error;
$st->close();
$conn->close();

/* Redirect */
if ($ok) {
  // ارجع لصفحة قائمة الطلبات أو صفحة التعديل—اختَر اللي بدك
  header("Location:order-edit.php?id={$id}&ok=1");
} else {
  header("Location:order-edit.php?id={$id}&ok=0&err=".rawurlencode($err));
}
exit;