<?php
/* admin_provider_add.php — Add Provider (Admin) */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }


mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

$flash_err = $_GET['err'] ?? '';
$flash_ok  = $_GET['ok']  ?? '';


$providerName = "Admin";
$uid = (int)($_SESSION['user_id'] ?? 0);

if ($uid > 0 && ($st = $conn->prepare("SELECT COALESCE(NULLIF(full_name,''), email) AS name FROM users WHERE id=? LIMIT 1"))) {
  $st->bind_param("i", $uid);
  $st->execute();
  $st->bind_result($name);
  if ($st->fetch()) { 
    $providerName = $name; 
  }
  $st->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Add Provider</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css"><!-- نفس ملف الهيرو -->
<style>
  .page{max-width:900px;margin:0 auto;padding:18px 24px 60px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
  .page-title{font:700 20px/1.2 "Inter",system-ui;margin:6px 0 16px}

  /* مسافات أريح بين الأسطر */
  .section{margin-top:16px}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
  @media (max-width:760px){.form-grid,.row-3{grid-template-columns:1fr}}

  .field{display:flex;flex-direction:column;gap:6px}
  .label{font-size:13px;color:#6b7280}
  .input,.select{height:44px;border:1px solid #e5e7eb;border-radius:10px;padding:0 12px;background:#fff;font-size:14px}
  .note{font-size:12px;color:#6b7280}

  /* صندوق الصورة — أصغر ومتمركز بالنص */
  .avatar-row{display:flex;justify-content:center;margin-top:8px}
  .avatar-wrap{width:min(340px,100%);display:flex;flex-direction:column;gap:8px}
  .avatar-box{
    width:100%;height:200px;border:2px dashed #e5e7eb;border-radius:12px;
    display:flex;align-items:center;justify-content:center;background:#fafafa;cursor:pointer;overflow:hidden
  }
  .avatar-box:hover{background:#f8fafc}
  .avatar-box img{max-width:100%;max-height:100%;object-fit:cover;display:block;border-radius:8px}
  
  .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
  .btn{height:42px;padding:0 16px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-weight:600;cursor:pointer}
  .btn-primary{background:#2b79ff;color:#fff;border-color:transparent}
  .btn-danger{background:#fff;color:#dc2626;border-color:#fecaca}

  .flash-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;margin-bottom:12px}
  .flash-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:12px;padding:10px 12px;margin-bottom:12px}


  
  /* ===== Sidebar push layout ===== */
:root { --sidebar-w: 240px; } /* غيّر العرض إذا بدك */

.sidebar{
  position: fixed;
  inset: 0 auto 0 0;   /* يثبت على اليسار */
  width: var(--sidebar-w);
  transform: translateX(-100%);   /* مخفية افتراضياً */
  transition: transform .2s ease;
  z-index: 1000;                  /* فوق المحتوى */
}
.sidebar.open{
  transform: translateX(0);
}


/* الخلفية تظهر فقط على الشاشات الصغيرة */
@media (min-width: 900px){
  .sidebar-backdrop{ display: none !important; }
}

/* لما السايدبار مفتوحة على الديسكتوب: ادفع المحتوى */
@media (min-width: 900px){
  body.sidebar-open .topbar { 
    margin-left: var(--sidebar-w);
    transition: margin-left .2s ease;
  }
  body.sidebar-open #cms,
  body.sidebar-open .page{               /* لو عندك .page بصفحات ثانية */
    margin-left: var(--sidebar-w);
    transition: margin-left .2s ease;
  }
}

/* على الموبايل: تظل أوفرلاي بدون دفع المحتوى */
@media (max-width: 899px){
  .sidebar-backdrop.show{
    position: fixed; inset:0;
    background: rgba(0,0,0,.35);
    z-index: 999;               /* تحت السايدبار مباشرة */
  }
}
</style>
</head>
<body>


<div class="sidebar" id="sidebar">
  <button class="sidebar-close" id="closeSidebar" aria-label="Close menu">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <h3>Menu</h3>
  <ul>
    <li><a href="dashboard admin.php"><i class="fa-solid fa-magnifying-glass"></i> Dashboard</a></li>
    <li><a href="admin-providers.php"><i class="fa-solid fa-person-digging"></i> Providers</a></li>
    <li><a href="admin-customers.php"><i class="fa-solid fa-users"></i> Customers</a></li>
    <li><a href="content management.php"><i class="fa-solid fa-cloud"></i> Manage Pages</a></li>
    <li><a href="admin-order.php"><i class="fa-solid fa-cart-shopping"></i> Services</a></li>
    <li><a href="admin-broadcasts.php"><i class="fa-regular fa-bell"></i> Notifications</a></li>
  </ul>

<div class="sidebar-profile">
    <img src="<?= $BASE ?>/image/2202112.png" alt="Admin Avatar"
         onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
    <div class="profile-info">
      <span class="name"><?= h($providerName) ?></span>
      <span class="role">Admin</span>
    </div>
</div>
  </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ===== Topbar (من ستايلك العام) ===== -->
<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left">
      <button class="icon-btn" aria-label="Settings" id="openSidebar"><i class="fa-solid fa-gear"></i></button>
      <div class="brand"><img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo"></div>
    </div>
        <div class="tb-center">
      <div class="search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search Here">
      </div>
    <div class="tb-center"><!-- فاضي هنا حسب حاجتك --></div>
    <div class="tb-right">
      <button class="notif-pill" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
    </div>
  </div>
</section>
<div class="page">
  <h2 class="page-title">Add provider</h2>

  <?php if($flash_ok): ?><div class="flash-ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="flash-err"><?= h($flash_err) ?></div><?php endif; ?>

  <div class="card">
    <form method="post" action="admin-provider-add-save.php" id="addForm" enctype="multipart/form-data" autocomplete="off">
      <!-- الصف الأول -->
      <div class="form-grid section">
        <label class="field">
          <span class="label">Full name *</span>
          <input class="input" type="text" name="full_name" required placeholder="Ex: Ahmed Saleh">
        </label>
        <label class="field">
          <span class="label">Email *</span>
          <input class="input" type="email" name="email" required placeholder="ahmed@example.com">
        </label>
      </div>

      <!-- الصف الثاني -->
      <div class="form-grid section">
        <label class="field">
          <span class="label">Phone</span>
          <input class="input" type="text" name="phone" placeholder="+9705XXXXXXXX">
        </label>
        <label class="field">
          <span class="label">National ID Number</span>
          <input class="input" type="text" name="national_id" placeholder="e.g. PD12324">
        </label>
      </div>

      <!-- الصف الثالث: التصنيف + الحالة + التوفر بنفس السطر -->
      <div class="row-3 section">
        <label class="field">
          <span class="label">Service Category (text)</span>
          <input class="input" type="text" name="category" placeholder="e.g. Cleaning">
          <span class="note">يمكن لاحقًا ربطه بخدمة فعلية من شاشة الخدمات.</span>
        </label>

        <label class="field">
          <span class="label">Status</span>
          <select class="select" name="status">
            <option value="active" selected>active</option>
            <option value="suspended">suspended</option>
          </select>
        </label>

        <label class="field" style="justify-content:flex-end">
          <span class="label">Availability</span>
          <div style="display:flex;align-items:center;gap:8px;height:44px">
            <input id="is_available" type="checkbox" name="is_available" value="1" checked>
            <label for="is_available" class="note" style="margin:0">Available now</label>
          </div>
        </label>
      </div>

      <!-- الصف الرابع: الصورة متمركزة -->
      <div class="avatar-row section">
        <div class="avatar-wrap">
          <span class="label">Avatar (optional)</span>
          <div class="avatar-box" onclick="document.getElementById('avatarInput').click()">
            <img id="avatarPrev" src="<?= $BASE ?>/image/no-avatar.png" alt="Preview">
          </div>
          <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none">
          <span class="note">JPG / PNG / WEBP</span>
        </div>
      </div>

      <!-- الأزرار -->
      <div class="actions">
        <button class="btn btn-danger" type="button" onclick="window.location='admin-providers.php'">Cancel</button>
        <button class="btn btn-primary" type="submit">Save provider</button>
      </div>
    </form>
  </div>
</div>

<script>
  // معاينة الصورة داخل نفس المربع
  const input = document.getElementById('avatarInput');
  const prev  = document.getElementById('avatarPrev');
  input?.addEventListener('change', (e)=>{
    const f = e.target.files?.[0]; if(!f) return;
    prev.src = URL.createObjectURL(f);
  });
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
</body>
</html>