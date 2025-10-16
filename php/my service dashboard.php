<?php
/* =========================================================
   Fixora – Services (Provider Dashboard)  (Full Page)
========================================================= */
session_start();
$BASE = '/mp';

/* ===== حماية الجلسة ===== */
if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

/* ===== اتصال أساسي للحصول على اسم وصورة المزود ===== */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

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

/* بيانات المزوّد */
$providerName  = "Unknown User";
$providerPhoto = "";
$sqlProv = "SELECT u.full_name, pp.avatar_path
            FROM users u LEFT JOIN provider_profiles pp ON pp.user_id = u.id
            WHERE u.id = ? LIMIT 1";
if ($st = $conn->prepare($sqlProv)) {
  $st->bind_param("i", $uid);
  $st->execute();
  $pr = $st->get_result();
  if ($pr && $row = $pr->fetch_assoc()) {
    if (!empty($row['full_name']))   $providerName  = $row['full_name'];
    if (!empty($row['avatar_path'])) $providerPhoto = img_url($row['avatar_path'], $BASE);
  }
  $st->close();
}
if ($providerPhoto === '') $providerPhoto = $BASE . '/image/no-avatar.png';
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/rating_dashbord.css">
<style>
  :root{
    --bg:#f6f8fc; --card:#fff; --border:#e7edf5; --muted:#6b7280; --text:#0f172a;
    --primary:#2f7af8; --danger:#ef4444; --success:#10b981;
    --radius:14px; --shadow:0 8px 20px rgba(16,24,40,.08);
  }
  body{background:var(--bg); color:var(--text)}
  .fx-page { padding: 26px 60px; }
  .fx-wrap{max-width:1280px;margin:0 auto;display:grid;grid-template-columns:1fr 330px;gap:22px}
  @media (max-width:1100px){.fx-wrap{grid-template-columns:1fr}}
  .fx-head{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between}
  .fx-title{margin:0;font-size:28px}
  .fx-sub{margin:6px 0 0;color:var(--muted);font-size:18px}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;border:1px solid var(--border);background:#fff;color:var(--text);cursor:pointer}
.add-sec{
  display:inline-flex;
  align-items:center;
  gap:15px;        /* 🔥 مسافة أكبر بين النص والأيقونة */
  background:var(--primary);
  color:#fff;
  border-color:var(--primary);
  padding:12px 18px;
  border-radius:12px;
  text-decoration:none;
}

.add-sec .icon-circle{
  width:18px;      /* 🔥 أصغر */
  height:18px;
  border-radius:50%;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:2px solid #fff;
  line-height:1;
  font-size:10px;  /* 🔥 أيقونة أصغر */
}
  .fx-head{justify-content:flex-start;gap:530px}
  .fx-head .add-sec{margin-right:60px}





  .btn.block{
  width:auto;                /* 🔥 ما ياخدش عرض كامل */
  min-width: 160px;          /* تعطيه حجم ثابت حلو */
  justify-content:center;
  margin: 0 auto;            /* يوسّطه جوّا الصندوق */
  display: flex;             /* يضمن محاذاة المحتوى */
}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
  .card-pad{padding:16px}

  /* شبكة الخدمات */
  .svc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
  @media (max-width:1024px){.svc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:680px){.svc-grid{grid-template-columns:1fr}}
  .svc-card{display:flex;flex-direction:column;border-radius:12px;overflow:hidden;transition:.18s;position:relative}
  .svc-card:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(16,24,40,.12)}
  .svc-img{aspect-ratio:16/9;background:#f3f5f8}
  .svc-img img{width:100%;height:100%;object-fit:cover}
  .svc-body{padding:16px;display:flex;flex-direction:column}
  .svc-title{margin:10px 0 8px;font-size:18px;font-weight:700;line-height:1.25}

  /* ✅ ترتيب ومحاذاة السعر/المدة + الحجوزات/الحالة */
  .info-row{
    display:flex;align-items:center;justify-content:space-between;
    gap:16px;color:#475569;font-size:14px;
  }
  .info-row + .info-row{ margin-top:8px; }
  .info-item{display:inline-flex;align-items:center;gap:6px}
  .info-item i{line-height:1}

  .divider{height:1px;background:var(--border);margin:14px 0 12px}
/* ✅ أزرار Edit / Delete صغيرة متساوية */
.svc-actions{
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 10px;
}

.btn.action-btn{
  padding: 6px 12px;         /* أصغر */
  font-size: 13px;           /* خط أصغر */
  border-radius: 8px;        /* زوايا أنعم */
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 70px;           /* نفس العرض */
  height: 30px;              /* ارتفاع أصغر */
  justify-content: center;
  line-height: 1;
  text-decoration: none;   /* 🔥 يشيل الخط */
}

.btn.action-btn.edit{
  background: var(--primary);
  color: #fff;
  border: 1px solid var(--primary);
}

.btn.action-btn.delete{
  background: #fff;
  color: var(--danger);
  border: 1px solid var(--danger);
}

.btn.action-btn.delete:hover{
  background:#fee2e2;
}

  /* سويتش */
  .switch{display:inline-flex;align-items:center;gap:10px;font-size:14px}
  .switch input{appearance:none;width:42px;height:24px;background:#e5e7eb;border-radius:999px;position:relative;cursor:pointer}
  .switch input:checked{background:var(--success)}
  .switch input::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.18s}
  .switch input:checked::after{transform:translateX(18px)}
  .state-on{color:var(--success);font-weight:600}
  .state-off{color:#111}

  /* شارة/تعتيم للخدمات المتوقفة */
  .svc-card.is-inactive{opacity:.6;filter:grayscale(.15)}
  .svc-badge{
    position:absolute;top:10px;left:10px;z-index:2;
    padding:6px 10px;font-size:12px;font-weight:700;
    background:#ffe8e8;color:#b91c1c;border:1px solid #f5c2c2;border-radius:999px;
  }

  /* Insights يمين */
  .insight-header{font-size:18px;font-weight:700;margin:0 0 10px}
  .insight-box{padding:14px;border:1px solid var(--border);border-radius:14px;background:#fbfdff;margin-bottom:14px}
  .insight-title{font-size:13px;color:var(--muted);margin:0 0 4px}
  .insight-value{font-weight:800;font-size:20px}
  .bar{height:10px;border-radius:999px;background:#eef2f7;overflow:hidden}
  .bar i{display:block;height:100%;background:linear-gradient(90deg,#2f7af8,#6aa9ff)}

  /* Pagination */
  .pager.pro{display:flex;align-items:center;justify-content:center;gap:8px;margin:20px 0 6px}
  .pager.pro a,.pager.pro span{min-width:38px;height:38px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border);border-radius:12px;background:#fff;color:#111;font-weight:600;text-decoration:none}
  .pager.pro .active{background:var(--primary);color:#fff;border-color:var(--primary)}
  .pager.pro .dots{border:none;background:transparent;color:#94a3b8;min-width:auto;padding:0 4px}
  .pager.pro .nav{min-width:38px}
  .pager.pro .nav.disabled{pointer-events:none;opacity:.5}

  /* ===== Quick Actions Panel Styles ===== */
  .qa-wrap {max-width:1060px;margin:14px auto 24px;padding:0 22px}
  .qa-title {margin:0 0 12px;font:800 20px/1.1 Nunito,sans-serif;color:#0b0f1a}
  .qa-grid {display:flex;align-items:center;justify-content:space-between;gap:28px}
  .qa-btn {display:flex;align-items:center;justify-content:space-between;width:260px;height:44px;padding:0 18px 0 22px;border-radius:10px;background:var(--primary);color:#fff;text-decoration:none;font-weight:800;font-size:15px;box-shadow:var(--shadow-btn)}
  .qa-ico {width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2}
  @media (max-width:768px){
    .qa-grid{flex-direction:column;gap:12px}
    .qa-btn{width:100%;justify-content:flex-start}
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
          </div>
        </div>

      </div>
    </div>
  </section>


<?php
/* ===== اتصال لجلب الخدمات والإحصائيات ===== */
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* Pagination */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 3; $offset = ($page-1)*$limit;

/* مجموع الخدمات */
$total_services = 0;
if ($st = $conn->prepare("SELECT COUNT(*) FROM services WHERE provider_id=? AND is_deleted=0")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($total_services); $st->fetch(); $st->close();
}
$pages = max(1, (int)ceil($total_services/$limit));
if ($page > $pages) { $page = $pages; $offset = ($page-1)*$limit; }

/* الخدمات */
$services = [];
$sqlSrv = "
  SELECT s.id, s.title, s.img_path,
         s.price_from, s.price_to,
         s.duration_minutes, s.is_active,
         COUNT(b.id) AS bookings_count
  FROM services s
  LEFT JOIN bookings b ON b.service_id = s.id
WHERE s.provider_id = ? AND s.is_deleted = 0
  GROUP BY s.id
  ORDER BY s.created_at DESC
  LIMIT ? OFFSET ?
";
if ($st = $conn->prepare($sqlSrv)) {
  $st->bind_param("iii", $uid, $limit, $offset);
  $st->execute();
  $rs = $st->get_result();
  while ($row = $rs->fetch_assoc()) {
    $row['image'] = img_url($row['img_path'] ?? '', $BASE);
    $services[] = $row;
  }
  $st->close();
}

/* Insights */
$avg_price = 0;
/* متوسط السعر للخدمات المفعّلة فقط */
if ($st = $conn->prepare("SELECT COALESCE(AVG((price_from+price_to)/2),0) FROM services
WHERE provider_id=? AND is_active=1 AND is_deleted=0")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($avg_price); $st->fetch(); $st->close();
}
$popular_title = '—';
if ($st = $conn->prepare("
  SELECT s.title FROM services s
  LEFT JOIN bookings b ON b.service_id=s.id
  WHERE s.provider_id=?
  GROUP BY s.id
  ORDER BY COUNT(b.id) DESC
  LIMIT 1
")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($popular_title);
  if (!$st->fetch()) $popular_title = '—';
  $st->close();
}
$active_count=0; $inactive_count=0;
if ($st = $conn->prepare("SELECT SUM(is_active=1), SUM(is_active=0) FROM services
WHERE provider_id=? AND is_deleted=0")) {
  $st->bind_param("i", $uid);
  $st->execute(); $st->bind_result($active_count,$inactive_count); $st->fetch(); $st->close();
}
$conn->close();

/* دالة عرض المدة */
function format_duration($mins){
  $mins = (int)$mins;
  if ($mins <= 0) return '—';
  if ($mins < 60) return '1 Hour';
  $hours = floor($mins / 60);
  $rem   = $mins % 60;
  if ($rem === 0) return $hours . ' Hours';
  return $hours . 'h ' . $rem . 'm';
}
?>

<!-- ===== Content ===== -->
<main class="fx-page">
  <div class="fx-wrap">
    <header class="fx-head">
      <div>
        <h1 class="fx-title">Services</h1>
        <p class="fx-sub">Manage your services and pricing</p>
      </div>
      <!-- <a class="btn primary add-sec" href="<?= $BASE ?>/provider/service-create.php">
        <span class="label">Add New Serviec</span>
        <span class="icon-circle"><i class="fa-solid fa-plus"></i></span>
      </a> -->

      <a class="btn primary add-sec" href="javascript:void(0)" onclick="openServicePopup()">
        <span class="label">Add New Service</span>
        <span class="icon-circle"><i class="fa-solid fa-plus"></i></span>
      </a>
    </header>

    <section class="card card-pad">
      <div class="svc-grid">
        <?php if (!empty($services)): foreach($services as $srv): ?>
          <article class="card svc-card <?= !empty($srv['is_active']) ? '' : 'is-inactive' ?>" data-id="<?= (int)$srv['id'] ?>">
            <?php if (empty($srv['is_active'])): ?>
              <div class="svc-badge">Inactive</div>
            <?php endif; ?>
            <div class="svc-img">
              <img src="<?= h($srv['image'] ?: ($BASE.'/image/placeholder-service.jpg')) ?>"
                   alt="<?= h($srv['title']) ?>"
                   onerror="this.src='<?= $BASE ?>/image/placeholder-service.jpg'">
            </div>
            <div class="svc-body">
              <h3 class="svc-title"><?= h($srv['title']) ?></h3>

              <?php $durLabel = format_duration($srv['duration_minutes']); ?>
              <div class="info-row">
                <div class="info-left">
                  <span class="info-item">
                    <i class="fa-solid fa-dollar-sign"></i>
                    <?= (float)$srv['price_from'] ?>–<?= (float)$srv['price_to'] ?>
                  </span>
                </div>
                <div class="info-right">
                  <span class="info-item">
                    <i class="fa-regular fa-clock"></i>
                    <?= h($durLabel) ?>
                  </span>
                </div>
              </div>

              <div class="info-row">
                <div class="info-left">
                  <span class="info-item">
                    <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                    <?= (int)$srv['bookings_count'] ?> booking
                  </span>
                </div>
                <div class="info-right">
                  <?php $isOn = !empty($srv['is_active']); ?>
                  <label class="switch" title="Toggle active">
                    <input type="checkbox"
                           class="js-toggle-active"
                           data-id="<?= (int)$srv['id'] ?>"
                           <?= $isOn ? 'checked':'' ?>>
                    <span class="<?= $isOn ? 'state-on':'state-off' ?>">
                      <?= $isOn ? 'Active':'Inactive' ?>
                    </span>
                  </label>
                </div>
              </div>

              <div class="divider"></div>
              <div class="svc-actions">
                <a class="btn action-btn edit js-edit-link"
                   href="#"
                   data-id="<?= (int)$srv['id'] ?>"
                   data-title="<?= h($srv['title']) ?>"
                   data-price_from="<?= (float)$srv['price_from'] ?>"
                   data-price_to="<?= (float)$srv['price_to'] ?>"
                   data-duration="<?= (int)$srv['duration_minutes'] ?>"
                   data-active="<?= !empty($srv['is_active']) ? 1 : 0 ?>"
                   data-image="<?= h($srv['image'] ?: ($BASE.'/image/placeholder-service.jpg')) ?>">
                  <i class="fa-regular fa-pen-to-square"></i> Edit
                </a>
               <a class="btn action-btn delete js-delete-link"
   href="service-delete.php?id=<?= (int)$srv['id'] ?>"
   data-id="<?= (int)$srv['id'] ?>">
  <i class="fa-regular fa-trash-can"></i> Delete
</a>
              </div>
            </div>
          </article>
        <?php endforeach; else: ?>
          <p style="grid-column:1/-1;color:#6b7280">No services found.</p>
        <?php endif; ?>
      </div>

      <?php if ($pages > 1): ?>
        <?php
          $make_qs = fn($p) => '?' . http_build_query(['page' => $p]);
          $items = [1];
          $start = max(2, $page - 2);
          $end   = min($pages - 1, $page + 2);
          if ($start > 2) $items[] = '...';
          for ($i = $start; $i <= $end; $i++) $items[] = $i;
          if ($end < $pages - 1) $items[] = '...';
          if ($pages > 1) $items[] = $pages;
        ?>
        <nav class="pager pro" aria-label="Pagination">
          <a class="nav <?= $page==1 ? 'disabled' : '' ?>" href="<?= $page==1 ? '#' : $make_qs($page-1) ?>" aria-label="Previous">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
          </a>
          <?php foreach ($items as $it): ?>
            <?php if ($it === '...'): ?>
              <span class="dots">…</span>
            <?php elseif ($it === $page): ?>
              <span class="active"><?= $it ?></span>
            <?php else: ?>
              <a href="<?= $make_qs($it) ?>"><?= $it ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="nav <?= $page==$pages ? 'disabled' : '' ?>" href="<?= $page==$pages ? '#' : $make_qs($page+1) ?>" aria-label="Next">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
          </a>
        </nav>
      <?php endif; ?>
    </section>

    <aside>
      <div class="card card-pad">
        <h3 class="insight-header">Pricing Insights</h3>

        <div class="insight-box">
          <div class="insight-title">Average Price</div>
          <div id="avgPrice" class="insight-value">$<?= number_format($avg_price,0) ?></div>
        </div>

        <div class="insight-box">
          <div class="insight-title">Most Booked Services</div>
          <div class="insight-value" style="font-size:16px;font-weight:700"><?= h($popular_title) ?></div>
        </div>

        <?php
          $a   = (int)$active_count;
          $i   = (int)$inactive_count;
          $tot = max(1, $a + $i);
          $pct = round(($a / $tot) * 100);
        ?>
        <div class="insight-box">
          <div class="insight-title">Active vs Inactive</div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span id="activeCount"   style="color:var(--success);font-weight:700"><?= $a ?></span>
            <span style="color:#94a3b8">/</span>
            <span id="inactiveCount" style="font-weight:700"><?= $i ?></span>
          </div>
          <div class="bar">
            <i id="activeBar" style="width:<?= $pct ?>%"></i>
          </div>
        </div>

        <div class="insight-box">
          <div class="insight-title">Need Help</div>
          <p style="margin:6px 0 12px;color:var(--muted)">
            Our Support Team Is Here To Assist With Your Services And Pricing
          </p>
          <a class="btn primary block" href="https://wa.me/972592643752" target="_blank">
            Contact Us <i class="fa-regular fa-envelope"></i>
          </a>
        </div>
      </div>
    </aside>

      <section class="qa-wrap" style="max-width:1060px;margin:14px auto 24px;padding:0 22px">

<h3 class="qa-title" style="margin:0 0 12px;font:800 20px/1.1 Nunito,sans-serif;color:#0b0f1a">Quick Actions Panel</h3>
    <div class="qa-grid" style="display:flex;align-items:center;justify-content:space-between;gap:28px">
      <a class="qa-btn" href="#" onclick="openAvailabilityAndLoad(); return false;">
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

  <!-- ===== Edit Service Modal ===== -->
  <div id="modalEdit" class="fx-modal" hidden>
    <div class="fx-dialog">
      <h3>✏ Edit Service</h3>
      <form id="editForm" enctype="multipart/form-data">
        <input type="hidden" name="id" id="edit_id">
        <div class="row">
          <label for="edit_title">Service Title</label>
          <input type="text" name="title" id="edit_title" required placeholder="Enter service title">
        </div>
        <div class="row">
          <label>Service Image</label>
          <div class="image-upload-container">
            <img id="edit_preview" src="<?= $BASE ?>/image/placeholder-service.jpg" alt="Preview" class="image-preview">
            <div class="file-input-wrapper">
              <label for="edit_image" class="file-input-label">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                Choose Image
              </label>
              <input type="file" name="image" id="edit_image" accept=".jpg,.jpeg,.png,.webp,.gif" class="file-input">
              <div class="file-info"><small>Supports: JPG, PNG, WebP, GIF • Max: 5MB</small></div>
            </div>
          </div>
        </div>

        <div class="grid-2-col">
          <div class="row">
            <label for="edit_price_from">Price From ($)</label>
            <input type="number" step="0.01" name="price_from" id="edit_price_from" required min="0" placeholder="0.00">
          </div>
          <div class="row">
            <label for="edit_price_to">Price To ($)</label>
            <input type="number" step="0.01" name="price_to" id="edit_price_to" required min="0" placeholder="0.00">
          </div>
        </div>

        <div class="grid-2-col">
          <div class="row">
            <label for="edit_duration">Duration (minutes)</label>
            <input type="number" min="1" name="duration_minutes" id="edit_duration" required placeholder="60">
          </div>
          <div class="row checkbox-row">
            <label class="checkbox-label">
              <input type="checkbox" id="edit_active" name="is_active" class="checkbox-input">
              <span class="checkbox-text">Active Service</span>
            </label>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-cancel" data-close>Cancel</button>
          <button type="submit" class="btn btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

<style>
/* ===== Modal Base Styles ===== */
.fx-modal{position:fixed;inset:0;background:rgba(15,23,42,.7);display:grid;place-items:center;z-index:10000;padding:20px;backdrop-filter:blur(8px)}
.fx-modal[hidden]{display:none}
.fx-dialog{width:min(580px,95vw);max-height:90vh;background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);padding:0;overflow-y:auto;animation:modalSlideIn .3s ease-out}
@keyframes modalSlideIn{from{opacity:0;transform:translateY(-30px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}
.fx-dialog h3{font-size:22px;font-weight:700;color:#1e293b;margin:0;padding:24px 24px 16px;border-bottom:1px solid #f1f5f9}
.fx-dialog form{padding:8px 24px 24px}
.row{display:flex;flex-direction:column;margin-bottom:16px}
.row label{font-size:14px;font-weight:600;color:#374151;margin-bottom:6px}
.row input[type="text"],.row input[type="number"]{border:2px solid #e5e7eb;border-radius:12px;padding:12px 16px;font-size:15px;transition:.2s;background:#fafafa}
.row input:focus{outline:none;border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.image-upload-container{display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap}
.image-preview{width:120px;height:90px;object-fit:cover;border-radius:12px;border:2px dashed #d1d5db;background:#f8fafc;transition:.3s;flex-shrink:0}
.image-preview:hover{border-color:#3b82f6;transform:scale(1.05)}
.file-input-wrapper{flex:1;min-width:200px}
.file-input-label{display:inline-flex;align-items:center;gap:8px;background:#3b82f6;color:#fff;padding:10px 16px;border-radius:10px;cursor:pointer;font-weight:500;font-size:14px;transition:.2s;border:2px solid #3b82f6;margin-bottom:8px}
.file-input-label:hover{background:#2563eb;border-color:#2563eb;transform:translateY(-1px)}
.file-input{display:none}
.file-info small{color:#6b7280;font-size:12px}
.grid-2-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
.checkbox-row{margin-top:8px}
.checkbox-label{display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 0;margin:0}
.checkbox-input{width:18px;height:18px;border-radius:5px;border:2px solid #d1d5db;cursor:pointer;margin:0}
.checkbox-input:checked{background:#10b981;border-color:#10b981}
.checkbox-text{font-weight:600;color:#374151;font-size:14px}
.modal-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb}
.modal-actions .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:12px;font-weight:600;font-size:14px;transition:.2s;border:2px solid;cursor:pointer;text-decoration:none;min-width:120px;justify-content:center;height:44px}
.btn-cancel{background:#fff;color:#dc2626;border-color:#dc2626}
.btn-cancel:hover{background:#fef2f2;border-color:#b91c1c;color:#b91c1c;transform:translateY(-1px);box-shadow:0 4px 12px rgba(220,38,38,.15)}
.btn-save{background:#3b82f6;color:#fff;border-color:#3b82f6}
.btn-save:hover{background:#2563eb;border-color:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(37,99,235,.3)}
@media (max-width:768px){
  .fx-dialog{width:95vw;margin:10px}
  .grid-2-col{grid-template-columns:1fr;gap:12px}
  .image-upload-container{flex-direction:column}
  .modal-actions{flex-direction:column}
  .modal-actions .btn{justify-content:center}
}
</style>

<!-- ===== Add New Service Popup (نسخة الاستايل المقيدة) ===== -->
<style>
  .svc-overlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:100001}
  .svc-modal{width:96%;max-width:900px;background:#fff;border-radius:14px;padding:24px;box-shadow:0 24px 64px rgba(0,0,0,.25)}
  .svc-modal h3{margin:0 0 16px;font:800 20px/1 Nunito,system-ui;color:#0b0f1a}
  .svc-modal .svc-body{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .svc-modal .svc-body .full{grid-column:1 / -1}
  .svc-modal .field{display:flex;flex-direction:column;gap:6px}
  .svc-modal .label{font-weight:700;font-size:14px}
  .svc-modal .input,.svc-modal .select,.svc-modal .textarea{border:1px solid #e8eef4;border-radius:8px;padding:10px;font-size:14px;background:#fff}
  .svc-modal .textarea{min-height:100px;resize:vertical}
  .svc-modal input,.svc-modal select,.svc-modal textarea{border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;transition:border-color .2s,box-shadow .2s}
  .svc-modal input:hover,.svc-modal select:hover,.svc-modal textarea:hover{border-color:#1e90ff}
  .svc-modal input:focus,.svc-modal select:focus,.svc-modal textarea:focus{border-color:#1e90ff;box-shadow:0 0 0 3px rgba(30,144,255,.2);outline:none}
  .svc-modal .drop{border:2px dashed #e2e8f0;border-radius:10px;width:40%;height:160px;display:flex;align-items:center;justify-content:center;text-align:center;color:#6b7280;cursor:pointer;overflow:hidden;background:#fafbfc;padding:0;margin:0 auto}
  .svc-modal .drop img{width:100%;height:100%;object-fit:cover;display:block;border-radius:10px}
  .svc-modal .svc-foot{display:flex;justify-content:center;gap:16px;margin-top:18px}
  .svc-modal .btn{padding:10px 18px;border-radius:8px;cursor:pointer;font-weight:700;border:1px solid #e8eef4}
  .svc-modal .btn-primary{background:#1e90ff;border-color:#1e90ff;color:#fff}
  .svc-modal .btn.btn-cancel{background:#fff;color:#ef4444;border-color:#ef4444}
  .svc-modal .btn.btn-cancel:hover{background:#fee2e2}
  @media (max-width:768px){.svc-modal .svc-body{grid-template-columns:1fr}}
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





<!-- ===== Availability Popup v4 (SCOPED) ===== -->
<div id="availabilityPopupRoot">
  <style>
   /* نحجّر كل ستايلات البوب داخل الـ id لمنع تضارب مع الصفحة */
#availabilityPopupRoot{font-family: Inter,"Segoe UI",Tahoma,Arial}

#availabilityPopupRoot .av-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.55);
  display:none;z-index:100000;align-items:center;justify-content:center
}
#availabilityPopupRoot .av-modal{
  width:96%;max-width:1100px;background:#fff;border-radius:14px;
  box-shadow:0 24px 64px rgba(0,0,0,.25);overflow:hidden
}
#availabilityPopupRoot .av-head{padding:18px 22px;border-bottom:1px solid #e8eef4;text-align:center}
#availabilityPopupRoot .av-title{margin:0;font:800 20px/1 Nunito,system-ui;color:#0aa0ff}
#availabilityPopupRoot .av-body{padding:14px 18px 8px}

/* صف واحد: Day+Toggle | From | time | To | time | Add | Copy */
#availabilityPopupRoot .day-block{padding:8px 0;border-bottom:1px solid #f1f5f9}
#availabilityPopupRoot .row{
  display: grid;
  align-items: center;
  gap: 10px;
  grid-template-columns: 190px 70px 140px 50px 140px 180px 180px; /* ✅ صغرنا العمود الأخير */
}
#availabilityPopupRoot .row.extra{
  grid-template-columns:200px 70px 160px 60px 160px 40px 1fr
}

#availabilityPopupRoot .day-cell{
  display:flex;align-items:center;justify-content:space-between;gap:18px;
  font-weight:800;color:#0b0f1a;min-width:180px
}

/* منع تكبير أيقونات/أزرار بسبب CSS خارجي */
#availabilityPopupRoot svg{width:18px;height:18px;flex:0 0 18px}
#availabilityPopupRoot .ico{width:18px;height:18px}

/* السويتش */
#availabilityPopupRoot .switch{position:relative;width:44px;height:24px;flex:0 0 auto}
#availabilityPopupRoot .switch input{display:none}
#availabilityPopupRoot .slider{position:absolute;inset:0;background:#e5e7eb;border-radius:999px;cursor:pointer;transition:.25s}
#availabilityPopupRoot .slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:#fff;border-radius:999px;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:.25s}
#availabilityPopupRoot .switch input:checked + .slider{background:#1e90ff}
#availabilityPopupRoot .switch input:checked + .slider:before{transform:translateX(20px)}

/* الحقول والأزرار داخل البوب فقط */
#availabilityPopupRoot .mini-label{
  display:inline-flex;align-items:center;justify-content:center;height:38px;
  padding:0 10px;border:1px solid #e8eef4;border-radius:8px;background:#f8fafc;color:#0b0f1a;font-weight:700;
  white-space:nowrap
}
#availabilityPopupRoot .time{
  height:38px;min-width:140px;padding:0 12px;border:1px solid #e8eef4;
  border-radius:8px;background:#fff;color:#111
}
#availabilityPopupRoot .btn{
  display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 14px;
  border:1px solid #e8eef4;border-radius:8px;background:#fff;color:#111;
  text-decoration:none;cursor:pointer;font-weight:700;justify-content:center;
  white-space:nowrap
}
#availabilityPopupRoot .btn-soft{background:#f8fafc}
#availabilityPopupRoot .btn-primary{background:#1e90ff;color:#fff;border-color:#1e90ff}

#availabilityPopupRoot .remove-slot{
  width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;
  border:1px solid #ffe3e3;background:#fff5f5;color:#ef4444;cursor:pointer;font-size:18px;font-weight:800
}

#availabilityPopupRoot .day-disabled .time,
#availabilityPopupRoot .day-disabled .mini-label,
#availabilityPopupRoot .day-disabled .btn{opacity:.45;pointer-events:none}

#availabilityPopupRoot .av-foot{
  display:flex;justify-content:center;gap:18px;padding:16px;border-top:1px solid #e8eef4;background:#fafbfc
}

/* زر إلغاء بإطار أحمر */
#availabilityPopupRoot .btn-soft_p,
#availabilityPopupRoot .btn-soft_p:focus,
#availabilityPopupRoot .btn-soft_p:active,
#availabilityPopupRoot .btn-soft_p:hover{
  background:#fff;color:#ef4444;border:1px solid #ef4444 !important
}
#availabilityPopupRoot .btn-soft_p:hover{background:#fee2e2}

/* Responsive */
@media (max-width:1020px){
  #availabilityPopupRoot .row{grid-template-columns:170px 60px 1fr 40px 1fr 1fr 1fr}
}
@media (max-width:720px){
  #availabilityPopupRoot .row{grid-template-columns:150px 60px 1fr 40px 1fr 1fr 1fr}
}

/* إصلاح الحسابات */
#availabilityPopupRoot, 
#availabilityPopupRoot *{box-sizing:border-box}
  </style>

  <div class="av-overlay" id="avOverlay" role="dialog" aria-modal="true" aria-labelledby="avTitle">
    <div class="av-modal">
      <div class="av-head"><h3 id="avTitle" class="av-title">Set Your Availability</h3></div>

      <div class="av-body" id="daysContainer">
        <!-- ===== 7 أيام ثابتة ===== -->
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
/* تفاعل بسيط مع نفس الهيكل */
(function () {
  const container = document.getElementById('daysContainer');
  const weekdayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

  container.addEventListener('change', (e) => {
    if (e.target.matches('.switch input')) {
      const block = e.target.closest('.day-block');
      block.classList.toggle('day-disabled', !e.target.checked);
    }
  });

  container.addEventListener('click', (e) => {
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
        <div></div>`;
      block.appendChild(extra);
    }

    if (e.target.closest('.remove-slot')) {
      e.target.closest('.row.extra')?.remove();
    }

    if (e.target.closest('.copy-weekdays')) {
      const sourceBlock = e.target.closest('.day-block');
      const enabled = !sourceBlock.classList.contains('day-disabled');

      const slots = Array.from(sourceBlock.querySelectorAll('.row')).map(r => {
        const t = r.querySelectorAll('input.time');
        return { from: t[0].value, to: t[1].value };
      });

      document.querySelectorAll('.day-block').forEach((block) => {
        const day = block.dataset.day;
        if (!weekdayNames.includes(day) || block === sourceBlock) return;

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
          </div>`;

        block.classList.toggle('day-disabled', !enabled);

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
            <div></div>`;
          block.appendChild(extra);
        });
      });
    }
  });

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
    fetch('save availability.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
  });

  document.getElementById('avOverlay').addEventListener('click', (e) => {
    if (e.target.id === 'avOverlay') e.currentTarget.style.display = 'none';
  });
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.getElementById('avOverlay').style.display = 'none';
  });
})();
</script>

<script>
/* نفس الدوال المساعدة التي لديك */
function applyAvailabilityToUI(payload){
  const daysWrap = document.getElementById('daysContainer');
  const allBlocks = daysWrap.querySelectorAll('.day-block');

  allBlocks.forEach(block=>{
    const day = block.dataset.day;
    const info = payload[day] || {enabled:false, slots:[]};

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
      </div>`;

    block.classList.toggle('day-disabled', !info.enabled);

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
        <div></div>`;
      block.appendChild(extra);
    });
  });
}

function openAvailabilityAndLoad(){
  document.getElementById('avOverlay').style.display = 'flex';
  fetch('get availability.php')
    .then(r=>r.json())
    .then(res=>{
      if(res.ok && res.data){ applyAvailabilityToUI(res.data); }
    })
    .catch(console.error);
}
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

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleURL = 'service_toggle.php';
  const deleteURL = 'service-delete.php';

  const avgEl = document.getElementById('avgPrice');
  const aEl   = document.getElementById('activeCount');
  const iEl   = document.getElementById('inactiveCount');
  const barEl = document.getElementById('activeBar');

  function applyCardUI(card, makeActive){
    let badge = card.querySelector('.svc-badge');
    if (makeActive) { card.classList.remove('is-inactive'); if (badge) badge.remove(); }
    else { card.classList.add('is-inactive'); if (!badge) { badge = document.createElement('div'); badge.className='svc-badge'; badge.textContent='Inactive'; card.prepend(badge); } }
  }

  document.querySelectorAll('.js-toggle-active').forEach(function (el) {
    const card  = el.closest('article.card.svc-card');
    const label = el.parentElement.querySelector('span');
    const isActive = el.checked;
    label.textContent = isActive ? 'Active' : 'Inactive';
    label.className   = isActive ? 'state-on' : 'state-off';
    applyCardUI(card, isActive);
  });

  document.querySelectorAll('.js-toggle-active').forEach(function (el) {
    if (el.dataset.bound === '1') return;
    el.dataset.bound = '1';

    el.addEventListener('change', async function (e) {
      const id = e.currentTarget.dataset.id;
      const is_active = e.currentTarget.checked ? 1 : 0;
      const label = e.currentTarget.parentElement.querySelector('span');
      const card  = e.currentTarget.closest('article.card.svc-card');

      label.textContent = is_active ? 'Active' : 'Inactive';
      label.className   = is_active ? 'state-on' : 'state-off';
      applyCardUI(card, !!is_active);

      try {
        const res = await fetch(toggleURL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: new URLSearchParams({ id, is_active })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.ok) throw new Error((data && data.error) ? data.error : 'toggle failed');

        if (aEl) aEl.textContent = (data.active_count ?? 0);
        if (iEl) iEl.textContent = (data.inactive_count ?? 0);
        if (barEl) barEl.style.width = ((data.pct_active ?? 0) + '%');
        if (avgEl) {
          const v = (typeof data.avg_price === 'number') ? data.avg_price : parseFloat(data.avg_price || 0);
          avgEl.textContent = '$' + Math.round(v);
        }
      } catch (err) {
        const revert = !is_active;
        e.currentTarget.checked = revert;
        label.textContent = revert ? 'Active' : 'Inactive';
        label.className   = revert ? 'state-on' : 'state-off';
        applyCardUI(card, revert);
        alert('Failed to update: ' + err.message);
        console.error(err);
      }
    });
  });

  document.querySelectorAll('.js-delete-link').forEach(a=>{
    if (a.dataset.bound === '1') return;
    a.dataset.bound = '1';

    a.addEventListener('click', async function(e){
      e.preventDefault();
      const id = new URL(a.href, location.href).searchParams.get('id');
      if (!id) return alert('Bad id');
      if (!confirm('Delete this service?')) return;

      try {
        const res = await fetch(deleteURL, {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
          body: new URLSearchParams({ id })
        });
        const data = await res.json().catch(()=>null);
        if (!res.ok || !data || !data.ok) throw new Error((data && data.error) ? data.error : 'delete failed');

        const card = document.querySelector('article[data-id="'+id+'"]');
        if (card) card.remove();

        const grid = document.querySelector('.svc-grid');
        if (grid && grid.querySelectorAll('article.card.svc-card').length === 0) {
          const p = document.createElement('p');
          p.style.cssText = 'grid-column:1/-1;color:#6b7280';
          p.textContent = 'No services found.';
          grid.appendChild(p);
        }

        if (aEl) aEl.textContent = (data.active_count ?? 0);
        if (iEl) iEl.textContent = (data.inactive_count ?? 0);
        if (barEl) barEl.style.width = ((data.pct_active ?? 0) + '%');
        if (avgEl) {
          const v = (typeof data.avg_price === 'number') ? data.avg_price : parseFloat(data.avg_price || 0);
          avgEl.textContent = '$' + Math.round(v);
        }

      } catch(err) {
        alert('Failed to delete: ' + err.message);
        console.error(err);
      }
    });
  });

});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const UPDATE_URL = 'service-update.php';
  const TOGGLE_URL = 'service_toggle.php';
  const DELETE_URL = 'service-delete.php';

  function openEditModal(link) {
    document.getElementById('edit_id').value = link.dataset.id || '';
    document.getElementById('edit_title').value = link.dataset.title || '';
    document.getElementById('edit_price_from').value = link.dataset.price_from || 0;
    document.getElementById('edit_price_to').value = link.dataset.price_to || 0;
    document.getElementById('edit_duration').value = link.dataset.duration || 60;
    document.getElementById('edit_active').checked = (link.dataset.active === '1');

    const imgPrev = document.getElementById('edit_preview');
    if (imgPrev) imgPrev.src = link.dataset.image || '';

    document.getElementById('modalEdit').hidden = false;
  }

  document.addEventListener('click', function(e) {
    if (e.target.closest('.js-edit-link')) {
      e.preventDefault();
      openEditModal(e.target.closest('.js-edit-link'));
    }
  });

  document.getElementById('modalEdit').addEventListener('click', function(e) {
    if (e.target.matches('[data-close]') || e.target.id === 'modalEdit') {
      e.currentTarget.hidden = true;
    }
  });

  document.getElementById('edit_image').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      document.getElementById('edit_preview').src = URL.createObjectURL(file);
    }
  });

  document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const saveBtn = this.querySelector('button[type="submit"]');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = 'Saving...';
    saveBtn.disabled = true;

    try {
      const formData = new FormData(this);
      formData.set('is_active', document.getElementById('edit_active').checked ? 1 : 0);

      const response = await fetch('service-update.php', { method:'POST', body: formData });
      const result = await response.json();

      if (result.ok) {
        alert('تم حفظ التعديلات بنجاح!');
        document.getElementById('modalEdit').hidden = true;
        setTimeout(()=>{ location.reload(); }, 1000);
      } else {
        alert('خطأ: ' + result.error);
      }
    } catch (error) {
      alert('خطأ في الشبكة: ' + error.message);
      console.error('Error:', error);
    } finally {
      saveBtn.innerHTML = originalText;
      saveBtn.disabled = false;
    }
  });

  document.querySelectorAll('.js-toggle-active').forEach(function(checkbox) {
    checkbox.addEventListener('change', async function() {
      const id = this.dataset.id;
      const isActive = this.checked ? 1 : 0;
      try {
        const response = await fetch(TOGGLE_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + id + '&is_active=' + isActive
        });
        const data = await response.json();
        if (!data.ok) { this.checked = !this.checked; alert('Error: ' + data.error); }
      } catch (error) {
        this.checked = !this.checked;
        alert('Network error: ' + error.message);
      }
    });
  });

 // اربط أزرار الحذف: POST ثم اشطب الكارد من الواجهة
document.querySelectorAll('.js-delete-link').forEach(function (btn) {
  if (btn.dataset.bound === '1') return;
  btn.dataset.bound = '1';

  btn.addEventListener('click', async function (e) {
    e.preventDefault();

    // نجيب الـ id من الـ href أو data-id
    const match = btn.getAttribute('href')?.match(/id=(\d+)/);
    const id = match?.[1] || btn.dataset.id;
    if (!id) return;

    if (!confirm('Are you sure you want to delete this service?')) return;

    try {
      // endpoint تبعك يستقبل POST
      const fd = new FormData();
      fd.append('id', id);

      const res  = await fetch('service-delete.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        alert('Delete failed: ' + (data.error || 'unknown'));
        return;
      }

      // ✅ احذف الكارد فورًا من DOM
      const card = btn.closest('.svc-card');
      card?.remove();

      // (اختياري) حدّث الإحصائيات لو رجعت من السيرفر
      const aEl = document.getElementById('activeCount');
      const iEl = document.getElementById('inactiveCount');
      const bar = document.getElementById('activeBar');
      const avg = document.getElementById('avgPrice');

      if (typeof data.active_count !== 'undefined') {
        if (aEl) aEl.textContent = data.active_count;
        if (iEl) iEl.textContent = data.inactive_count;
        if (bar) bar.style.width = (data.pct_active || 0) + '%';
        if (avg) avg.textContent = '$' + Math.round(data.avg_price || 0);
      }

      // (اختياري) لو الشبكة فاضية، أظهر رسالة
      const grid = document.querySelector('.svc-grid');
      if (grid && grid.children.length === 0) {
        const p = document.createElement('p');
        p.style.gridColumn = '1/-1';
        p.style.color = '#6b7280';
        p.textContent = 'No services found.';
        grid.appendChild(p);
      }

    } catch (err) {
      console.error(err);
      alert('Network error: ' + err.message);
    }
  });
});
</script>

<script>
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
const closeSidebar    = document.getElementById('closeSidebar');
const sidebar         = document.getElementById('sidebar');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');

function openNav(){
  document.body.classList.add('sidebar-open');
  sidebar.classList.add('open');
  if (window.matchMedia('(max-width: 899px)').matches){
    sidebarBackdrop?.classList.add('show');
  }
}
function closeNav(){
  document.body.classList.remove('sidebar-open');
  sidebar.classList.remove('open');
  sidebarBackdrop?.classList.remove('show');
}

openSidebar?.addEventListener('click', openNav);
closeSidebar?.addEventListener('click', closeNav);
sidebarBackdrop?.addEventListener('click', closeNav);
document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeNav(); });

document.querySelectorAll('.sidebar a').forEach(link => {
  link.addEventListener('click', (e) => {
    document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
    e.currentTarget.classList.add('active');
  });
});
</script>
</body>
</html>