<?php
// cms_boot.php  (ضعِي الملف في نفس المجلد مع باقي ملفات الCMS)

session_start();

// مسار موقعك الأساسي (عدّليه لو لزم)
$BASE = '/mp';

/* ===== التحقق من جلسة الأدمن ===== */
if (empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
  // رجوع لتسجيل الدخول لو مش أدمن
  header("Location: {$BASE}/login.html");
  exit;
}

/* ===== اتصال قاعدة البيانات ===== */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
  http_response_code(500);
  die("DB failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===== دوال مساعدة بسيطة ===== */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }