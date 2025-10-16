<?php
session_start();
$BASE = "/mp";

/* Admin guard */
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403); exit('Forbidden');
}

/* ===== جِيب ID أو آخر مزوّد ===== */
$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid === 0) {
  mysqli_report(MYSQLI_REPORT_OFF);
  $conn_temp = @new mysqli("localhost","root","","fixora");
  if (!$conn_temp->connect_error) {
    $res = $conn_temp->query("SELECT id FROM users WHERE role='provider' ORDER BY created_at DESC LIMIT 1");
    if ($res && $res->num_rows) {
      $pid = (int)$res->fetch_assoc()['id'];
      header("Location: ?id=".$pid); exit;
    }
    $conn_temp->close();
  }
}

/* DB */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* Helpers */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function qrow($conn,$sql,$types='',$params=[]){
  $st = $conn->prepare($sql); if(!$st) return null;
  if($types && $params) $st->bind_param($types, ...$params);
  $st->execute();
  $res = $st->get_result();
  $row = $res? $res->fetch_assoc() : null;
  $st->close();
  return $row;
}
function qval($conn,$sql,$types='',$params=[]){
  $r = qrow($conn,$sql,$types,$params);
  if(!$r) return 0;
  $first = array_values($r)[0] ?? 0;
  return is_numeric($first) ? $first+0 : $first;
}
function duration_label($mins){
  $m = (int)$mins;
  if ($m <= 0) return '—';
  if ($m < 60) return $m . ' Minutes';
  $h = intdiv($m,60); $r = $m % 60;
  return $r ? ($h.'h '.$r.'m') : ($h.' Hours');
}
function format_price($from, $to) {
  if (!$from && !$to) return '—';
  return ($from ? '$' . $from : '') . ($to ? ' - $' . $to : '');
}

/* === Provider core info === */
$provider = null;
if ($pid > 0) {
  $provider = qrow($conn, "
    SELECT u.id, u.full_name, u.email, u.phone, u.address, u.created_at, u.status,
           pp.national_id, pp.avatar_path, pp.years_experience, pp.gender, pp.age
    FROM users u
    LEFT JOIN provider_profiles pp ON pp.user_id = u.id
    WHERE u.id=? AND u.role='provider'
    LIMIT 1
  ","i",[$pid]);

  if (!$provider) {
    $provider = qrow($conn, "
      SELECT user_id AS id, full_name, email, phone, address, created_at,
             'active' AS status, national_id, avatar_path, years_experience, gender, age
      FROM provider_profiles WHERE user_id=? LIMIT 1
    ","i",[$pid]);
  }
}

/* === KPIs === */
$total_completed = $pid ? qval($conn, "SELECT COUNT(*) FROM bookings WHERE provider_id=? AND status='completed'","i",[$pid]) : 0;
$avg_rating      = $pid ? qval($conn, "SELECT ROUND(AVG(r.rating),1) FROM service_reviews r JOIN services s ON s.id=r.service_id WHERE s.provider_id=?","i",[$pid]) : 0;
$incoming_orders = $pid ? qval($conn, "SELECT COUNT(*) FROM bookings WHERE provider_id=? AND status IN ('pending','confirmed')","i",[$pid]) : 0;
$missed_orders   = $pid ? qval($conn, "SELECT COUNT(*) FROM bookings WHERE provider_id=? AND status='cancelled'","i",[$pid]) : 0;

/* === Latest service === */
$service = $pid ? qrow($conn, "
  SELECT s.id, s.title, s.category, s.sub_section, s.price_from, s.price_to,
         s.duration_minutes, s.is_active, s.description,
         GROUP_CONCAT(si.text SEPARATOR '|') AS includes
  FROM services s
  LEFT JOIN service_includes si ON si.service_id = s.id
  WHERE s.provider_id=?
  GROUP BY s.id
  ORDER BY s.created_at DESC
  LIMIT 1
","i",[$pid]) : null;

/* === Current booking status for latest service === */
$current_booking_status = '—';
if ($pid && $service) {
  $latest_booking = qrow($conn, "
    SELECT status FROM bookings
    WHERE service_id=? AND provider_id=?
    ORDER BY scheduled_at DESC LIMIT 1
  ","ii",[$service['id'],$pid]);
  if ($latest_booking && !empty($latest_booking['status'])) {
    $current_booking_status = $latest_booking['status'];
  }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Provider details</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="<?= $BASE ?>/css/identification.css">
<style>
  :root{ --border:#e7edf5; --muted:#6b7280; --text:#0f172a; --primary:#2f7af8; --card:#fff; --shadow:0 8px 24px rgba(16,24,40,.06); }
  body{ background:#f7fafc; color:var(--text); }
  .tb-inner{max-width:1200px;margin:0 auto;padding:18px 24px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px}
  .brand-logo{width:150px;height:auto;object-fit:contain}
  .search-wrap{position:relative;width:min(680px,90%);margin-left:90px}
  .search-wrap input{width:500px;height:48px;padding:0 16px 0 44px;border:1px solid #cfd7e3;border-radius:12px;font-size:16px;background:#fff;outline:none}
  .search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a94a6;font-size:18px}
  .notif-pill{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dfe6ef;background:#fff;border-radius:50%;cursor:pointer}
  .notif-pill i{font-size:18px;color:#1e73ff}

  .container{max-width:1100px;margin:16px auto 40px;padding:0 16px}
  .page-head{display:flex;align-items:center;justify-content:space-between;margin:10px 0 18px}
  .page-title{font-size:22px;font-weight:800;margin:0}
  .page-sub{color:var(--muted);margin-top:4px;font-size:14px}

  .kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:8px 0 20px}
  .kpi-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 18px;box-shadow:var(--shadow)}
  .kpi-label{color:var(--muted);font-size:13px;margin-bottom:6px}
  .kpi-value{font-size:28px;font-weight:800}

  .card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow)}
  .block{padding:18px;margin-bottom:18px}
  .block h4{margin:0 0 12px;font-size:16px;font-weight:800}
  .muted{color:var(--muted)}
  .info-table{width:100%;border-collapse:separate;border-spacing:0}
  .info-table tr+tr td{border-top:1px solid var(--border)}
  .info-table td{padding:12px 14px}
  .info-table td:first-child{width:30%;color:#6b7280;font-weight:600}
  .status.badge{display:inline-block;padding:6px 12px;border-radius:999px;font-weight:700;font-size:12px}

  /* ===== Switch (يشتغل أكيد) ===== */
  .switch{position:relative;display:inline-block}
  #suspendToggle{
    appearance:none;width:46px;height:26px;background:#e5e7eb;border-radius:999px;position:relative;cursor:pointer;outline:none
  }
  #suspendToggle::after{
    content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.18s
  }
  #suspendToggle:checked{background:#10b981}
  #suspendToggle:checked::after{transform:translateX(20px)}

  .btn-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:14px 20px;border-radius:12px;border:1px solid var(--border);background:#fff;cursor:pointer;text-decoration:none;font-weight:700;font-size:14px;min-height:48px;box-sizing:border-box;text-align:center}
  .btn.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
  .btn.gray{background:#eaf1ff;color:#2f7af8;border-color:#d6e5ff}
</style>
</head>
<body>

<!-- ===== Topbar ===== -->
<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left" style="display:flex;align-items:center;gap:50px">
      <button class="icon-btn" aria-label="Settings" style="width:40px;height:40px;display:grid;place-items:center;border:none;background:transparent;cursor:pointer">
        <i class="fa-solid fa-gear" style="font-size:18px;color:#6b7280"></i>
      </button>
      <div class="brand"><img src="<?= $BASE ?>/image/home-logo.png" class="brand-logo" alt="Fixora"></div>
    </div>
    <div class="tb-center" style="display:flex;justify-content:center">
      <div class="search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search Here">
      </div>
    </div>
    <div class="tb-right" style="display:flex;align-items:center;gap:35px">
      <button class="notif-pill"><i class="fa-solid fa-bell"></i></button>
      <div class="profile-menu" style="position:relative">
        <button class="profile-trigger" aria-expanded="false" style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--border);padding:6px 12px;border-radius:40px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.06)">
          <img class="avatar" src="https://i.pravatar.cc/48?u=<?= (int)$_SESSION['user_id'] ?>" alt="Profile" style="width:48px;height:48px;object-fit:cover;border-radius:50%;display:block">
          <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="menu-card" hidden style="position:absolute;right:0;top:calc(100% + 10px);z-index:9999;width:280px;background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:0 12px 30px rgba(0,0,0,.12);padding:12px;overflow:auto;max-height:80vh">
          <a class="menu-item" href="<?= $BASE ?>/php/my_booking.php" style="display:flex;gap:12px;padding:12px;border-radius:14px;color:#0f172a;text-decoration:none;font-weight:600;background:#fff;border:0;cursor:pointer">My Bookings</a>
          <hr class="divider" style="border:0;height:1px;background:var(--border);margin:4px 0">
          <a class="menu-item" href="<?= $BASE ?>/provider/Account settings.php" style="display:flex;gap:12px;padding:12px;border-radius:14px;color:#0f172a;text-decoration:none;font-weight:600;background:#fff;border:0;cursor:pointer">Account Settings</a>
          <hr class="divider" style="border:0;height:1px;background:var(--border);margin:4px 0">
          <a class="menu-item" href="<?= $BASE ?>/php/logout.php" style="display:flex;gap:12px;padding:12px;border-radius:14px;color:#dc2626;text-decoration:none;font-weight:600;background:#fff;border:0;cursor:pointer;justify-content:space-between">Log Out <i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== Page ===== -->
<div class="container">

  <div class="page-head">
    <div>
      <h2 class="page-title">Provider details</h2>
      <div class="page-sub">
        <?php if($provider): ?>
          View and manage provider information – Provider ID: <?= (int)$pid ?>
        <?php else: ?>
          No providers found in the system
        <?php endif; ?>
      </div>
    </div>

    <?php if ($provider): ?>
      <div class="switch-line" style="display:flex;align-items:center;gap:10px">
        <span style="color:#374151;font-weight:600">Suspension</span>
        <label class="switch" title="">
          <input type="checkbox" id="suspendToggle"
                 <?= (strtolower((string)$provider['status'])==='suspended')?'checked':'' ?>
                 data-id="<?= (int)$provider['id'] ?>">
        </label>
      </div>
    <?php endif; ?>
  </div>

  <?php if($provider): ?>
  <div class="kpi-row">
    <div class="kpi-card"><div class="kpi-label">Total Service Completed</div><div class="kpi-value"><?= (int)$total_completed ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Average Rating</div><div class="kpi-value"><?= $avg_rating?number_format($avg_rating,1):'—' ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Incoming order</div><div class="kpi-value"><?= (int)$incoming_orders ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Missed Orders</div><div class="kpi-value"><?= (int)$missed_orders ?></div></div>
  </div>

  <section class="card block">
    <h4>Personal information</h4>
    <table class="info-table">
      <tr><td>Full Name</td><td><?= h($provider['full_name'] ?: '—') ?></td></tr>
      <tr><td>National ID Number</td><td><?= h($provider['national_id'] ?: '—') ?></td></tr>
      <tr><td>Email</td><td><?= h($provider['email'] ?: '—') ?></td></tr>
      <tr><td>Phone Number</td><td><?= h($provider['phone'] ?: '—') ?></td></tr>
      <tr><td>Address</td><td><?= h($provider['address'] ?: '—') ?></td></tr>
      <tr><td>Age</td><td><?= h($provider['age'] ? $provider['age'].' Years' : '—') ?></td></tr>
      <tr><td>Gender</td><td><?= h($provider['gender'] ?: '—') ?></td></tr>
      <tr><td>Registration Date</td><td><?= h(substr((string)$provider['created_at'],0,10)) ?></td></tr>
    </table>
  </section>

  <section class="card block">
    <h4>Service Information</h4>
    <?php if ($service): ?>
      <table class="info-table">
        <tr><td>Service Name</td><td><?= h($service['title'] ?: '—') ?></td></tr>
        <tr><td>Service Category</td><td><?= h($service['category'] ?: '—') ?></td></tr>
        <tr><td>Sub-Service Name</td><td><?= h($service['sub_section'] ?: '—') ?></td></tr>
        <tr><td>Service Price</td><td><?= format_price($service['price_from'], $service['price_to']) ?></td></tr>
        <tr><td>Experience</td><td><?= h(($provider['years_experience'] ?? '') ? $provider['years_experience'].' Years' : '—') ?></td></tr>
        <tr><td>Duration</td><td><?= h(duration_label($service['duration_minutes'])) ?></td></tr>
        <tr><td>Description</td><td><?= h($service['description'] ?: '—') ?></td></tr>
        <tr>
          <td>Service Includes</td>
          <td>
            <?php if (!empty($service['includes'])): ?>
              <?php $incs = array_filter(array_map('trim', explode('|',$service['includes']))); ?>
              <?php if ($incs): ?>
                <ul style="margin:0;padding-left:20px">
                  <?php foreach($incs as $it): ?><li><?= h($it) ?></li><?php endforeach; ?>
                </ul>
              <?php else: ?>—<?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <tr>
          <td>Status</td>
          <td>
            <?php
              $status_text = $current_booking_status;
              switch($current_booking_status){
                case 'pending':     $style='background:#fef3c7;color:#92400e'; break;
                case 'confirmed':   $style='background:#d1fae5;color:#065f46'; break;
                case 'in_progress': $style='background:#dbeafe;color:#1e40af'; break;
                case 'completed':   $style='background:#dcfce7;color:#166534'; break;
                case 'cancelled':   $style='background:#fee2e2;color:#991b1b'; break;
                default:            $style='background:#f3f4f6;color:#374151'; $status_text='—';
              }
            ?>
            <span class="status badge" style="<?= $style ?>"><?= ucfirst($status_text) ?></span>
          </td>
        </tr>
      </table>

      <div class="btn-row">
        <a class="btn primary" href="javascript:void(0)"><i class="fa-regular fa-paper-plane"></i><span>Send a Message</span></a>
        <button class="btn gray" id="suspendBtn"><i class="fa-regular fa-circle-pause"></i><span>Suspend Provider</span></button>
      </div>
    <?php else: ?>
      <div class="muted">No services found for this provider.</div>
    <?php endif; ?>
  </section>

  <?php else: ?>
    <div class="card block">
      <div style="text-align:center;padding:40px">
        <i class="fa-solid fa-user-slash" style="font-size:48px;color:#9ca3af;margin-bottom:16px"></i>
        <h3 style="margin:0 0 8px;color:#374151">No Providers Found</h3>
        <p style="color:#6b7280;margin:0">There are no registered providers in the system.</p>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  // افتح/سكّر قائمة البروفايل
  document.querySelector('.profile-trigger')?.addEventListener('click', (e)=>{
    const wrap = e.currentTarget.closest('.profile-menu');
    const card = wrap.querySelector('.menu-card');
    const open = wrap.classList.toggle('open');
    card.hidden = !open;
    e.currentTarget.setAttribute('aria-expanded', String(open));
  });

  // API
  const STATUS_API = 'admin-provider-toggle-status.php';

  const toggle = document.getElementById('suspendToggle');
  if (!toggle) return;

  console.log('Toggle ready. Provider ID =', toggle.dataset.id, 'checked =', toggle.checked);

  async function setStatus(providerId, suspended){
    if(!providerId || providerId === '0'){ alert('Provider id missing'); return false; }
    const body = new URLSearchParams({ id:String(providerId), status: suspended ? 'suspended' : 'active' });

    try{
      const res = await fetch(STATUS_API, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      const raw = await res.text();
      if(!res.ok){ console.error('HTTP', res.status, raw); alert('Failed: HTTP '+res.status); return false; }
      let data; try{ data = JSON.parse(raw); } catch(e){ console.error('Not JSON:', raw); alert('الخادم لم يرجّع JSON'); return false; }
      if(!data.ok){ console.error('API error', data); alert('Failed: '+(data.message||'Unknown error')); return false; }
      console.log('Status updated →', data.status);
      return true;
    }catch(err){
      console.error('Network error:', err);
      alert('Network error: ' + err.message);
      return false;
    }
  }

  // تغيير السويتش
  toggle.addEventListener('change', async (e)=>{
    const pid = e.currentTarget.dataset.id;
    const suspended = e.currentTarget.checked; // true => suspended
    const ok = await setStatus(pid, suspended);
    if(!ok) e.currentTarget.checked = !suspended; // رجّع الحالة لو فشل
  });

  // زر إيقاف المزوّد
  document.getElementById('suspendBtn')?.addEventListener('click', async ()=>{
    const t = document.getElementById('suspendToggle');
    if(!t) return;
    const pid = t.dataset.id;
    if(!pid || pid==='0'){ alert('Provider id missing'); return; }
    if(t.checked) return; // already suspended
    t.checked = true;
    const ok = await setStatus(pid, true);
    if(!ok) t.checked = false;
  });
});
</script>
</body>
</html>