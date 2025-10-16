
<?php
session_start();
$BASE = '/mp';

/* ==== اتصال قاعدة البيانات ==== */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ==== أدوات مساعدة ==== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function img_url($dbPath, $base = '/mp') {
  if (!$dbPath) return '';
  if (preg_match('~^https?://~i', $dbPath)) return $dbPath;
  $dbPath = str_replace('\\','/',$dbPath);
  $dbPath = ltrim($dbPath, '/');
  $dir  = dirname($dbPath);
  $file = basename($dbPath);
  return rtrim($base, '/') . '/' . ($dir === '.' ? '' : $dir . '/') . rawurlencode($file);
}

/* ==== قراءة اسم وصورة المزود بحسب جلسة المستخدم ==== */
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$providerName  = "Unknown User";
$providerPhoto = $BASE . "/image/no-avatar.png";

if ($uid > 0) {
  $sql = "SELECT u.full_name, pp.avatar_path
          FROM users u
          LEFT JOIN provider_profiles pp ON pp.user_id = u.id
          WHERE u.id = ? LIMIT 1";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("i", $uid);
    $st->execute();
    $res = $st->get_result();
    if ($res && $row = $res->fetch_assoc()) {
      if (!empty($row['full_name']))   $providerName  = $row['full_name'];
      if (!empty($row['avatar_path'])) $providerPhoto = img_url($row['avatar_path'], $BASE);
    }
    $st->close();
  }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Account Settings</title>
  <!-- أيقونات -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
 <link rel="stylesheet" href="/mp/css/help_center.css?v=1">
</head>
<body>

<!-- سايدبارك كما هو (محافظين على القوائم) -->
<div class="sidebar" id="sidebar">
  <button class="sidebar-close" id="closeSidebar" aria-label="Close menu">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <h3>Menu</h3>
  <ul>
    <li><a href="#"><i class="fa-solid fa-table-columns"></i> Dashboard</a></li>
    <li><a href="#"><i class="fa-regular fa-calendar"></i> My booking</a></li>
    <li><a href="#"><i class="fa-solid fa-cart-shopping"></i> Services</a></li>
    <li><a href="rating dashbord.php"><i class="fa-regular fa-comment-dots"></i> Review</a></li>
    <li><a href="Help center.php"><i class="fa-regular fa-circle-question"></i> Help Center</a></li>
  </ul>

  <div class="sidebar-profile">
    <img src="<?= h($providerPhoto) ?>" alt="User"
         onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
    <div class="profile-info">
      <span class="name"><?= h($providerName) ?></span>
      <span class="role">My Account</span>
    </div>
  </div>
</div>

<!-- خلفية للموبايل -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- لفّ المحتوى كله هنا -->
<div class="main-content">
  <!-- ===== Topbar ===== -->
  <section class="topbar">
    <div class="tb-inner">
      <div class="tb-left">
        <button class="icon-btn" aria-label="Settings" id="openSidebar">
          <i class="fa-solid fa-gear"></i>
        </button>
        <div class="brand">
          <img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo">
        </div>
      </div>
      <div class="tb-center">
        <div class="search-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search Here">
        </div>
      </div>
      <div class="tb-right">
        <button class="notif-pill" aria-label="Notifications">
          <i class="fa-solid fa-bell"></i>
        </button>

        <div class="profile-menu">
          <button class="profile-trigger" aria-expanded="false">


<img class="avatar" src="<?= h($providerPhoto) ?>" alt="Profile"
                 onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
            <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

          <div class="menu-card" hidden>
            <a class="menu-item" href="identification.php">
              <i class="fa-solid fa-gear"></i>
              <span>Account Settings</span>
            </a>
            <hr class="divider">
                <a class="menu-item danger"
   href="<?= $BASE ?>/php/logout.php"
   style="color:#dc2626; display:flex; align-items:center; justify-content:space-between; gap:10px; white-space:nowrap;">
  <span>Log Out</span>
  <i class="fa-solid fa-right-from-bracket"></i>
</a>


          </div>
        </div>

      </div>
    </div>
  </section>






<!-- ===== Page header (خارج الكارد) ===== -->
<section class="faq-header-wrap">
  <div class="faq-header">
    <h2 class="faq-title">How Can  We Help ?</h2>
    <p class="faq-sub">Search For Topic Or Question To Find What You Need ?</p>

  
  </div>
</section>

<!-- ===== FAQ Card (داخل كارد أبيض) ===== -->
<section class="faq-wrap" id="faq">
  <div class="faq-card">
    <h3 class="faq-section-title">Frequently Asked Question</h3>

    <div class="faq-list" id="faqList">
      <article class="faq-item open">
        <button class="faq-q" aria-expanded="true">
          <span class="faq-icon" aria-hidden="true">×</span>
          <span class="faq-text">Do you provide a service guarantee?</span>
        </button>
        <div class="faq-a">
          Yes! We offer a warranty on our work to ensure quality and customer satisfaction.
        </div>
      </article>

      <article class="faq-item">
        <button class="faq-q" aria-expanded="false">
          <span class="faq-icon" aria-hidden="true">+</span>
          <span class="faq-text">How can I reschedule my booking?</span>
        </button>
        <div class="faq-a">
          Go to <strong>My Bookings</strong>, open the booking and click <em>Reschedule</em>.
        </div>
      </article>

      <article class="faq-item">
        <button class="faq-q" aria-expanded="false">
          <span class="faq-icon" aria-hidden="true">+</span>
          <span class="faq-text">What payment methods are accepted?</span>
        </button>
        <div class="faq-a">
          We accept major cards and cash on delivery (in supported areas).
        </div>
      </article>
    </div>
  </div>
</section>







<!-- Contact Us Section -->
<!-- Contact Us Section -->
<section class="contact-container">
  <form class="contact-card" method="POST" action="/mp/php/contact_submit.php">
    <h3 class="contact-title">Contact Us</h3>

    <label class="field-label" for="email">
      Your email <span>Email</span>
    </label>

    <div class="input-with-icon">
      <input id="email" name="email" type="email" placeholder="ex: sajahnauh@gmail.com" required />
      <svg class="mail-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4.236-7.54 5.026a2 2 0 0 1-2.12 0L4 8.236V6l8 5.333L20 6v2.236Z"/>
      </svg>
    </div>

    <label class="field-label" for="help">How can we help</label>
    <textarea id="help" name="message" placeholder="Describe here" required></textarea>

    <!-- زر الإرسال -->
    <div class="actions">
      <button class="btn-primary" type="submit">Submit</button>
    </div>
  </form>
</section>
</div>

</body>
</html>

<script>
  // Accordion (+/×)
  (function(){
    const list = document.getElementById('faqList');
    list.addEventListener('click', (e)=>{
      const btn = e.target.closest('.faq-q');
      if(!btn) return;
      const item = btn.closest('.faq-item');
      const icon = btn.querySelector('.faq-icon');
      const open = !item.classList.contains('open');

      // اغلاق الباقي (احذف هذا البلوك لو بدك عدة عناصر مفتوحة)
      document.querySelectorAll('.faq-item.open').forEach(i=>{
        if(i!==item){ i.classList.remove('open'); i.querySelector('.faq-q').setAttribute('aria-expanded','false'); i.querySelector('.faq-icon').textContent='+'; }
      });


item.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open ? 'true':'false');
      icon.textContent = open ? '×' : '+';
    });
  })();
</script>

  
<script>
    // Dropdown البروفايل (كما في كودك)
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
      card.addEventListener('click', (e)=> e.stopPropagation());
      document.addEventListener('click', ()=> openMenu(false));
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') openMenu(false); });
    })();

  
  </script>

  <script>
const openSidebar     = document.getElementById('openSidebar');
const closeSidebar    = document.getElementById('closeSidebar'); // زر الإكس
const sidebar         = document.getElementById('sidebar');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');

// فتح السايدبار
function openNav(){
  document.body.classList.add('sidebar-open');
  sidebar.classList.add('open');
  if (window.matchMedia('(max-width: 899px)').matches){
    sidebarBackdrop?.classList.add('show');
  }
}

// إغلاق السايدبار
function closeNav(){
  document.body.classList.remove('sidebar-open');
  sidebar.classList.remove('open');
  sidebarBackdrop?.classList.remove('show');
}

// الأحداث
openSidebar?.addEventListener('click', openNav);
closeSidebar?.addEventListener('click', closeNav);
sidebarBackdrop?.addEventListener('click', closeNav);
document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeNav(); });

// تفعيل العنصر عند الضغط
document.querySelectorAll('.sidebar a').forEach(link => {
  link.addEventListener('click', (e) => {
    // إزالة active من الكل
    document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
    // إضافة active للعنصر المضغوط
    e.currentTarget.classList.add('active');
  });
});
</script>


<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const form = document.querySelector('.contact-card'); // هو نفس الـ form
  if(!form) return;

  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(form);

    // زرار submit لو بتحب تعطلّه لحين الاستجابة
    const btn = form.querySelector('.btn-primary');
    btn && (btn.disabled = true);

    try{
      const res = await fetch(form.action, { method:'POST', body: fd });
      const data = await res.json().catch(()=>({ok:false, error:'invalid_json'}));

      if (data.ok){
        alert('تم إرسال رسالتك بنجاح ✅');
        form.reset();
      }else{
        alert('تعذّر الإرسال: ' + (data.error || 'unknown_error'));
      }
    }catch(err){
      console.error(err);
      alert('Network error');
    }finally{
      btn && (btn.disabled = false);
    }
  });
});
</script>