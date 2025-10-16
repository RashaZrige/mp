
<?php
// mp/php/account-personal.php
session_start();
$BASE = "/mp";

if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ========== 1) جلب بيانات المستخدم ========== */
$sql  = "SELECT id, full_name, email, phone, address, role, avatar FROM users WHERE id=?";
$stmt = $conn->prepare($sql);
if(!$stmt){ die("Prepare failed: ".$conn->error); }
$stmt->bind_param("i",$uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$user){ $conn->close(); die("User not found"); }

/* دالة URL للصورة (نفسها للهيدر وتحت) */
function avatar_url($base, $path, $uid){
  if ($path && preg_match('~^https?://~i',$path)) return $path;
  if ($path && $path!=='') return rtrim($base,'/').'/'.ltrim($path,'/');
  // Fallback (ثابت لكلا المكانين)
 return "../image/avater.jpg?u={$uid}";
}

$flash = "";

/* ========== 2) حفظ التعديلات (مع رفع صورة) ========== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $full_name = trim($_POST['full_name'] ?? "");
  $email     = trim($_POST['email'] ?? "");
  $phone     = trim($_POST['phone'] ?? "");
  $address   = trim($_POST['address'] ?? "");
  $avatarRel = $user['avatar']; // المسار الحالي إن وُجد

  // هل رفع صورة جديدة؟
  if (!empty($_FILES['avatar']['name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
    $uploadDirAbs = DIR . "/../uploads/avatars/";
    $uploadDirRel = "uploads/avatars/";
    if (!is_dir($uploadDirAbs)) { @mkdir($uploadDirAbs, 0777, true); }

    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/i','',$ext);
    if ($ext === '') $ext = 'jpg';

    $fileName = "user_{$uid}_" . time() . "." . $ext;
    $tmpPath  = $_FILES['avatar']['tmp_name'];
    $destPath = $uploadDirAbs . $fileName;

    if (move_uploaded_file($tmpPath, $destPath)) {
      $avatarRel = $uploadDirRel . $fileName;
    }
  }

  // تحديث users فقط (ما في provider_profiles)
  $sql  = "UPDATE users SET full_name=?, email=?, phone=?, address=?, avatar=? WHERE id=?";
  $stmt = $conn->prepare($sql);
  if(!$stmt){ die("Prepare failed (update): ".$conn->error); }
  $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $avatarRel, $uid);



  
  if ($stmt->execute()) {
    $flash = "Saved successfully.";
    // حدِّث البيانات المعروضة
    $user['full_name'] = $full_name;
    $user['email']     = $email;
    $user['phone']     = $phone;
    $user['address']   = $address;
    $user['avatar']    = $avatarRel;
  } else {
    $flash = "Save failed: " . $stmt->error;
  }
  $stmt->close();
}

$conn->close();

/* نفس الـ URL للصورتين */
$avatarUrl = avatar_url($BASE, $user['avatar'] ?? '', $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Account – Personal Info</title>
  <link rel="stylesheet" href="<?= $BASE ?>/css/account-personal.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <style>
    /* فقط لضمان دائرية الصور لو ملف CSS عندك ما فيه */
    .avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;display:block}
    .avatar-lg{width:140px;height:140px;border-radius:50%;object-fit:cover;display:block;box-shadow:0 8px 24px rgba(0,0,0,.08)}
  </style>
</head>
<body>


<header class="navbar">
  <div class="navbar-inner">
    <div class="logo-wrap">
      <img src="<?= $BASE ?>/image/home-logo.png" class="logo" alt="Fixora logo">
    </div>

    <nav class="nav-links">
      <a href="<?= $BASE ?>/index.html">Home</a>
      <a href="aboutus.php">About Us</a>
      <a href="<?= $BASE ?>/contact.html">Contact</a>
      <a href="<?= $BASE ?>/php/viewmore.php">Services</a>
    </nav>


<div class="profile-menu">
      <button class="profile-trigger" aria-expanded="false">
        <!-- نفس المصدر الذي تحت -->
        <img id="headerAvatar" class="avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Profile">
        <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
          <div class="menu-card" hidden>
      <a class="menu-item" href="<?= $BASE ?>/php/my_booking.php"><span>My Bookings</span></a>
      <hr class="divider">
      <a class="menu-item" href="Account settings.php"><span>Account Settings</span></a>
      <hr class="divider">
      <a class="menu-item danger" href="logout.php"><span>Log Out</span></a>
    </div>
    </div>

  </div>
</header>

<section class="account-settings">
  <div class="profile-center">
    <div class="profile-photo">
      <!-- نفس الصورة وتكون دائرية -->
      <img id="profilePreview" class="avatar-lg" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar">
      <label class="upload-btn" title="Change photo">
        <i class="fa-solid fa-camera"></i>
        <!-- مربوط بنفس الفورم -->
        <input type="file" name="avatar" form="personalForm" accept="image/*" hidden>
      </label>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <p class="flash"><?= htmlspecialchars($flash) ?></p>
  <?php endif; ?>

  <!-- حقول الفورم -->
  <form id="personalForm" class="fields-grid" method="post" enctype="multipart/form-data">
    <div class="form-group">
      <label for="fullname">Full Name</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-user"></i>
        <input id="fullname" name="full_name" type="text"
               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Full name">
      </div>
    </div>

    <div class="form-group">
      <label for="email">Email</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-envelope"></i>
        <input id="email" name="email" type="email"
               value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="example@mail.com">
      </div>
    </div>

    <div class="form-group">
      <label for="phone">Phone Number</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-phone"></i>
        <input id="phone" name="phone" type="tel"
               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="ex: +9725xxxxxxx">
      </div>
    </div>

    <div class="form-group">
      <label for="address">Address</label>
      <div class="input-wrapper">
        <i class="fa-solid fa-location-dot"></i>
        <input id="address" name="address" type="text"
               value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="City, Street, Details">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn save">Save Changes</button>
    </div>
  </form>
</section>

<!-- JS: معاينة فورية للصورتين -->
<script>
(function(){
  const fileInput  = document.querySelector('input[name="avatar"]');
  const headerImg  = document.getElementById('headerAvatar');
  const previewImg = document.getElementById('profilePreview');
  if (!fileInput) return;

  fileInput.addEventListener('change', function(){
    const f = this.files && this.files[0];
    if (!f) return;
    const url = URL.createObjectURL(f);
    if (headerImg)  headerImg.src  = url;
    if (previewImg) previewImg.src = url;
    previewImg && previewImg.addEventListener('load', () => URL.revokeObjectURL(url), { once:true });
  });
})();
</script>

</body>
</html>




<footer class="site-footer">
  <div class="footer-container">


    <div class="footer-col footer-brand">
      <div class="brand-row">
        <img src="../image/home-logo.png" alt="Fixora logo" class="brand-logo">
      </div>

      <p class="brand-desc">
        Our Go-To Platform For Cleaning, Plumbing, And Electrical Maintenance
        Services With Live Tracking And Special Discounts.
      </p>

      <ul class="social">
        <li>
          <a class="soc fb" href="https://facebook.com/yourpage" target="_blank" rel="noopener" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
        </li>
        <li>
          <a class="soc ig" href="https://instagram.com/yourhandle" target="_blank" rel="noopener" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
          </a>
        </li>
        <li>
          <a class="soc x" href="https://x.com/yourhandle" target="_blank" rel="noopener" aria-label="X">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
        </li>
        <li>
          <a class="soc li" href="https://www.linkedin.com/company/yourcompany" target="_blank" rel="noopener" aria-label="LinkedIn">
            <i class="fa-brands fa-linkedin-in"></i>
          </a>
        </li>
      </ul>
    </div>

    <div class="footer-col">
      <h4 class="col-title">Company</h4>
      <ul class="col-links">
        <li><a href="#">About Us</a></li>
        <li><a href="#">Careers</a></li>
        <li><a href="#">Contact Us</a></li>
        <li><a href="#">Terms Of Service</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4 class="col-title">Services</h4>
      <ul class="col-links">
        <li><a href="#">About Us</a></li>
        <li><a href="#">Careers</a></li>
        <li><a href="#">Contact Us</a></li>
        <li><a href="#">How It Works</a></li>
        <li><a href="#">Terms Of Service</a></li>
      </ul>
    </div>

  
    <div class="footer-col">
      <h4 class="col-title">Contact Information</h4>  
      <ul class="contact-list">
        <li><i class="fa-solid fa-location-dot"></i> Gaza – Palestine</li>
        <li>
          <i class="fa-solid fa-envelope"></i>
          <a href="mailto:Fixora2025@gmail.com">Fixora2025@gmail.com</a>
        </li>
        <li>
          <i class="fa-solid fa-phone"></i>
          <a href="tel:+972597789185">+972 592643752</a>
        </li>
      </ul>
    </div>

  </div>

  <p class="footer-copy">© 2025 All Rights Reserved — <span class="brand">Fixora</span></p>
</footer>





<script>
// Toggle profile dropdown
(function(){
  const pm  = document.querySelector('.profile-menu');
  if(!pm) return;
  const btn  = pm.querySelector('.profile-trigger');
  const card = pm.querySelector('.menu-card');
  function openMenu(open){
    pm.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    card.hidden = !open;
  }
  btn.addEventListener('click', (e)=>{ e.stopPropagation(); openMenu(card.hidden); });
  document.addEventListener('click', ()=> openMenu(false));
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') openMenu(false); });
  card.addEventListener('click', (e)=> e.stopPropagation());
})();
</script>

