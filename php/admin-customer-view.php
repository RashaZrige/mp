<?php
/* admin_customer_view.php — Admin › Customer details */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

/* أثناء التطوير: أظهر الأخطاء بدل صفحة بيضاء */
ini_set('display_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ↓↓↓ أهم تعديل: اقرأ id من GET أو POST */
$cid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($cid <= 0) { die("Invalid customer id"); }

/* ==== toggle suspension (POST) ==== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='toggle_status') {
  $sql = "UPDATE users 
          SET status = CASE WHEN status='active' THEN 'suspended' ELSE 'active' END
          WHERE id=? AND role='customer' AND is_deleted=0";
  $st = $conn->prepare($sql);
  if (!$st) { die("Prepare failed: ".$conn->error); }
  $st->bind_param("i", $cid);
  if (!$st->execute()) { $st->close(); die("Execute failed: ".$conn->error); }
  $st->close();
  header("Location: {$BASE}/php/admin_customer_view.php?id=".$cid);
  exit;
}

/* ==== fetch customer ==== */
$sqlU = "SELECT id, full_name, email, phone, address, created_at, status, avatar
         FROM users
         WHERE id=? AND role='customer' AND is_deleted=0";
$st = $conn->prepare($sqlU);
if (!$st) { die("Prepare failed: ".$conn->error); }
$st->bind_param("i", $cid);
$st->execute();
$u = $st->get_result()->fetch_assoc();
$st->close();
if (!$u) { die("Customer not found"); }

/* ==== KPIs ==== */
$st = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id=? AND status='completed' AND is_deleted=0");
$st->bind_param("i", $cid); $st->execute(); $totalCompleted = (int)$st->get_result()->fetch_row()[0]; $st->close();

$st = $conn->prepare("SELECT ROUND(AVG(rating),1) FROM service_reviews WHERE customer_id=?");
$st->bind_param("i",$cid); $st->execute(); $avgRating = (float)($st->get_result()->fetch_row()[0] ?? 0.0); $st->close();

$st = $conn->prepare("SELECT COUNT(*) FROM bookings 
                      WHERE customer_id=? AND is_deleted=0 
                        AND status IN ('pending','confirmed') AND scheduled_at > NOW()");
$st->bind_param("i", $cid); $st->execute(); $incoming = (int)$st->get_result()->fetch_row()[0]; $st->close();

$st = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id=? AND status='cancelled' AND is_deleted=0");
$st->bind_param("i", $cid); $st->execute(); $missed = (int)$st->get_result()->fetch_row()[0]; $st->close();

/* recent activity */
$lastCompletedAt = null;
$st = $conn->prepare("SELECT scheduled_at FROM bookings 
                      WHERE customer_id=? AND status='completed' AND is_deleted=0
                      ORDER BY scheduled_at DESC, id DESC LIMIT 1");
$st->bind_param("i",$cid); $st->execute(); 
if ($row=$st->get_result()->fetch_row()){ $lastCompletedAt = $row[0]; }
$st->close();

/* last reviews */
$reviews = [];
$sqlR = "SELECT sr.rating, sr.comment, sr.created_at, s.title AS service_title
         FROM service_reviews sr
         LEFT JOIN services s ON s.id = sr.service_id
         WHERE sr.customer_id=?
         ORDER BY sr.created_at DESC
         LIMIT 5";
$st = $conn->prepare($sqlR);
$st->bind_param("i", $cid); $st->execute();
$rres = $st->get_result();
while($r = $rres->fetch_assoc()) $reviews[] = $r;
$st->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Customer details</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css">
<style>
.page{max-width:1100px;margin:0 auto;padding:18px 24px 60px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:12px 0 18px}
.kpi{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
.kpi .k{font-size:13px;color:#6b7280;margin-bottom:6px}
.kpi .v{font:700 20px/1 "Inter",system-ui}
.header-line{display:flex;align-items:center;gap:12px;margin-top:6px}
.back-btn{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;cursor:pointer;text-decoration:none;color:inherit}
.back-btn:focus{outline:none}
.page-title{font:700 20px/1.2 "Inter",system-ui;margin:0}
.grid-2{display:grid;grid-template-columns:1fr;gap:12px}
.info-table{width:100%;border-collapse:collapse}
.info-table td{border-top:1px solid #f3f4f6;padding:10px}
.info-key{color:#6b7280;width:220px}
.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46}
.badge.suspended{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.switch-wrap{margin-left:auto;display:flex;align-items:center;gap:10px}
.switch-wrap form{margin:0}
.rev-card{display:flex;gap:12px;align-items:flex-start;padding:12px;border-top:1px solid #f1f5f9}
.rev-avatar{width:42px;height:42px;border-radius:50%;background:#f3f4f6;flex:0 0 auto}
.rev-title{font-weight:700}
.rev-sub{color:#6b7280;font-size:13px}
.rev-stars{margin-left:auto;color:#f59e0b}
@media (max-width:900px){ .kpis{grid-template-columns:repeat(2,1fr)} .info-key{width:160px} }
</style>
</head>
<body>

<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left">
      <a class="back-btn" href="admin_customers.php" title="Back"><i class="fa-solid fa-arrow-left"></i></a>
      <div class="brand"><img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo"></div>
    </div>
    <div class="tb-center"></div>
    <div class="tb-right"><button class="notif-pill"><i class="fa-solid fa-bell"></i></button></div>
  </div>
</section>

<div class="page">
  <div class="header-line">
    <h2 class="page-title">Customer details</h2>
    <div class="switch-wrap">
      <span class="rev-sub">Suspension</span>
      <form method="post">
        <!-- ↓↓↓ نمرر id دائماً -->
        <input type="hidden" name="id" value="<?= (int)$cid ?>">
        <input type="hidden" name="action" value="toggle_status">
        <button type="submit" class="back-btn" title="Toggle suspension">
          <?php if ($u['status']==='active'): ?>
            <i class="fa-solid fa-toggle-on"></i>
          <?php else: ?>
            <i class="fa-solid fa-toggle-off"></i>
          <?php endif; ?>
        </button>
      </form>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi"><div class="k">Total Service Completed</div><div class="v"><?= $totalCompleted ?></div></div>
    <div class="kpi"><div class="k">Average Rating</div><div class="v"><?= number_format($avgRating,1) ?></div></div>
    <div class="kpi"><div class="k">Incoming order</div><div class="v"><?= $incoming ?></div></div>
    <div class="kpi"><div class="k">Missed Orders</div><div class="v"><?= $missed ?></div></div>
  </div>

  <div class="card">
    <h3 style="margin:4px 0 10px;font-weight:700">Personal information</h3>
    <table class="info-table">
      <tr><td class="info-key">Full Name</td><td><?= h($u['full_name']) ?></td></tr>
      <tr><td class="info-key">CUSID</td><td><?= 'CUS'.str_pad($u['id'],6,'0',STR_PAD_LEFT) ?></td></tr>
      <tr><td class="info-key">Email</td><td><?= h($u['email'] ?: '—') ?></td></tr>
      <tr><td class="info-key">Phone Number</td><td><?= h($u['phone'] ?: '—') ?></td></tr>
      <tr><td class="info-key">Address</td><td><?= h($u['address'] ?: '—') ?></td></tr>
      <tr><td class="info-key">Registration Date</td><td><?= h(substr($u['created_at'],0,10)) ?></td></tr>
      <tr>
        <td class="info-key">Status</td>
        <td><span class="badge <?= $u['status']==='active' ? '' : 'suspended' ?>"><?= h($u['status']) ?></span></td>
      </tr>
    </table>
  </div>

  <div class="card" style="margin-top:14px">
    <h3 style="margin:4px 0 10px;font-weight:700">Recent activity</h3>
    <div class="rev-sub" style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid #f3f4f6">
      <span>Service Completed</span>
      <span><?= $lastCompletedAt ? h(substr($lastCompletedAt,0,10)) : '—' ?></span>
    </div>
    <div class="rev-sub" style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid #f3f4f6">
      <span>Account Created</span>
      <span><?= h(substr($u['created_at'],0,10)) ?></span>
    </div>
  </div>

  <h3 style="margin:16px 0 10px;font-weight:700">last Reviews</h3>
  <div class="card">
    <?php if (!$reviews): ?>
      <div class="rev-card" style="border-top:0">No reviews yet</div>
    <?php else: foreach($reviews as $rv): ?>
      <div class="rev-card">
        <div class="rev-avatar"></div>
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:10px">
            <span class="rev-title"><?= h($u['full_name']) ?></span>
            <span class="rev-sub"><?= h($rv['service_title'] ?? '—') ?></span>
            <span class="rev-stars">
              <?php $stars = (int)($rv['rating'] ?? 0); for($i=0;$i<5;$i++) echo $i<$stars?'★':'☆'; ?>
              <span class="rev-sub" style="margin-left:6px"><?= number_format((float)$rv['rating'],1) ?></span>
            </span>
          </div>
          <div style="margin-top:6px;color:#111827"><?= h($rv['comment'] ?? '') ?></div>
          <div class="rev-sub" style="margin-top:4px">Reviewed on : <?= h(substr($rv['created_at'],0,10)) ?></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
</body>
</html>