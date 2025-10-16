<?php
/* admin-provider-edit.php */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$uid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($uid <= 0) { header("Location: admin_providers.php?msg=".rawurlencode("Invalid provider id")); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* اجلب من users + provider_profiles */
$sql = "SELECT 
          u.id,
          u.full_name,
          u.email,
          u.phone,
          u.status,
          COALESCE(pp.national_id,'')      AS national_id,
          COALESCE(pp.avatar_path,'')      AS avatar_path,
          COALESCE(pp.is_available,0)      AS is_available
        FROM users u
        LEFT JOIN provider_profiles pp ON pp.user_id=u.id
        WHERE u.id=? AND u.role='provider' AND u.is_deleted=0
        LIMIT 1";
$st = $conn->prepare($sql);
$st->bind_param("i",$uid);
$st->execute();
$provider = $st->get_result()->fetch_assoc();
$st->close();

if(!$provider){ header("Location: admin_providers.php?msg=".rawurlencode("Provider not found")); exit; }

/* فلاش */
$flash_ok  = $_GET['ok']  ?? '';
$flash_err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Edit Provider</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css">
<style>
  .page{max-width:900px;margin:0 auto;padding:18px 24px 60px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
  .page-title{font:700 20px/1.2 "Inter",system-ui;margin:6px 0 16px}

  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
  @media (max-width:760px){.form-grid,.row-3{grid-template-columns:1fr}}

  .field{display:flex;flex-direction:column;gap:6px}
  .label{font-size:13px;color:#6b7280}
  .input,.select{height:44px;border:1px solid #e5e7eb;border-radius:10px;padding:0 12px;background:#fff;font-size:14px}
  .note{font-size:12px;color:#6b7280}

  .avatar-wrap{display:flex;flex-direction:column;gap:6px}
  .avatar-box{width:100%;height:180px;border:2px dashed #e5e7eb;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fafafa;cursor:pointer;overflow:hidden}
  .avatar-box img{max-width:100%;max-height:100%;object-fit:cover;display:block}

  .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:14px}
  .btn{height:42px;padding:0 16px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-weight:600;cursor:pointer}
  .btn-primary{background:#2b79ff;color:#fff;border-color:transparent}
  .btn-danger{background:#fff;color:#dc2626;border-color:#fecaca}

  .flash-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;margin-bottom:12px}
  .flash-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:12px;padding:10px 12px;margin-bottom:12px}
  
</style>
</head>
<body>

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
    <div class="tb-right"><button class="notif-pill"><i class="fa-solid fa-bell"></i></button></div>
  </div>
</section>

<div class="page">
  <h2 class="page-title">Edit provider</h2>

  <?php if($flash_ok): ?><div class="flash-ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="flash-err"><?= h($flash_err) ?></div><?php endif; ?>

  <div class="card">
    <form method="post" action="admin-provider-edit-save.php" id="editForm" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$provider['id'] ?>"/>

      <div class="form-grid">
        <label class="field">
          <span class="label">Full name *</span>
          <input class="input" type="text" name="full_name" required value="<?= h($provider['full_name']) ?>">
        </label>

        <label class="field">
          <span class="label">Email *</span>
          <input class="input" type="email" name="email" required value="<?= h($provider['email']) ?>">
        </label>
      </div>

      <div class="form-grid">
        <label class="field">
          <span class="label">Phone</span>
          <input class="input" type="text" name="phone" value="<?= h($provider['phone']) ?>">
        </label>

        <label class="field">
          <span class="label">National ID Number</span>
          <input class="input" type="text" name="national_id" value="<?= h($provider['national_id']) ?>">
        </label>
      </div>

      <div class="row-3">
        <label class="field">
          <span class="label">Status</span>
          <select class="select" name="status">
            <option value="active"   <?= $provider['status']==='active'?'selected':'' ?>>active</option>
            <option value="suspended"<?= $provider['status']==='suspended'?'selected':'' ?>>suspended</option>
          </select>
        </label>

        <label class="field" style="justify-content:flex-end">
          <span class="label">Availability</span>
          <div style="display:flex;align-items:center;gap:8px;height:44px">
            <input id="is_available" type="checkbox" name="is_available" value="1" <?= ((int)$provider['is_available']===1)?'checked':'' ?>>
            <label for="is_available" class="note" style="margin:0">Available now</label>
          </div>
        </label>

        <div></div>
      </div>

      <div class="form-grid">
        <div class="avatar-wrap">
          <span class="label">Avatar (optional)</span>
          <div class="avatar-box" onclick="document.getElementById('avatarInput').click()">
            <img id="avatarPrev" src="<?= $provider['avatar_path'] ? $BASE.'/'.h($provider['avatar_path']) : $BASE.'/image/no-avatar.png' ?>" alt="Preview">
          </div>
          <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none">
          <span class="note">JPG / PNG / WEBP</span>
        </div>
        <div></div>
      </div>

      <div class="actions">
        <button class="btn btn-danger" type="button" onclick="window.location='admin-providers.php'">Cancel</button>
        <button class="btn btn-primary" type="submit">Save changes</button>
      </div>
    </form>
  </div>
</div>

<script>
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