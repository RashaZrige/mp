
<?php
session_start();
if (!isset($_SESSION['user_id'])) { die('No user logged in'); }
$user_id = (int)$_SESSION['user_id'];

/* اتصال PDO */
try {
  $pdo = new PDO("mysql:host=localhost;dbname=fixora;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch(Exception $e){
  die("DB Error: ".$e->getMessage());
}

/* لو ضغط Previous → رجّعه للخطوة 3 بدون أي تحقق */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['go']) && $_POST['go']==='prev') {
  header("Location: provider-step3.php");
  exit;
}

$errors = [];
$ok     = false;

/* معالجة حفظ عند Next */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['go']) && $_POST['go']==='next') {

  // لازم يوافق على الشروط
  if (empty($_POST['agree'])) {
    $errors[] = "Please agree to Terms & Privacy.";
  }

  $password = trim($_POST['password'] ?? '');
  $confirm  = trim($_POST['confirm_password'] ?? '');

  // لو بدو يغيّر كلمة السر
  if ($password !== '' || $confirm !== '') {
    if (strlen($password) < 5) {
      $errors[] = "Password must be at least 5 characters.";
    }
    if ($password !== $confirm) {
      $errors[] = "Password and confirmation do not match.";
    }
  }

  if (empty($errors)) {
    $pdo->beginTransaction();
    try {
      // إذا أدخل باسورد جديد → حدّثه
      if ($password !== '' && $password === $confirm) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $u = $pdo->prepare("UPDATE users SET password_hash=:h WHERE id=:id");
        $u->execute([':h'=>$hash, ':id'=>$user_id]);
      }

      // علّم أن الخطوة 4 تمت
      $pp = $pdo->prepare("
        INSERT INTO provider_profiles (user_id, step4_done, created_at, updated_at)
        VALUES (:id, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE step4_done=1, updated_at=NOW()
      ");
      $pp->execute([':id'=>$user_id]);

      $pdo->commit();
      $ok = true;
      header("Location: provider-step5.php");
      exit;
    } catch (Exception $e){
      $pdo->rollBack();
      $errors[] = "Save failed: ".$e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Complete Your Provider Profile</title>
  <link rel="stylesheet" href="../css/provider-step4.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>
<main class="page">
  <section class="card">
    <div class="card-title">
      <h1>Complete Your Provider Profile</h1>
      <ol class="steps">
        <li class="step is-done"><div class="dot">01</div><small>Basic Information</small></li>
        <li class="step is-done"><div class="dot">02</div><small>identification</small></li>
        <li class="step is-done"><div class="dot">03</div><small>Services</small></li>
        <li class="step is-active"><div class="dot">04</div><small>security</small></li>
      </ol>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="margin:0 28px 12px;padding:12px 14px;border:1px solid #fecaca;background:#fee2e2;color:#7f1d1d;border-radius:8px;">
        <strong>Please fix the following:</strong>
        <ul style="margin:8px 0 0 18px;">
          <?php foreach($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form class="body" method="post">
      <h2 class="section-title">Account Security</h2>

      <div class="field">
        <label>Password</label>
        <div class="with-icon">
          <i class="fa-solid fa-lock"></i>
          <!-- ما بنقدر نظهر الباسورد الحقيقي؛ اتركه فاضي ولو تركه المستخدم فاضي بنبقي القديم -->
          <input type="password" name="password" placeholder="Create Password" />
        </div>
      </div>

      <div class="field">
        <label>Confirm password</label>
        <div class="with-icon">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="confirm_password" placeholder="Confirm password" />
        </div>
      </div>

<div class="field checkbox">
        <label>
          <input type="checkbox" name="agree" value="1" />
          By signing in, you're agree to our
          <a href="#">Terms & Condition</a> and
          <a href="#">Privacy Policy</a>.
        </label>
      </div>

      <div class="actions">
        <!-- Previous: لا يتحقق من الحقول -->
        <button type="submit" class="btn prev" name="go" value="prev" formnovalidate>
          Previous
        </button>

        <!-- Next: يحفظ (إن تغيّر) ثم يوجّه للخطوة 5 -->
        <button type="submit" class="btn next" name="go" value="next">
          Next
        </button>
      </div>
    </form>
  </section>
</main>
</body>
</html>