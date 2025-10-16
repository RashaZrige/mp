<?php
// /mp/provider/service-update.php
session_start();
header('Content-Type: application/json; charset=utf-8');

/* ===== [حماية الجلسة والطريقة] ===== */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

$uid = (int)$_SESSION['user_id'];

/* ===== [قراءة القيم] ===== */
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = trim($_POST['title'] ?? '');
$price_from = isset($_POST['price_from']) ? (float)$_POST['price_from'] : null;
$price_to = isset($_POST['price_to']) ? (float)$_POST['price_to'] : null;
$duration_minutes = isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

if ($id <= 0 || $title === '' || $price_from === null || $price_to === null || $duration_minutes === null) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}

if ($price_from < 0 || $price_to < 0 || $price_from > $price_to) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'price_range_invalid']); exit;
}

if ($duration_minutes <= 0) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'duration_invalid']); exit;
}

$is_active = ($is_active === 1) ? 1 : 0;

/* ===== DB ===== */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { 
    http_response_code(500); 
    echo json_encode(['ok'=>false,'error'=>'db_conn']); exit; 
}
$conn->set_charset('utf8mb4');

/* ===== [تأكيد ملكية الخدمة] ===== */
$old_img_path = null;
if ($st = $conn->prepare("SELECT img_path FROM services WHERE id=? AND provider_id=? AND is_deleted=0 LIMIT 1")) {
    $st->bind_param("ii", $id, $uid);
    $st->execute();
    $st->bind_result($old_img_path);
    if (!$st->fetch()) { 
        $st->close(); 
        $conn->close(); 
        http_response_code(403); 
        echo json_encode(['ok'=>false,'error'=>'forbidden']); 
        exit; 
    }
    $st->close();
} else { 
    $conn->close(); 
    http_response_code(500); 
    echo json_encode(['ok'=>false,'error'=>'prep_failed']); 
    exit; 
}

/* ===== [رفع صورة (اختياري]) ===== */
$BASE_URL = '/mp';
$uploadDirRel = 'uploads/services';
$uploadRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$BASE_URL;
$image_url = null;

if (!empty($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ((int)$_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed, true)) {
            $absUploads = $uploadRoot . '/' . $uploadDirRel;
            if (!is_dir($absUploads)) @mkdir($absUploads, 0775, true);
            $fname = 'svc_'.$id.''.date('YmdHis').''.bin2hex(random_bytes(3)).'.'.$ext;
            $abs = $absUploads . '/' . $fname;
            $rel = $uploadDirRel . '/' . $fname;
            if (@move_uploaded_file($_FILES['image']['tmp_name'], $abs)) {
                if ($old_img_path && !preg_match('~^https?://~i', $old_img_path)) {
                    $oldAbs = $uploadRoot . '/' . ltrim(str_replace('\\','/',$old_img_path), '/');
                    if (is_file($oldAbs)) @unlink($oldAbs);
                }
                $old_img_path = $rel;
                $image_url = rtrim($BASE_URL, '/').'/'.$rel;
            }
        }
    }
}

/* ===== [تحديث] ===== */
if ($image_url !== null) {
    $sql = "UPDATE services 
            SET title=?, price_from=?, price_to=?, duration_minutes=?, is_active=?, img_path=?
            WHERE id=? AND provider_id=? AND is_deleted=0 LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("sddiissi", $title, $price_from, $price_to, $duration_minutes, $is_active, $old_img_path, $id, $uid);
} else {
    $sql = "UPDATE services 
            SET title=?, price_from=?, price_to=?, duration_minutes=?, is_active=?
            WHERE id=? AND provider_id=? AND is_deleted=0 LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("sddiiii", $title, $price_from, $price_to, $duration_minutes, $is_active, $id, $uid);
}

if (!$st || !$st->execute()) { 
    if ($st) $st->close();
    $conn->close(); 
    http_response_code(500); 
    echo json_encode(['ok'=>false,'error'=>'update_failed']); 
    exit; 
}

$st->close();

/* ===== [إحصائيات] ===== */
$active_count=$inactive_count=0; $avg_price=0.0;
if ($st = $conn->prepare("SELECT SUM(is_active=1), SUM(is_active=0) FROM services WHERE provider_id=? AND is_deleted=0")) {
    $st->bind_param("i", $uid); 
    $st->execute(); 
    $st->bind_result($active_count,$inactive_count); 
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

$total = max(1, $active_count + $inactive_count);
$pct = (int)round(($active_count / $total) * 100);

$out = [
    'ok'=>true,
    'active_count'=>(int)$active_count,
    'inactive_count'=>(int)$inactive_count,
    'pct_active'=>$pct,
    'avg_price'=>(float)$avg_price
];

if ($image_url !== null) $out['image_url'] = $image_url;

echo json_encode($out);
?>