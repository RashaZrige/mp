<?php
/* content management.php */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }
$uid = (int)$_SESSION['user_id'];

/* DB */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* Helpers */
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


/* Pages list */
$pages = [];
$res = $conn->query("SELECT id, title, slug, status, visible, updated_at FROM cms_pages ORDER BY id ASC");
if ($res) { while ($row = $res->fetch_assoc()) { $pages[] = $row; } $res->free(); }

/* Current page (للمحرّر) */
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentPage = null;
if ($editId > 0 && ($st = $conn->prepare("SELECT id, slug, title, content, seo_title, seo_desc FROM cms_pages WHERE id=? LIMIT 1"))) {
  $st->bind_param("i", $editId);
  $st->execute();
  $rs = $st->get_result();
  $currentPage = $rs ? $rs->fetch_assoc() : null;
  $st->close();
}
$hasPage = is_array($currentPage);

/* Version History للصفحة المحددة */
$versions = [];
if (!empty($currentPage['id'])) {
  $sql = "SELECT 
            v.id AS ver_id,
            p.title AS page,
            COALESCE(u.full_name, CONCAT('User #', v.editor_user_id)) AS editor,
            v.created_at AS date,
            v.status
          FROM cms_page_versions v
          LEFT JOIN cms_pages p ON p.id = v.page_id
          LEFT JOIN users u     ON u.id = v.editor_user_id   -- <- هاي المهمة
          WHERE v.page_id = ?
          ORDER BY v.id DESC";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $currentPage['id']);
  $st->execute();
  $rs = $st->get_result();
  while($row = $rs->fetch_assoc()){ $versions[] = $row; }
  $st->close();
}


/* Close DB (بعد ما خلصنا كل الجلب) */
$conn->close();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Content Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <!-- ستايلك العام -->
  <link rel="stylesheet" href="../css/rating_dashbord.css">

  <style>
  /* ================== CMS Layout ================== */
  #cms.cms-wrap{
    --card-bg:#fff;
    --muted:#6b7280;
    --border:#e5e7eb;
    --primary:#2b79ff;
    --shadow:0 1px 0 rgba(16,24,40,.02);

    display:grid !important;
    grid-template-columns: minmax(0,1fr) minmax(300px,320px) !important;
    align-items:start !important;

    gap:20px;
    padding:24px 24px 8px;
    max-width:1100px;
    margin:0 auto;
  }
  #cms .cms-left, #cms .cms-right, #cms .card{ min-width:0 !important; }

  #cms .card{
    background:var(--card-bg);
    border:1px solid var(--border);
    border-radius:14px;
    padding:16px;
    box-shadow:var(--shadow);
    margin-bottom:20px;
  }
  #cms .card.sticky{ position:sticky; top:16px; }
  #cms .card-title{ font-size:16px; font-weight:700; margin:0 0 12px 0; }

  /* Tables */
  #cms .table-wrap{ overflow:auto; border-radius:12px; max-width:100% !important; }
  #cms .cms-table{ width:100% !important; border-collapse:collapse; table-layout:fixed; font-size:14px; background:#fff; }
  #cms .cms-table thead th{
    background:#f9fafb; color:#0b0f1a; font-weight:600; padding:12px 14px; border-bottom:1px solid var(--border);
    text-align:left; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  #cms .cms-table tbody td{
    padding:12px 14px; border-bottom:1px solid #f1f5f9;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  #cms .cms-table.compact thead th, #cms .cms-table.compact tbody td{ padding:10px 12px; }
  #cms .txt-right{ text-align:right; }

  /* Actions */
  #cms .actions{ text-align:right; white-space:nowrap; }
  #cms .icon-btn{
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:10px;
    border:1px solid var(--border); background:#fff; cursor:pointer;
    margin-left:6px; transition:.15s ease;
  }
  #cms .icon-btn:hover{ transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,.04); }
  #cms .action-eye.off i{ opacity:.4; }

  /* Badges */
  #cms .badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid transparent; }
  #cms .badge-publish{ color:#10b981; background:#ecfdf5; border-color:#a7f3d0; }
  #cms .badge-draft{ color:#f59e0b; background:#fffbeb; border-color:#fde68a; }

  /* Right form */
  #cms .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
  #cms .label{ font-size:13px; color:var(--muted); }
  #cms .input, #cms .textarea{
    width:100%; border:1px solid var(--border); border-radius:12px; padding:10px 12px; outline:0; background:#fff; font-size:14px; box-sizing:border-box;
  }
  #cms .textarea{ min-height:120px; resize:vertical; }

  #cms .btns{ display:flex; flex-direction:column; gap:8px; margin-top:8px; }
  #cms .btn{
    height:40px; border-radius:10px; border:1px solid var(--border); background:#fff; font-weight:600; cursor:pointer; font-size:14px;
  }
  #cms .btn-primary{ background:var(--primary); color:#fff; border-color:transparent; }
  #cms .btn-outline{ background:#eef2ff; color:#1f2937; border-color:#e0e7ff; }
  #cms .btn-disabled{ background:#eef2f7; color:#94a3b8; cursor:not-allowed; }

  /* Distances */
  #cms .cms-left > .card:first-of-type{ margin-bottom:24px; } /* manage page */

  /* Version History جدول طبيعي بلا أي تكديس */
  #cms .cms-left > .card:nth-of-type(2) .cms-table thead{ display:table-header-group !important; }
  #cms .cms-left > .card:nth-of-type(2) .cms-table tbody tr{ display:table-row !important; }
  #cms .cms-left > .card:nth-of-type(2) .cms-table tbody td{ display:table-cell !important; vertical-align:middle !important; }
  #cms .cms-left > .card:nth-of-type(2) .cms-table th.txt-right,
  #cms .cms-left > .card:nth-of-type(2) .cms-table td.txt-right{ text-align:left !important; }

  /* Responsive: عمود واحد تحت 900px */
  @media (max-width:900px){
    #cms.cms-wrap{ grid-template-columns:1fr !important; }
    #cms .card.sticky{ position:static !important; }
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

<!-- ===== Sidebar (من ستايلك العام) ===== -->
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

<!-- ===== CMS ===== -->
<section id="cms" class="cms-wrap">
  <!-- العمود الأيسر -->
  <div class="cms-left">
    <!-- Manage Page -->
    <div class="card">
      <h3 class="card-title">manage page</h3>
      <div class="table-wrap">
        <table class="cms-table">
          <thead>
            <tr>
              <th>page</th>
              <th class="txt-right">actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$pages): ?>
            <tr><td colspan="2">No pages found</td></tr>
          <?php else: foreach ($pages as $p): ?>
            <tr>
              <td><?= h($p['title']) ?></td>
              <td class="actions">
                <!-- Show/Hide -->
                <button class="icon-btn ghost action-eye <?= (int)$p['visible'] ? '' : 'off' ?>" title="show/hide" data-id="<?= (int)$p['id'] ?>">
                  <i class="fa-regular <?= (int)$p['visible'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                </button>
                <!-- Edit: نفس الصفحة + قفز للمحرّر -->
                <a class="icon-btn ghost" title="edit" href="?id=<?= (int)$p['id'] ?>#editor">
                  <i class="fa-regular fa-pen-to-square"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Version History -->
    <div class="card">
      <h3 class="card-title">Version History</h3>
      <div class="table-wrap">
        <table class="cms-table compact">
          <thead>
            <tr>
              <th>version id</th>
              <th>page</th>
              <th>edited by</th>
              <th>date</th>
              <th class="txt-right">status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($versions)): ?>
              <tr>
                <td colspan="5" style="text-align:center;color:#888;">Not found</td>
              </tr>
            <?php else: foreach($versions as $v):
              $badgeClass = ($v['status']==='published') ? 'badge-publish' : 'badge-draft';
            ?>
              <tr>
                <td>#<?= (int)$v['ver_id'] ?></td>
                <td><?= h($v['page'] ?? '-') ?></td>
                <td><?= h($v['editor'] ?? '—') ?></td>
                <td><?= date('Y-m-d', strtotime($v['date'])) ?></td>
                <td class="txt-right">
                  <span class="badge <?= $badgeClass ?>"><?= h($v['status']) ?></span>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- العمود الأيمن: Page Editor -->
  <aside class="cms-right">
    <div class="card sticky" id="editor">
      <h3 class="card-title">Page Editor</h3>

      <form method="post" action="cms_save.php">
        <input type="hidden" name="id"   value="<?= (int)($currentPage['id'] ?? 0) ?>">
        <input type="hidden" name="slug" value="<?= h($currentPage['slug'] ?? '') ?>">

        <label class="field">
          <span class="label">Page Title</span>
          <input type="text" class="input" name="title"
                 value="<?= h($currentPage['title'] ?? '') ?>"
                 placeholder="About Us" <?= $hasPage ? '' : 'disabled' ?>>
        </label>

        <label class="field">
          <span class="label">Content</span>
          <textarea class="textarea" name="content" rows="6"
                    placeholder="In Today’s Fast-Paced World, Comfort And Well ..."
                    <?= $hasPage ? '' : 'disabled' ?>><?= h($currentPage['content'] ?? '') ?></textarea>
        </label>

        <label class="field">
          <span class="label">Seo Meta Title</span>
          <input type="text" class="input" name="seo_title"
                 value="<?= h($currentPage['seo_title'] ?? '') ?>"
                 placeholder="Enter Seo Meta Title" <?= $hasPage ? '' : 'disabled' ?>>
        </label>

        <label class="field">
          <span class="label">Seo Meta Title Description</span>
          <input type="text" class="input" name="seo_desc"
                 value="<?= h($currentPage['seo_desc'] ?? '') ?>"
                 placeholder="Enter Seo Meta Title" <?= $hasPage ? '' : 'disabled' ?>>
        </label>

        <div class="btns">
          <!-- Save -->
          <button class="btn btn-primary" type="submit" <?= $hasPage ? '' : 'disabled' ?>>Save Changes</button>

          <!-- Preview (GET + target _blank) -->
          <button type="submit" class="btn btn-outline"
                  formaction="cms-preview.php" formmethod="get" formtarget="_blank"
                  <?= $hasPage ? '' : 'disabled' ?>>
            Preview Page
          </button>

          <!-- Publish -->
          <button type="submit" class="btn btn-primary"
                  formaction="cms-publish.php" formmethod="post"
                  <?= $hasPage ? '' : 'disabled' ?>>
            Publish Page
          </button>
        </div>
      </form>
    </div>
  </aside>
</section>

</body>
</html>




<script>
document.addEventListener('click', async (e)=>{
  const btn = e.target.closest('.action-eye');
  if(!btn) return;
  e.preventDefault();

  if (btn.dataset.busy === '1') return;
  btn.dataset.busy = '1';

  try {
    const r = await fetch('cms-toggle.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ id: btn.dataset.id })
    });
    const j = await r.json();
    if (j.ok) {
      btn.classList.toggle('off', j.visible === 0);
      const i = btn.querySelector('i');
      if (i){
        i.classList.remove('fa-eye','fa-eye-slash');
        i.classList.add(j.visible === 1 ? 'fa-eye' : 'fa-eye-slash');
      }
    } else {
      alert('Failed to toggle visibility');
    }
  } catch(err){ alert('Network error'); }
  finally { btn.dataset.busy = '0'; }
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