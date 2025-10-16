<?php
session_start(); // ✅ لازم يكون أول سطر
$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone    = $_POST['phone'];
    $password = $_POST['password'];// 🔎 افحص إذا الرقم موجود
    $check = $conn->query("SELECT * FROM users WHERE phone = '$phone' LIMIT 1");
    if ($check->num_rows == 0) {
        echo "⚠️ هذا الرقم غير موجود!";
        exit;
    }
    $row = $check->fetch_assoc();
 // ✅ افحص كلمة المرور (مقارنة مع الهاش)
if (password_verify($password, $row['password_hash'])) {
    // ✅ خزّن معلومات المستخدم في السيشن
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['role']    = $row['role'];
    $_SESSION['phone']   = $row['phone'];
    // ===== التوجيه حسب نوع الحساب =====
    if ($row['role'] === 'admin') {
        header("Location:dashboard admin.php");
        exit;
    } elseif ($row['role'] === 'customer') {
        header("Location: viewmore.php");
        exit;
    } elseif ($row['role'] === 'provider') {
        $uidTmp = (int)$row['id'];
        $needsOnboarding = true; // نفترض يحتاج ستِب1 ما لم يثبت العكس// نجلب الحقول اللازمة من provider_profiles
        if ($st = $conn->prepare("
            SELECT 
              COALESCE(address,''), 
              COALESCE(avatar_path,''),
              COALESCE(step1_done,0),
              COALESCE(step2_done,0),
              COALESCE(step3_done,0),
              COALESCE(step4_done,0),
              COALESCE(step5_done,0),
              COALESCE(terms_accepted,0)
            FROM provider_profiles
            WHERE user_id = ?
            LIMIT 1
        ")) {
            $st->bind_param("i", $uidTmp);
            $st->execute();
            $st->bind_result($addr,$avatar,$s1,$s2,$s3,$s4,$s5,$terms);

            if ($st->fetch()) {
                // شرط الاكتمال: كل الستبس =1 + موافقة الشروط + عنوان وصورة
                $allSteps  = ((int)$s1 === 1 && (int)$s2 === 1 && (int)$s3 === 1 && (int)$s4 === 1 && (int)$s5 === 1);
                $hasAddr   = trim($addr)   !== '';
                $hasAvatar = trim($avatar) !== '';
                $accepted  = ((int)$terms === 1);

                if ($allSteps && $accepted && $hasAddr && $hasAvatar) {
                    $needsOnboarding = false;
                }
            }
            $st->close();
        }

        // التوجيه النهائي
        if ($needsOnboarding) {
            header("Location: provider-step1.php");
        } else {
            header("Location:dashboard.php");
        }
        exit;

    } else {
        echo "⚠️ نوع الحساب غير معروف!";
    }

} else {
    echo "⚠️ كلمة المرور غير صحيحة!";
}
    // ✅ افحص كلمة المرور (مقارنة مع الهاش)
}

$conn->close();
?>