<?php
// order_delete.php — Soft Delete
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

// لازم نتأكد إنه الأدمن (ممكن تغير حسب نظامك)
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin') { die("Forbidden"); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location:admin-order.php?err=invalid"); exit; }

$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// نعمل Soft Delete
$sql = "UPDATE bookings SET is_deleted = 1 WHERE id=? LIMIT 1";
$st = $conn->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$st->close();
$conn->close();

header("Location:admin-order.php?deleted=1");
exit;