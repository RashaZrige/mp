<?php
session_start();
$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) { die("فشل الاتصال: " . $conn->connect_error); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $phone = trim($_POST['phone'] ?? '');

    $res = $conn->query("SELECT id FROM users WHERE phone = '".$conn->real_escape_string($phone)."' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        // ولّدي كود 6 أرقام
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // خزن الكود بالسيشن وصلاحيته 5 دقايق
        $_SESSION['otp']         = $otp;
        $_SESSION['otp_expires'] = time() + 300;
        $_SESSION['reset_phone'] = $phone;

        // ✅ للعرض أثناء التطوير: بين الكود برسالة alert
        echo "<script>alert('كود التحقق هو: $otp'); window.location.href='../rest_sacend.html';</script>";
        exit;
    } else {
        echo "<script>alert('⚠️ الرقم غير موجود'); window.history.back();</script>";
        exit;
    }
} else {
    header("Location: ../rest_start.html");
    exit;
}