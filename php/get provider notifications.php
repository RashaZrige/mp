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

$unread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE provider_id={$uid} AND is_read=0")->fetchColumn();

$st = $pdo->prepare("
  SELECT id, title, COALESCE(body,'') AS body, created_at, is_read
  FROM notifications
  WHERE provider_id = :pid
  ORDER BY created_at DESC, id DESC
  LIMIT 50
");
$st->execute([':pid'=>$uid]);
$items = $st->fetchAll();

echo json_encode(['success'=>true,'unread_count'=>$unread,'items'=>$items], JSON_UNESCAPED_UNICODE);