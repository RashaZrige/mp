<?php
// book.php
session_start();

// افترضنا إنك بتحط علامة "logged_in" بالسيشن بعد ما يعمل Login
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // ✅ المستخدم مسجّل دخول → يروح على صفحة الحجز مباشرة
    header("Location: services.php"); 
    exit;
} else {
    // ❌ مش مسجّل → يروح على صفحة welcome.html
    header("Location: ../welcome.html"); 
    exit;
}