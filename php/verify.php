<?php
// php/verify.php
session_start();

$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) { die("DB Error: " . $conn->connect_error); }
$conn->set_charset('utf8mb4');

// ===== إعادة إرسال كود =====
if (isset($_GET['resend'])) {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = $otp;

    // ⛳️ أثناء التطوير: أظهري الكود في الـ alert
    echo "<script>
      alert('📩 تم إرسال كود جديد (محاكاة)\\nOTP: {$otp}');
      window.location.href='../verify.html';
    </script>";
    exit;
}

// ===== استلام الكود من النموذج =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // اجمع 6 خانات d1..d6 — تأكدي إن حقولك فيها name='d1' ... 'd6'
    $code = '';
    for ($i = 1; $i <= 6; $i++) {
        $code .= isset($_POST['d'.$i]) ? trim($_POST['d'.$i]) : '';
    }

    // تحقق من الشكل
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        echo "<script>
          alert('⚠️ أدخلي 6 أرقام صحيحة.');
          window.location.href='../verify.html';
        </script>";
        exit;
    }

    // لو ما في OTP بسبب انتهاء الجلسة — نولد واحد جديد ونظهره
    if (empty($_SESSION['otp'])) {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp'] = $otp;

        echo "<script>
          alert('🔄 انتهت جلسة الكود. تم إنشاء كود جديد (محاكاة)\\nOTP: {$otp}');
          window.location.href='../verify.html';
        </script>";
        exit;
    }

    // مقارنة
    if ($code === $_SESSION['otp']) {
        // نجاح
        unset($_SESSION['otp']);
        header("Location: ../success.html");
        exit;
    } else {
        // خطأ — نولد OTP جديد ونظهره
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp'] = $otp;

        echo "<script>
          alert('❌ الكود غير صحيح. تم إرسال كود جديد (محاكاة)\\nOTP: {$otp}');
          window.location.href='../verify.html';
        </script>";
        exit;
    }
}

// أي وصول آخر يرجع لصفحة التحقق
header("Location: ../verify.html");
exit;