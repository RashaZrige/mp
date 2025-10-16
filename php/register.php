<?php
session_start();

$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $phone     = $_POST['phone'];
    $address   = $_POST['address'];
    $password  = $_POST['password'];
    $confirm   = $_POST['password_confirm'];
    $role      = $_POST['role'];

    // تأكيد كلمة المرور
    if ($password !== $confirm) {
        echo "<script>alert('⚠️ كلمة المرور غير متطابقة!');history.back();</script>";
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // التحقق لو الرقم موجود مسبقًا
    $check = $conn->query("SELECT * FROM users WHERE phone = '$phone'");
    if ($check->num_rows > 0) {
        echo "<script>alert('⚠️ هذا الرقم مسجّل مسبقًا!');history.back();</script>";
        exit;
    }

    // إدخال البيانات
    $sql = "INSERT INTO users (full_name, phone, address, role, password_hash)
            VALUES ('$full_name', '$phone', '$address', '$role', '$password_hash')";

    if ($conn->query($sql) === TRUE) {
        // ✅ نولّد كود تحقق
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = (string)$otp;
        $_SESSION['otp_expires'] = time() + 300; // صلاحية 5 دقائق
        $_SESSION['verify_phone'] = $phone; // نخزن رقم المستخدم

        // 🔔 نعرض الكود (بدل SMS حالياً)
        echo "<script>
            alert('✅ كود التحقق الخاص بك هو: $otp');
            window.location.href='../verify.html';
        </script>";
        exit;
    } else {
        echo "خطأ: " . $conn->error;
    }
}

$conn->close();
?>