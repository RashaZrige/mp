
<?php
// mp/php/account-security.php
session_start();
$BASE = "/mp";

// لازم يكون المستخدم داخل
if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

// اتصال DB
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error){ http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// جلب الهاش الحالي للمستخدم
$sql  = "SELECT password_hash FROM users WHERE id=?";
$stmt = $conn->prepare($sql);
if(!$stmt){ die("Prepare failed: ".$conn->error); }
$stmt->bind_param("i",$uid);
$stmt->execute();
$res  = $stmt->get_result();
$row  = $res->fetch_assoc();
$stmt->close();
if(!$row){ $conn->close(); die("User not found"); }

$flash_msg = $_SESSION['sec_flash_msg'] ?? '';
$flash_typ = $_SESSION['sec_flash_type'] ?? ''; // success | error
unset($_SESSION['sec_flash_msg'], $_SESSION['sec_flash_type']);

// معالجة حفظ كلمة المرور
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $curr  = isset($_POST['current_password'])  ? trim($_POST['current_password'])  : '';
  $newpw = isset($_POST['new_password'])      ? trim($_POST['new_password'])      : '';
  $conf  = isset($_POST['confirm_password'])  ? trim($_POST['confirm_password'])  : '';

  // فحص المدخلات
  if ($curr === ''  ||  $newpw === '' ||  $conf === '') {
    $_SESSION['sec_flash_msg']  = "Please fill all password fields.";
    $_SESSION['sec_flash_type'] = "error";
  } elseif (!password_verify($curr, $row['password_hash'])) {
    $_SESSION['sec_flash_msg']  = "Current password is incorrect.";
    $_SESSION['sec_flash_type'] = "error";
  } elseif (strlen($newpw) < 8) {
    $_SESSION['sec_flash_msg']  = "New password must be at least 8 characters.";
    $_SESSION['sec_flash_type'] = "error";
  } elseif ($newpw !== $conf) {
    $_SESSION['sec_flash_msg']  = "New passwords do not match.";
    $_SESSION['sec_flash_type'] = "error";
  } else {
    // كل شيء تمام → تحديث
    $newHash = password_hash($newpw, PASSWORD_DEFAULT);
    $u = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
    if($u){
      $u->bind_param("si", $newHash, $uid);
      if ($u->execute()){
        $_SESSION['sec_flash_msg']  = "Password updated successfully.";
        $_SESSION['sec_flash_type'] = "success";
      } else {
        $_SESSION['sec_flash_msg']  = "Save failed. Try again.";
        $_SESSION['sec_flash_type'] = "error";
      }
      $u->close();
    } else {
      $_SESSION['sec_flash_msg']  = "Prepare failed.";
      $_SESSION['sec_flash_type'] = "error";
    }
  }

  // منع إعادة الإرسال
  header("Location: {$BASE}/php/account-security.php");
  
  exit;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Security</title>

  <!-- حط ملف CSS تبع الأمان تبعك -->
  <link rel="stylesheet" href="<?= $BASE ?>/css/account-security.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

  <style>
    /* فلاش خفيف أعلى الصفحة */
    .flash {
      position: fixed;
      top: 18px; left: 50%;
      transform: translateX(-50%);
      z-index: 9999;
      padding: 12px 18px;
      border-radius: 10px;
      font-weight: 700;
      box-shadow: 0 6px 18px rgba(0,0,0,.12);
      display: none;
    }
    .flash.show { display:block; animation: slideDown .25s ease-out; }
    .flash.success { background:#d1fae5; color:#065f46; }
    .flash.error   { background:#fee2e2; color:#dc2626; }
    @keyframes slideDown { from {opacity:0; transform:translate(-50%,-8px)} to {opacity:1; transform:translate(-50%,0)} }

 
  </style>
</head>
<body>

<!-- فلاش -->
<?php if ($flash_msg): ?>
  <div id="flash" class="flash <?= htmlspecialchars($flash_typ) ?> show">
    <?= htmlspecialchars($flash_msg) ?>
  </div>
<?php endif; ?>

<!-- نافبارك المعتاد (اختياري) -->
<header class="navbar">
  <div class="navbar-inner">
    <div class="logo-wrap">
      <img src="<?= $BASE ?>/image/home-logo.png" class="logo" alt="Fixora logo" />
    </div>
    <nav class="nav-links">
      <a href="<?= $BASE ?>/index.html">Home</a>
      <a href="aboutus.php">About Us</a>
      <a href="<?= $BASE ?>/contact.html">Contact</a>
      <a href="<?= $BASE ?>/php/viewmore.php">Services</a>
    </nav>
    <div class="profile-menu">
      <button class="profile-trigger" aria-expanded="false">
            <img class="avatar" src="/mp/image/avater.jpg" alt="Profile">
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

<section id="security-section" class="settings-section">
  <div class="settings-inner">
    <h2 class="section-title"><i class="fa-solid fa-shield-halved"></i> Security</h2>

    <form id="security-form" action="<?= $BASE ?>/php/account-security.php" method="post" novalidate>
      <!-- Current password (ReadOnly حتى يضغط Edit) -->
      <div class="field-row">
        <label for="current_password">Current password</label>
        <div class="input-wrap">
          <i class="fi-left fa-solid fa-lock"></i>
          <input id="current_password" name="current_password" type="password" autocomplete="current-password" readonly />
          <button type="button" class="toggle-visibility" data-target="current_password" aria-label="Toggle current password visibility">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
        <small class="hint">Press “Edit” to change your password.</small>
      </div>

      <!-- New / Confirm (مخفية إلى أن يضغط Edit) -->
      <div id="new-fields" class="hidden">
        <div class="field-row">
          <label for="new_password">New password</label>
          <div class="input-wrap">
            <i class="fi-left fa-solid fa-key"></i>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" />
            <button type="button" class="toggle-visibility" data-target="new_password" aria-label="Toggle new password visibility">
              <i class="fa-solid fa-eye"></i>


</button>
          </div>
        </div>
        <div class="field-row">
          <label for="confirm_password">Confirm new password</label>
          <div class="input-wrap">
            <i class="fi-left fa-solid fa-check"></i>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" />
            <button type="button" class="toggle-visibility" data-target="confirm_password" aria-label="Toggle confirm password visibility">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- أزرار -->
      <div class="actions-row">
        <button id="edit-btn" type="button" class="btn btn-edit">Edit</button>
        <button id="save-btn" type="submit" class="btn">Save changes</button>
      </div>
    </form>
  </div>
</section>

<script>
  // إظهار/إخفاء الحقول بالطريقة اللي بدك إياها: Edit → يظهر الحقول ويفتح Current
  (function(){
    const editBtn   = document.getElementById('edit-btn');
    const currInput = document.getElementById('current_password');
    const newFields = document.getElementById('new-fields');

    if (editBtn){
      editBtn.addEventListener('click', () => {
        currInput.removeAttribute('readonly');
        newFields.classList.remove('hidden');
        currInput.focus();
      });
    }
  })();

  // Toggle رؤية كلمة المرور
  (function(){
    document.querySelectorAll('.toggle-visibility').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-target');
        const input = document.getElementById(id);
        if (!input) return;
        input.type = (input.type === 'password') ? 'text' : 'password';
        btn.querySelector('i')?.classList.toggle('fa-eye');
        btn.querySelector('i')?.classList.toggle('fa-eye-slash');
      });
    });
  })();

  // إخفاء الفلاش بعد 3 ثواني
  (function(){
    const f = document.getElementById('flash');
    if (!f) return;
    setTimeout(()=>{ f.classList.remove('show'); }, 3000);
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


<script>
document.getElementById("edit-btn").addEventListener("click", function() {
  document.getElementById("new-fields").classList.remove("hidden");
  document.getElementById("current_password").removeAttribute("readonly");
});
</script>