
<?php
// mp/php/account-addresses.php
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

/* ---- معالجة الحفظ/الحذف ---- */
$flash = "";
$flashType = "success";

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action  = $_POST['action'] ?? '';
  if ($action === 'save') {
    $addr = trim($_POST['address'] ?? '');
    $sql  = "UPDATE users SET address=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param("si", $addr, $uid);
      if ($stmt->execute()) {
        $flash = "Address saved successfully.";
      } else {
        $flash = "Save failed: ".$stmt->error; $flashType="error";
      }
      $stmt->close();
    } else {
      $flash = "Prepare failed: ".$conn->error; $flashType="error";
    }
  } elseif ($action === 'delete') {
    $empty = "";
    $sql  = "UPDATE users SET address=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param("si", $empty, $uid);
      if ($stmt->execute()) {
        $flash = "Address deleted.";
      } else {
        $flash = "Delete failed: ".$stmt->error; $flashType="error";
      }
      $stmt->close();
    } else {
      $flash = "Prepare failed: ".$conn->error; $flashType="error";
    }
  }
}

/* ---- جلب العنوان الحالي ---- */
$address = "";
$sql = "SELECT address FROM users WHERE id=?";
$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param("i",$uid);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $address = $res['address'] ?? "";
  $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Saved Addresses</title>
  <link rel="stylesheet" href="../css/account-addresses.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <style>
    /* فلاش لطيف داخل الصفحة */
    .flash{
      margin: 18px auto 0;
      max-width: 920px;
      padding: 12px 16px;
      border-radius: 10px;
      font-weight: 600;
      text-align: center;
      box-shadow: 0 6px 16px rgba(0,0,0,.08);
      animation: fadeIn .25s ease;
    }
    .flash.success{ background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .flash.error{   background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
    .flash .close{ background:transparent;border:0;cursor:pointer;font-size:18px;font-weight:700;margin-left:10px; }
    @keyframes fadeIn{ from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
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

<?php if ($flash): ?>
  <div id="flashBox" class="flash <?= htmlspecialchars($flashType) ?>">
    <?= htmlspecialchars($flash) ?>
    <button class="close" aria-label="Close">&times;</button>
  </div>
<?php endif; ?>

<section class="saved-addresses">
  <h3>Saved Addresses</h3>

  <!-- بنخليها داخل فورم عشان نبعث الحفظ -->
  <form id="addrForm" method="post">
    <input type="hidden" name="action" value="save" />
    <div class="address-card">
      <div class="address-info">
        <label for="addr1">Address</label>
        <input id="addr1" name="address" type="text" value="<?= htmlspecialchars($address) ?>" readonly>
      </div>
      <div class="btn-group">
        <button type="button" id="editBtn" class="btn edit">Edit</button>
        <button type="button" id="deleteBtn" class="btn delete">Delete</button>
      </div>
    </div>

    <div class="save-btn">
      <button type="submit" class="btn save">Save Changes</button>
    </div>
  </form>
</section>

<script>
// فكّ readOnly عند الضغط على Edit
document.getElementById('editBtn')?.addEventListener('click', () => {
  const input = document.getElementById('addr1');
  input.readOnly = false;
  input.focus();
  // حط action = save
  document.querySelector('input[name="action"]').value = 'save';
});

// حذف (تفريغ) العنوان
document.getElementById('deleteBtn')?.addEventListener('click', () => {
  if (!confirm('Delete this address?')) return;
  const form = document.getElementById('addrForm');
  document.querySelector('input[name="action"]').value = 'delete';
  form.submit();
});

// اغلاق الفلاش بعد قليل أو عند الضغط على X
(function(){
  const box = document.getElementById('flashBox');
  if (!box) return;
  const close = box.querySelector('.close');
  close?.addEventListener('click', ()=> box.remove());
  setTimeout(()=> box.remove(), 2500);
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

