
<?php
// mp/php/account-preferences.php
session_start();
$BASE = "/mp";

if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ========= حفظ AJAX ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__save'])) {
  // استلام القيم
  $lang  = $_POST['lang'] ?? 'en';
  $sms   = isset($_POST['notif_sms'])   ? 1 : 0;
  $email = isset($_POST['notif_email']) ? 1 : 0;
  $push  = isset($_POST['notif_push'])  ? 1 : 0;

  // حفظ
  $sql  = "UPDATE users SET preferred_lang=?, notif_sms=?, notif_email=?, notif_push=? WHERE id=?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'msg'=>"Prepare failed: ".$conn->error]);
    exit;
  }
  $stmt->bind_param("siiii", $lang, $sms, $email, $push, $uid);
  $ok = $stmt->execute();
  $msg = $ok ? "Preferences saved." : ("Save failed: ".$stmt->error);
  $stmt->close();

  header('Content-Type: application/json');
  echo json_encode(['ok'=>$ok,'msg'=>$msg]);
  exit;
}

/* ========= جلب القيم للعرض ========= */
$prefs = [
  'preferred_lang' => 'en',
  'notif_sms'      => 0,
  'notif_email'    => 1,
  'notif_push'     => 0,
];

$sql  = "SELECT preferred_lang, notif_sms, notif_email, notif_push FROM users WHERE id=?";
$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  if ($res) { $prefs = array_merge($prefs, $res); }
  $stmt->close();
}
$conn->close();

// helper
function checked($v){ return $v ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Preferences</title>
  <link rel="stylesheet" href="../css/account-preferences.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <style>
    /* فلاش رسالة فوق الكارد */
    .flash {
      position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
      z-index: 9999; padding: 12px 18px; border-radius: 10px;
      font-weight: 700; box-shadow: 0 8px 20px rgba(0,0,0,.12);
      background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; display:none;
    }
    .flash.error { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
    .navbar{width:100%; background:linear-gradient(90deg,#cfe2ff 0%,#eaf2ff 50%,#fde7c4 100%); box-shadow:0 4px 12px rgba(0,0,0,.08)}
    .navbar-inner{max-width:1200px;margin:0 auto;padding:18px 40px;display:flex;align-items:center;justify-content:space-between;gap:18px}
    .nav-links{flex:1;display:flex;justify-content:center;gap:20px}
    .logo{width:160px;height:auto}
    .profile-trigger{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #e5e7eb;padding:6px 12px;border-radius:40px}
    .avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;display:block}
  </style>
</head>
<body>

<header class="navbar">
  <div class="navbar-inner">
    <div class="logo-wrap">
      <img src="../image/home-logo.png" class="logo" alt="Fixora logo" />
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

<svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <div class="menu-card" hidden>
        <a class="menu-item" href="my_booking.php"><span>My Bookings</span></a>
        <hr class="divider">
        <a class="menu-item" href="Account settings.php"><span>Account Settings</span></a>
        <hr class="divider">
        <a class="menu-item danger" href="logout.php"><span>Log Out</span></a>
      </div>
    </div>
  </div>
</header>

<div id="flash" class="flash">Preferences saved.</div>

<section class="prefs-wrap">
  <div class="prefs-card">
    <h3 class="prefs-title">Preferences</h3>

    <!-- Preferred Language -->
    <div class="prefs-row">
      <span class="prefs-label">Preferred Language</span>

      <label class="choice">
        <input type="radio" name="lang" value="en" <?= $prefs['preferred_lang']==='en'?'checked':'' ?>>
        <span class="dot"></span>
        <span>English</span>
      </label>

      <label class="choice">
        <input type="radio" name="lang" value="ar" <?= $prefs['preferred_lang']==='ar'?'checked':'' ?>>
        <span class="dot"></span>
        <span>Arabic</span>
      </label>
    </div>

    <!-- Notifications -->
    <div class="prefs-row">
      <span class="prefs-label">Notifications</span>

      <label class="choice">
        <input type="checkbox" name="notif_sms"   <?= checked((int)$prefs['notif_sms']) ?>>
        <span class="box"></span>
        <span>SMS</span>
      </label>

      <label class="choice">
        <input type="checkbox" name="notif_email" <?= checked((int)$prefs['notif_email']) ?>>
        <span class="box"></span>
        <span>Email</span>
      </label>

      <label class="choice">
        <input type="checkbox" name="notif_push"  <?= checked((int)$prefs['notif_push']) ?>>
        <span class="box"></span>
        <span>Push</span>
      </label>
    </div>
  </div>

  <div class="prefs-actions">
    <button type="button" id="saveBtn" class="prefs-save">Save Changes</button>
  </div>
</section>

<script>
const $ = sel => document.querySelector(sel);
const flash = $('#flash');

function showFlash(msg, isError=false){
  flash.textContent = msg;
  flash.classList.toggle('error', !!isError);
  flash.style.display = 'block';
  setTimeout(()=> flash.style.display='none', 2200);
}

$('#saveBtn')?.addEventListener('click', async () => {
  // نقرأ القيم من الصفحة
  const data = new FormData();
  data.append('__save', '1');
  data.append('lang', document.querySelector('input[name="lang"]:checked')?.value || 'en');
  if (document.querySelector('input[name="notif_sms"]')?.checked)   data.append('notif_sms','1');
  if (document.querySelector('input[name="notif_email"]')?.checked) data.append('notif_email','1');
  if (document.querySelector('input[name="notif_push"]')?.checked)  data.append('notif_push','1');

  try {
    const res = await fetch(location.href, { method:'POST', body:data });
    const json = await res.json();
    showFlash(json.msg || (json.ok ? 'Preferences saved.' : 'Save failed'), !json.ok);
  } catch (e) {
    showFlash('Network error. Try again.', true);
  }
});
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
