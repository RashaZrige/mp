lop <?php
// php/rest_third.php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: ../rest_third.html");
  exit;
}

$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['password_confirm'] ?? '');

// لو الحقول فاضية
if ($password === '' || $confirm === '') {
  echo "<script>alert('⚠️ الرجاء إدخال كلمة المرور والتأكيد.'); window.location.href='../fail_rest.html';</script>";
  exit;
}

// لو مش متطابقين
if ($password !== $confirm) {
  echo "<script>alert('❌ كلمتا المرور غير متطابقتين.'); window.location.href='../fail_rest.html';</script>";
  exit;
}

// تحقق من الشروط الثلاثة
$errors = [];
if (strlen($password) < 8) $errors[] = "• لا يقل عن 8 أحرف";
if (!preg_match('/[A-Z]/', $password)) $errors[] = "• يحتوي على حرف كبير (A–Z)";
if (!preg_match('/\d/', $password))   $errors[] = "• يحتوي على رقم";

if (!empty($errors)) {
  $msg = "كلمة المرور غير قوية. يجب أن تتوفر الشروط التالية:\n" . implode("\n", $errors);
  echo "<script>alert(".json_encode($msg, JSON_UNESCAPED_UNICODE)."); window.location.href='../fail_rest.html';</script>";
  exit;
}

// حفظ الباسورد
$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
  echo "<script>alert('خطأ في الاتصال بقاعدة البيانات.'); window.location.href='../fail_rest.html';</script>";
  exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// مؤقت: تحديث آخر مستخدم
$stmt = $conn->prepare("UPDATE users SET password_hash = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $hash);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if ($ok) {
  header("Location: ../success_rest.html");
  exit;
}

header("Location: ../fail_rest.html");
exit;