<?php
session_start();

/* إعادة إرسال الكود */
if (isset($_GET['resend'])) {
  $_SESSION['otp'] = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $_SESSION['otp_expires'] = time() + 300; // 5 دقائق
  echo "<script>
          alert('📩 تم إرسال كود جديد: {$_SESSION['otp']}');
          window.location.href='../rest_sacend.html';
        </script>";
  exit;
}

/* تحقق أن الكود موجود ولم تنتهِ صلاحيته */
if (
  empty($_SESSION['otp']) ||
  empty($_SESSION['otp_expires']) ||
  time() > $_SESSION['otp_expires']
) {
  echo "<script>alert('انتهت صلاحية الكود أو غير موجود. أرسلي من جديد.'); 
        window.location.href='../rest_start.html';</script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $digits = [];
  for ($i=1; $i<=6; $i++) { $digits[] = trim($_POST['d'.$i] ?? ''); }
  $code = implode('', $digits);

  if (strlen($code) !== 6 || !ctype_digit($code)) {
    echo "<script>alert('الرجاء إدخال 6 أرقام صحيحة.'); history.back();</script>"; exit;
  }

  if ($code === $_SESSION['otp']) {
    unset($_SESSION['otp'], $_SESSION['otp_expires']);
    header("Location: ../rest_third.html");
     exit;
     
  } else {
    echo "<script>alert('الكود غير صحيح. حاول مرة أخرى.'); 
          window.location.href='../rest_sacend.html';</script>";
    exit;
  }
} else {
  header("Location: ../rest_sacend.html"); exit;
}