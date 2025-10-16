<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$uid  = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
if ($uid <= 0 || $role !== 'provider') {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'Unauthorized']);
  exit;
}

try {
  $pdo = new PDO("mysql:host=localhost;dbname=fixora;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'DB failed']); exit;
}

$st = $pdo->prepare("UPDATE notifications SET is_read=1 WHERE provider_id=:pid AND is_read=0");
$ok = $st->execute([':pid'=>$uid]);

echo json_encode(['success'=>$ok], JSON_UNESCAPED_UNICODE);