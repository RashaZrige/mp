
<?php
session_start();
if (!isset($_SESSION['user_id'])) { die('No user logged in'); }

$user_id = (int)$_SESSION['user_id'];

try {
  $pdo = new PDO("mysql:host=localhost;dbname=fixora;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (Exception $e) {
  die("DB Error: " . $e->getMessage());
}

/* =========[ 0) اجلب المستخدم من users (المصدر الوحيد للهاتف) ]========= */
$u = [];
$st = $pdo->prepare("SELECT id, full_name, phone, address FROM users WHERE id=? LIMIT 1");
$st->execute([$user_id]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) { die('User not found'); }

/* =========[ 1) حفظ عند POST ]========= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // لاحظ: لا نقرأ phone من POST أبداً
  $full_name = $_POST['full_name'] ?? null;
  $age       = ($_POST['age'] ?? '') !== '' ? (int)$_POST['age'] : null;
  $gender    = $_POST['gender'] ?? null;
  $email     = $_POST['email'] ?? null;
  $address   = $_POST['address'] ?? null;

  // رفع صورة (اختياري)
  $avatarPath = null;
  if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
      $newName   = 'avatar_'.$user_id.'_'.time().'.'.$ext;
      $targetRel = 'uploads/avatars/'.$newName;  // يخزن في DB (نسبي من مجلد المشروع)
      $targetFs  = '../'.$targetRel;             // مسار فعلي (لأن الملف داخل /php)
      if (!is_dir(dirname($targetFs))) { mkdir(dirname($targetFs), 0777, true); }
      if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFs)) {
        $avatarPath = $targetRel;
      }
    }
  }

  // upsert في provider_profiles
  $sql = "
    INSERT INTO provider_profiles
      (user_id, full_name, phone, age, gender, email, address, avatar_path, step1_done, created_at, updated_at)
    VALUES
      (:user_id, :full_name, :phone, :age, :gender, :email, :address, :avatar_path, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
      full_name    = VALUES(full_name),
      phone        = VALUES(phone),         -- الهاتف يؤخذ من users أدناه، لكن نخزّنه هنا للعرض
      age          = VALUES(age),
      gender       = VALUES(gender),
      email        = VALUES(email),
      address      = VALUES(address),
      avatar_path  = COALESCE(VALUES(avatar_path), avatar_path),
      step1_done   = 1,
      updated_at   = NOW()
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':user_id'     => $user_id,
    ':full_name'   => $full_name,
    ':phone'       => $u['phone'],   // ← ناخذه من users فقط
    ':age'         => $age,
    ':gender'      => $gender,
    ':email'       => $email,
    ':address'     => $address,
    ':avatar_path' => $avatarPath,
  ]);

  /* =========[ 2) مزامنة مع users (بدون الهاتف) ]========= */
  try {
    $sync = $pdo->prepare("
      UPDATE users
      SET full_name = :full_name,
          address   = :address
      WHERE id = :id
    ");
    $sync->execute([
      ':full_name' => ($full_name !== null && $full_name !== '') ? $full_name : $u['full_name'],
      ':address'   => ($address   !== null && $address   !== '') ? $address   : $u['address'],
      ':id'        => $user_id
    ]);
  } catch (Exception $e) {
    // تجاهل بهدوء
  }

  header("Location: provider-step2.php");
  exit;
}

/* =========[ 3) جلب بيانات provider_profiles + تحضير القيم للعرض ]========= */
$pp = [];
$st = $pdo->prepare("SELECT * FROM provider_profiles WHERE user_id=? LIMIT 1");
$st->execute([$user_id]);
$pp = $st->fetch(PDO::FETCH_ASSOC) ?: [];

// قيم العرض مع fallback: provider_profiles ثم users
$val_full_name = ($pp['full_name'] ?? '') ?: ($u['full_name'] ?? '');
$val_phone     = $u['phone']; // الهاتف دائمًا من users
$val_email     = $pp['email'] ?? '';
$val_address   = ($pp['address'] ?? '') ?: ($u['address'] ?? '');
$val_age       = $pp['age']    ?? '';
$val_gender    = $pp['gender'] ?? '';
$val_avatar    = $pp['avatar_path'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Provider Onboarding – Step 1</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../css/provider-step1.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="page">
    <div class="card">
      <header class="header">
        <h1>Complete Your Provider Profile</h1>
        <ol class="steps">
          <li class="step is-active"><span class="dot">01</span><small>Basic Information</small></li>
          <li class="step"><span class="dot">02</span><small>identification</small></li>
          <li class="step"><span class="dot">03</span><small>Services</small></li>
          <li class="step"><span class="dot">04</span><small>security</small></li>
        </ol>
      </header>

      <form class="body" method="post" enctype="multipart/form-data" autocomplete="off">
        <section class="grid">
          <div class="left">
            <h2>Basic Information</h2>

            <div class="row">
              <div class="field">
                <label>Full name</label>
                <div class="with-icon">
                  <i class="fa-regular fa-user"></i>
                  <input type="text" name="full_name" required value="<?=htmlspecialchars($val_full_name)?>">
                </div>
              </div>
              <div class="field">
                <label>Phone Number</label>
                <div class="with-icon">
                  <i class="fa-solid fa-phone"></i>
                  <!-- الهاتف للعرض فقط -->
                  <input type="tel" value="<?=htmlspecialchars($val_phone)?>" readonly>
                  <!-- لو بدك يروح مع POST كمعلومة إضافية (لن نستخدمه في الحفظ) -->
                  <input type="hidden" name="phone_shadow" value="<?=htmlspecialchars($val_phone)?>">
                </div>
              </div>
            </div>

            <div class="row">
              <div class="field">
                <label>Select your age</label>
                <div class="with-icon">
                  <i class="fa-regular fa-calendar"></i>
                  <input type="number" name="age" min="16" max="80" value="<?=htmlspecialchars($val_age)?>">
                </div>
              </div>
              <div class="field">
                <label>Select your gender</label>
                <select name="gender">
                  <option value="" <?=$val_gender==''?'selected':''?>>select your gender</option>
                  <option value="Female" <?=$val_gender=='Female'?'selected':''?>>Female</option>
                  <option value="Male" <?=$val_gender=='Male'?'selected':''?>>Male</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label>Email (optional)</label>
              <div class="with-icon">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" name="email" value="<?=htmlspecialchars($val_email)?>">
              </div>
            </div>

            <div class="field">
              <label>Address</label>
              <div class="with-icon">
                <i class="fa-solid fa-location-dot"></i>
                <input type="text" name="address" value="<?=htmlspecialchars($val_address)?>">
              </div>
            </div>

            <div class="actions">
              <button type="submit" class="btn primary">Next</button>
            </div>
          </div>

          <aside class="right">
            <div class="avatar">
              <img id="avatarPreview" src="<?=$val_avatar ? '../'.htmlspecialchars($val_avatar) : ''?>"

زهرة اللوتس, [13/09/2025 12:21 م]
style="<?=$val_avatar ? '' : 'display:none;'?>">
              <div id="avatarHint" class="avatar-hint" style="<?=$val_avatar ? 'display:none;' : ''?>">
                <i class="fa-regular fa-image"></i>
              </div>
            </div>
            <p class="small">Upload A Photo Of Yourself</p>
            <label class="btn ghost">
              <input id="avatarInput" type="file" name="avatar" accept="image/*" hidden>
              Upload Photo
            </label>
          </aside>
        </section>
      </form>
    </div>
  </div>

  <script>
    const inp = document.getElementById('avatarInput');
    const img = document.getElementById('avatarPreview');
    const hint = document.getElementById('avatarHint');
    inp?.addEventListener('change', (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      const r = new FileReader();
      r.onload = ev => {
        img.src = ev.target.result;
        img.style.display = 'block';
        hint.style.display = 'none';
      };
      r.readAsDataURL(file);
    });
  </script>
</body>
</html>