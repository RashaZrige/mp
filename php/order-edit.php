<?php
/* order_edit.php — Admin: Edit Order */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ==== جلب ID الطلب ==== */
$oid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($oid <= 0) { die("Invalid order id."); }

/* ==== جلب بيانات الطلب ==== */
$order = null;
$sql = "
  SELECT b.id, b.customer_id, b.provider_id, b.service_id,
         b.phone, b.scheduled_at, b.status
  FROM bookings b
  WHERE b.id = ?
  LIMIT 1
";
$st = $conn->prepare($sql);
if (!$st) { die("Prepare failed (order): ".$conn->error); }
$st->bind_param("i", $oid);
$st->execute();
$rs = $st->get_result();
$order = $rs ? $rs->fetch_assoc() : null;
$st->close();

if (!$order) { die("Order not found."); }

/* ==== جلب قائمة المزوّدين (providers) ==== */
/* نعتمد إنو المزوّدين هم اللي إلهم صفّ في provider_profiles */
$providers = [];
$rp = $conn->query("
  SELECT u.id, u.full_name
  FROM users u
  INNER JOIN provider_profiles pp ON pp.user_id = u.id
  GROUP BY u.id, u.full_name
  ORDER BY u.full_name ASC
");
if ($rp) { while($row = $rp->fetch_assoc()) $providers[] = $row; }

/* ==== جلب قائمة الخدمات (services) ==== */
$services = [];
$rsrv = $conn->query("SELECT DISTINCT id, title FROM services ORDER BY title ASC");
if ($rsrv) { while($row = $rsrv->fetch_assoc()) $services[] = $row; }

/* ==== حفظ التعديل ==== */
$flash_ok = $flash_err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $provider_id  = isset($_POST['provider_id']) ? (int)$_POST['provider_id'] : null;
  $service_id   = isset($_POST['service_id'])  ? (int)$_POST['service_id']  : null;
  $status       = trim($_POST['status'] ?? '');
  $phone        = trim($_POST['phone']  ?? '');
  $scheduled_at = trim($_POST['scheduled_at'] ?? ''); // yyyy-mm-ddThh:mm من input datetime-local
  $notes        = trim($_POST['notes'] ?? '');       // اختياري — لو الجدول ما فيه notes بتتجاهل

  /* validate بسيطة */
  $validStatuses = ['pending','confirmed','in_progress','completed','cancelled'];
  if ($provider_id <= 0)                 $flash_err = "Please choose a provider.";
  else if ($service_id <= 0)             $flash_err = "Please choose a service.";
  else if (!in_array($status,$validStatuses,true)) $flash_err = "Invalid status.";
  else if ($scheduled_at !== '') {
    // نحول تنسيق datetime-local -> MySQL DATETIME
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $scheduled_at);
    if ($dt === false) { $flash_err = "Invalid date/time."; }
    else { $scheduled_at = $dt->format('Y-m-d H:i:s'); }
  } else {
    $scheduled_at = null; // بنخليه NULL لو فاضي
  }

  if ($flash_err==='') {
    // نبني SQL ديناميكي بحيث لو عمود notes أو updated_at مش موجود ما نكسّر
    // نتاكد بقراءة وصف الجدول مرّة واحدة:
    $colNotes = false; $colUpdatedAt = false;
    if ($desc = $conn->query("SHOW COLUMNS FROM bookings")) {
      while($c = $desc->fetch_assoc()){
        if (strcasecmp($c['Field'],'notes')===0)      $colNotes = true;
        if (strcasecmp($c['Field'],'updated_at')===0) $colUpdatedAt = true;
      }
      $desc->free();
    }

    $sql = "UPDATE bookings
            SET provider_id = ?, service_id = ?, status = ?, phone = ?, scheduled_at = ?";
    if ($colNotes)      $sql .= ", notes = ?";
    if ($colUpdatedAt)  $sql .= ", updated_at = NOW()";
    $sql .= " WHERE id = ?";

    $st = $conn->prepare($sql);
    if (!$st) { $flash_err = "Prepare failed (update): ".$conn->error; }
    else {
      if ($colNotes) {
        // types: i i s s s s i
        $st->bind_param("iissssi",
          $provider_id, $service_id, $status, $phone, $scheduled_at, $notes, $oid
        );
      } else {
        // types: i i s s s i
        $st->bind_param("iisssi",
          $provider_id, $service_id, $status, $phone, $scheduled_at, $oid
        );
      }
      if ($st->execute()) {
        $flash_ok = "Order updated successfully.";
        // رجّع البيانات الجديدة عالشكل
        $order['provider_id']  = $provider_id;
        $order['service_id']   = $service_id;
        $order['status']       = $status;
        $order['phone']        = $phone;
        $order['scheduled_at'] = $scheduled_at;
        if ($colNotes) $order['notes'] = $notes;
      } else {
        $flash_err = "Update failed.";
      }
      $st->close();
    }
  }
}

$conn->close();

/* helper لتعبئة datetime-local */
function as_local_dt($mysqlDT){
  if (!$mysqlDT) return '';
  $ts = strtotime($mysqlDT);
  if (!$ts) return '';
  return date('Y-m-d\TH:i', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Edit Order #<?= (int)$order['id'] ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css"><!-- نفس الثيم العام -->

<style>
/* غلاف بسيط */
.page-wrap{max-width:900px;margin:0 auto;padding:20px 24px 60px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
.hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.hdr h2{font:700 18px/1.2 "Inter",system-ui;margin:0}
.muted{font-size:13px;color:#6b7280}

/* فورم */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{display:flex;flex-direction:column;gap:6px}
.label{font-size:13px;color:#6b7280}
.input,.select,.textarea{
  height:40px;border:1px solid #e5e7eb;border-radius:10px;padding:0 12px;background:#fff;outline:0;font-size:14px
}
.textarea{min-height:110px;height:auto;padding:10px 12px;resize:vertical}
.full{grid-column:1 / -1}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}

/* أزرار */
.btn{height:40px;padding:0 16px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-weight:700;cursor:pointer}
.btn-primary{background:#2b79ff;color:#fff;border-color:transparent}
.btn-cancel{background:#fff;color:#dc2626;border-color:#dc2626} /* أبيض بحدّ أحمر */
.btn:focus{outline:none}

/* شيل الخط تحت الأيقونات والروابط */
a, .btn, .icon-btn{ text-decoration:none; }

/* تصليح أي خط تحت “السهم/الأيقونة” */
.icon-link{ text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
.icon-link i{ text-decoration:none; }

/* صفّ معلومات */
.info-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px}
.info-row .pill{background:#f9fafb;border:1px solid #e5e7eb;border-radius:999px;padding:6px 10px;font-size:12px}
</style>
</head>
<body>

<!-- Topbar مصغّر -->
<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left">
      <a class="icon-link" href="admin-order.php" title="Back">
        <i class="fa-solid fa-arrow-left"></i> <span>Back</span>
      </a>
      <div class="brand"><img src="/mp/image/home-logo.png" alt="Fixora" class="brand-logo"></div>
    </div>
    <div class="tb-center"></div>
    <div class="tb-right"><button class="notif-pill" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button></div>
  </div>
</section>

<div class="page-wrap">
  <div class="hdr">
    <h2>Edit order #<?= (int)$order['id'] ?></h2>
    <span class="muted">Modify provider, service, time or status</span>
  </div>

  <?php if ($flash_ok): ?><div class="card" style="border-color:#a7f3d0;background:#ecfdf5;color:#065f46;margin-bottom:12px"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;margin-bottom:12px"><?= h($flash_err) ?></div><?php endif; ?>

  <div class="card">
    <div class="info-row">
      <span class="pill"><strong>Order ID:</strong> #<?= (int)$order['id'] ?></span>
      <?php if (!empty($order['scheduled_at'])): ?>
        <span class="pill"><strong>Current date:</strong> <?= h(substr($order['scheduled_at'],0,16)) ?></span>
      <?php endif; ?>
    </div>

    <form method="post">
      <div class="form-grid">

        <!-- Provider -->
        <label class="field">
          <span class="label">Provider</span>
          <select class="select" name="provider_id" required>
            <option value="">Choose provider</option>
            <?php foreach ($providers as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((int)$order['provider_id']===(int)$p['id'])?'selected':'' ?>>
                <?= h($p['full_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Service -->
        <label class="field">
          <span class="label">Service</span>
          <select class="select" name="service_id" required>
            <option value="">Choose service</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ((int)$order['service_id']===(int)$s['id'])?'selected':'' ?>>
                <?= h($s['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Phone -->
        <label class="field">
          <span class="label">Phone</span>
          <input class="input" type="text" name="phone" value="<?= h($order['phone'] ?? '') ?>" placeholder="+9705XXXXXXXX">
        </label>

        <!-- Date/Time -->
        <label class="field">
          <span class="label">Scheduled at</span>
          <input class="input" type="datetime-local" name="scheduled_at" value="<?= h(as_local_dt($order['scheduled_at'])) ?>">
        </label>

        <!-- Status -->
        <label class="field">
          <span class="label">Order status</span>
          <?php $validStatuses = ['pending','confirmed','in_progress','completed','cancelled']; ?>
          <select class="select" name="status" required>
            <?php foreach ($validStatuses as $st): ?>
              <option value="<?= $st ?>" <?= ($order['status']===$st)?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Notes (اختياري) -->
        <label class="field full">
          <span class="label">Notes (optional)</span>
          <textarea class="textarea" name="notes" placeholder="Internal notes for this order (optional)"><?= h($order['notes'] ?? '') ?></textarea>
        </label>
      </div>

      <div class="actions">
        <button type="button" class="btn btn-cancel" onclick="window.location='admin-order.php'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>