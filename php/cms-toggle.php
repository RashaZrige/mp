<?php
// cms_toggle.php
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); echo json_encode(['ok'=>false]); exit; }
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$stmt = $conn->prepare("UPDATE cms_pages SET visible = 1 - visible, updated_at = NOW() WHERE id = ? LIMIT 1");
$stmt->bind_param("i",$id);
$stmt->execute();
$stmt->close();

$res = $conn->query("SELECT visible FROM cms_pages WHERE id={$id} LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
$conn->close();

echo $row ? json_encode(['ok'=>true,'visible'=>(int)$row['visible']]) : json_encode(['ok'=>false]);