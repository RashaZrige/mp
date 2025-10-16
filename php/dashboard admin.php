<?php
/* Admin Dashboard */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function q1i($conn,$sql){ $r=$conn->query($sql); if(!$r) return 0; $row=$r->fetch_row(); return (int)($row[0]??0); }
function qrows($conn,$sql){ $r=$conn->query($sql); $out=[]; if($r){ while($x=$r->fetch_assoc()) $out[]=$x; } return $out; }


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





/* ===== KPIs (تستثني المحذوفين) ===== */
$total_customers  = q1i($conn, "SELECT COUNT(*) FROM users WHERE role='customer' AND is_deleted=0");
$total_providers  = q1i($conn, "SELECT COUNT(*) FROM users WHERE role='provider' AND is_deleted=0");
$active_providers = q1i($conn, "SELECT COUNT(*) FROM users WHERE role='provider' AND status='active' AND is_deleted=0");

$completed_orders = q1i($conn, "SELECT COUNT(*) FROM bookings WHERE is_deleted=0 AND status IN ('completed','done','finished')");
$completed_jobs   = $completed_orders;

/* ===== Latest Activity ===== */
$recent_providers = qrows($conn, "
  SELECT id, full_name, email, phone, created_at, status
  FROM users
  WHERE role='provider' AND is_deleted=0
  ORDER BY created_at DESC
  LIMIT 6
");

$recent_customers = qrows($conn, "
  SELECT id, full_name, email, phone, created_at
  FROM users
  WHERE role='customer' AND is_deleted=0
  ORDER BY created_at DESC
  LIMIT 6
");

$recent_orders = qrows($conn, "
  SELECT b.id, b.created_at, b.status,
         COALESCE(c.full_name,'—') AS customer_name,
         COALESCE(s.title,'—')     AS service_title
  FROM bookings b
  LEFT JOIN users c ON c.id=b.customer_id
  LEFT JOIN services s ON s.id=b.service_id
  WHERE b.is_deleted=0
  ORDER BY b.created_at DESC
  LIMIT 6
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/rating_dashbord.css">
  <style>
    .container{max-width:1000px;margin:0 auto;padding:24px 16px}
    .card{border:1px solid #e7edf5;background:#fff;border-radius:14px;padding:14px}

    .kpi-wrapper{border:1px solid #e7edf5;background:#fff;border-radius:16px;padding:22px;margin-bottom:16px}
    .kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;text-align:center}
    @media (max-width:1100px){.kpis{grid-template-columns:repeat(3,1fr)}}
    @media (max-width:700px){.kpis{grid-template-columns:repeat(2,1fr)}}
    .kpi .label{font-size:13px;color:#6b7280;margin-bottom:6px}
    .kpi .value{font-size:22px;font-weight:800;color:#111827}

    .tabs{display:flex;gap:16px;margin:0 0 12px;border-bottom:1px solid #e7edf5}
    .tab{padding:10px 0;cursor:pointer;color:#475569;font-weight:600}
    .tab.active{color:#2f7af8;border-bottom:2px solid #2f7af8}

    .table{width:100%;border-collapse:separate;border-spacing:0}
    .table th,.table td{padding:10px 12px;border-bottom:1px solid #eef2f7;text-align:left}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
    .badge.ok{background:#d1fae5;color:#065f46}
    .badge.warn{background:#fee2e2;color:#991b1b}
    .muted{color:#6b7280}

    /* Topbar الخفيف */
    section.topbar{background:#fff}
    section.topbar .tb-inner{max-width:1200px;margin:0 auto;padding:18px 24px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px}
    section.topbar .tb-left{display:flex;align-items:center;gap:50px}
    .brand-logo{width:150px;height:auto;object-fit:contain}
    section.topbar .tb-center{display:flex;justify-content:center}
    .search-wrap{position:relative;width:min(680px,90%);margin-left:90px}
    .search-wrap input{width:500px;height:48px;padding:0 16px 0 44px;border:1px solid #cfd7e3;border-radius:12px;font-size:16px;background:#fff;outline:none}
    .search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a94a6;font-size:18px}
    .notif-pill{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dfe6ef;background:#fff;border-radius:50%;cursor:pointer}
    .notif-pill i{font-size:18px;color:#1e73ff}



    
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

<div class="container">

  <!-- KPIs -->
  <div class="card kpi-wrapper">
    <div class="kpis">
      <div class="kpi"><div class="label">Total Customers</div><div class="value"><?= $total_customers ?></div></div>
      <div class="kpi"><div class="label">Total Providers</div><div class="value"><?= $total_providers ?></div></div>
      <div class="kpi"><div class="label">Active Providers</div><div class="value"><?= $active_providers ?></div></div>
      <div class="kpi"><div class="label">Completed Orders</div><div class="value"><?= $completed_orders ?></div></div>
      <div class="kpi"><div class="label">Completed Jobs</div><div class="value"><?= $completed_jobs ?></div></div>
    </div>
  </div>

  <!-- Latest Activity -->
  <div class="card">
    <h3 style="margin:0 0 10px">Latest Activity</h3>
    <div class="tabs" role="tablist">
      <div class="tab active" data-tab="providers">Recent Providers</div>
      <div class="tab" data-tab="customers">Recent Customers</div>
      <div class="tab" data-tab="orders">Recent Orders</div>
    </div>

    <!-- Providers -->
    <div class="tab-panel" id="panel-providers">
      <table class="table">
        <thead>
          <tr>
            <th style="width:28%">Name</th>
            <th style="width:26%">Email</th>
            <th style="width:18%">Phone</th>
            <th style="width:18%">Registration Date</th>
            <th style="width:10%">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_providers): foreach($recent_providers as $p): ?>
            <tr>
              <td><?= h($p['full_name'] ?: '—') ?></td>
              <td><?= h($p['email'] ?: '—') ?></td>
              <td><?= h($p['phone'] ?: '—') ?></td>
              <td><?= h(substr((string)$p['created_at'],0,10)) ?></td>
              <td>
                <?php $ok = (strtolower((string)$p['status']) === 'active'); ?>
                <span class="badge <?= $ok ? 'ok' : 'warn' ?>"><?= $ok ? 'Active' : 'Suspended' ?></span>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5" class="muted">No data.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Customers -->
    <div class="tab-panel" id="panel-customers" hidden>
      <table class="table">
        <thead>
          <tr>
            <th style="width:28%">Name</th>
            <th style="width:26%">Email</th>
            <th style="width:18%">Phone</th>
            <th style="width:18%">Registration Date</th>
            <th style="width:10%"></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_customers): foreach($recent_customers as $c): ?>
            <tr>
              <td><?= h($c['full_name'] ?: '—') ?></td>
              <td><?= h($c['email'] ?: '—') ?></td>
              <td><?= h($c['phone'] ?: '—') ?></td>
              <td><?= h(substr((string)$c['created_at'],0,10)) ?></td>
              <td></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5" class="muted">No data.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Orders -->
    <div class="tab-panel" id="panel-orders" hidden>
      <table class="table">
        <thead>
          <tr>
            <th style="width:12%">Order #</th>
            <th style="width:26%">Customer</th>
            <th style="width:32%">Service</th>
            <th style="width:18%">Created</th>
            <th style="width:12%">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_orders): foreach($recent_orders as $o): ?>
            <tr>
              <td>#<?= (int)$o['id'] ?></td>
              <td><?= h($o['customer_name']) ?></td>
              <td><?= h($o['service_title']) ?></td>
              <td><?= h(substr((string)$o['created_at'],0,16)) ?></td>
              <td>
                <?php $done = in_array(strtolower((string)$o['status']), ['completed','done','finished'], true); ?>
                <span class="badge <?= $done ? 'ok' : 'warn' ?>"><?= h($o['status'] ?: '—') ?></span>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5" class="muted">No data.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  // Tabs
  const tabs = document.querySelectorAll('.tab');
  const panels = {
    providers: document.getElementById('panel-providers'),
    customers: document.getElementById('panel-customers'),
    orders: document.getElementById('panel-orders'),
  };
  tabs.forEach(t=>{
    t.addEventListener('click', ()=>{
      tabs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      const key = t.dataset.tab;
      Object.keys(panels).forEach(k => panels[k].hidden = (k !== key));
    });
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