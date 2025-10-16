<?php
// /mp/php/provider-status-toggle.php
header('Content-Type: application/json; charset=utf-8');
session_start();

$BASE = '/mp';

// مسموح للأدمن فقط
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Forbidden']);
    exit;
}

// اتصال DB
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'DB failed','error'=>$conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

// قراءة البيانات
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = strtolower(trim($_POST['status'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Missing provider id']);
    exit;
}

if (!in_array($status, ['active','suspended'])) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Invalid status value']);
    exit;
}

// تحديث الحالة
$stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? AND role='provider' LIMIT 1");
$stmt->bind_param("si", $status, $id);
$ok = $stmt->execute();
$aff = $stmt->affected_rows;
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Update failed']);
    exit;
}

echo json_encode(['ok'=>true,'id'=>$id,'status'=>$status,'affected'=>$aff]);