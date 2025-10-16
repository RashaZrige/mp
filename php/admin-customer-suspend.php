<?php
/* admin_customer_suspend.php — Soft delete customer */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location:admin-customers.php?err=Invalid customer id");
  exit;
}

$sql = "UPDATE users SET is_deleted=1 WHERE id=? AND role='customer'";
$st = $conn->prepare($sql);
if (!$st) {
  header("Location:admin-customers.php?err=Prepare failed: ".$conn->error);
  exit;
}
$st->bind_param("i", $id);
if ($st->execute() && $st->affected_rows > 0) {
  $st->close();
  $conn->close();
  header("Location:admin-customers.php?ok=Customer deleted successfully");
} else {
  $st->close();
  $conn->close();
  header("Location:admin-customers.php?err=Delete failed or already deleted");
}