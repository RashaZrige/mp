<?php
session_start();

// امسح كل بيانات الجلسة
$_SESSION = [];

// امسح الكوكيز إذا موجودة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// دمّر الجلسة
session_destroy();

// رجّع المستخدم لصفحة تسجيل الدخول
header("Location: ../login.html");
exit;