<?php
/* Fixora – Admin › Provider Details (unified suspend/unsuspend button) */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* ✅ حل مشكلة إعادة تعريف الدالة */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid <= 0) { die('Missing provider id'); }

/* === Provider (users + profile) === */
$provider = [
  'id'=>$pid, 'full_name'=>'—', 'email'=>'—', 'phone'=>'—', 'address'=>'—',
  'status'=>'active', 'created_at'=>'—', 'national_id'=>'—', 'is_available'=>1
];

$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.address, u.status, u.created_at,
               COALESCE(pp.national_id,'—') AS national_id,
               COALESCE(pp.is_available,1)  AS is_available
        FROM users u
        LEFT JOIN provider_profiles pp ON pp.user_id=u.id
        WHERE u.id=? AND u.role='provider' AND u.is_deleted=0
        LIMIT 1";
if ($st = $conn->prepare($sql)) {
  $st->bind_param("i",$pid);
  $st->execute();
  $res = $st->get_result();
  if ($row = $res->fetch_assoc()) $provider = $row;
  $st->close();
}

/* === KPIs === */
$kpi = ['completed'=>0,'avg_rating'=>0,'incoming'=>0,'missed'=>0];

$sql = "SELECT COUNT(*) FROM bookings b
        JOIN services s ON s.id=b.service_id
        WHERE s.provider_id=? AND b.status IN ('completed','done','finished')";
if ($st = $conn->prepare($sql)) { $st->bind_param("i",$pid); $st->execute();
  $kpi['completed'] = (int)$st->get_result()->fetch_row()[0]; $st->close(); }

$sql = "SELECT AVG(rating) FROM service_reviews WHERE provider_id=?";
if ($st = $conn->prepare($sql)) { $st->bind_param("i",$pid); $st->execute();
  $avg = $st->get_result()->fetch_row()[0]; $kpi['avg_rating'] = $avg ? round($avg,1) : 0; $st->close(); }

$sql = "SELECT COUNT(*) FROM bookings b
        JOIN services s ON s.id=b.service_id
        WHERE s.provider_id=? AND b.status IN ('pending','confirmed')";
if ($st = $conn->prepare($sql)) { $st->bind_param("i",$pid); $st->execute();
  $kpi['incoming'] = (int)$st->get_result()->fetch_row()[0]; $st->close(); }

$sql = "SELECT COUNT(*) FROM bookings b
        JOIN services s ON s.id=b.service_id
        WHERE s.provider_id=? AND b.status IN ('cancelled','missed','no_show')";
if ($st = $conn->prepare($sql)) { $st->bind_param("i",$pid); $st->execute();
  $kpi['missed'] = (int)$st->get_result()->fetch_row()[0]; $st->close(); }

/* === Latest service (need service_id) === */
$service = [
  'service_id'=>0,'title'=>'—','sub_section'=>'—','price_from'=>0,'price_to'=>0,
  'duration_minutes'=>0,'rating'=>0,'is_active'=>1
];
$sql = "SELECT
          id AS service_id,
          title, COALESCE(sub_section,'—') AS sub_section,
          COALESCE(price_from,0) AS price_from,
          COALESCE(price_to,0)   AS price_to,
          COALESCE(duration_minutes,0) AS duration_minutes,
          COALESCE(rating,0) AS rating,
          COALESCE(is_active,1) AS is_active
        FROM services
        WHERE provider_id=? AND is_deleted=0
        ORDER BY created_at DESC
        LIMIT 1";
if ($st = $conn->prepare($sql)) {
  $st->bind_param("i",$pid); $st->execute();
  if ($row = $st->get_result()->fetch_assoc()) $service = $row;
  $st->close();
}

/* === Latest booking status for that service/provider === */
$booking_status_label = '—';
$booking_status_class = 'orange'; // default

if (!empty($service['service_id'])) {
  $sql = "SELECT b.status
          FROM bookings b
          WHERE b.provider_id = ? AND b.service_id = ?
          ORDER BY COALESCE(b.scheduled_at, b.created_at) DESC, b.id DESC
          LIMIT 1";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param("ii", $pid, $service['service_id']);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    if (!empty($r['status'])) {
      $status = strtolower(trim($r['status']));
      switch ($status) {
        case 'pending':      $booking_status_label='Pending';      $booking_status_class='orange'; break;
        case 'confirmed':    $booking_status_label='Confirmed';    $booking_status_class='blue';   break;
        case 'in_progress':  $booking_status_label='In Progress';  $booking_status_class='blue';   break;
        case 'completed':    $booking_status_label='Completed';    $booking_status_class='ok';     break;
        case 'cancelled':    $booking_status_label='Cancelled';    $booking_status_class='warn';   break;
        default:             $booking_status_label=ucfirst($status); $booking_status_class='orange';
      }
    }
  }
}

/* === Provider display status (Active / Not available / Suspended) === */
$display_status_label = 'Active';
$display_status_class = 'ok';
if ((int)$provider['is_available'] === 0) { $display_status_label='Not available'; $display_status_class='orange'; }
elseif (strtolower((string)$provider['status']) === 'suspended') { $display_status_label='Suspended'; $display_status_class='warn'; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Provider Details</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css">
<style>
  :root{--border:#e7edf5;--card:#fff;--muted:#6b7280;--brand:#2f7af8;--text:#0f172a}
  *{box-sizing:border-box}
  body{margin:0;color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f9ff}

  /* Hero */
  section.topbar{background:#fff;border-bottom:1px solid #eef2f7}
  .tb-inner{max-width:1200px;margin:0 auto;padding:18px 24px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px}
  .tb-left{display:flex;align-items:center;gap:50px}
  .brand-logo{width:150px}
  .tb-center{display:flex;justify-content:center}
  .search-wrap{position:relative;width:min(680px,90%)}
  .search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a94a6}
  .search-wrap input{width:100%;height:44px;border:1px solid #cfd7e3;border-radius:12px;padding:0 14px 0 40px}
  .notif-pill{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dfe6ef;background:#fff;border-radius:50%}

  .page{max-width:1100px;margin:20px auto 80px;padding:0 20px}
  .h1{font:800 22px/1.2 "Inter",system-ui;margin:10px 0 16px}

  /* KPIs */
  .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:12px 0 22px}
  .kpi{background:#fff;border:1px solid var(--border);border-radius:12px;padding:14px}
  .kpi .label{font-size:12px;color:#6b7280;margin-bottom:6px}
  .kpi .value{font-size:22px;font-weight:800}

  /* Cards */
  .card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:14px}
  .card + .card{margin-top:16px}
  .card-title{font:800 16px/1.2 "Inter";margin:0 0 10px}

  /* 2-col info table style */
  .info{border:1px solid var(--border);border-radius:12px;overflow:hidden}
  .row{display:grid;grid-template-columns:220px 1fr;border-top:1px solid #f1f5f9}
  .row:first-child{border-top:0}
  .row>div{padding:12px 14px}
  .row>div:first-child{background:#f9fafb;color:#475569;font-weight:700}

  .badge{display:inline-flex;align-items:center;justify-content:center;height:28px;padding:0 12px;border-radius:8px;border:1px solid #e5e7eb;font-weight:800;font-size:13px}
  .badge.ok{background:#e6fff7;color:#065f46;border-color:#baf2de}
  .badge.warn{background:#ffe7e7;color:#b4231a;border-color:#f7b6b6}
  .badge.orange{background:#fff7ed;color:#9a3412;border-color:#fed7aa}
  .badge.blue{background:#e1effe;color:#1d4ed8;border-color:#bfdbfe}

  /* Actions: equal size buttons */
  .actions{display:grid;grid-template-columns:1fr 1fr;gap:16px;justify-content:center;margin-top:16px}
  .btn{height:44px;padding:0 18px;border-radius:10px;border:1px solid #cfd7e3;background:#fff;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
  .btn.primary{background:#2f7af8;border-color:#2f7af8;color:#fff}
  .btn.soft{background:#eef2ff;border-color:#dbe2ff;color:#2743d3}
  .btn.soft.danger{background:#fff;border-color:#fecaca;color:#dc2626}

  @media (max-width:900px){ .kpis{grid-template-columns:repeat(2,1fr)} .row{grid-template-columns:160px 1fr} }
  @media (max-width:600px){ .kpis{grid-template-columns:1fr} .row{grid-template-columns:1fr} .row>div:first-child{border-bottom:1px solid #f1f5f9} .actions{grid-template-columns:1fr} }

  /* Modal */
  .modal{position:fixed;inset:0;display:block;z-index:9999}
  .modal[hidden]{display:none}
  .modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45)}
  .modal__card{
    position:relative;max-width:640px;margin:6vh auto;background:#fff;border-radius:18px;
    padding:22px;border:1px solid #e6ebf2;box-shadow:0 18px 50px rgba(0,0,0,.18)
  }
  .modal__icon{width:92px;height:92px;border-radius:999px;background:#e9f2ff;color:#2f7af8;
    display:grid;place-items:center;margin:6px auto 12px;font-size:34px}
  .modal__title{margin:0 0 8px;font:800 22px/1.2 "Inter",system-ui;text-align:center}
  .modal__sub{margin:0 auto 14px;color:#6b7280;max-width:520px;text-align:center}
  .modal__group{margin:14px 0}
  .modal__label{display:block;font-weight:700;color:#374151;margin-bottom:8px}
  .modal__textarea{
    width:100%;min-height:120px;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;
    font:14px/1.5 system-ui;resize:vertical;background:#fff
  }
  .modal__check{margin-right:18px;color:#374151}
  .modal-actions {display:flex;justify-content:center;gap:12px;margin-top:20px}
  .modal-actions .btn{min-width:120px;height:42px;border-radius:8px;font-weight:600;cursor:pointer}
  .btn.confirm{background:#2f7af8;color:#fff;border:none}
  .btn.cancel{background:#fff;border:1px solid #dc2626;color:#dc2626}
</style>
</head>
<body>

<!-- Hero -->
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
      <button class="notif-pill"><i class="fa-solid fa-bell" style="color:#1e73ff"></i></button>
    </div>
  </div>
</section>

<div class="page">
  <h1 class="h1" style="margin:0">Provider details</h1>

  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi"><div class="label">Total Service Completed</div><div class="value"><?= (int)$kpi['completed'] ?></div></div>
    <div class="kpi"><div class="label">Average Rating</div><div class="value"><?= number_format($kpi['avg_rating'],1) ?></div></div>
    <div class="kpi"><div class="label">Incoming order</div><div class="value"><?= (int)$kpi['incoming'] ?></div></div>
    <div class="kpi"><div class="label">Missed Orders</div><div class="value"><?= (int)$kpi['missed'] ?></div></div>
  </div>

  <!-- Personal information -->
  <div class="card">
    <h3 class="card-title">Personal information</h3>
    <div class="info">
      <div class="row"><div>Full Name</div><div><?= h($provider['full_name']) ?></div></div>
      <div class="row"><div>National ID Number</div><div><?= h($provider['national_id']) ?></div></div>
      <div class="row"><div>Email</div><div><?= h($provider['email']) ?></div></div>
      <div class="row"><div>Phone Number</div><div><?= h($provider['phone']) ?></div></div>
      <div class="row"><div>Address</div><div><?= h($provider['address'] ?: '—') ?></div></div>
      <div class="row"><div>Registration Date</div><div><?= h(substr((string)$provider['created_at'],0,10)) ?></div></div>
      <div class="row">
        <div>Status</div>
        <div>
          <span id="providerStatusBadge" class="badge <?= $display_status_class ?>"><?= h($display_status_label) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Service information -->
  <div class="card">
    <h3 class="card-title">Service Information</h3>
    <div class="info">
      <div class="row"><div>Service Name</div><div><?= h($service['title']) ?></div></div>
      <div class="row"><div>Sub-Service Name</div><div><?= h($service['sub_section']) ?></div></div>
      <div class="row"><div>Service Price</div>
        <div><?= $service['title']==='—' ? '—' : h((int)$service['price_from']).' - '.h((int)$service['price_to']).' $' ?></div>
      </div>
      <div class="row"><div>Duration</div><div><?= $service['title']==='—' ? '—' : (int)$service['duration_minutes'].' Minutes' ?></div></div>
      <div class="row"><div>Status</div>
        <div><span class="badge <?= h($booking_status_class) ?>"><?= h($booking_status_label) ?></span></div>
      </div>
    </div>
  </div>

  <!-- Actions (same size buttons) -->
  <div class="actions">
    <!-- <a class="btn primary"
       href="<?= $BASE ?>/admin/admin_broadcast_new.php?to=provider&id=<?= (int)$provider['id'] ?>">
       Send A Message Or Notification
    </a> -->

    <a class="btn primary" href="javascript:void(0)" id="openSendModal">
  Send A Message Or Notification
</a>

    <?php $isSusp = (strtolower((string)$provider['status']) === 'suspended'); ?>
    <a href="#"
       id="statusBtn"
       class="btn soft <?= $isSusp ? 'danger' : '' ?>"
       data-id="<?= (int)$provider['id'] ?>"
       data-status="<?= $isSusp ? 'suspended' : 'active' ?>">
      <span id="statusBtnLabel"><?= $isSusp ? 'Unsuspend Provider' : 'Suspend Provider' ?></span>
    </a>
  </div>
</div>

<!-- Suspend Modal -->
<div id="suspendModal" class="modal" hidden>
  <div class="modal__backdrop"></div>
  <div class="modal__card" role="dialog" aria-modal="true" aria-labelledby="susTitle">
    <div class="modal__icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <h3 id="susTitle" class="modal__title">Temporarily Suspend Provider</h3>
    <p class="modal__sub">
      Are you sure you want to temporarily suspend the provider
      <b><?= h($provider['full_name']) ?></b>? They will no longer be able to accept orders.
    </p>

    <div class="modal__group">
      <label class="modal__label">Customer Message</label>
      <textarea id="susMsg" class="modal__textarea" placeholder="Enter message to send to the provider …"></textarea>
    </div>

    <div class="modal__group">
      <span class="modal__label">Send Via</span>
      <label class="modal__check"><input type="checkbox" id="viaEmail"> Email</label>
      <label class="modal__check"><input type="checkbox" id="viaSMS"> SMS</label>
      <label class="modal__check"><input type="checkbox" id="viaPush"> Notification</label>
    </div>

    <div class="modal-actions">
      <button id="susConfirm" type="button" class="btn confirm">Confirm</button>
      <button id="susCancel"  type="button" class="btn cancel" data-close>Cancel</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const STATUS_API = '<?= $BASE ?>/php/admin-provider-toggle-status.php ';
  const pid        = <?= (int)$provider['id'] ?>;

  // UI elements
  const badge      = document.getElementById('providerStatusBadge');
  const statusBtn  = document.getElementById('statusBtn');
  const statusLbl  = document.getElementById('statusBtnLabel');

  // Modal
  const modal      = document.getElementById('suspendModal');
  const btnConfirm = document.getElementById('susConfirm');
  const btnCancel  = document.getElementById('susCancel');
  const backdrop   = modal?.querySelector('.modal__backdrop');

  // Optional message + channels
  const msgEl    = document.getElementById('susMsg');
  const viaEmail = document.getElementById('viaEmail');
  const viaSMS   = document.getElementById('viaSMS');
  const viaPush  = document.getElementById('viaPush');

  // Helpers
  function openModal(e){ e?.preventDefault(); modal.hidden = false; }
  function closeModal(){ modal.hidden = true; }
  function isSuspended(){ return statusBtn?.dataset.status === 'suspended'; }
  function setBtnUI(suspended){
    statusBtn.dataset.status = suspended ? 'suspended' : 'active';
    statusLbl.textContent    = suspended ? 'Unsuspend Provider' : 'Suspend Provider';
    statusBtn.classList.toggle('danger', suspended);
  }
  function setBadgeUI(to){
    if (!badge) return;
    if (to === 'suspended'){
      badge.classList.remove('ok','orange','blue');
      badge.classList.add('warn');
      badge.textContent = 'Suspended';
    } else {
      badge.classList.remove('warn','orange','blue');
      badge.classList.add('ok');
      badge.textContent = 'Active';
    }
  }
  async function updateStatus(to){
    const body = new URLSearchParams({ id:String(pid), status:to });
    const res  = await fetch(STATUS_API, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body
    });
    const raw = await res.text();
    if(!res.ok) throw new Error('HTTP '+res.status+': '+raw);
    let data;
    try { data = JSON.parse(raw); } catch { throw new Error('Server did not return JSON'); }
    if(!data.ok) throw new Error(data.message || 'Update failed');
    return true;
  }

  // Unified button behavior
  statusBtn?.addEventListener('click', (e)=>{
    e.preventDefault();
    if (isSuspended()) {
      // Unsuspend immediately
      updateStatus('active')
        .then(()=>{ setBtnUI(false); setBadgeUI('active'); alert('Provider unsuspended'); })
        .catch(err=>{ console.error(err); alert(err.message); });
    } else {
      // Ask for confirmation to suspend
      openModal();
    }
  });

  // Confirm suspend
  btnConfirm?.addEventListener('click', ()=>{
    updateStatus('suspended')
      .then(()=>{
        const message  = (msgEl?.value || '').trim();
        const channels = { email:!!viaEmail?.checked, sms:!!viaSMS?.checked, push:!!viaPush?.checked };
        console.log('Would send notification:', { message, channels });

        setBtnUI(true);
        setBadgeUI('suspended');
        closeModal();
        alert('Provider suspended');
      })
      .catch(err=>{ console.error(err); alert(err.message); });
  });

  btnCancel?.addEventListener('click', closeModal);
  backdrop?.addEventListener('click', closeModal);
});
</script>











<!-- Send Message Modal -->
<div id="sendModal" class="modal" hidden>
  <div class="modal__backdrop"></div>
  <div class="modal__card" role="dialog" aria-modal="true" aria-labelledby="sendTitle">
    <div class="modal__icon">
      <i class="fa-regular fa-paper-plane"></i>
    </div>

    <h3 id="sendTitle" class="modal__title">Send Message / Notification</h3>
    <p class="modal__sub">
      This message will be sent to <b><?= h($provider['full_name']) ?></b>.
    </p>

    <div class="modal__group">
      <label class="modal__label">Message</label>
      <textarea id="sendMsg" class="modal__textarea" placeholder="Type your message…"></textarea>
    </div>

    <div class="modal__group">
      <span class="modal__label">Send Via</span>
      <label class="modal__check"><input type="checkbox" id="sendViaEmail" checked> Email</label>
      <label class="modal__check"><input type="checkbox" id="sendViaSMS"> SMS</label>
      <label class="modal__check"><input type="checkbox" id="sendViaPush"> Notification</label>
    </div>

    <div class="modal-actions">
      <button id="sendConfirm" type="button" class="btn confirm">Send</button>
      <button id="sendCancel"  type="button" class="btn cancel"  data-close>Cancel</button>
    </div>
  </div>
</div>




<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const pid           = <?= (int)$provider['id'] ?>;
  const btnOpenSend   = document.getElementById('openSendModal'); // زر Send A Message Or Notification
  const sendModal     = document.getElementById('sendModal');
  const sendBackdrop  = sendModal?.querySelector('.modal__backdrop');
  const btnSendOK     = document.getElementById('sendConfirm');
  const btnSendCancel = document.getElementById('sendCancel');

  const sendMsg   = document.getElementById('sendMsg');
  const viaEmail  = document.getElementById('sendViaEmail');
  const viaSMS    = document.getElementById('sendViaSMS');
  const viaPush   = document.getElementById('sendViaPush');

  function openSendModal(e){ e?.preventDefault(); sendModal.hidden = false; }
  function closeSendModal(){ sendModal.hidden = true; }

  btnOpenSend?.addEventListener('click', openSendModal);
  btnSendCancel?.addEventListener('click', closeSendModal);
  sendBackdrop?.addEventListener('click', closeSendModal);

  // إرسال تجريبي (حالياً console فقط؛ اربطه لاحقاً بAPI الإشعارات عندك)
  btnSendOK?.addEventListener('click', async ()=>{
    const message = (sendMsg?.value || '').trim();
    const channels = {
      email: !!viaEmail?.checked,
      sms:   !!viaSMS?.checked,
      push:  !!viaPush?.checked
    };
    if (!message) { alert('Please write a message first'); return; }

    // TODO: اربطها بواجهة الإرسال عندك لما تجهز (fetch لملف PHP الخاص بالإرسال)
    console.log('Would send message to provider', pid, {message, channels});
    alert('Message queued (demo).');

    // نظّف وأغلق
    sendMsg.value = '';
    closeSendModal();
  });
});
</script>

</body>
</html>