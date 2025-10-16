
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
  if (!$dbPath) return $base . "/image/no-avatar.png";
  if (preg_match('~^https?://~i', $dbPath)) return $dbPath;
  $dbPath = str_replace('\\','/',$dbPath);
  $dbPath = ltrim($dbPath, '/');
  return rtrim($base, '/') . '/' . $dbPath;
}
function slugify($s){
  $s = mb_strtolower($s ?? '', 'UTF-8');
  $s = preg_replace('~[^\p{L}\p{Nd}]+~u','-', $s);
  $s = trim($s,'-'); 
  return $s === '' ? 'na' : $s;
}

/* ==== المستخدم الحالي ==== */
$uid  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$providerName  = "Unknown User";
$providerPhoto = $BASE . "/image/no-avatar.png";
$PROVIDER_ID   = 0;

// لو المستخدم مزود => استخدمه
if ($role === 'provider' && $uid > 0) {
  $PROVIDER_ID = $uid;
} else {
  // تجربة: اختر أول مزود موجود
  $tmp = $conn->query("SELECT id FROM users WHERE role='provider' LIMIT 1");
  if ($tmp && $tmp->num_rows) { $PROVIDER_ID = (int)$tmp->fetch_assoc()['id']; }
}

// اسم وصورة المستخدم الحالي (الظاهر في الهيدر)
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





// هل المزوّد موقّف من الإدارة؟
$adminSuspended = false;
if ($PROVIDER_ID > 0) {
  if ($st = $conn->prepare("SELECT status FROM users WHERE id=? LIMIT 1")) {
    $st->bind_param("i", $PROVIDER_ID);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $adminSuspended = (strtolower((string)($row[0] ?? '')) === 'suspended');
    $st->close();
  }
}

// حالة التوفر من provider_profiles.is_available (1=متاح, 0=غير متاح)
$isAvailable = true; // افتراضي
if ($PROVIDER_ID > 0) {
  if ($st = $conn->prepare("SELECT is_available FROM provider_profiles WHERE user_id=? LIMIT 1")) {
    $st->bind_param("i", $PROVIDER_ID);
    $st->execute();
    if ($row = $st->get_result()->fetch_row()) {
      $isAvailable = ((int)$row[0] === 1);
    }
    $st->close();
  }
}









/* ==== KPIs (lifetime) ==== */

/* خُذ المزوّد من الجلسة */
$PROVIDER_ID = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

/* مصفوفة الافتراضات */
$kpi = [
  'total_customers' => 0,
  'upcoming'        => 0,
  'completed'       => 0,
  'cancelled'       => 0,
  'earnings'        => 0.0,
];

/* إجمالي العملاء (غير محذوفين) */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer' AND is_deleted=0")) {
  $row = $res->fetch_assoc();
  $kpi['total_customers'] = (int)($row['c'] ?? 0);
}

/* لو إحنا على داشبورد مزوّد */
if ($PROVIDER_ID > 0) {
  /* Upcoming / Completed / Cancelled — بدون فلترة تاريخ */
  $sql = "SELECT
            SUM(CASE WHEN LOWER(b.status) IN ('pending','confirmed','in_progress') THEN 1 ELSE 0 END) AS upcoming,
            SUM(CASE WHEN LOWER(b.status) IN ('completed','done','finished') THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN LOWER(b.status) IN ('cancelled','missed','no_show') THEN 1 ELSE 0 END) AS cancelled
          FROM bookings b
          WHERE b.provider_id = ?
            AND b.is_deleted = 0";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("i", $PROVIDER_ID);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $kpi['upcoming']  = (int)($r['upcoming']  ?? 0);
    $kpi['completed'] = (int)($r['completed'] ?? 0);
    $kpi['cancelled'] = (int)($r['cancelled'] ?? 0);
    $st->close();
  }

  /* Earnings — نجمع price_from من الخدمات للحجوزات المكتملة */
  $sql = "SELECT SUM(COALESCE(s.price_from,0)) AS earn
          FROM bookings b
          JOIN services s ON s.id = b.service_id
          WHERE b.provider_id = ?
            AND b.is_deleted = 0
            AND s.is_deleted = 0
            AND LOWER(b.status) IN ('completed','done','finished')";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("i", $PROVIDER_ID);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $kpi['earnings'] = (float)($r['earn'] ?? 0);
    $st->close();
  }
}

/* ملاحظة: لو بدك أرقام الشهر الحالي بدل lifetime
   ارجع فلترة التاريخ بـ COALESCE(b.scheduled_at, b.created_at)
   زي ما شرحنا سابقًا، بس بعد ما تتأكد إن التواريخ بالحجوزات واقعة داخل الشهر. */



/* ==== منيو الخدمات ==== */
$services = [];
if ($PROVIDER_ID > 0) {
  $sql = "SELECT id, title FROM services WHERE provider_id=? ORDER BY title";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("i", $PROVIDER_ID);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()){
      $services[] = ['id'=>(int)$row['id'], 'title'=>$row['title'], 'slug'=>slugify($row['title'])];
    }
    $st->close();
  }
}

/* ==== حجوزات الجدول ==== */
$rows = [];
if ($PROVIDER_ID > 0) {
$sql = "
  SELECT 
    b.id, b.customer_id, b.service_id,
    COALESCE(b.phone,'') AS phone,
    b.scheduled_at, b.created_at, b.status,
    u.full_name AS customer_name,
    s.title AS service_title
  FROM bookings b
  LEFT JOIN users u ON u.id = b.customer_id
  LEFT JOIN services s ON s.id = b.service_id
  WHERE s.provider_id = ?
  ORDER BY
    CASE 
      WHEN b.status IN ('pending','confirmed') THEN 1
      WHEN b.status = 'in_progress' THEN 2
      WHEN b.status IN ('completed','done','finished') THEN 3
      WHEN b.status = 'cancelled' THEN 4
      ELSE 5
    END,
    CASE 
      WHEN b.status IN ('pending','confirmed','in_progress') 
      THEN COALESCE(b.scheduled_at, b.created_at)
    END ASC,
    CASE 
      WHEN b.status IN ('completed','done','finished','cancelled') 
      THEN COALESCE(b.scheduled_at, b.created_at)
    END DESC,
    b.id DESC
  LIMIT 100
";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("i", $PROVIDER_ID);
    $st->execute();
    $res = $st->get_result();
    while($r = $res->fetch_assoc()){
      $statusKey   = strtolower(trim($r['status'] ?? 'pending'));
      $statusLabel = ucfirst($statusKey);
      $rows[] = [
        'id'            => (int)$r['id'],
        'customer_name' => $r['customer_name'] ?: 'Customer #'.$r['customer_id'],
        'service_title' => $r['service_title'] ?: '—',
        'phone'         => $r['phone'] ?: 'N/A',
        'scheduled_at'  => $r['scheduled_at'],
        'status_key'    => $statusKey,
        'status_label'  => $statusLabel,
        'service_slug'  => slugify($r['service_title']),
      ];
    }
    $st->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – My booking</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="/mp/css/help_center.css?v=1">
  <style>
    :root{
      --bg1:#f3f8ff; --bg2:#eef6ff; --text:#0b0f1a; --muted:#99a3b2;
      --card:#ffffff; --border:#edf0f4; --radius-xl:26px; --radius-md:10px;
      --shadow:0 20px 46px rgba(22,60,120,.12);
      --primary:#137BEA; --blue:#1e90ff;
      --green:#12b886; --green-100:#e6fff7; --green-200:#baf2de;
      --amber:#f59f00; --amber-100:#fff3cd; --amber-200:#ffe19a;
      --red:#ef4444; --red-100:#ffe3e3; --red-200:#f7b6b6;
      --blue-100:#e1efff;
    }
    *{ box-sizing:border-box }
    html,body{ margin:0; font-family:'Nunito',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif; color:var(--text); }
    .wrap{ max-width:1060px; margin:26px auto 40px; padding:0 22px; }
    .toprow{ display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .welcome{ margin:0; font-size:22px; font-weight:800; }
    .right-actions{ display:flex; align-items:center; gap:18px; }
    .availability{ display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
    .availability .ask{ color:#7a84a0; font-weight:700; font-size:14px; }
    .availability input{ display:none; }
    .switch{ width:46px; height:24px; border-radius:999px; background:#d5dbe6; position:relative; box-shadow:inset 0 2px 6px rgba(0,0,0,.06); transition:background .15s ease;}
    .switch .knob{ position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.15); transition:left .15s ease;}
    .switch.on{ background:var(--blue); } .switch.on .knob{ left:24px; }
    .btn-primary{ display:inline-flex; align-items:center; justify-content:center; height:44px; padding:0 18px; background:var(--blue); color:#fff; text-decoration:none; font-weight:800; font-size:14px; border-radius:10px; box-shadow:0 10px 24px rgba(30,144,255,.25); }


.card.kpi{ background:var(--card); border-radius:28px; box-shadow:var(--shadow); padding:22px 26px; }
    .kpi-grid{ display:flex; align-items:center; justify-content:space-between; gap:26px; flex-wrap:wrap; }
    .kpi-item{ display:flex; align-items:center; gap:18px; min-width:220px; }
    .kpi-icon{ width:62px; height:62px; border-radius:50%; display:grid; place-items:center; background:radial-gradient(120% 120% at 30% 20%, #bfe0ff 0%, #86c5ff 45%, #5ab1ff 100%); box-shadow:inset 0 0 0 6px rgba(255,255,255,.42); }
    .kpi-icon svg{ width:30px; height:30px; }
    .kpi-label{ color:#9aa3b2; font-size:13px; font-weight:800; margin-bottom:4px; }
    .kpi-value{ font-size:28px; font-weight:800; color:#1d2736; line-height:1.1; }
    .kpi-value.money{ letter-spacing:.5px; }
    .kpi-sub{ margin-top:6px; font-size:12px; color:#8c95a6; font-weight:700; }

    .ql-title{ margin:0 0 10px; font-size:22px; font-weight:800; }
    .ql-card{ background:var(--card); border-radius:var(--radius-xl); box-shadow:var(--shadow); padding:18px; }
    .ql-head{ display:flex; align-items:center; justify-content:space-between; padding:10px 8px 16px; }
    .ql-heading{ margin:0; font-size:22px; font-weight:800; }
    .filters{ display:flex; gap:12px; }
    .filter-btn{ display:inline-flex; align-items:center; gap:10px; height:36px; padding:0 14px; border-radius:10px; border:1px solid #d8dde6; background:#f3f5f7; color:#1f2a44; font-weight:800; font-size:14px; cursor:pointer; }
    .filter-btn svg{ width:16px; height:16px; }
    .filter-btn.active{ outline:2px solid #b9d9ff; }
    .dropdown{ position:relative; }
    .menu{ position:absolute; top:calc(100% + 8px); right:0; background:#fff; border:1px solid #e6ecf5; border-radius:12px; box-shadow:0 16px 36px rgba(20,40,80,.14); z-index:30; display:none; padding:12px; }
    .menu.open{ display:block; }
    .list-menu ul{ list-style:none; margin:0; padding:4px; min-width:200px; }
    .list-menu li{ padding:8px 10px; border-radius:8px; font-weight:800; color:#1f2a44; cursor:pointer; }
    .list-menu li:hover{ background:#f1f6ff; }
    .list-menu li.active{ background:#e7f1ff; outline:1px solid #b7d0ff; }
    .date-menu{ width:320px; }
    .cal-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .cal-title{ font-weight:800; font-size:16px; }
    .cal-nav{ width:30px; height:28px; border-radius:8px; border:1px solid #d8dde6; background:#f7fbff; font-weight:900; cursor:pointer; }
    .cal-week{ display:grid; grid-template-columns:repeat(7,1fr); gap:4px; color:#9aa3b2; font-size:12px; font-weight:800; text-align:center; margin-bottom:4px; }
    .cal-grid{ display:grid; grid-template-columns:repeat(7,1fr); grid-auto-rows:38px; gap:4px; user-select:none; }
    .cal-day{ display:flex; align-items:center; justify-content:center; border-radius:8px; font-weight:800; cursor:pointer; color:#0b0f1a; }
    .cal-day:hover{ background:#f1f6ff; }
    .cal-day.muted{ color:#b6bfcc; }
    .cal-day.today{ outline:2px solid var(--blue); outline-offset:-2px; }
    .cal-day.selected{ background:var(--blue); color:#fff; }
    .cal-foot{ margin-top:8px; padding-top:8px; border-top:1px solid #e6ecf5; font-size:13px; color:#1f2a44; }

  
.table{ 
width:100%; 
border-top:1px solid var(--border); 
}
.thead, .trow{
  display:grid; 
  gap:12px; 
  align-items:center; 
  padding:14px 8px;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr 1.2fr 1.8fr;
}
.thead{ 
color:#9aa3b2; 
font-size:13px; 
font-weight:800; 
}
.trow{ 
border-top:1px solid var(--border); 
font-size:14px; 
}
.link{ 
color:#1e6ef7; 
font-weight:800; 
text-decoration:none; 
}

.badge{
  display:inline-flex; 
  align-items:center; 
  justify-content:center;
  height:28px; 
  padding:0 12px; 
  border-radius:8px; 
  font-size:13px; 
  font-weight:800; 
  border:1px solid transparent;
}
.badge.green{ 
background:#e6fff7; 
color:#0f8e6b; 
border-color:#baf2de; 
}
.badge.green.soft{ 
background:var(--green-100); 
}
.badge.amber{ 
background:var(--amber-100); 
color:#8a5a00; 
border-color:#ffe19a; 
}
.badge.red{ 
background:#ffe7e7; 
color:#b4231a; 
border-color:#f7b6b6; 
}
.badge.blue{ 
background:var(--blue-100); 
color:#1658c5; 
border-color:#b7d0ff; 
}

.btn-table{
  display:inline-flex; 
  align-items:center; 
  justify-content:center;
  height:32px; 
  padding:0 14px; 
  border-radius:8px;
  font-weight:800; 
  text-decoration:none; 
  border:1.5px solid transparent;
  background:transparent; 
  color:inherit; 
  box-shadow:none;
}
.btn-table.primary{ 
background:#1e90ff; 
color:#fff; 
box-shadow:var(--shadow-btn); 
font-size: small; 
}
.btn-table.primary.ghost{ 
background:#e7f1ff; 
color:#145db8; 
border-color:#b7d0ff; 
box-shadow:none; 
}
.btn-table.outline.red{ 
background:#fff; 
color:#d12a20; 
border-color:#ffc8c8; 
}
.btn-table.solid.red{ 
background:#ff5a59; 
color:#fff; 
border-color:transparent; 
}
.btn-table.soft.green{ 
background:var(--green-100); 
color:#0f8e6b; 
border-color:#baf2de; 
}

.actions{ 
display:flex; 
gap:10px; 
}

.qa-title{ 
margin:0 0 12px; 
font-size:20px; 
font-weight:800; 
}
.qa-grid{ 
display:flex; 
align-items:center; 
justify-content:space-between; 
gap:28px; 
}
.qa-btn{
  display:flex; 
  align-items:center; 
  justify-content:space-between;
  width:260px; 
  height:44px; 
  padding:0 18px 0 22px; 
  border-radius:10px;
  background:var(--primary); 
  color:#fff; 
  text-decoration:none; 
  font-weight:800; 
  font-size:15px;
  box-shadow:var(--shadow-btn);
}
.qa-btn svg{ 
width:22px; 
height:22px; 
stroke:#fff; 
fill:none; 
stroke-width:2; 
}

.stat-ico i{
  font-size: 22px;   /* نفس مقاس الـSVG القديم */
  color: #fff;       /* نحافظ على الأبيض فوق الخلفية المتدرّجة */
}

@media (max-width:980px){
  .thead,.trow{ grid-template-columns: 1.6fr 1.2fr 1.4fr 1.4fr 1fr 1.6fr; }
}
@media (max-width:760px){
  .tabs{ gap:12px; }
  .tab{ min-width:140px; height:40px; }
  .filters{ display:none; }
  .thead{ display:none; }
  .trow{
    grid-template-columns:1fr;
    gap:6px; border-top:1px solid var(--border);
    padding:14px 6px 16px;
  }
  .trow > div{ display:flex; justify-content:space-between; }
  .trow > div:nth-child(1){ font-weight:800; }
  .actions{ justify-content:flex-end; }
  .qa-grid{ flex-wrap:wrap; gap:16px; }
  .qa-btn{ width:100%; }
  
}


/* إصلاح محاذاة Status و Action */
.thead, .trow{
  display: grid; 
  gap: 8px;
  align-items: center;
  padding: 14px 8px;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr 1.2fr 2fr; /* غير الأعمدة الأخيرة */
  min-height: 60px;
}

.trow > div {
  display: flex;
  align-items: center;
  height: 100%;
  justify-content: flex-start;
}

/* خاص لعمود Status */
.trow > div:nth-child(5) {
  justify-content: center;
  align-items: center;
}

/* خاص لعمود Action */
.trow > div:nth-child(6) {
  justify-content: flex-start;
  align-items: center;
  padding: 0;
}

.badge {
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 90px;
  margin: 0;
}

.actions {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 100%;
  justify-content: flex-start;
  margin: 0;
  padding: 0;
}

.btn-table {
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
  font-size: 13px;
  white-space: nowrap;
  min-width: 80px;
  margin: 0;
}


.btn-table.solid.red{ 
  background:#ff5a59; 
  color:#fff; 
  border-color:transparent; 
  min-width: 90px;
  padding: 0 14px;
  font-size: 13px;
} 



.badge.orange { 
    background: #fff4e6; 
    color: #d46b08; 
    border-color: #ffd591; 

}





.switch.disabled{ opacity:.5; cursor:not-allowed }
.availability[title] { cursor: help; }



  </style>
</head>
<body>

<!-- سايدبارك كما هو -->
<div class="sidebar" id="sidebar">
  <button class="sidebar-close" id="closeSidebar" aria-label="Close menu">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <h3>Menu</h3>
  <ul>
    <li><a href="dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a></li>
    <li><a href="my booking dashboard.php"><i class="fa-regular fa-calendar"></i> My booking</a></li>
    <li><a href="my service dashboard.php"><i class="fa-solid fa-cart-shopping"></i> Services</a></li>
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

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-content">
  <!-- ===== Topbar ===== -->
  <section class="topbar">
    <div class="tb-inner" style="max-width:1200px;margin:0 auto;padding:18px 24px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px">
      <div class="tb-left" style="display:flex;align-items:center;gap:50px">
        <button class="icon-btn" aria-label="Settings" id="openSidebar" style="width:40px;height:40px;display:grid;place-items:center;border:none;background:transparent;cursor:pointer">
          <i class="fa-solid fa-gear" style="font-size:18px;color:#6b7280"></i>
        </button>
        <div class="brand">
          <img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo" style="width:150px;height:auto;object-fit:contain">
        </div>
      </div>
      <div class="tb-center" style="display:flex;justify-content:center">
        <div class="search-wrap" style="position:relative;width:min(680px,90%);margin-left:90px">
          <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a94a6;font-size:18px"></i>
          <input type="text" placeholder="Search Here"
                 style="width:500px;height:48px;padding:0 16px 0 44px;border:1px solid #cfd7e3;border-radius:12px;font-size:16px;background:#fff;outline:none">
        </div>
      </div>

      
   <div class="tb-right" style="display:flex;align-items:center;gap:35px">
    <button class="notif-pill" id="notifButton" aria-label="Notifications" 
            style="width:42px;height:42px;display:grid;place-items:center;border:1px solid #dfe6ef;background:#fff;border-radius:50%;cursor:pointer;position:relative">
        <i class="fa-solid fa-bell" style="font-size:18px;color:#1e73ff"></i>
        <span id="notifCount" style="display:none;position:absolute;top:-8px;right:-8px;background:#ef4444;color:white;border-radius:50%;min-width:18px;height:18px;font-size:10px;font-weight:bold;display:flex;align-items:center;justify-content:center;border:2px solid white;line-height:1">0</span>
    </button>


<div class="profile-menu" style="position:relative">
     <button class="profile-trigger" type="button" aria-expanded="false"
        style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #e5e7eb;padding:6px 12px;border-radius:40px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.06)">
  <img class="avatar" src="<?= h($providerPhoto) ?>" alt="Profile"
       onerror="this.src='<?= $BASE ?>/image/no-avatar.png'"
       style="width:48px;height:48px;object-fit:cover;border-radius:50%;display:block">
  <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</button>

          <div class="menu-card" hidden
               style="position:absolute;right:0;top:calc(100% + 10px);z-index:9999;width:280px;background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 12px 30px rgba(0,0,0,.12);padding:12px;overflow:auto;max-height:80vh">
            <a class="menu-item" href="identification.php" style="width:100%;display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;color:#0f172a;text-decoration:none;font-weight:600;background:#fff;border:0;cursor:pointer">
              <i class="fa-solid fa-gear"></i> <span>Account Settings</span>
            </a>
            <hr class="divider" style="border:0;height:1px;background:#e5e7eb;margin:4px 0">
           <a class="menu-item danger"
   href="<?= $BASE ?>/php/logout.php"
   style="color:#dc2626; display:flex; align-items:center; justify-content:space-between; gap:10px; white-space:nowrap;">
  <span>Log Out</span>
  <i class="fa-solid fa-right-from-bracket"></i>
</a>
            </a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ===== عنوان + سويتش + زر ===== -->
  <section class="wrap">
    <div class="toprow">
      <h1 class="welcome">Welcome Back, <?= h($providerName) ?></h1>

      <div class="right-actions">
  <label class="availability" for="availToggle" <?= $adminSuspended ? 'title="Your account is suspended by admin"' : '' ?>>
    <span class="ask">Are You Available For Work Now?</span>
    <input id="availToggle" type="checkbox" <?= $isAvailable ? 'checked' : '' ?> <?= $adminSuspended ? 'disabled' : '' ?> />
    <span id="switch"
          class="switch<?= $isAvailable ? ' on' : '' ?><?= $adminSuspended ? ' disabled' : '' ?>"
          role="switch"
          aria-checked="<?= $isAvailable ? 'true' : 'false' ?>">
      <span class="knob"></span>
    </span>
  </label>

  <!-- <a class="btn-primary" href="#">Go To My Bookings</a> -->
   <button class="btn-primary" onclick="window.location.href='my booking dashboard.php'">
  Go to My Booking
</button>
</div>
    </div>


    <!-- KPIs -->
    <div class="card kpi">
      <div class="kpi-grid">
        <!-- Total Customers -->
        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M15 9a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z" fill="#fff"/>
              <path d="M2 19a6 6 0 0 1 11.7-2M12 19a6 6 0 0 1 11.7-2" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Total Customers</div>
            <div class="kpi-value"><?= number_format($kpi['total_customers']) ?></div>
          </div>
        </div>

        <!-- Upcoming Jobs -->
        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="4" width="18" height="16" rx="3" fill="none" stroke="#fff" stroke-width="2"/>
              <path d="M7 2v4M17 2v4M3 9h18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Upcoming Jobs</div>
            <div class="kpi-value"><?= (int)$kpi['upcoming'] ?></div>
            <div class="kpi-sub">Scheduled this month</div>
          </div>
        </div>


<!-- Completed Jobs -->
        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="8.5" fill="none" stroke="#fff" stroke-width="2"/>
              <path d="M8 12.5l2.6 2.6L16.5 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Completed Jobs</div>
            <div class="kpi-value"><?= (int)$kpi['completed'] ?></div>
          </div>
        </div>

        <!-- Earnings -->
        <div class="kpi-item">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="8.5" fill="none" stroke="#fff" stroke-width="2"/>
              <path d="M12 7v10M15.5 9.5a3.5 3.5 0 0 0-7 0c0 1.9 1.6 3 3.5 3s3.5 1.1 3.5 3a3.5 3.5 0 0 1-7 0" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-meta">
            <div class="kpi-label">Earnings</div>
            <div class="kpi-value money">$ <?= number_format($kpi['earnings'], 2) ?></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wrap">
    <h2 class="ql-title">Quick Look</h2>

    <div class="ql-card">
      <header class="ql-head">
        <h3 class="ql-heading">Upcoming Bookings</h3>

        <div class="filters">
          <!-- Date -->
          <div class="dropdown">
            <button id="btnDate" class="filter-btn" type="button" aria-expanded="false">
              <span>Date</span>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M7 2v4M17 2v4M3 9h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>

            <div id="menuDate" class="menu date-menu" aria-hidden="true">
              <div class="cal-head">
                <button id="calPrev" class="cal-nav" aria-label="Prev month">‹</button>
                <div id="calTitle" class="cal-title">Month YYYY</div>
                <button id="calNext" class="cal-nav" aria-label="Next month">›</button>
              </div>
              <div class="cal-week">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
              </div>
              <div id="calGrid" class="cal-grid"></div>
              <div class="cal-foot">
                <span>Today: </span><strong id="todayStr"></strong>
              </div>
            </div>
          </div>
          <div class="dropdown">
            <button id="btnStatus" class="filter-btn" type="button" aria-expanded="false">
              <span>Status</span>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
              </svg>
            </button>
            <div id="menuStatus" class="menu list-menu" aria-hidden="true">
              <ul>
                <li data-value="all" class="active">All statuses</li>
                <li data-value="confirmed">Confirmed</li>
                <li data-value="pending">Pending</li>
                <li data-value="in_progress">In Progress</li>
                <li data-value="completed">Completed</li>
                <li data-value="cancelled">Cancelled</li>
              </ul>
            </div>
          </div>

          <!-- Services -->
          <div class="dropdown">
<button id="btnServices" class="filter-btn" type="button" aria-expanded="false">
              <span>Services</span>
              <svg viewBox="0 0 24 24" aria-hidden="true">=
                <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
              </svg>
            </button>
            <div id="menuServices" class="menu list-menu" aria-hidden="true">
              <ul>
                <li data-service="all" class="active">All services</li>
                <?php foreach ($services as $sv): ?>
                  <li data-service="<?= h($sv['slug']) ?>"><?= h($sv['title']) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </header>

        <div class="table">
        <div class="thead">
          <div>Client name</div>
          <div>Service</div>
          <div>Phone Number</div>
          <div>Date &amp; Time</div>
          <div>Status</div>
          <div>Action</div>
        </div>

    <?php foreach ($rows as $r): 
    $badgeClass = 'blue';
    if ($r['status_key'] === 'confirmed') $badgeClass = 'green';
    elseif ($r['status_key'] === 'pending') $badgeClass = 'amber';
    elseif ($r['status_key'] === 'in_progress') $badgeClass = 'orange'; // أضف هذا السطر
    elseif ($r['status_key'] === 'cancelled') $badgeClass = 'red';
    elseif ($r['status_key'] === 'completed') $badgeClass = 'green soft'; // أضف هذا السطر
    $serviceSlug = slugify($r['service_title']);
?>
          <div class="trow" data-status="<?= h($r['status_key']) ?>" data-service="<?= h($serviceSlug) ?>">

<div><a class="link" href="#"><?= h($r['customer_name']) ?></a></div>
            <div><?= h($r['service_title']) ?></div>
            <div><?= h($r['phone']) ?></div>
            <div><?= date('M d, h:i A', strtotime($r['scheduled_at'] ?? 'now')) ?></div>
            <div><span class="badge <?= $badgeClass ?>"><?= h($r['status_label']) ?></span></div>
          <div class="actions">
    <?php if ($r['status_key'] === 'pending'): ?>
        <!-- حالة الانتظار -->
        <button class="btn-table primary" onclick="updateBooking(<?= $r['id'] ?>, 'confirmed')">Confirm</button>
        <button class="btn-table outline red" onclick="updateBooking(<?= $r['id'] ?>, 'cancelled')">Reject</button>
    
    <?php elseif ($r['status_key'] === 'confirmed'): ?>
        <!-- حالة التأكيد -->
        <button class="btn-table primary" onclick="updateBooking(<?= $r['id'] ?>, 'in_progress')">Start job</button>
        <button class="btn-table outline red" onclick="updateBooking(<?= $r['id'] ?>, 'cancelled')">Cancel</button>
    
    <?php elseif ($r['status_key'] === 'in_progress'): ?>
        <!-- حالة التنفيذ -->
        <button class="btn-table primary" onclick="updateBooking(<?= $r['id'] ?>, 'completed')">Mark Completed</button>
        <button class="btn-table outline red" onclick="updateBooking(<?= $r['id'] ?>, 'cancelled')">Cancel Job</button>
    
    <?php elseif ($r['status_key'] === 'completed'): ?>
        <!-- حالة الاكتمال -->
        <button class="btn-table primary ghost" onclick="viewBooking(<?= $r['id'] ?>)">View Only</button>
    
    <?php elseif ($r['status_key'] === 'cancelled'): ?>
        <!-- حالة الإلغاء -->
        <button class="btn-table solid red" disabled>Cancelled</button>
    
    <?php else: ?>
        <!-- حالة افتراضية -->
        <button class="btn-table" onclick="viewBooking(<?= $r['id'] ?>)">Details</button>
    <?php endif; ?>
</div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($rows)): ?>
          <div class="trow" style="grid-column:1/-1; color:#6b7280;">
            No bookings yet.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Quick Actions Panel -->
  <section class="qa-wrap" style="max-width:1060px;margin:14px auto 24px;padding:0 22px">

<h3 class="qa-title" style="margin:0 0 12px;font:800 20px/1.1 Nunito,sans-serif;color:#0b0f1a">Quick Actions Panel</h3>
    <div class="qa-grid" style="display:flex;align-items:center;justify-content:space-between;gap:28px">
      <a class="qa-btn" href="" onclick="document.getElementById('avOverlay').style.display='flex';return false;">
        <span>Add Availability</span>
        <svg class="qa-ico" viewBox="0 0 24 24" aria-hidden="true" style="width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M12 7v5l3 1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </a>

    <a class="qa-btn" href="#" onclick="openServicePopup();return false;">
        <span>Add New Service</span>
        <svg class="qa-ico" viewBox="0 0 24 24" aria-hidden="true" style="width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M12 8v8M8 12h8" stroke-linecap="round"></path>
        </svg>
      </a>

<a class="qa-btn" id="openPaymentBtn" href="#" onclick="openPaymentPopup();return false;">
        <span>Upload Payment Proof</span>
        <svg class="qa-ico box" viewBox="0 0 24 24" aria-hidden="true" style="width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2">
          <rect x="3" y="6" width="18" height="12" rx="4" ry="4"></rect>
          <circle cx="12" cy="12" r="3.2"></circle>
          <path d="M8.2 6l1.2-1.6h5.2L15.8 6" stroke-linecap="round"></path>
        </svg>
      </a>
    </div>
  </section>
</div>










<!-- ===== Availability Popup v4 (STATIC DAYS, no build errors) ===== -->
<div id="availabilityPopupRoot">
  <style>
    #availabilityPopupRoot{font-family: Inter,"Segoe UI",Tahoma,Arial}
    .av-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;z-index:100000;align-items:center;justify-content:center}
    .av-modal{width:96%;max-width:1100px;background:#fff;border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.25);overflow:hidden}
    .av-head{padding:18px 22px;border-bottom:1px solid #e8eef4;text-align:center}
    .av-title{margin:0;font:800 20px/1 Nunito,system-ui;color:#0aa0ff}
    .av-body{padding:14px 18px 8px}
    /* صف واحد: Day+Toggle | From | time | To | time | Add | Copy */
    .day-block{padding:8px 0;border-bottom:1px solid #f1f5f9}
    .row{display:grid;grid-template-columns:190px 70px 140px 50px 140px 180px 220px;gap:10px;align-items:center}
    .row.extra{grid-template-columns:190px 70px 140px 50px 140px 34px 1fr}
     .day-cell{
  display:flex;
  align-items:center;   /* يضلوا جمب بعض */
  justify-content:space-between; /* يخلي بينهم مسافة */
  gap:18px;             /* المسافة بين اسم اليوم والسويتش (عدّل الرقم حسب رغبتك) */
  font-weight:800;
  color:#0b0f1a;
  min-width:180px;      /* ثبّت عرض العمود عشان كل السويتشات يصفوا تحت بعض */
}
    .switch{position:relative;width:44px;height:24px}
    .switch input{display:none}
    .slider{position:absolute;inset:0;background:#e5e7eb;border-radius:999px;cursor:pointer;transition:.25s}
    .slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:#fff;border-radius:999px;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:.25s}
    .switch input:checked + .slider{background:#1e90ff}
    .switch input:checked + .slider:before{transform:translateX(20px)}
    .mini-label{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 10px;border:1px solid #e8eef4;border-radius:8px;background:#f8fafc;color:#0b0f1a;font-weight:700}
    .time{height:38px;width:100%;padding:0 12px;border:1px solid #e8eef4;border-radius:8px;background:#fff;color:#111}
    .btn{display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 14px;border:1px solid #e8eef4;border-radius:8px;background:#fff;color:#111;text-decoration:none;cursor:pointer;font-weight:700;justify-content:center}
    .btn-soft{background:#f8fafc}
    .btn-primary{background:#1e90ff;color:#fff;border-color:#1e90ff}
    .remove-slot{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid #ffe3e3;background:#fff5f5;color:#ef4444;cursor:pointer;font-size:18px;font-weight:800}
    .day-disabled .time,.day-disabled .mini-label,.day-disabled .btn{opacity:.45;pointer-events:none}
    .av-foot{display:flex;justify-content:center;gap:18px;padding:16px;border-top:1px solid #e8eef4;background:#fafbfc}
    @media (max-width:1020px){.row{grid-template-columns:170px 60px 1fr 40px 1fr 1fr 1fr}}
    @media (max-width:720px){.row{grid-template-columns:150px 60px 1fr 40px 1fr 1fr 1fr}}
    /* أيقونات */
    .ico{width:18px;height:18px}
    .btn-soft_p, 
.btn-soft_p:focus, 
.btn-soft_p:active, 
.btn-soft_p:hover {
    background: #fff;
    color: #ef4444;
    border: 1px solid #ef4444 !important;
}

.btn-soft_p:hover {
    background: #fee2e2;
}
  </style>

  <div class="av-overlay" id="avOverlay" role="dialog" aria-modal="true" aria-labelledby="avTitle">
    <div class="av-modal">
      <div class="av-head"><h3 id="avTitle" class="av-title">Set Your Availability</h3></div>

      <div class="av-body" id="daysContainer">
        <!-- ===== 7 أيام ثابتة، كل يوم فيه سطر أساسي جاهز ===== -->
        <!-- helper: slot-row(template) -->
        <!-- Sunday -->
        <div class="day-block" data-day="Sunday">
          <div class="row main">
            <div class="day-cell">
              <span>Sunday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">

<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>

        <!-- Monday -->
        <div class="day-block" data-day="Monday">
          <div class="row main">
            <div class="day-cell"><span>Monday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>

        <!-- Tuesday -->
        <div class="day-block" data-day="Tuesday">
          <div class="row main">
            <div class="day-cell"><span>Tuesday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>

        <!-- Wednesday -->
        <div class="day-block" data-day="Wednesday">
          <div class="row main">
            <div class="day-cell"><span>Wednesday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">

<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>

        <!-- Thursday -->
        <div class="day-block" data-day="Thursday">
          <div class="row main">
            <div class="day-cell"><span>Thursday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>

        <!-- Friday -->
        <div class="day-block" data-day="Friday">
          <div class="row main">
            <div class="day-cell"><span>Friday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>

        <!-- Saturday -->
        <div class="day-block" data-day="Saturday">
          <div class="row main">
            <div class="day-cell"><span>Saturday</span>
              <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div><input class="time from" type="time" value="12:00" step="900">
            <div class="mini-label">To</div><input class="time to"   type="time" value="19:00" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="7" width="11" height="13" rx="2"/><rect x="4" y="4" width="11" height="13" rx="2"/></svg>
              Copy to all weekdays
            </button>
          </div>
        </div>
      </div>


<div class="av-foot">
      <button type="button" class="btn btn-primary" id="saveAvailabilityBtn">Save Availability</button>
    <button type="button" class="btn btn-soft_p" onclick="document.getElementById('avOverlay').style.display='none'">Cancel</button>
      </div>
    </div>
  </div>

 
</div>



<script>
// تفاعل بسيط بدون بناء DOM — يعمل مع الهيكل الحالي تمامًا
(function () {
  const container = document.getElementById('daysContainer');

  // مبدئيًا: النسخ لأيام الأسبوع (Mon–Fri). لو بدك لكل الأيام بدّل المصفوفة بالأسفل.
  // const weekdayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
  const weekdayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; // ← لكل الأيام

  // Toggle تمكين/تعطيل اليوم
  container.addEventListener('change', (e) => {
    if (e.target.matches('.switch input')) {
      const block = e.target.closest('.day-block');
      block.classList.toggle('day-disabled', !e.target.checked);
    }
  });

  container.addEventListener('click', (e) => {
    // إضافة سطر جديد (محاذاة 1:1 مع السطر الأساسي عبر ghost-switch)
    if (e.target.closest('.add-slot')) {
      const block = e.target.closest('.day-block');
      const day = block.dataset.day;
      const extra = document.createElement('div');
      extra.className = 'row extra';
      extra.innerHTML = `
        <div class="day-cell">
          <span>${day}</span>
          <span class="ghost-switch"></span>
        </div>
        <div class="mini-label">From</div>
        <input class="time" type="time" value="12:00" step="900">
        <div class="mini-label">To</div>
        <input class="time" type="time" value="19:00" step="900">
        <button class="remove-slot" type="button" title="Remove">×</button>
        <div></div>
      `;
      block.appendChild(extra);
    }

    // حذف سطر إضافي
    if (e.target.closest('.remove-slot')) {
      e.target.closest('.row.extra')?.remove();
    }

    // نسخ إلى أيام الأسبوع (Mon–Fri) — يحافظ على نفس عدد السطور والمحاذاة
    if (e.target.closest('.copy-weekdays')) {
      const sourceBlock = e.target.closest('.day-block');
      const enabled = !sourceBlock.classList.contains('day-disabled');

      // جمع كل السطور (الرئيسي + الإضافية) من يوم المصدر
      const slots = Array.from(sourceBlock.querySelectorAll('.row')).map((r) => {
        const t = r.querySelectorAll('input.time');
        return { from: t[0].value, to: t[1].value };
        // ملاحظة: السطر الرئيسي والسطر الإضافي نفس البنية (عمودين time)
      });

      // انسخ إلى الأيام المطلوبة
      document.querySelectorAll('.day-block').forEach((block) => {
        const day = block.dataset.day;
        if (!weekdayNames.includes(day) || block === sourceBlock) return;

        // إعادة بناء السطر الرئيسي
        block.innerHTML = `
          <div class="row main">
            <div class="day-cell">
              <span>${day}</span>
              <label class="switch"><input type="checkbox" ${enabled ? 'checked' : ''}><span class="slider"></span></label>
            </div>
            <div class="mini-label">From</div>
            <input class="time from" type="time" value="${slots[0]?.from || '12:00'}" step="900">
            <div class="mini-label">To</div>
            <input class="time to" type="time" value="${slots[0]?.to || '19:00'}" step="900">
            <button type="button" class="btn add-slot">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="9"></circle><path d="M12 8v8M8 12h8" stroke-linecap="round"></path>
              </svg>
              Add another slot
            </button>
            <button type="button" class="btn btn-soft copy-weekdays">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="7" width="11" height="13" rx="2"></rect><rect x="4" y="4" width="11" height="13" rx="2"></rect>
              </svg>
              Copy to all weekdays
            </button>
          </div>
        `;

        if (!enabled) block.classList.add('day-disabled'); else block.classList.remove('day-disabled');

        // أضف السطور الإضافية بنفس الأعمدة (مع ghost-switch)
        slots.slice(1).forEach((s) => {
          const extra = document.createElement('div');
          extra.className = 'row extra';
          extra.innerHTML = `
            <div class="day-cell">
              <span>${day}</span>
              <span class="ghost-switch"></span>
            </div>
            <div class="mini-label">From</div>
            <input class="time" type="time" value="${s.from}" step="900">
            <div class="mini-label">To</div>
            <input class="time" type="time" value="${s.to}" step="900">
            <button class="remove-slot" type="button" title="Remove">×</button>
            <div></div>
          `;
          block.appendChild(extra);
        });
      });
    }
  });

  // حفظ (يجمع كل شيء كـ JSON)
  document.getElementById('saveAvailabilityBtn').addEventListener('click', () => {
    const payload = {};
    document.querySelectorAll('.day-block').forEach((block) => {
      const day = block.dataset.day;
      const enabled = !block.classList.contains('day-disabled');
      const rows = block.querySelectorAll('.row');
      payload[day] = { enabled, slots: [] };
      if (enabled) {
        rows.forEach((r) => {
          const t = r.querySelectorAll('input.time');
          payload[day].slots.push({ from: t[0].value, to: t[1].value });
        });
      }
    });
    document.getElementById('avOverlay').style.display = 'none';
    fetch('save availability.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
  });

  // إغلاق عند الضغط خارج/ Esc
  document.getElementById('avOverlay').addEventListener('click', (e) => {
    if (e.target.id === 'avOverlay') e.currentTarget.style.display = 'none';
  });
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.getElementById('avOverlay').style.display = 'none';
  });
})();
</script>


<script>
// تبنّي نفس عناصر DOM الموجودة عندك
function applyAvailabilityToUI(payload){
  const daysWrap = document.getElementById('daysContainer');
  const allBlocks = daysWrap.querySelectorAll('.day-block');

  allBlocks.forEach(block=>{
    const day = block.dataset.day;
    const info = payload[day] || {enabled:false, slots:[]};

    // ابنِ السطر الرئيسي
    block.innerHTML = `
      <div class="row main">
        <div class="day-cell">
          <span>${day}</span>
          <label class="switch"><input type="checkbox" ${info.enabled?'checked':''}><span class="slider"></span></label>
        </div>
        <div class="mini-label">From</div>
        <input class="time from" type="time" value="${(info.slots[0]?.from || '12:00')}" step="900">
        <div class="mini-label">To</div>
        <input class="time to" type="time" value="${(info.slots[0]?.to || '19:00')}" step="900">
        <button type="button" class="btn add-slot">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9"></circle><path d="M12 8v8M8 12h8" stroke-linecap="round"></path>
          </svg>
          Add another slot
        </button>
        <button type="button" class="btn btn-soft copy-weekdays">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="9" y="7" width="11" height="13" rx="2"></rect><rect x="4" y="4" width="11" height="13" rx="2"></rect>
          </svg>
          Copy to all weekdays
        </button>
      </div>
    `;

    // حالة التمكين
    block.classList.toggle('day-disabled', !info.enabled);

    // السطور الإضافية (بنفس المحاذاة – ghost-switch)
    info.slots.slice(1).forEach(s=>{
      const extra = document.createElement('div');
      extra.className = 'row extra';
      extra.innerHTML = `
        <div class="day-cell">
          <span>${day}</span>
          <span class="ghost-switch"></span>
        </div>
        <div class="mini-label">From</div>
        <input class="time" type="time" value="${s.from}" step="900">
        <div class="mini-label">To</div>
        <input class="time" type="time" value="${s.to}" step="900">
        <button class="remove-slot" type="button" title="Remove">×</button>
        <div></div>
      `;
      block.appendChild(extra);
    });
  });
}

// استدعاء الفتح مع التحميل
function openAvailabilityAndLoad(){
  // افتح الـPopup
  document.getElementById('avOverlay').style.display = 'flex';
  // حمّل آخر حفظة
  fetch('get availability.php')
    .then(r=>r.json())
    .then(res=>{
      if(res.ok && res.data){
        applyAvailabilityToUI(res.data);
      }
    })
    .catch(console.error);
}
</script>






























<!-- ===== Add New Service Popup ===== -->
<style>
  .svc-overlay {
    position: fixed; inset: 0;
    display: none;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,.55);
    z-index: 100001;
  }
  .svc-modal {
    width: 96%; max-width: 900px;
    background: #fff; border-radius: 14px;
    padding: 24px;
    box-shadow: 0 24px 64px rgba(0,0,0,.25);
  }
  .svc-modal h3 {
    margin: 0 0 16px; font: 800 20px/1 Nunito,system-ui; color: #0b0f1a;
  }
  .svc-body { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .svc-body .full { grid-column: 1 / -1; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .label { font-weight: 700; font-size: 14px; }
  .input, .select, .textarea {
    border: 1px solid #e8eef4; border-radius: 8px;
    padding: 10px; font-size: 14px; background: #fff;
  }
  .textarea { min-height: 100px; resize: vertical; }
.drop{
  border: 2px dashed #e2e8f0;
  border-radius: 10px;
  width: 40%;
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #6b7280;
  cursor: pointer;
  overflow: hidden;
  background: #fafbfc;
  padding: 0;

  margin: 0 auto;   /* 👈 يوسّط المربع أفقياً */
}

.drop img{
  width: 100%;
  height: 100%;
  object-fit: cover;        /* ✅ تُظهر الصورة كاملة بدون قصّ */
  display: block;
  border-radius: 10px;
}



/* القاعدة الأساسية */
input,
select,
textarea {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px 12px;
  transition: border-color .2s, box-shadow .2s;
}

/* عند الهوفر */
input:hover,
select:hover,
textarea:hover {
  border-color: #1e90ff;   /* أزرق */
}

/* عند التركيز */
input:focus,
select:focus,
textarea:focus {
  border-color: #1e90ff;   /* أزرق */
  box-shadow: 0 0 0 3px rgba(30,144,255,.2);
  outline: none;
}



  .svc-foot {
    display: flex; justify-content: center; gap: 16px;
    margin-top: 18px;
  }
  .btn {
    padding: 10px 18px; border-radius: 8px; cursor: pointer;
    font-weight: 700; border: 1px solid #e8eef4;
  }
  .btn-primary { background: #1e90ff; border-color: #1e90ff; color: #fff; }
.btn.btn-cancel {
  background: #fff;
  color: #ef4444;
  border-color: #ef4444;   /* يغيّر لون الحد */
}

.btn.btn-cancel:hover {
  background: #fee2e2;     /* لمسة هوفر اختيارية */
}
  @media (max-width: 768px){ .svc-body{grid-template-columns: 1fr;} }
</style>

<div class="svc-overlay" id="serviceOverlay">
  <div class="svc-modal">
    <h3>Add New Services</h3>
    <div class="svc-body">
      <div class="field">
        <label class="label">Choose Service</label>
        <select class="select" id="svcService">
          <option disabled selected>Choose Service</option>
          <option>Cleaning</option>
          <option>Plumbing</option>
          <option>Electrical</option>
        </select>
      </div>
      <div class="field">
        <label class="label">Sub-Section Services</label>
        <select class="select" id="svcSubService">
          <option>Choose Sub Service</option>
          <option>Home Cleaning</option>
          <option>Bedroom Cleaning</option>
          <option>Office Cleaning</option>
          <option>Holiday Home Cleaning</option>
          <option>Post-Construction Cleaning</option>
          <option>Leak repair</option>
          <option>Pipe Installation & Replacement</option>
          <option>Drain & Pipe Cleaning</option>
          <option>Faucet & Sink Installation/Repair</option>
          <option>Water Heater Services</option>
          <option>Emergency Plumbing</option>
          <option>Home Electrical Repair</option>
          <option>Switch & Outlet Installation/Repair</option>
          <option>Electrical Panel (Breaker) Maintenance</option>
          <option>Lighting Installation & Maintenance</option>
          <option>Generator Maintenance</option>
          <option>Voltage Fluctuation Solutions</option>
          <option>Home Plumbing </option>
          <option> Home Electrical </option>
        </select>
      </div>
        <div class="field full">
        <label class="label">Upload Image</label>
        <div class="drop" id="svcDrop" onclick="document.getElementById('svcFile').click()">
          <span id="svcHint">Click to Upload (PNG/JPG, max 800×400)</span>
          <img id="svcPreview" style="display:none; max-width:100%; max-height:150px;">
          <input type="file" id="svcFile" name="image" accept="image/png,image/jpeg,image/webp" hidden>
        </div>
      </div>
      <div class="field">
        <label class="label">Price Range (Min)</label>
        <input class="input" type="number" id="priceMin" placeholder="0.00">
      </div>
      <div class="field">
        <label class="label">Price Range (Max)</label>
        <input class="input" type="number" id="priceMax" placeholder="100.00">
      </div>
      <div class="field">
        <label class="label">Duration</label>
        <select class="select" id="svcDuration">
          <option disabled selected>Select Duration</option>
          <option>30 Min</option>
          <option> 45 Min</option>
          <option>60 Min</option>
          <option>90 Min</option>
          <option>120 Min</option>
         <option>180 Min</option>
        </select>
      </div>
      <div class="field">
        <label class="label">Your Experience</label>
        <input class="input" id="svcExp" placeholder="Experience">
      </div>
      <div class="field full">
        <label class="label">What Does The Service Include?</label>
        <textarea class="textarea" id="svcIncludes" placeholder="• Sweep, Mop&#10;• Dust and Wipe"></textarea>
      </div>
    </div>
    <div class="svc-foot">
   <button class="btn btn-primary" onclick="saveService()">Save Service</button>
<button type="button" class="btn btn-cancel" onclick="closeServicePopup()">Cancel</button>
   </div>
  </div>
</div>

<script>
(function(){
  const overlay   = document.getElementById('serviceOverlay');
  const waOverlay = document.getElementById('waOverlay'); // بوپ واتساب الموجود عندك

  // ✅ دالة عامة تفحص من السيرفر إذا مسموح إضافة خدمة
  async function checkCanAdd(){
    try{
      const r = await fetch('can-add-serviece.php', { credentials: 'same-origin' });
      return await r.json();
    }catch(e){
      console.error(e);
      return { ok:false, error:'network_error' };
    }
  }

  // ✅ اظهار بوپ واتساب + رسالة توضيحية اختيارياً
  function showPaymentPopup(opts={}){
    // لو بدك رسالة فوق زر الواتساب
    if (waOverlay) {
      waOverlay.style.display='flex';
    } else {
      alert(opts.message || 'Payment is required before adding more services.');
    }
  }

  // ✅ افتح نموذج الإضافة… لكن بعد الفحص
  window.openServicePopup = async function(){
    const res = await checkCanAdd();
    if (res.ok) {
      // مسموح (أول خدمة مجاناً أو دفع مؤكد)
      if (overlay){
        overlay.style.display='flex';
        loadProviderExperience();
      }
    } else if (res.state === 'pending') {
      // عنده طلب دفع معلّق أو لازم يدفع → افتح واتساب
      showPaymentPopup({ message: res.message, payment_id: res.payment_id });
    } else {
      alert(res.error || res.message || 'Not allowed');
    }
  };

  window.closeServicePopup = function(){
    if(overlay){
      overlay.style.display='none';
      resetForm();
    }
  };

  // ==== تحميل خبرة المزود (كما هي) ====
  function loadProviderExperience() {
    const el = document.getElementById('svcExp');
    if(!el) return;
    el.value = 'Loading...';
    setTimeout(()=>{ el.value = '0 years experience'; }, 500);
  }

  function resetForm(){
    const prev  = document.getElementById('svcPreview');
    const hint  = document.getElementById('svcHint');
    const fileI = document.getElementById('svcFile');
    if(prev) prev.style.display='none';
    if(hint) hint.style.display='block';
    if(fileI) fileI.value='';
  }

  // ==== رفع الصورة (كما هي) ====
  const fileInput = document.getElementById('svcFile');
  const drop      = document.getElementById('svcDrop');
  const previewEl = document.getElementById('svcPreview');
  const hintEl    = document.getElementById('svcHint');

  function showPreviewViaImgTag(file){
    const validTypes = ['image/png','image/jpeg','image/webp'];
    if(!validTypes.includes(file.type)){ alert('Please select a valid image (PNG, JPG, JPEG, WEBP)'); return; }
    if(file.size > 5*1024*1024){ alert('Image size should be less than 5MB'); return; }
    const reader = new FileReader();
    reader.onload = (evt)=>{ 
      if(previewEl) previewEl.src = evt.target.result;
      if(previewEl) previewEl.style.display='block';
      if(hintEl) hintEl.style.display='none';
    };
    reader.readAsDataURL(file);
  }

  fileInput?.addEventListener('change', e=>{
    const f = e.target.files?.[0];
    if (f) showPreviewViaImgTag(f);
  });

  if (drop){
    ['dragenter','dragover','dragleave','drop'].forEach(n=>drop.addEventListener(n, preventDefaults,false));
    function preventDefaults(e){ e.preventDefault(); e.stopPropagation(); }
    ['dragenter','dragover'].forEach(n=>drop.addEventListener(n, ()=>drop.classList.add('is-drag'), false));
    ['dragleave','drop'].forEach(n=>drop.addEventListener(n, ()=>drop.classList.remove('is-drag'), false));
    drop.addEventListener('drop', e=>{
      const f = e.dataTransfer.files?.[0];
      if(f && fileInput){ fileInput.files = e.dataTransfer.files; showPreviewViaImgTag(f); }
    });
  }

  // ==== حفظ الخدمة: تحقّق أولاً ثم أرسل ====
  window.saveService = async function(){
    // فحص من السيرفر قبل الإرسال (لو نافذة الإضافة مفتوحة بالغلط)
    const res = await checkCanAdd();
    if (!res.ok) {
      // مش مسموح → افتح واتساب ووقف الحفظ
      if (res.state === 'pending') showPaymentPopup({ message: res.message, payment_id: res.payment_id });
      else alert(res.error || res.message || 'Payment required before adding more services.');
      return;
    }

    const fd = new FormData();
    fd.append('service',     document.getElementById('svcService')?.value || '');
    fd.append('sub_service', document.getElementById('svcSubService')?.value || '');
    fd.append('price_min',   document.getElementById('priceMin')?.value || '');
    fd.append('price_max',   document.getElementById('priceMax')?.value || '');
    fd.append('duration',    document.getElementById('svcDuration')?.value || '');
    fd.append('includes',    document.getElementById('svcIncludes')?.value || '');

    const f = document.getElementById('svcFile')?.files?.[0];
    if (f) fd.append('image', f);

    try{
      const r = await fetch('save service.php', { method:'POST', body: fd });
      if(!r.ok) throw new Error('HTTP '+r.status);
      const data = await r.json();
      if (data.ok){
        alert('Service saved successfully! Service ID: ' + data.service_id);
        closeServicePopup();
        // TODO: أعيدي تحميل القائمة أو أضيفي الكارد الجديد مباشرة
        location.reload(); // أبسط حل الآن
      } else {
        alert('Save failed: ' + (data.msg || 'Unknown error'));
      }
    }catch(err){
      console.error(err);
      alert('Network error: ' + err.message);
    }
  };

  overlay?.addEventListener('click', e=>{ if(e.target.id==='serviceOverlay') closeServicePopup(); });
  window.addEventListener('keydown', e=>{ if(e.key==='Escape') closeServicePopup(); });

})();
</script>







<div class="wa-overlay" id="waOverlay">
  <div class="wa-modal">
    <div class="wa-head">
      <h3 class="wa-title">Confirm Your Payment via WhatsApp</h3>
      <button class="x" onclick="closePaymentPopup()">✕</button>
    </div>
    <div class="wa-body">
      <p class="lead">Please contact <span class="high">Finance Department</span> on WhatsApp to send your payment receipt and confirm your transaction ✨</p>
      <p class="pitch">Click the button below to open the chat instantly.</p>
    </div>
    <div class="wa-foot">
      <a class="btn btn-wa" href="https://wa.me/972592643752" target="_blank">
        Contact Finance on WhatsApp
      </a>
      <button class="btn btn-cancel" onclick="closePaymentPopup()">Cancel</button>
    </div>
  </div>
</div>

<style>
.wa-overlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:10000}
.wa-modal{width:95%;max-width:500px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.25);animation:pop .25s ease}
.wa-head{padding:16px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
.wa-title{margin:0;font:800 18px Nunito,sans-serif;color:#0aa0ff}
.wa-body{padding:20px}
.lead{font:700 16px/1.6 Inter,sans-serif;margin-bottom:8px;color:#0b0f1a}
.pitch{color:#555;line-height:1.6}
.high{color:#0aa0ff;font-weight:800}
.wa-foot{padding:16px;border-top:1px solid #eee;display:flex;gap:12px;justify-content:center}
.btn{padding:10px 16px;border-radius:8px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-block;min-width:180px;text-align:center}
.btn-wa{background:#25D366;color:#fff;border:none}
.btn-cancel{background:#fff;border:2px solid #ef4444;color:#ef4444}
.x{background:none;border:none;font-size:18px;cursor:pointer;color:#888}
@keyframes pop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
</style>

<script>
  function openPaymentPopup(){
    document.getElementById('waOverlay').style.display='flex';
  }
  function closePaymentPopup(){
    document.getElementById('waOverlay').style.display='none';
  }
</script>













<script>
/* ===== Availability toggle ===== */
document.addEventListener('DOMContentLoaded', () => {
    const checkbox = document.getElementById('availToggle');
    const switchEl = document.getElementById('switch');
    const bookingsTable = document.querySelector('.table'); // استخدام الكلاس الموجود
    
    if (checkbox && switchEl && bookingsTable) {
        const syncSwitch = () => {
            const on = checkbox.checked;
            switchEl.classList.toggle('on', on);
            switchEl.setAttribute('aria-checked', on ? 'true' : 'false');
            
            // إظهار أو إخفاء جدول الحجوزات
            if (on) {
                bookingsTable.style.display = 'block';
            } else {
                bookingsTable.style.display = 'none';
            }
        };
        
        checkbox.addEventListener('change', syncSwitch);
        syncSwitch();
    }
});

/* ===== Helpers/Dropdowns ===== */
const qs  = (s, r=document) => r.querySelector(s);
const qsa = (s, r=document) => [...r.querySelectorAll(s)];

function closeAllMenus(except=null){
  qsa('.menu.open').forEach(m => { if (m !== except) m.classList.remove('open'); });
  qsa('.filter-btn[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded','false'));
  qsa('.filter-btn').forEach(b => b.classList.remove('active'));
}
function bindDropdown(btnId, menuId){
  const btn = qs('#'+btnId), menu = qs('#'+menuId);
  if(!btn || !menu) return;
  btn.addEventListener('click', e=>{
    e.stopPropagation();
    const willOpen = !menu.classList.contains('open');
    closeAllMenus(menu);
    menu.classList.toggle('open', willOpen);
    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen) btn.classList.add('active');
  });
}
bindDropdown('btnDate','menuDate');
bindDropdown('btnStatus','menuStatus');
bindDropdown('btnServices','menuServices');
document.addEventListener('click', ()=> closeAllMenus());
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeAllMenus(); });

/* ===== Calendar (عرض) ===== */
(function(){
  const title = qs('#calTitle'), grid = qs('#calGrid');
  const todayStr = qs('#todayStr'), prev = qs('#calPrev'), next = qs('#calNext');
  if(!title || !grid) return;

  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  let view = new Date(); view.setDate(1);
  const today = new Date();

  function ymd(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }

function render(){
    title.textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
    todayStr.textContent = (today.getMonth()+1)+'/'+today.getDate()+'/'+today.getFullYear();

    grid.innerHTML = '';
    const year = view.getFullYear(), month = view.getMonth();
    const start = new Date(year, month, 1 - new Date(year, month, 1).getDay());
    for(let i=0;i<42;i++){
      const d = new Date(start); d.setDate(start.getDate()+i);
      const cell = document.createElement('div');
      cell.className = 'cal-day';
      if (d.getMonth() !== month) cell.classList.add('muted');
      if (d.toDateString() === today.toDateString()) cell.classList.add('today');
      cell.textContent = d.getDate();
      cell.dataset.date = ymd(d);
      cell.addEventListener('click', ()=>{ qsa('.cal-day', grid).forEach(el=>el.classList.remove('selected')); cell.classList.add('selected'); closeAllMenus(); });
      grid.appendChild(cell);
    }
  }
  prev?.addEventListener('click', ()=>{ view.setMonth(view.getMonth()-1); render(); });
  next?.addEventListener('click', ()=>{ view.setMonth(view.getMonth()+1); render(); });
  render();
})();

/* ===== فلترة الجدول (Status + Service) ===== */
(function(){
  const statusMenu = qs('#menuStatus');
  const serviceMenu = qs('#menuServices');
  const rows = qsa('.table .trow');

  let currentStatus = 'all';
  let currentService = 'all';

  function applyFilters(){
    rows.forEach(r=>{
      const st = (r.dataset.status || '');
      const sv = (r.dataset.service || '');
      const okStatus = (currentStatus === 'all' || st === currentStatus);
      const okService = (currentService === 'all' || sv === currentService);
      r.style.display = (okStatus && okService) ? 'grid' : 'none';
    });
  }

  if(statusMenu){
    qsa('li', statusMenu).forEach(li=>{
      li.addEventListener('click', ()=>{
        qsa('li', statusMenu).forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        currentStatus = li.dataset.value;
        applyFilters();
        closeAllMenus();
      });
    });
  }
  if(serviceMenu){
    qsa('li', serviceMenu).forEach(li=>{
      li.addEventListener('click', ()=>{
        qsa('li', serviceMenu).forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        currentService = li.dataset.service;
        applyFilters();
        closeAllMenus();
      });
    });
  }
  applyFilters();
})();


/* ===== سايدبار + قائمة الحساب ===== */
const openSidebar     = document.getElementById('openSidebar');
const closeSidebar    = document.getElementById('closeSidebar');
const sidebar         = document.getElementById('sidebar');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');
function openNav(){ document.body.classList.add('sidebar-open'); sidebar?.classList.add('open'); if (window.matchMedia('(max-width: 899px)').matches){ sidebarBackdrop?.classList.add('show'); } }
function closeNav(){ document.body.classList.remove('sidebar-open'); sidebar?.classList.remove('open'); sidebarBackdrop?.classList.remove('show'); }
openSidebar?.addEventListener('click', openNav);
closeSidebar?.addEventListener('click', closeNav);
sidebarBackdrop?.addEventListener('click', closeNav);

(function(){
  const pm = document.querySelector('.profile-menu');
  if(!pm) return;
  const btn = pm.querySelector('.profile-trigger');
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

function openAvailabilityPopup(){ alert('Availability popup…'); }
</script>




<script>
function updateBooking(bookingId, newStatus) {
    if (!confirm('تأكيد تحديث الحجز؟')) return;
    
    // أبسط طريقة
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'Update booking status.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        console.log('Response:', xhr.responseText);
        
        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                alert('✅ تم التحديث!');
                setTimeout(() => location.reload(), 500);
            } else {
                alert('❌ ' + data.message);
            }
        } catch (e) {
            alert('⚠️ خطأ في الرد: ' + xhr.responseText);
        }
    };
    
    xhr.send('id=' + bookingId + '&status=' + newStatus);
}

</script>
</body>
</html>
<?php $conn->close(); ?>






  
<style>

 .tb-inner{
      max-width:1200px; margin:0 auto;
      padding:18px 24px;
      display:grid; grid-template-columns:auto 1fr auto;
      align-items:center; gap:18px;
    }
    .tb-left{ display:flex; align-items:center; gap:50px; }
    .icon-btn{
      width:40px; height:40px; display:grid; place-items:center;
      border:none; background:transparent; box-shadow:none; cursor:pointer;
    }
    .icon-btn i{ font-size:18px; color:#6b7280; }
    .brand{ display:flex; align-items:center; gap:10px; }
    .brand-logo{ width:150px; height:auto; object-fit:contain; }

    .tb-center{ display:flex; justify-content:center; }
    .search-wrap{ position:relative; width:min(680px,90%); margin-left:90px; }
    .search-wrap input{
      width:500px; height:48px; padding:0 16px 0 44px;
      border:1px solid #cfd7e3; border-radius:12px; font-size:16px; background:#fff; outline:none;
    }
    .search-wrap input::placeholder{ color:#9aa4b2; }
    .search-wrap i{
      position:absolute; left:14px; top:50%; transform:translateY(-50%);
      color:#8a94a6; font-size:18px;
    }

    .tb-right{ display:flex; align-items:center; gap:35px; }
    .notif-pill{
      width:42px; height:42px; display:grid; place-items:center;
      border:1px solid #dfe6ef; background:#fff; border-radius:50%; cursor:pointer;
    }
    .notif-pill i{ font-size:18px; color:#1e73ff; }
    .profile-trigger{
      display:flex; align-items:center; gap:10px;
      background:#fff; border:1px solid #e5e7eb;
      padding:6px 12px; border-radius:40px; cursor:pointer;
      box-shadow:0 2px 8px rgba(0,0,0,.06);
    }
    .avatar{ width:48px; height:48px; object-fit:cover; border-radius:50%; display:block }
    .chev{ color:#64748b; transition:transform .18s ease }

  


      .profile-menu{ position:relative }
.profile-trigger{
  display:flex; align-items:center; gap:10px;
  background:#fff; border:1px solid var(--border);
  padding:6px 12px; border-radius:40px; cursor:pointer;
  box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.avatar{ width:48px; height:48px; object-fit:cover; border-radius:50%; display:block }
.chev{ color:#64748b; transition:transform .18s ease }
.profile-menu.open .chev{ transform:rotate(180deg) }
.menu-card{
  position:absolute; right:0; top:calc(100% + 10px); z-index:9999; width:280px;
  background:#fff; border:1px solid var(--border);
  border-radius:18px; box-shadow:0 12px 30px rgba(0,0,0,.12);
  padding:12px; overflow:auto; max-height:80vh;
}
.menu-item{
  width:100%; display:flex; align-items:center; gap:12px;
  padding:12px; border-radius:14px; color:#0f172a; text-decoration:none;
  font-weight:600; background:#fff; border:0; cursor:pointer;
}
.menu-item:hover{ background:#f8fafc }
.menu-item.danger {
  color:#dc2626;
  display:flex;
  align-items:center;
  gap:160px;              /* ✨ مسافة صغيرة بين النص والأيقونة */
  /* justify-content:flex-start;  */
}
.divider{ border:0; height:1px; background:var(--border); margin:4px 0 }



 :root{
  --bg1:#f3f8ff; 
  --bg2:#eef6ff;
  --text:#0b0f1a; 
  --muted:#99a3b2;
  --card:#ffffff; 
  --border:#edf0f4;
  --radius-xl:26px; 
  --radius-md:10px;
  --shadow:0 20px 46px rgba(22,60,120,.12);
  --primary:#137BEA; 
  --blue:#1e90ff;
  --green:#12b886; 
  --green-100:#e6fff7; 
  --green-200:#baf2de;
  --amber:#f59f00; 
  --amber-100:#fff3cd; 
  --amber-200:#ffe19a;
  --red:#ef4444;   
  --red-100:#ffe3e3;  
  --red-200:#f7b6b6;
}

*{ 
    box-sizing:border-box; 
}
html,body{
  margin:0;
  font-family:'Nunito',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
}

.wrap{ 
    max-width:1060px; 
    margin:26px auto 40px; 
    padding:0 22px; 
}

.toprow{ 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    margin-bottom:16px; 
}
.welcome{ 
    margin:0; 
    font-size:22px; 
    font-weight:800; 
}
.right-actions{ 
    display:flex; 
    align-items:center; 
    gap:18px; 
}

.availability{ 
    display:flex; 
    align-items:center; 
    gap:10px; 
    cursor:pointer; 
    user-select:none; 
}
.availability .ask{ 
    color:#7a84a0; 
    font-weight:700; 
    font-size:14px; 
}
.availability input{ 
    display:none; 
}

.switch{
  width:46px; 
  height:24px; 
  border-radius:999px; 
  background:#d5dbe6;
  position:relative; 
  box-shadow:inset 0 2px 6px rgba(0,0,0,.06);
  transition:background .15s ease;
}
.switch .knob{
  position:absolute; 
  top:2px; 
  left:2px; 
  width:20px; 
  height:20px; 
  border-radius:50%;
  background:#fff; 
  box-shadow:0 2px 6px rgba(0,0,0,.15); 
  transition:left .15s ease;
}
.switch.on{ 
    background:var(--blue); 
}
.switch.on .knob{ 
    left:24px; 
}

.btn-primary{
  display:inline-flex; 
  align-items:center; 
  justify-content:center;
  height:44px; 
  padding:0 18px; 
  background:var(--blue);
  color:#fff; 
  text-decoration:none; 
  font-weight:800; 
  font-size:14px;
  border-radius:10px; 
  box-shadow:0 10px 24px rgba(30,144,255,.25);
}

.card.kpi{ 
    background:var(--card); 
    border-radius:28px; 
    box-shadow:var(--shadow); 
    padding:22px 26px;
}
.kpi-grid{ 
    display:flex; 
    align-items:center; 
    justify-content:space-between; 
    gap:26px; 
}
.kpi-item{ 
    display:flex; 
    align-items:center; 
    gap:18px; 
    min-width:220px; 
}
.kpi-icon{
  width:62px; 
  height:62px; 
  border-radius:50%; 
  display:grid; 
  place-items:center;
  background:radial-gradient(120% 120% at 30% 20%, #bfe0ff 0%, #86c5ff 45%, #5ab1ff 100%);
  box-shadow:inset 0 0 0 6px rgba(255,255,255,.42);
}
.kpi-icon svg{ 
    width:30px; 
    height:30px; 
}
.kpi-label{ 
    color:#9aa3b2; 
    font-size:13px; 
    font-weight:800; 
    margin-bottom:4px; 
}
.kpi-value{ 
    font-size:28px; 
    font-weight:800; 
    color:#1d2736; 
    line-height:1.1; 
}
.kpi-value.money{ 
    letter-spacing:.5px; 
}
.kpi-sub{ 
    margin-top:6px; 
    font-size:12px; 
    color:#8c95a6; 
    font-weight:700; 
}

.ql-title{ 
    margin:0 0 10px; 
    font-size:22px; 
    font-weight:800; 
}
.ql-card{ 
    background:var(--card); 
    border-radius:var(--radius-xl); 
    box-shadow:var(--shadow); 
    padding:18px; 
}

.ql-head{ 
    display:flex; 
    align-items:center; 
    justify-content:space-between; 
    padding:10px 8px 16px; 
}
.ql-heading{ 
    margin:0; 
    font-size:22px; 
    font-weight:800; 
}

.filters{ 
    display:flex; 
    gap:12px; 
}
.filter-btn{
  display:inline-flex; 
  align-items:center; 
  gap:10px; 
  height:36px; 
  padding:0 14px;
  border-radius:10px; 
  border:1px solid #d8dde6; 
  background:#f3f5f7; 
  color:#1f2a44;
  font-weight:800; 
  font-size:14px; 
  cursor:pointer;
}
.filter-btn svg{ 
    width:16px; 
    height:16px; 
}
.filter-btn.active{ 
    outline:2px solid #b9d9ff; 
}

زهرة اللوتس, [24/09/2025 06:42 ص]
.dropdown{ 
    position:relative; 
}
.menu{
  position:absolute; 
  top:calc(100% + 8px); 
  right:0; 
  background:#fff;
  border:1px solid #e6ecf5; 
  border-radius:12px; 
  box-shadow:0 16px 36px rgba(20,40,80,.14);
  z-index:30; 
  display:none; 
  padding:12px;
}
.menu.open{ 
    display:block; 
}

.list-menu ul{ 
  list-style:none; 
  margin:0; 
  padding:4px; 
  min-width:200px; 
}
.list-menu li{ 
  padding:8px 10px; 
  border-radius:8px; 
  font-weight:800; 
  color:#1f2a44; 
  cursor:pointer; 
}
.list-menu li:hover{ 
  background:#f1f6ff; 
}
.list-menu li.active{ 
  background:#e7f1ff; 
  outline:1px solid #b7d0ff; 
}

.date-menu{ 
  width:320px; 
}
.cal-head{ 
  display:flex; 
  align-items:center; 
  justify-content:space-between; 
  gap:10px; 
  margin-bottom:8px; 
}
.cal-title{ 
  font-weight:800; 
  font-size:16px; 
}
.cal-nav{
  width:30px; 
  height:28px; 
  border-radius:8px; 
  border:1px solid #d8dde6;
  background:#f7fbff; 
  font-weight:900; 
  cursor:pointer;
}
.cal-week{
  display:grid; 
  grid-template-columns:repeat(7,1fr); 
  gap:4px; 
  color:#9aa3b2;
  font-size:12px; 
  font-weight:800; 
  text-align:center; 
  margin-bottom:4px;
}
.cal-week span{ 
  padding:6px 0; 
}
.cal-grid{ 
  display:grid; 
  grid-template-columns:repeat(7,1fr); 
  grid-auto-rows:38px; 
  gap:4px; 
  user-select:none; 
}
.cal-day{ 
  display:flex; 
  align-items:center; 
  justify-content:center; 
  border-radius:8px; 
  font-weight:800; 
  cursor:pointer; 
  color:#0b0f1a; 
}
.cal-day:hover{ 
  background:#f1f6ff; 
}
.cal-day.muted{ 
  color:#b6bfcc; 
}
.cal-day.today{ 
  outline:2px solid var(--blue); 
  outline-offset:-2px; 
}
.cal-day.selected{ 
  background:var(--blue); 
  color:#fff; 
}
.cal-foot{ 
  margin-top:8px; 
  padding-top:8px; 
  border-top:1px solid #e6ecf5; 
  font-size:13px; 
  color:#1f2a44; 
}

.table{ 
  width:100%; 
  border-top:1px solid var(--border); 
}
.thead, .trow{
  display:grid; 
  align-items:center; 
  gap:12px;
  grid-template-columns: 2fr 1.3fr 1.4fr 1.5fr 1.1fr 1.6fr;
  padding:14px 8px;
}
.thead{ 
  color:#a7b0bf; 
  font-size:13px; 
  font-weight:800; 
}
.trow{ 
  border-top:1px solid var(--border); 
  font-size:14px; 
}
.link{ 
  color:#1e6ef7; 
  text-decoration:none; 
  font-weight:800; 
}

.badge{
  display:inline-flex; 
  align-items:center; 
  justify-content:center; 
  height:28px; 
  padding:0 12px;
  border-radius:8px; 
  font-size:13px; 
  font-weight:800; 
  border:1.5px solid transparent;
}
.badge.green{ 
  background:var(--green-100); 
  color:#0f8e6b; 
  border-color:var(--green-200); 
}
.badge.amber{ 
  background:var(--amber-100); 
  color:#8a5a00; 
  border-color:var(--amber-200); 
}
.badge.red{ 
  background:var(--red-100); 
  color:#b4231a; 
  border-color:var(--red-200); 
}

.actions{ 
  display:flex; 
  gap:10px; 
}
.btn{
  display:inline-flex; 
  align-items:center; 
  justify-content:center; 
  height:32px;
  padding:0 14px; 
  border-radius:8px; 
  font-weight:800; 
  text-decoration:none; 
  border:1.5px solid transparent;
}
.btn.blue{ 
  background:var(--blue); 
  color:#fff; 
  font-size:13px; 
  padding:0 12px; 
  white-space:nowrap; 
}
.btn.outline.red{ 
  background:#fff; 
  color:#d12a20; 
  border-color:#ffc8c8; 
}
.btn.solid.red{ 
  background:#ff5a59; 
  color:#fff; 
}

/*Quick Actions Panel*/
.qa-wrap{ 
  max-width:1060px; 
  margin:14px auto 24px; 
  padding:0 22px; 
}
.qa-title{ 
  margin:0 0 12px;
  font:800 20px/1.1 "Nunito",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; 
  color:var(--text); 
}

.qa-grid{ 
  display:flex; 
  align-items:center; 
  justify-content:space-between; 
  gap:28px; 
}
.qa-btn{
  display:flex; 
  align-items:center; 
  justify-content:space-between; 
  width:260px; 
  height:40px;
  padding:0 16px 0 18px; 
  border-radius:8px;
  background:var(--blue); 
  color:#fff; 
  text-decoration:none;
  font-weight:800; 
  font-size:14px; 
  box-shadow:var(--shadow); 
  white-space:nowrap; 
  transition:transform .06s ease, box-shadow .2s ease;
}
.qa-btn:active{ 
  transform:translateY(1px); 
  box-shadow:0 6px 16px rgba(30,144,255,.25); 
}
.qa-ico{ 
  width:20px; 
  height:20px; 
  stroke:#fff; 
  fill:none; 
  stroke-width:2; 
}
.qa-ico.box rect{ 
  stroke:#fff; 
  fill:none; 
}

@media (max-width:920px){
  .kpi-grid{ 
    flex-wrap:wrap; 
  }
  .kpi-item{ 
    min-width:46%; 
  }
}
@media (max-width:820px){
  .thead, .trow{ 
    grid-template-columns: 1.6fr 1.2fr 1.4fr 1.4fr 1fr 1.6fr; 
  }
}
@media (max-width:720px){
  .qa-grid{ 
    flex-wrap:wrap; 
    gap:14px; 
  }
  .qa-btn{ 
    width:100%; 
    justify-content:center; 
    gap:10px; 
  }
}
@media (max-width:640px){
  .right-actions{ 
    gap:12px; 
  }
  .btn-primary{ 
    height:40px; 
    padding:0 14px; 
  }
  .kpi-item{ 
    min-width:100%; 
  }
  .filters{ 
    display:none; 
  }
  .thead{ 
    display:none; 
  }
  .trow{ 
    grid-template-columns:1fr; 
    gap:6px; 
    padding:14px 6px 16px; 
  }
  .trow > div{ 
    display:flex; 
    justify-content:space-between; 
  }
  .trow > div:nth-child(1){ 
    font-weight:800; 
  }
  .actions{ 
    justify-content:flex-end; 
  }
}

</style>













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

document.addEventListener('DOMContentLoaded', () => {
  /* ===== Availability switch ===== */
  const checkbox = document.getElementById('availToggle');
  const switchEl = document.getElementById('switch');

  if (checkbox && switchEl) {
    const syncSwitch = () => {
      const on = checkbox.checked;
      switchEl.classList.toggle('on', on);
      switchEl.setAttribute('aria-checked', on ? 'true' : 'false');
    };
    checkbox.addEventListener('change', syncSwitch);
    syncSwitch();
  }

  /* ===== Dropdowns ===== */
  const qs  = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => [...r.querySelectorAll(s)];

  function closeAllMenus(except=null){
    qsa('.menu.open').forEach(m => { if (m !== except) m.classList.remove('open'); });
    qsa('.filter-btn[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded','false'));
    qsa('.filter-btn').forEach(b => b.classList.remove('active'));
  }
  function bindDropdown(btnId, menuId){
    const btn = qs('#'+btnId), menu = qs('#'+menuId);
    if(!btn || !menu) return;
    btn.addEventListener('click', e=>{
      e.stopPropagation();
      const willOpen = !menu.classList.contains('open');
      closeAllMenus(menu);
      menu.classList.toggle('open', willOpen);
      btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if (willOpen) btn.classList.add('active');
    });
  }
  bindDropdown('btnDate','menuDate');
  bindDropdown('btnStatus','menuStatus');
  bindDropdown('btnServices','menuServices');

  document.addEventListener('click', ()=> closeAllMenus());
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeAllMenus(); });

  /* ===== Calendar ===== */
  (function(){
    const title = qs('#calTitle'), grid = qs('#calGrid');
    const todayStr = qs('#todayStr'), prev = qs('#calPrev'), next = qs('#calNext');
    if(!title || !grid) return;

    const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    let view = new Date(); view.setDate(1);
    const today = new Date();

    const ymd = d => ${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')};

    function render(){
      title.textContent = ${MONTHS[view.getMonth()]} ${view.getFullYear()};
      todayStr.textContent = ${today.getMonth()+1}/${today.getDate()}/${today.getFullYear()};

      grid.innerHTML = '';
      const year = view.getFullYear(), month = view.getMonth();
      const start = new Date(year, month, 1 - new Date(year, month, 1).getDay());


for(let i=0;i<42;i++){
        const d = new Date(start); d.setDate(start.getDate()+i);
        const cell = document.createElement('div');
        cell.className = 'cal-day';
        if (d.getMonth() !== month) cell.classList.add('muted');
        if (d.toDateString() === today.toDateString()) cell.classList.add('today');
        cell.textContent = d.getDate();
        cell.dataset.date = ymd(d);
        cell.addEventListener('click', ()=>{
          qsa('.cal-day', grid).forEach(el=>el.classList.remove('selected'));
          cell.classList.add('selected');
          closeAllMenus();
        });
        grid.appendChild(cell);
      }
    }
    prev?.addEventListener('click', ()=>{ view.setMonth(view.getMonth()-1); render(); });
    next?.addEventListener('click', ()=>{ view.setMonth(view.getMonth()+1); render(); });
    render();
  })();

  /* ===== Status filter (real filtering) ===== */
  (function(){
    const list = qs('#menuStatus');
    if(!list) return;
    const rows = qsa('.table .trow');

    function apply(f){
      rows.forEach(r=>{
        const st = r.dataset.status || '';
        r.style.display = (f==='all' || st===f) ? 'grid' : 'none';
      });
    }
    qsa('li', list).forEach(li=>{
      li.addEventListener('click', ()=>{
        qsa('li', list).forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        apply(li.dataset.value);
        closeAllMenus();
      });
    });
    apply('all');
  })();

  /* ===== Services (hook جاهز) ===== */
  (function(){
    const list = qs('#menuServices');
    if(!list) return;
    qsa('li', list).forEach(li=>{
      li.addEventListener('click', ()=>{
        qsa('li', list).forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        // للتفعيل لاحقًا: فلترة حسب data-service بنفس أسلوب Status
        closeAllMenus();
      });
    });
  })();
});

</script>









<script>
// دالة تحميل الإشعارات
function loadNotifications() {
    console.log('🚀 بدء تحميل الإشعارات...');
    
    fetch('get provider natifications.php')
        .then(response => {
            console.log('📡 حالة الرد:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📊 البيانات المستلمة:', data);
            
            if (data.success) {
                const counter = document.getElementById('notifCount');
                console.log('🔍 العنصر notifCount:', counter);
                
                if (counter) {
                    if (data.unread_count > 0) {
                        counter.textContent = data.unread_count;
                        counter.style.display = 'flex';
                        console.log('✅ تم عرض الرقم:', data.unread_count);
                    } else {
                        counter.style.display = 'none';
                        console.log('❌ لا توجد إشعارات جديدة');
                    }
                } else {
                    console.log('❌ العنصر notifCount غير موجود');
                }
            } else {
                console.log('❌ فشل في جلب الإشعارات');
            }
        })
        .catch(error => {
            console.error('❌ خطأ في الاتصال:', error);
        });
}

// دالة تحديد الإشعارات كمقروءة
function markNotificationsAsRead() {
    console.log('📝 تحديد الإشعارات كمقروءة...');
    
    fetch('mark provider natifications read.php', { 
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('✅ نتيجة التحديث:', data);
    })
    .catch(error => {
        console.error('❌ خطأ في التحديث:', error);
    });
}

// أحداث الصفحة
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 الصفحة محملة - جاري تهيئة الإشعارات...');
    
    // تحميل الإشعارات أول مرة
    loadNotifications();
    
    // تحديث كل 30 ثانية
    setInterval(loadNotifications, 30000);
    
    // حدث الضغط على زر الجرس
    const notifButton = document.getElementById('notifButton');
    if (notifButton) {
        notifButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱 تم الضغط على زر الجرس');
            
            const counter = document.getElementById('notifCount');
            if (counter) {
                counter.style.display = 'none';
                console.log('🔴 تم إخفاء العداد');
            }
            
            markNotificationsAsRead();
        });
    } else {
        console.log('❌ زر notifButton غير موجود');
    }
    
    // إغلاق الإشعارات عند النقر خارجها
    document.addEventListener('click', function(e) {
        const notifButton = document.getElementById('notifButton');
        if (notifButton && !notifButton.contains(e.target)) {
            console.log('👆 تم النقر خارج الزر');
        }
    });
});

// دالة مساعدة للتجربة
function testNotifications() {
    console.log('🧪 بدء الاختبار...');
    loadNotifications();
}


</script>




<script>
(function(){
  const toggle = document.getElementById('availToggle');
  const sw     = document.getElementById('switch');
  const API    = 'provider-toggle-avaibility.php'; // نفس المسار بجذر نفس الصفحة

  function syncVisual(){
    if (!toggle) return;
    sw?.classList.toggle('on', !!toggle.checked);
    sw?.setAttribute('aria-checked', toggle.checked ? 'true' : 'false');
  }
  syncVisual();

  if (!toggle) return;

  // لو المزوّد موقّف من الإدارة، لا نحاول إرسال أي شيء
  if (toggle.disabled) {
    return;
  }

  let busy = false;
  toggle.addEventListener('change', async (e)=>{
    if (busy) { e.preventDefault(); return; }
    busy = true;
    sw?.classList.add('disabled');

    try {
      const form = new FormData();
      form.append('status', toggle.checked ? '1' : '0');

      const res  = await fetch(API, {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });

      const data = await res.json().catch(()=> ({}));

      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Request failed');
      }

      // نجّحنا — ثبّت الشكل
      syncVisual();

    } catch(err){
      // رجّع الحالة القديمة
      toggle.checked = !toggle.checked;
      syncVisual();
      alert('Could not update availability: ' + err.message);
    } finally {
      busy = false;
      sw?.classList.remove('disabled');
    }
  });
})();
</script>





<script>
document.addEventListener('DOMContentLoaded', function () {
  const pm   = document.querySelector('.profile-menu');
  if (!pm) return;

  const btn  = pm.querySelector('.profile-trigger');
  const card = pm.querySelector('.menu-card');

  function openMenu(open) {
    pm.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    card.hidden = !open;
  }

  // افتح/سكر عند الضغط على الزر
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    openMenu(card.hidden);
  });

  // ما نخلي كليك داخل القائمة يقفلها
  card.addEventListener('click', function (e) { e.stopPropagation(); });

  // اقفل عند الكليك خارجها أو زر ESC
  document.addEventListener('click', function () { openMenu(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') openMenu(false);
  });
});
</script>