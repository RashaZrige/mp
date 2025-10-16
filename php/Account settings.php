<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – My Booking</title>
  <link rel="stylesheet" href="../css/account_settings.css?v=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
</head>
<body>

<header class="navbar">
  <div class="logo-wrap">
    <img src="../image/home-logo.png" class="logo" alt="Fixora logo" />
  </div>
  <nav class="nav-links">
    <a href="<?= $BASE ?>/index.html">Home</a>
    <a href="<?= $BASE ?>/aboutUs.html">About Us</a>
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
       <a class="menu-item" href="my_booking.php"><span>My Bookings</span></a>
      <hr class="divider">
      <a class="menu-item" href="Account settings.php"><span>Account Settings</span></a>
      <hr class="divider">
      <a class="menu-item danger" href="logout.php"><span>Log Out</span></a>
    </div>
  </div>
</header>

<section class="hero-booking">
  <div class="container">
    <button class="hero-tag">Account  Settings</button>
    <p class="hero-line blue">Manage your personal information,security,and preferences.</p>
  </div>
</section>










<section class="settings-list container">
  <a class="settings-item" href="account-personal.php">
    <span>Personal Information</span>
    <i class="fa-solid fa-chevron-right"></i>
  </a>

  <a class="settings-item" href="account-security.php">
    <span>Security</span>
    <i class="fa-solid fa-chevron-right"></i>
  </a>

  <a class="settings-item" href="account-addresses.php">
    <span>Saved Addresses</span>
    <i class="fa-solid fa-chevron-right"></i>
  </a>

  <a class="settings-item" href="account-preferences.php">
    <span>Preferences</span>
    <i class="fa-solid fa-chevron-right"></i>
  </a>

<!-- <a class="settings-item danger" href="javascript:void(0)" id="linkDeleteAccount">
  <span>Delete Account</span>
  <i class="fa-solid fa-chevron-right"></i>
</a> -->




<!-- زر الإعدادات (بدل رابطك الحالي) -->
<a class="settings-item danger" href="account-delete.php" id="deleteAccountLink">
  <span>Delete Account</span>
   <i class="fa-solid fa-chevron-right"></i>
</a>

<!-- ===== Delete Confirm Modal ===== -->
<div class="del-overlay" id="delOverlay" hidden>
  <div class="del-modal" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <div class="del-icon" aria-hidden="true"></div>
    <h3 id="delTitle">Once You Delete Your Account, This Action Cannot Be Undone</h3>
    <div class="del-actions">
      <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
      <button type="button" class="btn btn-cancel" id="cancelDeleteBtn">Cancel</button>
    </div>
  </div>
</div>

<style>
/* Overlay */
.del-overlay{
  position:fixed; inset:0; background:rgba(0,0,0,.55);
  display:grid; place-items:center; z-index:10000;
}
/* نخفيه عبر [hidden] بدل display:none لسهولة الظهور بالـJS */
.del-overlay[hidden]{ display:none; }

.del-modal{
  width:min(520px,92vw); background:#fff; border-radius:16px;
  border:1px solid #e5e7eb; padding:28px; text-align:center;
  box-shadow:0 24px 64px rgba(0,0,0,.25); font-family:Inter,system-ui;
}
.del-icon{ font-size:54px; margin-bottom:10px }
.del-modal h3{ margin:0 0 18px; font-size:18px; line-height:1.5; color:#0f172a }

.del-actions{ display:flex; gap:12px; justify-content:center }
.btn{
  min-width:160px; height:44px; padding:0 18px; border-radius:10px;
  font-weight:700; cursor:pointer; border:2px solid transparent;
}
.btn-danger{ background:#ef4444; color:#fff; border-color:#ef4444 }
.btn-danger:hover{ background:#dc2626; border-color:#dc2626 }
.btn-cancel{ background:#fff; color:#ef4444; border-color:#ef4444 }
.btn-cancel:hover{ background:#fee2e2 }



</style>

<script>
// افتح البوب-أپ بدل الانتقال المباشر
document.getElementById('deleteAccountLink')?.addEventListener('click', function (e) {
  e.preventDefault();
  document.getElementById('delOverlay').hidden = false;
});

// إغلاق البوب-أپ
document.getElementById('cancelDeleteBtn')?.addEventListener('click', function(){
  document.getElementById('delOverlay').hidden = true;
});
document.getElementById('delOverlay')?.addEventListener('click', function(e){
  if(e.target.id === 'delOverlay'){ this.hidden = true; }
});
window.addEventListener('keydown', function(e){
  if(e.key === 'Escape') document.getElementById('delOverlay').hidden = true;
});

// تأكيد الحذف -> إرسال POST إلى account-delete.php
document.getElementById('confirmDeleteBtn')?.addEventListener('click', function(){
  // في حال عندك CSRF token أضيفيه هنا
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'account-delete.php';
  // مثال توكن (اختياري):
  // const t = document.createElement('input');
  // t.type='hidden'; t.name='csrf'; t.value='<?= $_SESSION["csrf"] ?? "" ?>';
  // form.appendChild(t);
  document.body.appendChild(form);
  form.submit();
});
</script>
</section>







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
