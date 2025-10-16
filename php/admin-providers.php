<?php
/* Fixora – Admin › Providers list */
session_start();
$BASE = '/mp';

// if (empty($_SESSION['user_id']) || ($_SESSION['role']??'')!=='admin') { http_response_code(403); exit('Forbidden'); }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* Helpers */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function img_url($p,$base='/mp'){
  if(!$p) return $base.'/image/no-avatar.png';
  if(preg_match('~^https?://~i',$p)) return $p;
  return rtrim($base,'/').'/'.ltrim(str_replace('\\','/',$p),'/');
}
function qs(array $add=[]){ $q=$_GET; foreach($add as $k=>$v){ if($v===null) unset($q[$k]); else $q[$k]=$v; } return http_build_query($q); }

/* Filters */
$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';       // '', 'active', 'suspended', 'not_available'
$minrate = isset($_GET['rating']) && $_GET['rating']!=='' ? (float)$_GET['rating'] : null;

/* KPI cards */
$kpi = [
  'total'     => (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='provider' AND is_deleted=0")->fetch_row()[0],
  'active'    => (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='provider' AND status='active' AND is_deleted=0")->fetch_row()[0],
  'suspended' => (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='provider' AND status='suspended' AND is_deleted=0")->fetch_row()[0],
  'newmonth'  => 0,
];
$monthStart = date('Y-m-01 00:00:00');
$nextMonth  = date('Y-m-01 00:00:00', strtotime('+1 month'));
$st = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='provider' AND is_deleted=0 AND created_at>=? AND created_at<?");
$st->bind_param("ss",$monthStart,$nextMonth); $st->execute();
$kpi['newmonth'] = (int)$st->get_result()->fetch_row()[0]; $st->close();

/* Build query */
$wheres = ["u.role='provider'","u.is_deleted=0"];
$params = []; $types='';

/* حالات الفلترة الأساسية */
if ($status==='suspended') {
  $wheres[] = "u.status='suspended'";
} elseif ($status==='active' || $status==='not_available') {
  // الاثنان يتطلبان users.status='active' ثم نفرّق لاحقًا على is_available في HAVING
  $wheres[] = "u.status='active'";
}

if ($q!=='') {
  $wheres[] = "(u.full_name LIKE CONCAT('%',?,'%')
            OR u.email LIKE CONCAT('%',?,'%')
            OR u.phone LIKE CONCAT('%',?,'%')
            OR pp.national_id LIKE CONCAT('%',?,'%')
            OR s.title LIKE CONCAT('%',?,'%'))";
  array_push($params,$q,$q,$q,$q,$q); $types.='sssss';
}

$sql =
"SELECT
    u.id,
    MAX(u.full_name)     AS full_name,
    MAX(u.email)         AS email,
    MAX(u.phone)         AS phone,
    MAX(u.created_at)    AS created_at,
    MAX(u.status)        AS ustatus,
    MAX(pp.avatar_path)  AS avatar_path,
    MAX(pp.national_id)  AS national_id,
    COALESCE(MAX(pp.is_available),1) AS is_available,
    MIN(s.category)      AS category,
    ROUND(AVG(r.rating),1) AS avg_rating
 FROM users u
 LEFT JOIN provider_profiles pp ON pp.user_id=u.id
 LEFT JOIN services s           ON s.provider_id=u.id AND s.is_deleted=0
 LEFT JOIN service_reviews r    ON r.provider_id=u.id
 WHERE ".implode(" AND ",$wheres)."
 GROUP BY u.id
 HAVING 1=1";

/* HAVING وفق الحالة المختارة */
if ($status==='active') {
  $sql .= " AND is_available=1";
} elseif ($status==='not_available') {
  $sql .= " AND is_available=0";
}
if ($minrate !== null) {
  $sql .= " AND (avg_rating IS NOT NULL AND avg_rating >= ?)";
  $params[] = $minrate; $types.='d';
}

$sql .= " ORDER BY MAX(u.created_at) DESC LIMIT 200";

$rows = [];
$st = $conn->prepare($sql);
if ($types!=='') { $st->bind_param($types, ...$params); }
$st->execute();
$res = $st->get_result();
while($row = $res->fetch_assoc()){
  $state = 'active';
  if (strtolower((string)$row['ustatus'])==='suspended') {
    $state = 'suspended';
  } else {
    $state = ((int)$row['is_available']===1 ? 'active' : 'not_available');
  }
  $rows[] = [
    'id'         => (int)$row['id'],
    'name'       => $row['full_name'] ?: '—',
    'email'      => $row['email'] ?: '—',
    'phone'      => $row['phone'] ?: '—',
    'created_at' => substr((string)$row['created_at'],0,10),
    'national_id'=> $row['national_id'] ?: '—',
    'category'   => $row['category'] ?: '—',
    'rating'     => ($row['avg_rating']!==null && $row['avg_rating']!=='') ? (float)$row['avg_rating'] : 0.0,
    'avatar'     => img_url($row['avatar_path'],$BASE),
    'state'      => $state,
  ];
}
$st->close();

/* Export CSV */
if (isset($_GET['export']) && (int)$_GET['export']===1) {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="providers.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['ID','Name','Email','Phone','National ID','Service Category','Registration Date','Rating','Status']);
  foreach($rows as $r){
    $stxt = $r['state']==='active'?'Active':($r['state']==='suspended'?'Suspended':'Not available');
    fputcsv($out,[$r['id'],$r['name'],$r['email'],$r['phone'],$r['national_id'],$r['category'],$r['created_at'],number_format($r['rating'],1),$stxt]);
  }
  fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Providers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<style>
  :root{
    --card:#fff; --border:#e7edf5; --muted:#6b7280; --text:#0f172a;
    --brand:#2f7af8; --ok:#0f8e6b; --okbg:#e6fff7; --okb:#baf2de;
    --warn:#991b1b; --warnbg:#fee2e2; --warnb:#fecaca;
    --orange:#d46b08; --orangebg:#fff4e6; --orangeb:#ffd591;
  }
  *{box-sizing:border-box}
  body{margin:0;background:#fff;color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}

  /* Topbar */
  section.topbar{background:#fff}
  .tb-inner{max-width:1200px;margin:0 auto;padding:18px 24px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px}
  .tb-left{display:flex;align-items:center;gap:50px}
  .brand-logo{width:150px;height:auto;object-fit:contain}
  .tb-center{display:flex;justify-content:center}
  .search-wrap{position:relative;width:min(680px,90%)}
  .search-wrap input{width:100%;height:48px;padding:0 16px 0 44px;border:1px solid #cfd7e3;border-radius:12px;font-size:16px;background:#fff;outline:none}
  .search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a94a6;font-size:18px}
  .tb-right{display:flex;align-items:center;gap:35px}
  .notif-pill{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dfe6ef;background:#fff;border-radius:50%;cursor:pointer}
  .notif-pill i{font-size:18px;color:#1e73ff}

  /* KPI row */
  .hero{max-width:1200px;margin:14px auto 0;padding:0 20px}
  .kpi-boxes{display:grid;grid-template-columns:repeat(4, 1fr);gap:18px}
  .kbox{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 18px}
  .kbox .k{font-size:12px;color:#6b7280;margin-bottom:8px}
  .kbox .v{font:800 24px/1 "Inter",system-ui}

  /* Unified card (tools + table) */
  .wrap{max-width:1200px;margin:20px auto 26px;padding:0 20px} /* ← مسافة إضافية بين الكروت والجدول */
  .card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:12px}

  .tools{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:6px 6px 12px}
  .hero-search{width:100%;height:44px;border:1px solid #cfd7e3;border-radius:12px;padding:0 14px 0 40px;font-size:15px;background:#fff;outline:none}
  .tools .left{flex:1;display:flex;gap:10px;align-items:center}
  .tools .left .box{position:relative;max-width:540px;width:100%}
  .tools .left .box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#8a94a6}

  .btn{display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 14px;border-radius:10px;border:1px solid #cfd7e3;background:#f3f5f7;color:#1f2a44;text-decoration:none;font-weight:700}
  .btn.primary{background:#2f7af8;color:#fff;border-color:#2f7af8}
  .btn.ghost{background:#fff}
  .rbtns{display:flex;gap:10px}

  .dropdown{position:relative}
  .dropdown .menu{position:absolute;right:0;top:calc(100% + 8px);background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 16px 36px rgba(20,40,80,.14);padding:12px;display:none;z-index:50;width:260px}
  .dropdown.open .menu{display:block}
  .menu label{display:block;font-size:12px;color:#6b7280;margin:6px 0 4px}
  .menu input,.menu select{width:100%;height:36px;border:1px solid #d8dde6;border-radius:8px;padding:0 10px}

  /* Table */
  table{width:100%;border-collapse:separate;border-spacing:0}
  th,td{padding:12px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px}
  th{color:#6b7280;font-size:12px}
  .cell-name{display:flex;align-items:center;gap:10px}
  .avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;display:block;background:#f1f5f9}
  .rating{display:flex;align-items:center;gap:6px}
  .rating i{color:#f59e0b}

  .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent}
  .badge.ok{background:var(--okbg);color:var(--ok);border-color:var(--okb)}
  .badge.warn{background:var(--warnbg);color:var(--warn);border-color:var(--warnb)}
  .badge.orange{background:var(--orangebg);color:var(--orange);border-color:var(--orangeb)}

  .actions-ico{display:flex;align-items:center;gap:12px}
  .actions-ico a{color:#475569;text-decoration:none}
  @media (max-width:900px){
    .kpi-boxes{grid-template-columns:repeat(2,1fr)}
    .tools{flex-direction:column;align-items:stretch}
  }
</style>
</head>
<body>

<!-- Topbar -->
<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left">
      <div class="brand"><img src="<?= $BASE ?>/image/home-logo.png" alt="Fixora" class="brand-logo"></div>
    </div>
    <div class="tb-center">
      <div class="search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search Here">
      </div>
    </div>
    <div class="tb-right">
      <button class="notif-pill" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
    </div>
  </div>
</section>

<!-- KPI cards -->
<div class="hero">
  <div class="kpi-boxes">
    <div class="kbox"><div class="k">Total providers</div><div class="v"><?= (int)$kpi['total'] ?></div></div>
    <div class="kbox"><div class="k">Active</div><div class="v"><?= (int)$kpi['active'] ?></div></div>
    <div class="kbox"><div class="k">Suspended</div><div class="v"><?= (int)$kpi['suspended'] ?></div></div>
    <div class="kbox"><div class="k">New provider this month</div><div class="v"><?= (int)$kpi['newmonth'] ?></div></div>
  </div>
</div>

<!-- Tools + Table (white card) -->
<div class="wrap">
  <div class="card">

    <div class="tools">
      <form id="searchForm" method="get" class="left">
        <div class="box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input class="hero-search" type="text" name="q" value="<?= h($q) ?>" placeholder="Search Providers by Name, ID, Service">
        </div>
      </form>

      <div class="rbtns">
        <div class="dropdown" id="filterDD">
          <button class="btn" type="button"><i class="fa-solid fa-filter"></i> Filter</button>
          <div class="menu">
            <form id="filterForm" method="get">
              <input type="hidden" name="q" value="<?= h($q) ?>">
              <label>Status</label>
              <select name="status">
                <option value="">All</option>
                <option value="active"        <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="suspended"     <?= $status==='suspended'?'selected':'' ?>>Suspended</option>
                <option value="not_available" <?= $status==='not_available'?'selected':'' ?>>Not available</option>
              </select>
              <label>Min Rating</label>
              <input type="number" step="0.1" min="0" max="5" name="rating" value="<?= $minrate!==null?h($minrate):'' ?>" placeholder="e.g. 4.0">
              <div style="display:flex;gap:8px;margin-top:10px">
                <button class="btn primary" type="submit">Apply</button>
                <a class="btn ghost" href="admin-providers.php">Reset</a>
              </div>
            </form>
          </div>
        </div>

        <a class="btn ghost" href="?<?= h(qs(['export'=>1])) ?>"><i class="fa-solid fa-file-arrow-down"></i> Export csv</a>
        <a class="btn primary" href="admin-provider-add.php"><i class="fa-solid fa-user-plus"></i> Add provider</a>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:26%">Provider</th>
          <th style="width:16%">National ID Number</th>
          <th style="width:18%">Service Category</th>
          <th style="width:16%">Registration Date</th>
          <th style="width:10%">Rating</th>
          <th style="width:12%">Status</th>
          <th style="width:12%">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="7" style="color:#6b7280">No data.</td></tr>
        <?php else: foreach($rows as $p): ?>
          <tr>
            <td>
              <div class="cell-name">
                <img class="avatar" src="<?= h($p['avatar']) ?>" alt="avatar" onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
                <div>
                  <div style="font-weight:700"><?= h($p['name']) ?></div>
                  <div style="font-size:12px;color:#6b7280"><?= h($p['phone']) ?></div>
                </div>
              </div>
            </td>
            <td><?= h($p['national_id']) ?></td>
            <td><?= h($p['category']) ?></td>
            <td><?= h($p['created_at']) ?></td>
            <td><div class="rating"><i class="fa-solid fa-star"></i> <?= number_format($p['rating'],1) ?></div></td>
            <td>
              <?php
                if ($p['state']==='active')      { $cls='ok';     $txt='Active'; }
                elseif ($p['state']==='suspended'){ $cls='warn';   $txt='Suspended'; }
                else                              { $cls='orange'; $txt='Not available'; }
              ?>
              <span class="badge <?= $cls ?>"><?= $txt ?></span>
            </td>
            <td>
              <div class="actions-ico">
                <a href="admin-provider-view.php?id=<?= (int)$p['id'] ?>" title="View"><i class="fa-regular fa-eye"></i></a>
                <a href="admin-provider-edit.php?id=<?= (int)$p['id'] ?>" title="Edit"><i class="fa-regular fa-pen-to-square"></i></a>
                <a href="admin-provider-delete.php?id=<?= (int)$p['id'] ?>" title="Delete" onclick="return confirm('Delete this provider?')"><i class="fa-regular fa-trash-can"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

  </div>
</div>

<script>
  // dropdown open/close
  const dd = document.getElementById('filterDD');
  dd.querySelector('button').addEventListener('click', ()=> dd.classList.toggle('open'));
  document.addEventListener('click', (e)=>{ if(!dd.contains(e.target)) dd.classList.remove('open'); });

  // submit search on Enter
  document.querySelector('.hero-search').addEventListener('keydown', e=>{
    if(e.key==='Enter'){ e.preventDefault(); document.getElementById('searchForm').submit(); }
  });
</script>
</body>
</html>