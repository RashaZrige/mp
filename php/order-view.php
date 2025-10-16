<?php
/* order_view.php — Admin: Order Details */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) { http_response_code(400); die("Invalid order id"); }

/* ====== احضر بيانات الطلب مع الأسماء والخدمة ====== */
$sql = "
SELECT 
  b.id, b.customer_id, b.provider_id, b.service_id,
  b.address, b.phone, b.scheduled_at, b.status, b.created_at,
  cu.full_name AS customer_name,
  pr.full_name AS provider_name,
  s.title      AS service_title
FROM bookings b
LEFT JOIN users cu ON cu.id = b.customer_id
LEFT JOIN users pr ON pr.id = b.provider_id
LEFT JOIN services s ON s.id = b.service_id
WHERE b.id = ?
LIMIT 1
";
$st = $conn->prepare($sql);
if (!$st) die("Prepare failed: ".$conn->error);
$st->bind_param("i", $orderId);
$st->execute();
$rs = $st->get_result();
$order = $rs ? $rs->fetch_assoc() : null;
$st->close();

if (!$order) { http_response_code(404); die("Order not found"); }

/* ====== What Included Services (اختياري) ======
   لو ما عندك جدول service_includes تجاهل المقطع التالي؛ مش هيكسر الصفحة.
*/
$included = [];
if ($order['service_id']) {
  if ($res = $conn->query("SELECT item FROM service_includes WHERE service_id=".(int)$order['service_id']." ORDER BY id ASC")) {
    while($r = $res->fetch_assoc()){ $included[] = $r['item']; }
    $res->free();
  }
}

/* ====== Feedback العميل (من جدول service_reviews) ====== */
$review = null;
$rvSql = "
  SELECT r.id, r.rating, r.comment, r.created_at, u.full_name AS customer_name
  FROM service_reviews r
  LEFT JOIN users u ON u.id = r.customer_id
  WHERE r.booking_id = ?
  ORDER BY r.id DESC
  LIMIT 1
";
$rv = $conn->prepare($rvSql);
if ($rv) {
  $rv->bind_param("i", $orderId);
  $rv->execute();
  $rvr = $rv->get_result();
  $review = $rvr ? $rvr->fetch_assoc() : null;
  $rv->close();
}

$conn->close();

/* فورمات للتاريخ */
function fmt_dt($dt){
  if (!$dt) return '-';
  $ts = strtotime($dt);
  return date('Y-m-d', $ts).'  •  '.date('g:i A', $ts);
}

/* بادجات الحالة */
function status_badge_class($s){
  switch ($s) {
    case 'pending':     return 'b-pending';
    case 'confirmed':   return 'b-confirmed';
    case 'in_progress': return 'b-inprogress';
    case 'completed':   return 'b-completed';
    case 'cancelled':   return 'b-cancelled';
    default:            return 'b-muted';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Order #<?= (int)$order['id'] ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css">

<style>
/* صفحة التفاصيل */
.page{max-width:1100px;margin:0 auto;padding:18px 24px 60px}
.section-title{font:700 20px/1.2 "Inter",system-ui;margin:8px 0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:0;overflow:hidden}
.table-like{width:100%;border-collapse:collapse;table-layout:fixed}
.table-like tr+tr td{border-top:1px solid #f1f5f9}
.table-like td{padding:12px 14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.table-like td.key{width:240px;color:#6b7280;background:#fafafa}
.header{display:flex;align-items:center;gap:12px}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent}
.b-pending{background:#fffbeb;color:#a16207;border-color:#fde68a}
.b-confirmed{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
.b-inprogress{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
.b-completed{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.b-cancelled{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.b-muted{background:#f3f4f6;color:#6b7280;border-color:#e5e7eb}

.meta-top{color:#6b7280;font-size:13px;margin-top:-6px}
.divider{height:1px;background:#f1f5f9;margin:16px 0}

.fb-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
.fb-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.user{display:flex;align-items:center;gap:10px}
.avatar{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#eef2ff;color:#1f2937;font-weight:700}
.stars{color:#f59e0b}
.note{color:#6b7280}
.small{font-size:13px;color:#6b7280}
</style>
</head>
<body>

<!-- ===== الهيدر نفسه (جرس فقط) ===== -->
<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left">
      <button class="icon-btn" id="openSidebar" aria-label="Menu"><i class="fa-solid fa-gear"></i></button>
      <div class="brand"><img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo"></div>
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

<div class="page">
  <div class="header">
    <h2 class="section-title" style="margin:0">Order details</h2>
  </div>
 <div class="meta-top" style="margin-top:12px;">order id #<?= (int)$order['id'] ?></div>

  <!-- ===== Basic info ===== -->
  <h3 class="section-title" style="margin-top:20px">Basic info</h3>
  <div class="card">
    <table class="table-like">
      <tr>
        <td class="key">Order Status</td>
        <td><span class="badge <?= status_badge_class($order['status']) ?>"><?= h($order['status']) ?></span></td>
      </tr>
      <tr>
        <td class="key">Provider</td>
        <td><?= h($order['provider_name'] ?: '—') ?></td>
      </tr>
      <tr>
        <td class="key">Customer</td>
        <td><?= h($order['customer_name'] ?: '—') ?></td>
      </tr>
      <tr>
        <td class="key">Location</td>
        <td><?= h($order['address'] ?: '—') ?></td>
      </tr>
      <tr>
        <td class="key">Service Type</td>
        <td><?= h($order['service_title'] ?: '—') ?></td>
      </tr>
      <tr>
        <td class="key">What Included Services</td>
        <td>
          <?php if ($included): ?>
            <?= h(implode(' • ', $included)) ?>
          <?php else: ?>
            <span class="small">No included items recorded</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td class="key">Date &amp; Time</td>
        <td><?= h(fmt_dt($order['scheduled_at'])) ?></td>
      </tr>
    </table>
  </div>

  <!-- ===== Customer feedback ===== -->
  <h3 class="section-title" style="margin-top:18px">customer feedback</h3>
  <?php if ($review): ?>
    <div class="fb-card">
      <div class="fb-header">
        <div class="user">
          <div class="avatar" aria-hidden="true">
            <?php
              $nm = trim((string)$review['customer_name']);
              $ini = $nm ? mb_strtoupper(mb_substr($nm,0,1)) : 'C';
              echo h($ini);
            ?>
          </div>
          <div>
            <div style="font-weight:700"><?= h($review['customer_name'] ?: 'Customer') ?></div>
            <div class="small"><?= h($order['service_title'] ?: '') ?></div>
          </div>
        </div>
        <div class="stars" title="<?= (float)$review['rating'] ?>">
          <?php
            $r = (int)round((float)$review['rating']);
            for($i=1;$i<=5;$i++){
              echo $i <= $r ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
            }
          ?>
          <span class="small" style="margin-left:6px"><?= number_format((float)$review['rating'],1) ?></span>
        </div>
      </div>
      <?php if (trim((string)$review['comment']) !== ''): ?>
        <div class="note">“<?= h($review['comment']) ?>”</div>
      <?php else: ?>
        <div class="note small">No written feedback.</div>
      <?php endif; ?>
      <div class="small" style="margin-top:8px">
        Reviewed on : <?= h(date('Y-m-d', strtotime($review['created_at']))) ?>
      </div>
    </div>
  <?php else: ?>
    <div class="fb-card">
      <div class="small">No feedback for this order.</div>
    </div>
  <?php endif; ?>
</div>

</body>
</html>