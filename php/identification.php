<?php
session_start();
$BASE = "/mp";
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }
$uid = (int)$_SESSION['user_id'];

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ==== حمّل ملف المزوّد من provider_profiles ==== */
$prof = [
  'full_name'         => '',
  'phone'             => '',
  'email'             => '',
  'address'           => '',
  'age'               => null,
  'gender'            => '',
  'avatar_path'       => '',
  'national_id'       => '',
  'years_experience'  => null,
];

$st = $conn->prepare("SELECT full_name,phone,email,address,age,gender,avatar_path,national_id,years_experience
                      FROM provider_profiles WHERE user_id=? LIMIT 1");
$st->bind_param("i",$uid);
$st->execute();
$st->bind_result(
  $prof['full_name'],$prof['phone'],$prof['email'],$prof['address'],
  $prof['age'],$prof['gender'],$prof['avatar_path'],$prof['national_id'],$prof['years_experience']
);
$hasProf = $st->fetch();
$st->close();

/* لو ما في سجل، أنشئه عند أول حفظ */
$ensureProf = function() use ($conn,$uid){
  $q=$conn->prepare("SELECT 1 FROM provider_profiles WHERE user_id=?");
  $q->bind_param("i",$uid); $q->execute();
  $exists = $q->get_result()->num_rows>0; $q->close();
  if(!$exists){
    $ins=$conn->prepare("INSERT INTO provider_profiles (user_id) VALUES (?)");
    $ins->bind_param("i",$uid); $ins->execute(); $ins->close();
  }
};

/* تحميل هاش كلمة المرور من users فقط لأجل تبويب Security */
$user = ['password_hash'=>''];
$s = $conn->prepare("SELECT password_hash FROM users WHERE id=?");
$s->bind_param("i",$uid); $s->execute(); $s->bind_result($user['password_hash']); $s->fetch(); $s->close();

$flash = ['type'=>null,'msg'=>null];

/* ==== التعامل مع الحفظ حسب التبويب ==== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $section = $_POST['section'] ?? '';

  if ($section==='basic') {
    $ensureProf();
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    $sql = "UPDATE provider_profiles SET full_name=?, phone=?, email=?, address=? WHERE user_id=?";
    $up  = $conn->prepare($sql);
    $up->bind_param("ssssi",$full_name,$phone,$email,$address,$uid);

    // إن تم اختيار صورة ضمن هذا التبويب، نحفظها الآن فقط عند الضغط على Save
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error']===UPLOAD_ERR_OK){
      $ext  = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
        $safe = "u{$uid}_".time().".".$ext;
        $dirFs = $_SERVER['DOCUMENT_ROOT']."/mp/uploads/avatars";
        @mkdir($dirFs,0777,true);
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dirFs.'/'.$safe)){
          $up->close();
          $pathRel = "uploads/avatars/".$safe;
          $sql = "UPDATE provider_profiles SET full_name=?, phone=?, email=?, address=?, avatar_path=? WHERE user_id=?";
          $up  = $conn->prepare($sql);
          $up->bind_param("sssssi",$full_name,$phone,$email,$address,$pathRel,$uid);
        }
      }
    }

    $ok = $up->execute(); $up->close();
    if ($ok){
      $prof['full_name']=$full_name; $prof['phone']=$phone; $prof['email']=$email; $prof['address']=$address;
      if (isset($pathRel)) $prof['avatar_path']=$pathRel;
      $flash=['type'=>'success','msg'=>'Profile updated successfully.'];
    } else {
      $flash=['type'=>'error','msg'=>'Failed to update profile.'];
    }

  } elseif ($section==='identification') {
    $ensureProf();
    $national_id = trim($_POST['national_id'] ?? '');
    $up = $conn->prepare("UPDATE provider_profiles SET national_id=? WHERE user_id=?");
    $up->bind_param("si",$national_id,$uid);
    $ok=$up->execute(); $up->close();
    if ($ok){ $prof['national_id']=$national_id; }
    $flash = $ok ? ['type'=>'success','msg'=>'ID updated.'] : ['type'=>'error','msg'=>'Could not update ID.'];

  } elseif ($section==='security') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!$current   || !$new    || !$confirm){
      $flash = ['type'=>'error','msg'=>'Please fill all password fields.'];
    } elseif ($new!==$confirm){
      $flash = ['type'=>'error','msg'=>'New passwords do not match.'];
    } elseif (!password_verify($current,$user['password_hash'])){
      $flash = ['type'=>'error','msg'=>'Current password is incorrect.'];
    } else {
      $hash = password_hash($new,PASSWORD_DEFAULT);
      $up=$conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
      $up->bind_param("si",$hash,$uid);
      $ok=$up->execute(); $up->close();
      $flash = $ok?['type'=>'success','msg'=>'Password changed.']:['type'=>'error','msg'=>'Could not change password.'];
      if ($ok) $user['password_hash']=$hash;
    }
  }
}

/* URL الصورة للعرض */
$avatarUrl = '';
if (!empty($prof['avatar_path'])) {
  $avatarUrl = preg_match('~^https?://~i',$prof['avatar_path']) ? $prof['avatar_path'] : $BASE.'/'.ltrim($prof['avatar_path'],'/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fixora – Account Settings</title>

  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/identification.css">
  <style>
    /* صورة التوب بار */
    .profile-trigger .avatar{
      width: 36px; height: 36px; border-radius: 50%;
      object-fit: cover; display:block; border:1px solid #e5e7eb;
    }
    .profile-trigger{ display:flex; align-items:center; gap:8px; }
    .profile-trigger .chev{ opacity:.8; }

    /* عرض صورة البروفايل في Basic info */
    .bi-avatar-view { text-align:center; margin-bottom: 12px; }
    .bi-avatar-circle {
      width: 112px; height: 112px; border-radius: 50%;
      overflow: hidden; margin: 0 auto 8px; background:#f3f4f6;
      display:grid; place-items:center; border:1px solid #e5e7eb;
    }
    .bi-avatar-circle img { width:100%; height:100%; object-fit:cover; display:block; }
    .bi-avatar-hint { color:#6b7280; font-size:13px; }

    .danger-card { padding:16px; border-radius:12px; margin-top:16px; }
    .delete-btn { border:none; background:transparent; color:#d9534f; font-weight:700; font-size:15px; display:flex; align-items:center; gap:8px; cursor:pointer; }
    .delete-btn i { font-size:18px; }

    .modal-backdrop[hidden]{ display:none; }
    .modal-backdrop{ position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.35); display:grid; place-items:center; padding:20px; }
    .modal{ width:min(520px,95vw); background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.25); padding:28px 24px; text-align:center; }
    .modal-icon{ width:86px; height:86px; margin:10px auto 14px; border-radius:50%; background:#fdecec; display:grid; place-items:center; }
    .modal-icon i{ color:#ef4444; font-size:36px; }
    .modal-text{ margin:8px 0 20px; font-weight:700; color:#111827; }
    .modal-actions{ display:flex; gap:12px; justify-content:center; }

    /* السايدبار: مخفي افتراضياً ويحضر من اليسار */
    .sidebar{
      position: fixed; top:0; left:0;
      width:260px; height:100%;
      background:#fff;
      border-right:1px solid #e5e7eb;
      box-shadow:4px 0 15px rgba(0,0,0,.08);
      padding:20px;
      transform:translateX(-100%);
      transition:transform .3s ease;
      z-index:1000;
      display:flex; flex-direction:column;
    }
    .sidebar.open{ transform:translateX(0); }

    /* يدزّ المحتوى يمين بالديسكتوب */
    .main-content{ transition:margin-left .3s ease; }
    @media (min-width:900px){
      body.sidebar-open .main-content{ margin-left:260px; }
      .sidebar-backdrop{ display:none !important; }
    }

    /* بالموبايل يطلع Overlay مع خلفية */
    @media (max-width:899px){
      .sidebar{ z-index:2000; }
      .sidebar-backdrop{
        position:fixed; inset:0;
        background:rgba(0,0,0,.45);
        display:none; z-index:1500;
      }
      .sidebar-backdrop.show{ display:block; }
    }

    /* تنسيقاتك الداخلية (محافظين عليها) */
    .sidebar h3{ margin:0 0 12px; font:800 18px/1 ui-sans-serif; color:#0f172a; }
    .sidebar ul{ list-style:none; margin:8px 0 0; padding:0; }
    .sidebar li{ margin-bottom:16px; }
    .sidebar a{
      display:flex; align-items:center; gap:12px;
      text-decoration:none; font-size:15px; font-weight:600; color:#111827;
      padding:8px 12px; border-radius:10px; transition:background .2s;
    }
    .sidebar a:hover{ background:#f3f4f6; color:#1e73ff; }
    .sidebar a.active{ background:#1e73ff; color:#fff; }

    .sidebar-profile{
      margin-top:auto; margin-bottom:20px;
      display:flex; align-items:center; gap:12px;
      padding:12px 10px; border-radius:10px; cursor:pointer;
      transition:background .2s;
    }
    .sidebar-profile:hover{ background:#f3f4f6; }
    .sidebar-profile img{ width:44px; height:44px; border-radius:50%; object-fit:cover; }
    .sidebar-profile .profile-info{ display:flex; flex-direction:column; }
    .sidebar-profile .name{ font-size:15px; font-weight:600; color:#111827; }
    .sidebar-profile .role{ font-size:13px; color:#6b7280; }

    .sidebar-close{
      background: transparent;
      border: none;
      font-size: 20px;
      color: #374151;
      cursor: pointer;
      margin-left: auto;    /* يخليه على اليمين */
      margin-bottom: 10px;
      display: block;
    }
    .sidebar-close:hover{
      color: #1e73ff;
    }
  </style>
</head>
<body>

<!-- السايدبار يبقى كما هو -->
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
    <img src="<?= htmlspecialchars($avatarUrl ?: $BASE.'/image/no-avatar.png') ?>" alt="User"
         onerror="this.src='<?= $BASE ?>/image/no-avatar.png'">
    <div class="profile-info">
      <span class="name"><?= htmlspecialchars($prof['full_name'] ?: 'User') ?></span>
      <span class="role">My Account</span>
    </div>
  </div>
</div>

<!-- باقي الكود يبقى كما هو تماماً -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-content">
  <!-- ===== Topbar ===== -->
  <section class="topbar">
    <div class="tb-inner">
      <div class="tb-left">
        <!-- أضفنا فقط id="openSidebar" عشان يشتغل السكربت -->
        <button class="icon-btn" id="openSidebar" aria-label="Settings">
          <i class="fa-solid fa-gear"></i>
        </button>
        <div class="brand">
          <img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo">
        </div>
      </div>

      <div class="tb-center">
        <div class="search-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search Here">
        </div>
      </div>

      <div class="tb-right">
        <button class="notif-pill" aria-label="Notifications">
          <i class="fa-solid fa-bell"></i>
        </button>

        <div class="profile-menu">
          <!-- ملاحظة: الضغط هنا يفتح القائمة فقط (لا يفتح اختيار صورة) -->
          <button class="profile-trigger" aria-expanded="false">
            <img class="avatar"
                 src="<?= htmlspecialchars($avatarUrl ?: $BASE.'/image/no-avatar.png') ?>"
                 alt="Profile">
            <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <div class="menu-card" hidden>
            <a class="menu-item" href="Account settings.php"><span>Account Settings</span></a>
            <hr class="divider">
            <a class="menu-item danger" href="<?= $BASE ?>/php/logout.php"><span>Log Out</span></a>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ===== Account content ===== -->
  <section class="account-wrap">
    <h2 class="page-title">Account Settings</h2>

    <!-- Tabs -->
    <nav class="tabs" id="tabs">
      <a href="#basic"          class="tab"        data-tab="basic">Basic info</a>
      <a href="#identification" class="tab active" data-tab="identification">identification</a>
      <a href="#security"       class="tab"        data-tab="security">Security</a>
      <a href="#subscription"   class="tab"        data-tab="subscription">Subscription</a>
    </nav>

    <!-- Panels -->
    <div class="tab-panels">

      <!-- Basic info (داخل فورم مستقل لحفظ الصورة والحقول عند الضغط على Save) -->
      <section class="tab-panel" id="panel-basic">
        <form id="basicForm" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="section" value="basic">

          <div class="section-head">
            <h3 class="section-title">Basic info</h3>
            <p class="section-desc">Update Your Personal Information</p>
          </div>

          <div class="card basic-card">
            <!-- عرض صورة المزوّد + زر اختيار صورة (معاينة فقط حتى الحفظ) -->
            <div class="bi-avatar-view">
              <div class="bi-avatar-circle">
                <img id="biAvatarImgStatic" src="<?= htmlspecialchars($avatarUrl ?: $BASE.'/image/no-avatar.png') ?>" alt="Provider photo"/>
              </div>
              <p class="bi-avatar-hint">Your Profile Photo</p>
              <input type="file" id="avatarInput" name="avatar" accept="image/*" hidden>
              <button type="button" class="btn btn-primary sm" id="btnChangePhoto">Change Photo</button>
            </div>

            <!-- الحقول -->
            <div class="grid-2">
              <div class="form-group">
                <label class="label" for="fullName">Full name</label>
                <div class="input-icon">
                  <i class="fa-regular fa-user"></i>
                  <input id="fullName" name="full_name" type="text"
                        value="<?= htmlspecialchars($prof['full_name'] ?? '') ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="label" for="phone">Phone Number</label>
                <div class="input-icon">
                  <i class="fa-solid fa-phone"></i>
                  <input id="phone" name="phone" type="tel"
                        value="<?= htmlspecialchars($prof['phone'] ?? '') ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="label" for="email">Email</label>
                <div class="input-icon">
                  <i class="fa-regular fa-envelope"></i>
                  <input id="email" name="email" type="email"
                        value="<?= htmlspecialchars($prof['email'] ?? '') ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="label" for="address">Address</label>
                <div class="input-icon">
                  <i class="fa-solid fa-location-dot"></i>
                  <input id="address" name="address" type="text"
                        value="<?= htmlspecialchars($prof['address'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- الأزرار -->
          <div class="actions">
            <button class="btn btn-primary" type="submit">Save Change</button>
            <button class="btn btn-outline-danger" type="reset" id="btnCancelBasic">Cancel</button>
          </div>
        </form>
      </section>

      <!-- identification -->
      <section class="tab-panel active" id="panel-identification">
        <form id="idForm" method="POST">
          <input type="hidden" name="section" value="identification">
          <div class="section-head">
            <h3 class="section-title">identification</h3>
            <p class="section-desc">Update Your Personal Information</p>
          </div>

          <div class="card">
            <div class="form-group">
              <label for="nid" class="label">National ID Number</label>
              <div class="input-icon">
                <i class="fa-regular fa-id-card"></i>
                <input id="nid" name="national_id"
                      value="<?= htmlspecialchars($prof['national_id'] ?? '') ?>"
                      placeholder="ex: 4023480030" autocomplete="off">
              </div>
            </div>
          </div>

          <div class="actions">
            <button class="btn btn-primary" type="submit">Save Change</button>
            <button class="btn btn-outline-danger" type="reset">Cancel</button>
          </div>
        </form>
      </section>

      <!-- Security -->
      <section class="tab-panel" id="panel-security">
        <div class="section-head">
          <h3 class="section-title">Security</h3>
          <p class="section-desc">Update Your Personal Information</p>
        </div>

        <div class="card">
          <div class="form-group">
            <label for="password" class="label">Password</label>
            <div class="input-icon has-trailing">
              <i class="fa-solid fa-lock"></i>
              <input id="password" type="password" placeholder="" />
              <button type="button" class="trailing-btn" id="togglePass" aria-label="Show/Hide password">
                <i class="fa-regular fa-eye-slash" id="eyeIcon"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="card danger-card">
          <button class="delete-btn" id="btnDeleteAccount">
            <i class="fa-solid fa-trash-can"></i>
            Delete account
          </button>
        </div>

        <div class="actions">
          <button class="btn btn-primary">Save Change</button>
          <button class="btn btn-outline-danger">Cancel</button>
        </div>
      </section>

      <!-- Subscription -->
      <section class="tab-panel" id="panel-subscription">
        <!-- لاحقًا -->
      </section>
    </div>
  </section>

  <!-- Modal: Delete account -->
  <div class="modal-backdrop" id="deleteModal" hidden>
    <div class="modal">
      <div class="modal-icon">
        <i class="fa-solid fa-trash-can"></i>
      </div>
      <p class="modal-text">
        Once You Delete Your Account, This Action Cannot Be Undone
      </p>
      <div class="modal-actions">
        <button class="btn btn-primary" id="confirmDelete">Delete</button>
        <button class="btn btn-outline-danger" id="cancelDelete">Cancel</button>
      </div>
    </div>
  </div>

  <!-- ===== Tabs JS ===== -->
  <script>
    const tabs = document.querySelectorAll('.tabs .tab');
    const panels = {
      basic:          document.getElementById('panel-basic'),
      identification: document.getElementById('panel-identification'),
      security:       document.getElementById('panel-security'),
      subscription:   document.getElementById('panel-subscription'),
    };
    function activateTab(name){
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
      Object.entries(panels).forEach(([key, el]) => el.classList.toggle('active', key === name));
      history.replaceState(null, '', '#' + name);
    }
    tabs.forEach(t => {
      t.addEventListener('click', e => { e.preventDefault(); activateTab(t.dataset.tab); });
    });
    const initial = location.hash ? location.hash.slice(1)
                  : document.querySelector('.tabs .tab.active')?.dataset.tab || 'identification';
    activateTab(initial);
  </script>

  <!-- Show/Hide password -->
  <script>
    (function(){
      const input = document.getElementById('password');
      const btn   = document.getElementById('togglePass');
      const eye   = document.getElementById('eyeIcon');
      if (input && btn && eye){
        btn.addEventListener('click', () => {
          const isPwd = input.type === 'password';
          input.type = isPwd ? 'text' : 'password';
          eye.classList.toggle('fa-eye', isPwd);
          eye.classList.toggle('fa-eye-slash', !isPwd);
        });
      }
    })();
  </script>

  <!-- قائمة البروفايل (Avatar click = يفتح القائمة فقط) -->
  <script>
    (function(){
      const pm = document.querySelector('.profile-menu');
      if (!pm) return;
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

  <!-- اختيار صورة داخل Basic info: معاينة فقط، والحفظ عند الضغط على Save -->
  <script>
    (function(){
      const changeBtn = document.getElementById('btnChangePhoto');
      const fileInput = document.getElementById('avatarInput');
      const imgPrev   = document.getElementById('biAvatarImgStatic');

      changeBtn?.addEventListener('click', ()=> fileInput?.click());
      fileInput?.addEventListener('change', ()=>{
        const file = fileInput.files?.[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        imgPrev.src = url; // معاينة فقط
        imgPrev.onload = ()=> URL.revokeObjectURL(url);
      });
    })();
  </script>

  <!-- حذف الحساب -->
  <script>
    const btnDeleteAccount = document.getElementById('btnDeleteAccount');
    const deleteModal      = document.getElementById('deleteModal');
    const confirmDelete    = document.getElementById('confirmDelete');
    const cancelDelete     = document.getElementById('cancelDelete');

    btnDeleteAccount?.addEventListener('click', () => { deleteModal.hidden = false; });
    cancelDelete?.addEventListener('click', () => { deleteModal.hidden = true; });
    deleteModal?.addEventListener('click', (e) => { if (e.target === deleteModal) deleteModal.hidden = true; });
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') deleteModal.hidden = true; });

    confirmDelete?.addEventListener('click', async () => {
      try {
        const res  = await fetch('/mp/php/delete_account.php', { method:'POST' });
        const data = await res.json().catch(()=>({ok:false,error:'invalid_json'}));
        if (!res.ok || !data.ok) { alert(data.error || 'Delete failed'); return; }
        window.location.href = '/mp/php/logout.php';
      } catch (err) {
        console.error(err);
        alert('Network error');
      }
    });
  </script>

  <!-- فتح/إغلاق السايدبار كما هو -->
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
        document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
        e.currentTarget.classList.add('active');
      });
    });
  </script>

<?php if ($flash['type']): ?>
  <script> alert(<?= json_encode($flash['msg']) ?>); </script>
<?php endif; ?>

</div>
</body>
</html>