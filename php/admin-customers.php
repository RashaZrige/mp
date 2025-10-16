
<?php
/* admin_customers.php — Admin › Customers (with last order status) */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cus_code($id){ return 'CUS'.str_pad((int)$id, 6, '0', STR_PAD_LEFT); }



/* Admin name (from users table) */
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

/* ================== Filters ================== */
$q        = trim($_GET['q'] ?? '');
$statusF  = trim($_GET['status'] ?? '');     // احتياطي لو أضفت فلتر حالة لاحقًا
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');

/* IMPORTANT: استثناء المحذوفين soft delete */
$whereU  = "WHERE u.role='customer' AND u.is_deleted=0";
$types   = "";
$params  = [];

if ($q !== '') {
  $like = "%{$q}%";
  $whereU .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR CAST(u.id AS CHAR)=?)";
  $types  .= "ssss";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $q;
}

/* تواريخ إنشاء الحساب (اختياري) */
if ($dateFrom !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateFrom)) {
  $whereU .= " AND DATE(u.created_at) >= ?";
  $types  .= "s"; $params[] = $dateFrom;
}
if ($dateTo !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateTo)) {
  $whereU .= " AND DATE(u.created_at) <= ?";
  $types  .= "s"; $params[] = $dateTo;
}

/* ================== KPIs (مع is_deleted=0) ================== */
function oneInt($conn,$sql){ $r=$conn->query($sql); if(!$r) return 0; $row=$r->fetch_row(); return (int)($row[0]??0); }
$totalCustomers = oneInt($conn, "SELECT COUNT(*) FROM users u WHERE u.role='customer' AND u.is_deleted=0");
$activeCustomers= oneInt($conn, "SELECT COUNT(*) FROM users u WHERE u.role='customer' AND u.status='active' AND u.is_deleted=0");
$newThisMonth   = oneInt($conn, "
  SELECT COUNT(*) FROM users u
  WHERE u.role='customer' AND u.is_deleted=0
    AND u.created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
");

/* ================== Export CSV ================== */
if (isset($_GET['export']) && $_GET['export']=='1') {
  $sql = "
   SELECT 
     u.id, u.full_name, u.phone, u.email, u.created_at,
     (SELECT b.status FROM bookings b WHERE b.customer_id = u.id ORDER BY b.scheduled_at DESC, b.id DESC LIMIT 1) AS last_status,
     (SELECT b.scheduled_at FROM bookings b WHERE b.customer_id = u.id ORDER BY b.scheduled_at DESC, b.id DESC LIMIT 1) AS last_sched
   FROM users u
   {$whereU}
   ORDER BY u.id DESC";
  $st = $conn->prepare($sql);
  if (!$st) { die('Prepare failed (export): '.$conn->error); }
  if ($types!=="") $st->bind_param($types, ...$params);
  $st->execute(); 
  $rs = $st->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=customers.csv');
  $out = fopen('php://output','w');
  fputcsv($out, ['Customer Code','Name','Phone','Email','Created At','Last Order Status']);

  $now = new DateTime();
  while($r=$rs->fetch_assoc()){
    $label = $r['last_status'] ?? '';
    if ($r['last_sched'] && in_array($label,['pending','confirmed'],true)) {
      $dt = new DateTime($r['last_sched']);
      if ($dt > $now) $label = 'upcoming';
    }
    fputcsv($out, [cus_code($r['id']), $r['full_name'], $r['phone'], $r['email'], $r['created_at'], $label ?: '—']);
  }
  fclose($out);
  exit;
}

/* ================== Pagination ================== */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

/* ================== List ================== */
$sql = "
  SELECT 
    u.id, u.full_name, u.phone, u.email, u.created_at,
    (SELECT b.status FROM bookings b WHERE b.customer_id = u.id ORDER BY b.scheduled_at DESC, b.id DESC LIMIT 1) AS last_status,

(SELECT b.scheduled_at FROM bookings b WHERE b.customer_id = u.id ORDER BY b.scheduled_at DESC, b.id DESC LIMIT 1) AS last_sched
  FROM users u
  {$whereU}
  ORDER BY u.id DESC
  LIMIT {$perPage} OFFSET {$offset}
";
$st = $conn->prepare($sql);
if (!$st) { die("Prepare failed (list): ".$conn->error); }
if ($types!=="") $st->bind_param($types, ...$params);
$st->execute(); 
$rs = $st->get_result();

$rows = [];
$now = new DateTime();
while($r = $rs->fetch_assoc()){
  $label = $r['last_status'] ?? '';
  if ($r['last_sched'] && in_array($label,['pending','confirmed'],true)) {
    $dt = new DateTime($r['last_sched']);
    if ($dt > $now) $label = 'upcoming';
  }
  $r['last_label'] = $label ?: '—';
  $rows[] = $r;
}
$st->close();

/* ===== Count for pagination (نفس شرط whereU) ===== */
$sqlCount = "SELECT COUNT(*) FROM users u {$whereU}";
$stc = $conn->prepare($sqlCount);
if ($types!=="") $stc->bind_param($types, ...$params);
$stc->execute();
$rc = $stc->get_result();
$totalFiltered = (int)($rc ? ($rc->fetch_row()[0] ?? 0) : 0);
$stc->close();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));









?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Customers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css">
<style>
.page{max-width:1100px;margin:0 auto;padding:18px 24px 60px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:12px 0 18px}
.kpi{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
.kpi .k{font-size:13px;color:#6b7280;margin-bottom:6px}
.kpi .v{font:700 20px/1 "Inter",system-ui}

.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px}

/* فلترة: السيرش يسار والأزرار يمين */
.filterbar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px}
.search-pill{display:flex;align-items:center;gap:8px;height:40px;padding:0 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;flex:0 0 auto;min-width:240px;max-width:360px}
.search-pill input{border:0;outline:0;width:100%;font-size:14px}
.filter-actions{display:flex;align-items:center;gap:10px;margin-left:auto}

.tile-btn{height:40px;display:inline-flex;align-items:center;gap:8px;padding:0 14px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;font-weight:600;cursor:pointer;text-decoration:none}
.tile-btn .ico{width:18px;text-align:center}
.tile-btn.primary{background:#2b79ff;color:#fff;border-color:transparent}
.tile-btn.outline{background:#eef2ff;color:#1f2937;border-color:#e0e7ff}

/* table */
.table{width:100%;border-collapse:collapse;table-layout:fixed}
.table thead th{background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:10px;text-align:left}
.table tbody td{border-bottom:1px solid #f3f4f6;padding:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-block}
.b-pending{background:#fffbeb;color:#a16207;border:1px solid #fde68a}
.b-confirmed{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.b-in_progress{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
.b-completed{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.b-cancelled{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.b-upcoming{background:#e0f2fe;color:#075985;border:1px solid #bae6fd}

.actions a{text-decoration:none}
.actions .icon-btn{width:34px;height:34px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;display:inline-flex;align-items:center;justify-content:center;margin-right:6px}
.actions .icon-btn.danger{border-color:#fecaca;color:#dc2626}
.actions .icon-btn.danger i{color:#dc2626}


.pagination{display:flex;gap:6px;align-items:center;justify-content:flex-end;margin-top:12px}
.pagination .meta{margin-right:auto;color:#6b7280;font-size:13px}
.page-btn{min-width:34px;height:34px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 10px}
.page-btn.active{background:#2b79ff;color:#fff;border-color:transparent}





    
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
  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi"><div class="k">Total customer</div><div class="v"><?= $totalCustomers ?></div></div>
    <div class="kpi"><div class="k">Active</div><div class="v"><?= $activeCustomers ?></div></div>
    <div class="kpi"><div class="k">New customer this month</div><div class="v"><?= $newThisMonth ?></div></div>
  </div>

  <div class="card">
    <form method="get" class="filterbar" id="filterForm">
      <!-- البحث يسار -->
      <label class="search-pill" for="q2">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="q2" type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name, id, phone, email">
      </label>

      <!-- الأزرار يمين -->
      <div class="filter-actions">
        <button class="tile-btn" type="submit">
          <span class="ico"><i class="fa-solid fa-filter"></i></span><span>Filter</span>
        </button>

        <!-- نحافظ على نفس الفلاتر بالرابط -->
        <a class="tile-btn outline" href="?<?= h(http_build_query(array_merge($_GET,['export'=>1]))) ?>">
          <span class="ico"><i class="fa-solid fa-download"></i></span><span>Export csv</span>
        </a>

        <a class="tile-btn" style="gap:10px" href="admin-customer-add.php">
          <span class="ico"><i class="fa-solid fa-user-plus"></i></span><span>Add customer</span>
        </a>
      </div>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:140px">id customer</th>
            <th>name</th>
            <th style="width:150px">phone number</th>
            <th>email</th>
            <th style="width:150px">date</th>
            <th style="width:120px">Status</th>
            <th style="width:160px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7">No customers found</td></tr>
          <?php else: foreach($rows as $r): 
            $badge = 'badge b-'.str_replace(' ','_',$r['last_label']);
          ?>
            <tr>
              <td><?= h(cus_code($r['id'])) ?></td>
              <td><?= h($r['full_name']) ?></td>
              <td><?= h($r['phone']) ?></td>
              <td><?= h($r['email']) ?></td>
              <td><?= h(substr($r['created_at'],0,10)) ?></td>
              <td><span class="<?= $badge ?>"><?= h(ucfirst($r['last_label'])) ?></span></td>


<td class="actions">
                <a class="icon-btn" href="admin-customer-view.php?id=<?= (int)$r['id'] ?>" title="View"><i class="fa-regular fa-eye"></i></a>
                <a class="icon-btn" href="admin-customer-add.php?id=<?= (int)$r['id'] ?>" title="Edit"><i class="fa-regular fa-pen-to-square"></i></a>
                <a class="icon-btn danger" href="admin-customer-suspend.php?id=<?= (int)$r['id'] ?>" title="Suspend" onclick="return confirm('Suspend (soft delete) this customer?');"><i class="fa-regular fa-trash-can"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <div class="meta">
        <?php
          $fromN = $totalFiltered ? ($offset+1) : 0;
          $toN   = min($offset+$perPage, $totalFiltered);
          echo "Showing {$fromN} to {$toN} Of {$totalFiltered} Result";
        ?>
      </div>
      <?php
      $qsBase = $_GET; unset($qsBase['page']);
      if ($page>1){
        $qsBase['page']=1;  echo '<a class="page-btn" href="?'.http_build_query($qsBase).'">&laquo;</a>';
        $qsBase['page']=$page-1; echo '<a class="page-btn" href="?'.http_build_query($qsBase).'">&lsaquo;</a>';
      }
      $start = max(1, $page-2); $end = min($totalPages, $page+2);
      if ($start>1) echo '<span class="page-btn">…</span>';
      for($i=$start;$i<=$end;$i++){
        $qsBase['page']=$i; $active = ($i===$page)?'active':'';
        echo '<a class="page-btn '.$active.'" href="?'.http_build_query($qsBase).'">'.$i.'</a>';
      }
      if ($end<$totalPages) echo '<span class="page-btn">…</span>';
      if ($page<$totalPages){
        $qsBase['page']=$page+1; echo '<a class="page-btn" href="?'.http_build_query($qsBase).'">&rsaquo;</a>';
        $qsBase['page']=$totalPages; echo '<a class="page-btn" href="?'.http_build_query($qsBase).'">&raquo;</a>';
      }
      ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Last Reviews -->
  <h3 style="margin:16px 0 10px;font-weight:700">last Reviews</h3>
  <div class="card">
    <?php
    $reviews = [];
    $revSQL = "
      SELECT 
        COALESCE(c.full_name,'Customer') AS customer_name,
        s.title AS service_title,
        sr.rating, sr.comment, sr.created_at
      FROM service_reviews sr
      LEFT JOIN users c ON c.id = sr.customer_id
      LEFT JOIN services s ON s.id = sr.service_id
      ORDER BY sr.created_at DESC
      LIMIT 5";
    if ($rev = $conn->query($revSQL)) { while($r=$rev->fetch_assoc()) $reviews[]=$r; }

    if (!$reviews): ?>
      <div class="rev-card" style="border-top:0">No reviews yet</div>
    <?php else: foreach($reviews as $rv): ?>
      <div class="rev-card" style="display:flex;gap:12px;align-items:flex-start;padding:12px;border-top:1px solid #f1f5f9">
        <div class="rev-avatar" style="width:42px;height:42px;border-radius:50%;background:#f3f4f6;flex:0 0 auto"></div>
        <div style="flex:1">
          <div class="rev-head" style="display:flex;align-items:center;gap:10px">
            <span class="rev-title" style="font-weight:700"><?= h($rv['customer_name']) ?></span>
            <span class="rev-sub" style="color:#6b7280;font-size:13px"><?= h($rv['service_title'] ?? '—') ?></span>
            <span class="rev-stars" style="margin-left:auto;color:#f59e0b">
              <?php $stars = (int)($rv['rating'] ?? 0); for($i=0;$i<5;$i++) echo $i<$stars?'★':'☆'; ?>
              <span style="margin-left:6px;color:#6b7280;font-size:13px"><?= number_format((float)($rv['rating'] ?? 0),1) ?></span>
            </span>
          </div>
          <div style="margin-top:6px;color:#111827"><?= h($rv['comment'] ?? '') ?></div>
          <div class="rev-sub" style="margin-top:4px;color:#6b7280;font-size:13px">Reviewed on : <?= h(substr($rv['created_at'],0,10)) ?></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>





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