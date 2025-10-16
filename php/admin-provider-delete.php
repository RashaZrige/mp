<?php
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// استقبل id من POST أو GET (لتجربة سريعة من المتصفح)
$user_id = 0;
if (isset($_POST['user_id'])) $user_id = (int)$_POST['user_id'];
elseif (isset($_GET['id']))   $user_id = (int)$_GET['id'];

if ($user_id <= 0) {
  header("Location:admin-providers.php?err=" . rawurlencode("No user_id received"));
  exit;
}

// تأكد أنه مزود غير محذوف
$st = $conn->prepare("SELECT id FROM users WHERE id=? AND role='provider' AND is_deleted=0 LIMIT 1");
if (!$st) {
  header("Location:admin-providers.php?err=" . rawurlencode("Prepare check failed: ".$conn->error));
  exit;
}
$st->bind_param("i", $user_id);
$st->execute();
$exists = $st->get_result()->num_rows > 0;
$st->close();

if (!$exists) {
  header("Location:admin-providers.php?err=" . rawurlencode("Provider not found or already deleted"));
  exit;
}

$conn->begin_transaction();
try {
  // soft delete + إيقاف
  $st = $conn->prepare("UPDATE users SET is_deleted=1, status='suspended' WHERE id=?");
  if (!$st) throw new Exception("Prepare update users failed: ".$conn->error);
  $st->bind_param("i", $user_id);
  if (!$st->execute()) throw new Exception("Execute update users failed: ".$st->error);
  if ($st->affected_rows <= 0) throw new Exception("No rows updated");
  $st->close();

  // عطّل توفره بالبروفايل (لو موجود)
  if ($st = $conn->prepare("UPDATE provider_profiles SET is_available=0, updated_at=NOW() WHERE user_id=?")) {
    $st->bind_param("i", $user_id);
    $st->execute();
    $st->close();
  }

  $conn->commit();
  header("Location:admin-providers.php?ok=" . rawurlencode("Provider deleted (soft)"));
} catch (Throwable $e) {
  $conn->rollback();
  header("Location:admin-providers.php?err=" . rawurlencode("DB error: ".$e->getMessage()));
}