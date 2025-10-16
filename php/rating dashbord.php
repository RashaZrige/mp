
<?php
/*
 * Fixora – Reviews & Ratings (Provider Dashboard)
 * rating dashbord.php
 */
session_start();
$BASE = '/mp';

/* ======= حماية الجلسة ======= */
if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

/* ======= الاتصال بقاعدة البيانات ======= */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ======= أدوات مساعدة ======= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** يبني رابط الصورة من مسار DB (نسبي أو كامل) مع base للمشروع */
function img_url($dbPath, $base = '/mp') {
  if (!$dbPath) return '';
  if (preg_match('~^https?://~i', $dbPath)) return $dbPath; // رابط جاهز
  $dbPath = str_replace('\\','/',$dbPath);
  $dbPath = ltrim($dbPath, '/');
  $dir  = dirname($dbPath);
  $file = basename($dbPath);
  return rtrim($base, '/') . '/' . ($dir === '.' ? '' : $dir . '/') . rawurlencode($file);
}

/** يرسم أيقونات النجوم */
function stars_html($rating){
  $rating = (int)$rating;
  $html = '';
  for ($i=1; $i<=5; $i++){
    $html .= ($i <= $rating) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
  }
  return $html;
}

/* ======= فلاتر GET ======= */
$q        = isset($_GET['q'])       ? trim($_GET['q'])       : '';   // Search Here
$dateStr  = isset($_GET['date'])    ? trim($_GET['date'])    : '';   // YYYY-MM-DD
$ratingF  = isset($_GET['rating'])  ? trim($_GET['rating'])  : '';   // 5 | 4+ | 3+ ...
$serviceF = isset($_GET['service']) ? trim($_GET['service']) : '';   // Plumbing | Cleaning | Electrical (title)

/* بناء WHERE ديناميكي مع بارامترات bind */
$where  = "1=1";
$types  = '';
$params = [];

/* بحث عام في عنوان الخدمة/اسم الزبون/التعليق */
if ($q !== '') {
  $where .= " AND (s.title LIKE ? OR u.full_name LIKE ? OR sr.comment LIKE ?)";
  $like = "%{$q}%";
  $types .= 'sss';
  $params[] = $like; $params[] = $like; $params[] = $like;
}

/* فلتر التاريخ (يوم واحد) */
if ($dateStr !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateStr)) {
  $where .= " AND DATE(sr.created_at) = ?";
  $types .= 's';
  $params[] = $dateStr;
}

/* فلتر التقييم */
if ($ratingF !== '') {
  if (preg_match('~^(\d)\+$~', $ratingF, $m)) {      // 4+ , 3+ ...
    $min = (int)$m[1];
    $where .= " AND sr.rating >= ?";
    $types .= 'i';
    $params[] = $min;
  } elseif (preg_match('~^\d$~', $ratingF)) {        // 5 , 4 , ...
    $eq = (int)$ratingF;
    $where .= " AND sr.rating = ?";
    $types .= 'i';
    $params[] = $eq;
  }
}

/* فلتر الخدمة (على عنوان الخدمة) */
if ($serviceF !== '') {
  $where .= " AND s.title = ?";
  $types  .= 's';
  $params[] = $serviceF;
}

/* ======= Summary (حسب الفلاتر) ======= */
$avg   = 0.0;
$total = 0;

$sqlSum = "
  SELECT COALESCE(AVG(sr.rating),0) AS avg_rating,
         COUNT(*) AS total_reviews
  FROM service_reviews sr
  JOIN services s ON s.id = sr.service_id
  JOIN users   u  ON u.id = sr.customer_id
  WHERE $where
";
$stmtSum = $conn->prepare($sqlSum);
if (!$stmtSum) { die("SQL prepare error (summary): ".$conn->error); }
if ($types !== '') $stmtSum->bind_param($types, ...$params);
$stmtSum->execute();
$resSum = $stmtSum->get_result();
if ($resSum && $row = $resSum->fetch_assoc()) {
  $avg   = (float)$row['avg_rating'];
  $total = (int)$row['total_reviews'];
}
$stmtSum->close();

/* ======= قائمة الريفيوز (حسب الفلاتر) ======= */
$reviews = [];
$sqlList = "
  SELECT
    sr.id,
    sr.rating,
    COALESCE(sr.comment,'') AS comment,
    sr.created_at,
    s.title        AS service_title,
    u.full_name,
    u.avatar       AS user_avatar
  FROM service_reviews sr
  JOIN services s ON s.id = sr.service_id


JOIN users    u ON u.id = sr.customer_id
  WHERE $where
  ORDER BY sr.created_at DESC, sr.id DESC
  LIMIT 200
";
$stmtList = $conn->prepare($sqlList);
if (!$stmtList) { die("SQL prepare error (list): ".$conn->error); }
if ($types !== '') $stmtList->bind_param($types, ...$params);
$stmtList->execute();
$resList = $stmtList->get_result();
while ($r = $resList->fetch_assoc()) { $reviews[] = $r; }
$stmtList->close();

/* ======= جلب بيانات المزوّد (اسم + صورة من provider_profiles) ======= */
$providerName  = "Unknown User";
$providerPhoto = ""; // سنحاول جلبها من avatar_path

$sqlProv = "
  SELECT u.full_name, pp.avatar_path
  FROM users u
  LEFT JOIN provider_profiles pp ON pp.user_id = u.id
  WHERE u.id = ?
  LIMIT 1
";
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
if ($providerPhoto === '') $providerPhoto = $BASE . '/image/no-avatar.png'; // fallback محلي

/* انتهى التعامل مع DB */
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Account Settings</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/rating_dashbord.css">
</head>
<body>

<!-- ===== Sidebar ===== -->
<div class="sidebar" id="sidebar">
  <button class="sidebar-close" id="closeSidebar" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
  <h3>Menu</h3>
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

<!-- ===== Main ===== -->
<div class="main-content">
  <!-- ===== Topbar ===== -->
  <section class="topbar">
    <div class="tb-inner">
      <div class="tb-left">
        <button class="icon-btn" aria-label="Settings" id="openSidebar"><i class="fa-solid fa-gear"></i></button>
        <div class="brand"><img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo"></div>
      </div>

      <div class="tb-center">
        <!-- نموذج البحث (GET) ويحافظ على الفلاتر -->
        <form method="get" id="searchForm" style="width:100%">
          <input type="hidden" name="date"    value="<?= h($dateStr) ?>">
          <input type="hidden" name="rating"  value="<?= h($ratingF) ?>">
          <input type="hidden" name="service" value="<?= h($serviceF) ?>">
          <div class="search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search Here">
          </div>
        </form>
      </div>

      <div class="tb-right">
        <button class="notif-pill" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
        <div class="profile-menu">
          <button class="profile-trigger" aria-expanded="false">

<!-- صورة المزوّد أيضًا هنا -->
            <img class="avatar" src="<?= h($providerPhoto) ?>" alt="Profile"
                 onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
            <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <div class="menu-card" hidden>
            <a class="menu-item" href="identification.php"><i class="fa-solid fa-gear"></i><span>Account Settings</span></a>
            <hr class="divider">
            <a class="menu-item danger" href="<?= $BASE ?>/php/logout.php">
              <span>Log Out</span><i class="fa-solid fa-right-from-bracket"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Reviews & Ratings ===== -->
  <section class="rv-section">
    <div class="rv-container">
      <header class="rv-head">
        <h2 class="rv-title">Reviews &amp; Ratings</h2>
        <p class="rv-sub">Manage And Analyze Client Feedback For Your Services</p>
      </header>

      <!-- Summary -->
      <h3 class="rv-subtitle">Summary</h3>
      <div class="rv-stats">
        <article class="rv-card">
          <div class="rv-bubble blue"><i class="fa-regular fa-star"></i></div>
          <div class="rv-meta">
            <span class="rv-label">Average Rating</span>
            <span class="rv-value"><?= number_format($avg, 1) ?></span>
          </div>
        </article>
        <article class="rv-card">
          <div class="rv-bubble green"><i class="fa-regular fa-comments"></i></div>
          <div class="rv-meta">
            <span class="rv-label">Total Review</span>
            <span class="rv-value"><?= (int)$total ?></span>
          </div>
        </article>
      </div>

      <!-- Filters -->
      <form method="get" class="rv-filters" id="filtersForm">
        <input type="hidden" name="q" value="<?= h($q) ?>">

        <!-- Date -->
        <label class="rv-filter js-date">
          <i class="fa-regular fa-calendar"></i>
          <span class="rv-date-value"><?= $dateStr ? h($dateStr) : 'Date' ?></span>
          <input type="date" class="rv-date-click" name="date" value="<?= h($dateStr) ?>" aria-label="Pick a date">
        </label>

        <!-- Rating -->
        <div class="rv-filter js-dd" data-dd="rating">
          <i class="fa-regular fa-star"></i>
          <span class="rv-filter-text rv-dd-value"><?= $ratingF ? h($ratingF.' ★') : 'Filter By Rating' ?></span>
          <i class="fa-solid fa-chevron-down chevron" aria-hidden="true"></i>
          <div class="rv-dd-menu">
            <button type="button" data-value="5">5 ★</button>
            <button type="button" data-value="4+">4+ ★</button>
            <button type="button" data-value="3+">3+ ★</button>
            <button type="button" data-value="2+">2+ ★</button>
            <button type="button" data-value="1+">1+ ★</button>
            <button type="button" data-value="">Clear</button>
          </div>
          <input type="hidden" name="rating" id="rvRating" value="<?= h($ratingF) ?>">
        </div>

        <!-- Service -->
        <div class="rv-filter js-dd" data-dd="service">
          <i class="fa-solid fa-screwdriver-wrench"></i>
          <span class="rv-filter-text rv-dd-value"><?= $serviceF ? h($serviceF) : 'Filter By Services' ?></span>
          <i class="fa-solid fa-chevron-down chevron" aria-hidden="true"></i>
          <div class="rv-dd-menu">
            <button type="button" data-value="Plumbing">Plumbing</button>
            <button type="button" data-value="Cleaning">Cleaning</button>
            <button type="button" data-value="Electrical">Electrical</button>
            <button type="button" data-value="">All</button>
          </div>
          <input type="hidden" name="service" id="rvService" value="<?= h($serviceF) ?>">
        </div>
      </form>
    </div>
  </section>


<!-- ===== All Reviews ===== -->
  <section class="rv-list">
    <h3 class="rv-block-title">All Reviews</h3>

    <?php if (empty($reviews)): ?>
      <div style="padding:18px;color:#64748b;">No reviews found for current filters.</div>
    <?php else: ?>
      <?php foreach ($reviews as $rv):
        $custName   = $rv['full_name'] ?: 'Unknown';
        $svcTitle   = $rv['service_title'] ?: '—';
        $date       = substr($rv['created_at'],0,10);
        $score      = (int)$rv['rating'];
        $comment    = $rv['comment'];
        // صورة الزبون من users.avatar
        $custPhoto  = !empty($rv['user_avatar']) ? img_url($rv['user_avatar'], $BASE) : '';
        if ($custPhoto === '') $custPhoto = $BASE . '/image/no-avatar.png';
      ?>
      <article class="rv-item">
        <img class="rv-avatar" src="<?= h($custPhoto) ?>" alt="<?= h($custName) ?>"
             onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
        <div class="rv-body">
          <div class="rv-row">
            <div class="rv-left">
              <div class="rv-name"><?= h($custName) ?></div>
              <div class="rv-meta">
                <span class="rv-service"><?= h($svcTitle) ?></span>
                <span class="rv-date">Reviewed on: <?= h($date) ?></span>
              </div>
            </div>
            <div class="rv-right">
              <div class="rv-stars" aria-label="Rating <?= $score ?> out of 5">
                <?= stars_html($score) ?>
                <span class="rv-score"><?= number_format($score, 1) ?></span>
              </div>
            </div>
          </div>
          <?php if ($comment !== ''): ?>
            <p class="rv-text"><?= nl2br(h($comment)) ?></p>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<!-- ===== JS (السلوك فقط) ===== -->
<script>
  // فتح/غلق السايدبار
  const openSidebar     = document.getElementById('openSidebar');
  const closeSidebar    = document.getElementById('closeSidebar');
  const sidebar         = document.getElementById('sidebar');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');

  function openNav(){ document.body.classList.add('sidebar-open'); sidebar.classList.add('open'); if (window.matchMedia('(max-width: 899px)').matches){ sidebarBackdrop?.classList.add('show'); } }
  function closeNav(){ document.body.classList.remove('sidebar-open'); sidebar.classList.remove('open'); sidebarBackdrop?.classList.remove('show'); }
  openSidebar?.addEventListener('click', openNav);
  closeSidebar?.addEventListener('click', closeNav);
  sidebarBackdrop?.addEventListener('click', closeNav);
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeNav(); });

  // دروبداون الفلاتر + إرسال الفورم
  (function(){
    const form = document.getElementById('filtersForm');

    document.querySelectorAll('.rv-filter.js-dd').forEach(dd=>{
      const valueEl = dd.querySelector('.rv-dd-value');
      const hidden  = dd.querySelector('input[type=hidden]');
      const menu    = dd.querySelector('.rv-dd-menu');
      const chev    = dd.querySelector('.chevron');

      function closeAll(){ document.querySelectorAll('.rv-filter.js-dd').forEach(x=>x.classList.remove('open')); }
      chev.addEventListener('click', (e)=>{ e.stopPropagation(); closeAll(); dd.classList.toggle('open'); });

      menu.querySelectorAll('button').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const v = btn.dataset.value || '';
          hidden.value = v;
          valueEl.textContent = v ? btn.textContent.trim()
                                  : (dd.dataset.dd==='rating' ? 'Filter By Rating' : 'Filter By Services');
          dd.classList.remove('open');
          form.submit();
        });
      });

      document.addEventListener('click', closeAll);
    });


// التاريخ: تغيّر => إرسال
    const dateInput = document.querySelector('.rv-date-click');
    const dateLabel = document.querySelector('.rv-date-value');
    if (dateInput){
      dateInput.addEventListener('change', ()=>{
        dateLabel.textContent = dateInput.value || 'Date';
        form.submit();
      });
    }
  })();
</script>
</body>
</html>



<script>
document.addEventListener('DOMContentLoaded', () => {
  const filters = document.querySelectorAll('.rv-filter.js-dd');
  if (!filters.length) return;

  const closeAll = () => {
    document.querySelectorAll('.rv-filter.js-dd.open').forEach(el => el.classList.remove('open'));
  };

  filters.forEach(f => {
    const chevron = f.querySelector('.chevron');
    const menu    = f.querySelector('.rv-dd-menu');
    const valueEl = f.querySelector('.rv-dd-value');
    const hidden  = f.querySelector('input[type="hidden"]');

    // لو أي عنصر ناقص نطلع بدون ما نكسر الباقي
    if (!chevron  !menu  !valueEl || !hidden) return;

    // افتح/اغلق القائمة عند الضغط على السهم فقط (بدون أي تأثير على CSS)
    chevron.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const wasOpen = f.classList.contains('open');
      closeAll();
      if (!wasOpen) f.classList.add('open');
    });

    // لا تخلي كليك داخل المنيو يقفلها بالغلط
    menu.addEventListener('click', (e) => e.stopPropagation());

    // اختيارات القائمة
    menu.querySelectorAll('button').forEach(btn => {
      // لو النوع مش محدد، عيّنه button عشان ما يبعث الفورم
      if (!btn.getAttribute('type')) btn.setAttribute('type', 'button');

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const v = btn.dataset.value ?? '';
        hidden.value = v;

        // حدّث النص الظاهر
        if (v) {
          valueEl.textContent = btn.textContent.trim();
        } else {
          valueEl.textContent = (f.dataset.dd === 'rating') ? 'Filter By Rating' : 'Filter By Services';
        }

        f.classList.remove('open');

        // ابعث الفورم (GET) عشان تطبّق الفلتر
        const form = f.closest('form');
        form && form.submit();
      });
    });
  });

  // اغلاق عند الضغط خارج أي قائمة
  document.addEventListener('click', closeAll);
  // اغلاق عند الضغط على Escape
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });
});
</script>
</script>



<!-- <script>
  (function(){
    const wrap = document.querySelector('.rv-filter.js-date');
    if(!wrap) return;
    const input = wrap.querySelector('.rv-date-click');
    const label = wrap.querySelector('.rv-date-value');

    function toNice(dStr){
      if(!dStr) return 'Date';
      const d = new Date(dStr + 'T00:00:00');
      return d.toLocaleDateString('en-GB', { 
        year:'numeric',
        month:'short',
        day:'2-digit',
        calendar: 'gregory' // يخليها ميلادي
      });
    }

    input.addEventListener('change', () => {
      label.textContent = toNice(input.value);
    });

    wrap.addEventListener('keydown', (e)=>{
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        input.showPicker?.(); 
        input.focus();
      }
    });
  })();
</script> -->



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
</body>
</html>