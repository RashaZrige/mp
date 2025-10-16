<?php
/* admin_broadcasts.php — Admin Notification Manage */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }
$uid = (int)$_SESSION['user_id'];

/* ===== DB ===== */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ===== Helpers ===== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }



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


/* ===================== POST: Create / Schedule / Send ===================== */
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $aud     = $_POST['audience']   ?? 'all';          // all | customers | providers
  $ntype   = $_POST['ntype']      ?? 'info';         // info | success | warning | error
  $title   = trim($_POST['title'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $link    = trim($_POST['link_url'] ?? '');
  $when    = trim($_POST['schedule_at'] ?? '');      // YYYY-MM-DDTHH:MM (from <input type="datetime-local">)
  $action  = $_POST['action']     ?? '';             // schedule | send_now

  if ($title==='' || $message==='') {
    $flash_err = "Please fill title and message.";
  } else if (!in_array($aud, ['all','customers','providers'], true)) {
    $flash_err = "Invalid audience.";
  } else if (!in_array($ntype, ['info','success','warning','error'], true)) {
    $flash_err = "Invalid type.";
  } else {
    // normalise schedule_at
// اضبط التوقيت (اختياري لكنه مفيد)
date_default_timezone_set('Asia/Gaza');

$schedule_at = null;

if ($action === 'schedule') {
    // لازم اسم الحقل في الفورم يطابق "schedule_at"
    $when = trim($_POST['schedule_at'] ?? '');

    if ($when === '') {
        $flash_err = "Please choose schedule date/time.";
    } else {
        // datetime-local يرجع بالشكل: 2025-10-02T22:30
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $when);
        if ($dt === false) {
            $flash_err = "Invalid date/time format.";
        } else {
            // تأكد إنه بالمستقبل
            $now = new DateTime('now');
            if ($dt <= $now) {
                $flash_err = "Schedule time must be in the future.";
            } else {
                // صيغة MySQL DATETIME
                $schedule_at = $dt->format('Y-m-d H:i:s');
            }
        }
    }
}

    if ($flash_err==='') {
      $status = ($action==='send_now') ? 'sent' : 'scheduled';
      $sql = "INSERT INTO admin_broadcasts
              (admin_id, audience, ntype, title, message, link_url, schedule_at, status, created_at)
              VALUES (?,?,?,?,?,?,?,?, NOW())";
      $st = $conn->prepare($sql);
      if (!$st) {
        $flash_err = "Prepare failed: ".$conn->error;
      } else {
        // schedule_at may be NULL
        $st->bind_param(
          "isssssss",
          $uid, $aud, $ntype, $title, $message, $link,
          $schedule_at, $status
        );
        $ok = $st->execute();
        $st->close();

        if ($ok) {
          if ($status==='sent') {
            // هنا لاحقاً ممكن تبعَث فعلياً للمستخدمين (cron/worker)
            $flash_ok = "Notification has been sent (recorded in history).";
          } else {
            $flash_ok = "Notification scheduled successfully.";
          }
        } else {
          $flash_err = "Save failed.";
        }
      }
    }
  }
}

/* ===================== Fetch lists ===================== */
// Scheduled
$scheduled = [];
$res = $conn->query("
  SELECT id, audience, title, ntype, schedule_at, status
  FROM admin_broadcasts
  WHERE status='scheduled'
  ORDER BY schedule_at ASC, id DESC
");
if ($res) { while($row = $res->fetch_assoc()) $scheduled[] = $row; $res->free(); }

// History (sent)
$history = [];
$res = $conn->query("
  SELECT id, audience, title, ntype, schedule_at, created_at, status
  FROM admin_broadcasts
  WHERE status='sent'
  ORDER BY id DESC
  LIMIT 20
");
if ($res) { while($row = $res->fetch_assoc()) $history[] = $row; $res->free(); }

/* Close late (نحتاجه للعرض فقط) */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Notification Manage</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/rating_dashbord.css"><!-- نفس ملف الستايل العام تبعك -->

  <style>
    /* ====== صفحة إدارة الإشعارات (تصميم خفيف مطابق للـUI) ====== */
    /* ====== Admin Broadcasts Page Styles ====== */

.page-wrap{
  max-width:1100px;
  margin:0 auto;
  padding:18px 24px 60px;
}

.page-title{
  font:700 20px/1.2 "Inter",system-ui;
  margin:8px 0 16px;
}

.card{
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:14px;
  padding:16px;
  box-shadow:0 1px 0 rgba(16,24,40,.02);
  margin-bottom:18px;
}

.grid-2{
  display:grid;
  grid-template-columns: minmax(0,1fr) minmax(260px, 320px); /* يمين أوسع شوي */
  gap:12px;
  align-items:start;
}
.grid-2 .left,
.grid-2 .right,
.grid-2 .field{ min-width:0; }

.field{
  display:flex;
  flex-direction:column;
  gap:6px;
  margin-bottom:10px;
}
.label{ font-size:13px; color:#6b7280; }

.select,
.input,
.textarea{
  width:100%;
  max-width:100%;
  box-sizing:border-box;
  border:1px solid #e5e7eb;
  border-radius:12px;
  padding:10px 12px;
  background:#fff;
  font-size:14px;
  outline:0;
}
.textarea{ min-height:120px; resize:vertical; }

.input[type="url"],
.input[type="datetime-local"]{
  height:40px;
  line-height:40px;
  padding:8px 12px;
}

.btns{
  display:flex;
  gap:10px;
  justify-content:flex-end;
}
.btn{
  height:40px;
  padding:0 16px;
  border-radius:10px;
  border:1px solid #e5e7eb;
  background:#fff;
  font-weight:600;
  cursor:pointer;
}
.btn-primary{ background:#2b79ff; color:#fff; border-color:transparent; }
.btn-secondary{ background:#eef2ff; color:#1f2937; border-color:#e0e7ff; }

.table{
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
}
.table thead th{
  text-align:left;
  background:#f9fafb;
  border-bottom:1px solid #e5e7eb;
  padding:12px;
  font-weight:600;
}
.table tbody td{
  border-bottom:1px solid #f3f4f6;
  padding:12px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
}
.badge.info{ color:#2563eb; background:#eff6ff; border:1px solid #bfdbfe; }
.badge.success{ color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; }
.badge.warning{ color:#d97706; background:#fffbeb; border:1px solid #fde68a; }
.badge.error{ color:#dc2626; background:#fef2f2; border:1px solid #fecaca; }

.status-chip{
  font-weight:700;
  font-size:12px;
  padding:4px 10px;
  border-radius:999px;
  border:1px solid transparent;
}
.status-scheduled{ background:#eef2ff; color:#1f2937; border-color:#dbeafe; }
.status-sent{ background:#ecfdf5; color:#059669; border-color:#a7f3d0; }

.muted{ color:#6b7280; font-size:13px; }
.flash-ok{
  background:#ecfdf5;
  color:#065f46;
  border:1px solid #a7f3d0;
  border-radius:12px;
  padding:10px 12px;
  margin-bottom:12px;
}
.flash-err{
  background:#fef2f2;
  color:#991b1b;
  border:1px solid #fecaca;
  border-radius:12px;
  padding:10px 12px;
  margin-bottom:12px;
}

/* Responsive: عمود واحد تحت 900px */
@media (max-width:900px){
  .grid-2{ grid-template-columns:1fr; }
}





  
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

<!-- ===================== Page Content ===================== -->
<div class="page-wrap">
  <h2 class="page-title">Create new notification</h2>

  <?php if ($flash_ok): ?><div class="flash-ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="flash-err"><?= h($flash_err) ?></div><?php endif; ?>

  <!-- Create -->
  <div class="card">
    <form method="post" id="createForm" onsubmit="return handleSubmit(event)">
      <div class="grid-2">
        <div class="left">
          <div class="field">
            <span class="label">Audience</span>
            <select class="select" name="audience" required>
              <option value="all">All Customers/Providers</option>
              <option value="customers">All Customers</option>
              <option value="providers">All Providers</option>
            </select>
          </div>

          <div class="field">
            <span class="label">Notification Title</span>
            <input class="input" type="text" name="title" placeholder="Ex: New feature announcement" required>
          </div>

          <div class="field">
            <span class="label">Message</span>
            <textarea class="textarea" name="message" placeholder="Compose your notification message..." required></textarea>
          </div>

          <div class="muted">You may optionally include a link that opens when the user taps the notification.</div>
        </div>

        <div class="right">
          <div class="field">
            <span class="label">Notification Type</span>
            <select class="select" name="ntype" required>
              <option value="info">Info</option>
              <option value="success">Success</option>
              <option value="warning">Warning</option>
              <option value="error">Error</option>
            </select>
          </div>

          <div class="field">
            <span class="label">Link (optional)</span>
            <input class="input" type="url" name="link_url" placeholder="https://example.com/page">
          </div>

          <div class="field">
            <span class="label">Schedule date/time</span>
            <input class="input" type="datetime-local" name="schedule_at">
          </div>
        </div>
      </div>

      <div class="btns">
        <button class="btn btn-secondary" type="submit" name="action" value="schedule">Schedule Send</button>
        <button class="btn btn-primary"  type="submit" name="action" value="send_now">Send Immediately</button>
      </div>
    </form>
  </div>

  <!-- Scheduled list -->
  <h3 class="page-title" style="margin-top:20px;">Schedule notification</h3>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:120px">notification id</th>
            <th>Audience</th>
            <th>title</th>
            <th style="width:220px">schedule date/time</th>
            <th style="width:120px">status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$scheduled): ?>
            <tr><td colspan="5" class="muted">No scheduled notifications</td></tr>
          <?php else: foreach($scheduled as $row): ?>
            <tr>
              <td>#<?= (int)$row['id'] ?></td>
              <td><?= h($row['audience']) ?></td>
              <td><?= h($row['title']) ?></td>
              <td><?= h($row['schedule_at']) ?></td>
              <td><span class="status-chip status-scheduled">scheduled</span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- History -->
  <h3 class="page-title" style="margin-top:12px;">sent notifications history</h3>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:120px">notification id</th>
            <th>Audience</th>
            <th>title</th>
            <th style="width:200px">date sent</th>
            <th style="width:120px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$history): ?>
            <tr><td colspan="5" class="muted">No sent notifications yet</td></tr>
          <?php else: foreach($history as $row): ?>
            <tr>
              <td>#<?= (int)$row['id'] ?></td>
              <td><?= h($row['audience']) ?></td>
              <td><?= h($row['title']) ?></td>
              <td><?= h($row['created_at']) ?></td>
              <td><span class="status-chip status-sent">sent</span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
/* منع الإرسال بخيار "Schedule" بدون وقت */
function handleSubmit(e){
  const btn = document.activeElement; // الزر اللي ضغطه المستخدم
  if (!btn || !btn.name || btn.value !== 'schedule') return true;
  const dt = document.querySelector('input[name="schedule_at"]');
  if (!dt || !dt.value) {
    alert('Please choose schedule date/time before scheduling.');
    e.preventDefault();
    return false;
  }
  return true;
}
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