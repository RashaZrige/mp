<?php
session_start();



$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// تنظيف بسيط
$q = strip_tags($q);
if (mb_strlen($q) > 120) {
  $q = mb_substr($q, 0, 120);
}

// تحويل بناءً على البحث
if ($q === '') {
  header("Location: ../php/viewmore.php");
} else {
  header("Location: ../php/viewmore.php?q=" . urlencode($q));
}
exit;