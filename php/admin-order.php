<?php
/* admin-order.php — Admin Orders (list + filters + stats) */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ================= Stats (top cards) ================= */
function fetchOneInt($conn, $sql){
  $r = $conn->query($sql);
  if (!$r) return 0;
  $row = $r->fetch_row();
  return (int)($row[0] ?? 0);
}

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


$totalOrders   = fetchOneInt($conn, "SELECT COUNT(*) FROM bookings WHERE is_deleted=0");
$activeOrders  = fetchOneInt($conn, "SELECT COUNT(*) FROM bookings WHERE is_deleted=0 AND status IN ('pending','confirmed','in_progress')");
$upcoming      = fetchOneInt($conn, "SELECT COUNT(*) FROM bookings WHERE is_deleted=0 AND DATE(scheduled_at) > CURDATE() AND status IN ('pending','confirmed')");
$pendingOrders = fetchOneInt($conn, "SELECT COUNT(*) FROM bookings WHERE is_deleted=0 AND status='pending'");
$cancelled     = fetchOneInt($conn, "SELECT COUNT(*) FROM bookings WHERE is_deleted=0 AND status='cancelled'");

/* ================= Filters ================= */
$q          = trim($_GET['q'] ?? '');
$statusF    = trim($_GET['status'] ?? ''); // pending | confirmed | in_progress | completed | cancelled
$dateFrom   = trim($_GET['from'] ?? '');   // YYYY-MM-DD
$dateTo     = trim($_GET['to'] ?? '');     // YYYY-MM-DD

// IMPORTANT: exclude soft-deleted rows
$where  = "WHERE b.is_deleted = 0";
$types  = "";
$params = [];

if ($q !== '') {
  $like = "%{$q}%";
  $where .= " AND (c.full_name LIKE ? OR p.full_name LIKE ? OR s.title LIKE ? OR b.phone LIKE ? OR CAST(b.id AS CHAR) = ?)";
  $types .= "sssss";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $q;
}

$validStatuses = ['pending','confirmed','in_progress','completed','cancelled'];
if ($statusF !== '' && in_array($statusF, $validStatuses, true)) {
  $where .= " AND b.status = ?";
  $types .= "s";
  $params[] = $statusF;
}

// تاريخ بصيغة صحيحة
if ($dateFrom !== '' && preg_match('^\d{4}-\d{2}-\d{2}$', $dateFrom)) {
  $where .= " AND DATE(b.scheduled_at) >= ?";
  $types .= "s";
  $params[] = $dateFrom;
}
if ($dateTo !== '' && preg_match('^\d{4}-\d{2}-\d{2}$', $dateTo)) {
  $where .= " AND DATE(b.scheduled_at) <= ?";
  $types .= "s";
  $params[] = $dateTo;
}

/* ================= Pagination ================= */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

/* get total with same filters (for pagination) */
$sqlCount = "
  SELECT COUNT(*) 
  FROM bookings b
  LEFT JOIN users    c ON c.id = b.customer_id
  LEFT JOIN users    p ON p.id = b.provider_id
  LEFT JOIN services s ON s.id = b.service_id
  {$where}
";
$stc = $conn->prepare($sqlCount);
if (!$stc) { die("SQL prepare error (count): ".$conn->error."\n\nSQL:\n".$sqlCount); }
if ($types !== "") { $stc->bind_param($types, ...$params); }
if (!$stc->execute()) { die("SQL execute error (count): ".$stc->error); }
$rc  = $stc->get_result();
$totalFiltered = (int)($rc ? ($rc->fetch_row()[0] ?? 0) : 0);
$stc->close();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));

/* ================= Main list ================= */
$sqlList = "
SELECT
  b.id,
  b.scheduled_at,
  b.status,
  c.full_name  AS customer_name,
  p.full_name  AS provider_name,
  s.title      AS service_title
FROM bookings b
LEFT JOIN users    c ON c.id = b.customer_id
LEFT JOIN users    p ON p.id = b.provider_id
LEFT JOIN services s ON s.id = b.service_id
{$where}
ORDER BY b.id DESC
LIMIT {$perPage} OFFSET {$offset}
";

$st = $conn->prepare($sqlList);
if (!$st) {
  die("SQL prepare error (list): ".$conn->error."\n\nSQL:\n".$sqlList);
}
if ($types !== "") {
  $st->bind_param($types, ...$params); // بارامترات WHERE فقط
}
if (!$st->execute()) { die("SQL execute error (list): ".$st->error); }
$rs   = $st->get_result();
$rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
$st->close();
/* close later if تريد */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Orders</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/rating_dashbord.css">

  <style>
    .orders-wrap{max-width:1100px;margin:0 auto;padding:18px 24px 60px}
    .kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin:12px 0 18px}
    .kpi{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi .k{font-size:13px;color:#6b7280;margin-bottom:6px}
    .kpi .v{font:700 20px/1 "Inter",system-ui}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px}

    /* filter bar — كلهم بنفس المستوى */
    .filterbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
    .input,.select{height:40px;border:1px solid #e5e7eb;border-radius:10px;padding:0 12px;background:#fff}
    .btn{height:40px;padding:0 14px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-weight:600;cursor:pointer}
    .btn-primary{background:#2b79ff;color:#fff;border-color:transparent}

    .search-pill{
      display:flex;align-items:center;gap:8px;height:40px;padding:0 12px;
      border:1px solid #e5e7eb;border-radius:10px;background:#fff;min-width:260px;flex:1
    }
    .search-pill input{border:0;outline:0;width:100%;font-size:14px}

    /* date-range dropdown */
    .dr{position:relative;min-width:180px;}
    .dr-toggle{
      height:40px;display:flex;align-items:center;justify-content:space-between;
      gap:10px;width:180px;padding:0 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;cursor:pointer;
    }
    .dr-menu{
      position:absolute;top:100%;left:0;z-index:30;width:280px;
      margin-top:6px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.06);
      padding:12px;display:none;
    }
    .dr.open .dr-menu{display:block;}
    .dr-field{display:flex;flex-direction:column;gap:6px;margin-bottom:10px;}
    .dr-field span{font-size:12px;color:#6b7280;}
    .dr-field input{height:38px;border:1px solid #e5e7eb;border-radius:10px;padding:0 10px;}

    /* table */
    .table{width:100%;border-collapse:collapse;table-layout:fixed}
    .table thead th{background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:10px;text-align:left}
    .table tbody td{border-bottom:1px solid #f3f4f6;padding:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    /* actions icons */
    .actions-cell a{text-decoration:none;}
    .actions-cell .icon-btn{
      width:34px;height:34px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;display:inline-flex;align-items:center;justify-content:center;margin-right:6px;
    }
    .actions-cell .icon-btn i{pointer-events:none;}
    .actions-cell .icon-btn.danger{border-color:#fecaca;color:#dc2626;}
    .actions-cell .icon-btn.danger i{color:#dc2626;}

    /* badges */
    .badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent;display:inline-block}
    .b-pending{background:#fffbeb;color:#a16207;border-color:#fde68a}
    .b-confirmed{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .b-in_progress{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
    .b-completed{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
    .b-cancelled{background:#fef2f2;color:#991b1b;border-color:#fecaca}

    /* pagination */
    .pagination{display:flex;gap:6px;align-items:center;justify-content:flex-end;margin-top:12px}
    .pagination .meta{margin-right:auto;color:#6b7280;font-size:13px}
    .page-btn{min-width:34px;height:34px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 10px}
    .page-btn.active{background:#2b79ff;color:#fff;border-color:transparent}

    @media (max-width:900px){.kpis{grid-template-columns:repeat(2,1fr)}}







        
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


<div class="orders-wrap">

  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi"><div class="k">Total order</div><div class="v"><?= $totalOrders ?></div></div>
    <div class="kpi"><div class="k">Active order</div><div class="v"><?= $activeOrders ?></div></div>
    <div class="kpi"><div class="k">Up coming order</div><div class="v"><?= $upcoming ?></div></div>
    <div class="kpi"><div class="k">Pending order</div><div class="v"><?= $pendingOrders ?></div></div>
    <div class="kpi"><div class="k">Cancelled</div><div class="v"><?= $cancelled ?></div></div>
  </div>

  <!-- Table + Filters -->
  <div class="card">
    <form method="get" class="filterbar" id="filterForm">
      <label class="search-pill" for="q">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="q" type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name, service, phone, order id">
      </label>

      <select class="select" name="status" aria-label="Order status">
        <option value="">order status</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>

      <div class="dr" id="dateRange">
        <button type="button" class="dr-toggle" aria-expanded="false">
          <span>Date range</span><i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="dr-menu" role="menu">
          <label class="dr-field">
            <span>From</span>
            <input type="date" name="from" value="<?= h($dateFrom) ?>">
          </label>
          <label class="dr-field" style="margin-bottom:0">
            <span>To</span>
            <input type="date" name="to" value="<?= h($dateTo) ?>">
          </label>
        </div>
      </div>

      <button class="btn btn-primary" type="submit">Filter</button>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:120px">order id</th>
            <th>customer</th>
            <th>provider</th>
            <th>services</th>
            <th style="width:130px">date</th>
            <th style="width:130px">Status</th>
            <th style="width:170px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7">No orders found</td></tr>
          <?php else: foreach($rows as $r):
            $st = $r['status'];
            $cls = 'b-'.$st;
          ?>
            <tr>
              <td>#<?= (int)$r['id'] ?></td>
              <td><?= h($r['customer_name'] ?? '-') ?></td>
              <td><?= h($r['provider_name'] ?? '-') ?></td>
              <td><?= h($r['service_title'] ?? '-') ?></td>
              <td><?= h(substr($r['scheduled_at'],0,10)) ?></td>
              <td><span class="badge <?= $cls ?>"><?= h($st) ?></span></td>
              <td class="actions-cell">
                <a class="icon-btn" href="order-view.php?id=<?= (int)$r['id'] ?>" title="View"><i class="fa-regular fa-eye"></i></a>
                <a class="icon-btn" href="order-edit.php?id=<?= (int)$r['id'] ?>" title="Edit"><i class="fa-regular fa-pen-to-square"></i></a>
                <a class="icon-btn danger" href="order-delete.php?id=<?= (int)$r['id'] ?>" title="Delete" onclick="return confirm('Delete this order?');"><i class="fa-regular fa-trash-can"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
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
  </div>
</div>

<script>
// Date range dropdown toggle
const dr = document.getElementById('dateRange');
if (dr) {
  const btn = dr.querySelector('.dr-toggle');
  btn.addEventListener('click', () => {
    dr.classList.toggle('open');
    btn.setAttribute('aria-expanded', dr.classList.contains('open') ? 'true' : 'false');
  });
  document.addEventListener('click', (e)=>{
    if(!dr.contains(e.target)) {
      dr.classList.remove('open');
      btn.setAttribute('aria-expanded','false');
    }
  });
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