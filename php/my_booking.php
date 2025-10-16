<?php
// mp/php/my_booking.php
session_start();
$BASE = '/mp';

if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

// تم إصلاح الشرط هنا - إزالة التحقق من provider
if (empty($_SESSION['user_id'])) {
 header("Location: /mp/login.html");
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { 
    http_response_code(500); 
    die("DB failed: ".$conn->connect_error); 
}
$conn->set_charset("utf8mb4");

// تبويب
$tab = isset($_GET['tab']) ? strtolower(trim($_GET['tab'])) : 'all';
if (!in_array($tab, ['all','upcoming','pending','past','cancelled'], true)) $tab = 'all';

/* ===== جلب الحجوزات ===== */
$sql = "
SELECT
  b.id,
  b.scheduled_at,
  COALESCE(NULLIF(b.status, ''), 'pending') AS raw_status,
  b.address,
  s.id            AS service_id,
  s.title         AS service_title,
  s.img_path      AS service_img,
  s.rating        AS service_rating,
  u.full_name     AS provider_name
FROM bookings b
JOIN services s   ON s.id = b.service_id
LEFT JOIN users u ON u.id = s.provider_id
WHERE b.customer_id = {$uid}
ORDER BY b.scheduled_at DESC, b.id DESC";

$res = $conn->query($sql);
if (!$res) { 
    die("SQL error (bookings): ".$conn->error); 
}

$rows = [];
$now  = new DateTime('now');

while ($r = $res->fetch_assoc()) {
    $raw_status = $r['raw_status'];
    if ($raw_status === null || $raw_status === '' || $raw_status === 'null') {
        $raw_status = 'pending';
    }
    
    $when = new DateTime($r['scheduled_at']);
    $current_now = new DateTime('now');
    
    $raw_lower = strtolower($raw_status);
    switch ($raw_lower) {
        case 'cancelled':
            $display_status = 'cancelled';
            break;
        case 'completed':
            $display_status = 'past';
            break;
        case 'confirmed':
            $display_status = ($when < $current_now) ? 'past' : 'upcoming';
            break;
        case 'pending':
        default:
            $display_status = 'pending';
            break;
    }

    $r['status'] = $raw_status;
    $r['display_status'] = $display_status;
    $rows[] = $r;
}

/* ===== الحجوزات التي تم تقييمها ===== */
$reviewed = [];
$qr = $conn->query("SELECT booking_id FROM service_reviews WHERE customer_id = {$uid}");
if ($qr) {
    while ($q = $qr->fetch_assoc()) {
        $reviewed[] = (int)$q['booking_id'];
    }
}

$conn->close();

function img_url($dbPath, $base){
    if (!$dbPath) return $base . "/image/placeholder.jpg";
    if (preg_match('~^https?://~i', $dbPath)) return $dbPath;
    $dbPath = ltrim($dbPath,'/');
    $dir  = dirname($dbPath);
    $file = basename($dbPath);
    return $base . '/' . $dir . '/' . rawurlencode($file);
}

if ($tab !== 'all') {
    $rows = array_values(array_filter($rows, function($r) use ($tab) {
        return $r['display_status'] === $tab;
    }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Fixora – My Booking</title>
    <link rel="stylesheet" href="<?= $BASE ?>/css/my_booking.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        .modal[hidden]{display:none}
        .modal{position:fixed;inset:0;z-index:9999;display:grid;place-items:center}
        .modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px)}
        .modal__dialog{position:relative;width:min(520px,92vw);background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.18);overflow:hidden}
        .modal__header{padding:14px 18px;border-bottom:1px solid #eef2f7;display:flex;align-items:center;justify-content:space-between}
        .modal__header h3{margin:0;font-size:18px;font-weight:800;color:#0f172a}
        .modal__close{width:36px;height:36px;border:0;border-radius:10px;background:#f8fafc;cursor:pointer;font-size:22px;line-height:1}

        .modal__body{padding:16px 18px 0}
        .modal__footer{padding:14px 18px 18px;display:flex;gap:10px;justify-content:flex-end}
        .modal__body .label{display:block;margin:0 0 8px;font-weight:700;font-size:13px;color:#1f2937}
        #resDate{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;font-size:14px}
        .hint{margin:8px 0 0;font-size:12px;color:#64748b}
        .modal__error{margin:10px 18px 0;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:13px}

        /* أزرار مخصصة */
        .btn.lg{height:48px;min-width:170px;font-size:16px;border-radius:12px;font-weight:600;border:2px solid;cursor:pointer;}
        .btn.cool-blue{background:#d6e9ff;color:#111827;border-color:#a4c8f8;}
        .btn.cool-blue:hover{background:#c5e0ff}
        .btn.soft-gray{background:#f3f4f6;color:#4b5563;border-color:#e5e7eb;}
        .btn.soft-gray:hover{background:#eceef1}
        .btn.primary-action{background:#2563eb;color:white;border-color:#2563eb;}
        .btn.primary-action:hover{background:#1d4ed8}
        .btn.white-red{background:white;color:#dc2626;border-color:#dc2626;}
        .btn.white-red:hover{background:#fef2f2;}
        .btn.dark-blue{background:#1e40af;color:white;border-color:#1e40af;}
        .btn.dark-blue:hover{background:#1e3a8a;}
        .btn.light-green{background:#dcfce7;color:#16a34a;border-color:#16a34a;}
        .btn.light-green:hover{background:#bbf7d0;}
        .btn.light-red{background:#fecaca;color:#dc2626;border-color:#dc2626;}
        .btn.light-red:hover{background:#fca5a5;}
        
        /* تحسينات للعرض */
        .empty { text-align: center; padding: 40px; color: #666; }
        .empty img { max-width: 200px; margin-bottom: 20px; }
        
        /* تحسين عرض الحالة - بدون بورد */
        .status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.upcoming { background: #d1ecf1; color: #0c5460; }
        .status.past { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        
        /* إزالة حدود الكارد */
        .service-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .service-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        /* تحسينات للأزرار */
        .card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .card-actions .btn.lg {
            flex: 1;
            min-width: 150px;
            max-width: 100%;
            text-align: center;
        }
        
        /* تحسينات خاصة لتبويب Cancelled */
        .tab-cancelled-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .tab-cancelled-actions .btn {
            min-width: 160px;
        }

        /* نظام الإشعارات */
        .notification-bell {
            position: relative;
            margin-left: 15px;
        }

        /* زر الجرس */
        .notif-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 12px;
            background: #eff6ff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #1d4ed8;
            box-shadow: 0 3px 8px rgba(29,78,216,0.25);
            transition: all 0.3s ease;
        }

        .notif-btn:hover {
            background: #dbeafe;
            color: #1e3a8a;
            transform: scale(1.05);
        }

        .notif-btn .fa-bell {
            color: inherit;
        }

        /* عداد الإشعارات */
        .notif-count {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ef4444;
            color: #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }

        /* أنيميشن للجرس */
        .pulse::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            transform: translate(-50%,-50%);
            background: rgba(29,78,216,0.25);
            animation: pulseAnim 1.5s infinite;
            z-index: -1;
        }
        @keyframes pulseAnim {
            0% { transform: translate(-50%,-50%) scale(1); opacity: 0.6; }
            100% { transform: translate(-50%,-50%) scale(1.6); opacity: 0; }
        }

        /* الدروب داون */
        .notif-dropdown {
            position: absolute;
            top: 110%;
            right: 0;
            width: 380px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .notif-dropdown.show { display: block; }
        @keyframes fadeIn {
            from {opacity:0; transform: translateY(-10px);}
            to {opacity:1; transform: translateY(0);}
        }

        /* العناصر */
        .notif-item {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .notif-item.unread {
            background: #f0f9ff;
            border-left: 3px solid #3b82f6;
        }
        .notif-item strong { font-size: 14px; color:#111; }
        .notif-item span { font-size: 13px; color:#555; }
        .notif-item small { font-size: 11px; color:#888; }

        .notif-item i {
            color: #3b82f6;
            font-size: 16px;
            margin-top: 2px;
        }

        .notif-content {
            flex: 1;
        }

        .notif-content strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .notif-content span {
            display: block;
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 2px;
        }

        .notif-content small {
            font-size: 11px;
            color: #9ca3af;
        }

        .notif-footer {
            padding: 12px 15px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
        }

        .notif-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .notif-footer a:hover {
            text-decoration: underline;
        }

        /* Bell blue like provider dashboard */
        .notif-btn{
            background:#eff6ff;
            border-color:#bfdbfe;
            color:#1d4ed8;
        }
        .notif-btn:hover{
            background:#dbeafe;
            color:#1e40af;
        }
        .notif-btn .fa-bell{ color:inherit; }

        /* رسالة النجاح */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            text-align: center;
            font-weight: bold;
        }





















    </style>
</head>
<body>
<!-- رسالة النجاح -->
<?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <div class="success-message">
        ✅ تم إنشاء الحجز بنجاح! رقم الحجز: <?= $_GET['booking_id'] ?? '' ?>
    </div>
<?php endif; ?>

<header class="navbar">
    <div class="logo-wrap">
        <img src="<?= $BASE ?>/image/home-logo.png" class="logo" alt="Fixora logo" />
    </div>
    <nav class="nav-links">
        <a href="<?= $BASE ?>/index.html">Home</a>
        <a href="aboutus.php">About Us</a>
        <a href="<?= $BASE ?>/contact.html">Contact</a>
        <a href="<?= $BASE ?>/php/viewmore.php">Services</a>
    </nav>

    <div class="notification-bell">
        <button class="notif-btn pulse" id="notifButton">
            <i class="fa-solid fa-bell"></i>
            <span class="notif-count" id="notifCount">0</span>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
                <h4>إشعاراتك</h4>
                <span class="notif-badge" id="notifBadge">0 جديد</span>
            </div>
            <div class="notif-list" id="notifList">
                <div class="notif-item">جاري التحميل...</div>
            </div>
        </div>
    </div>

    <div class="profile-menu">
        <button class="profile-trigger" aria-expanded="false">
     <img class="avatar" src="../image/avater.jpg?<?= (int)$_SESSION['user_id'] ?>" alt="Profile">
            <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        <div class="menu-card" hidden>
            <a class="menu-item" href="<?= $BASE ?>/php/my_booking.php"><span>My Bookings</span></a>
            <hr class="divider">
            <a class="menu-item" href="Account settings.php"><span>Account Settings</span></a>
            <hr class="divider">
            <a class="menu-item danger" href="<?= $BASE ?>/php/logout.php"><span>Log Out</span></a>
        </div>
    </div>
</header>

<section class="hero-booking">
    <div class="container">
        <button class="hero-tag">My Booking</button>
        <p class="hero-line blue">Track and manage your upcoming and past bookings easily</p>
    </div>
</section>

<section class="booking-section">
    <div class="container">
        <!-- Tabs -->
        <div class="tabs">
            <a class="tab <?= $tab==='all'?'active':'' ?>" href="?tab=all">All</a>
            <a class="tab <?= $tab==='upcoming'?'active':'' ?>" href="?tab=upcoming">Upcoming</a>
            <a class="tab <?= $tab==='pending'?'active':'' ?>" href="?tab=pending">Pending</a>
            <a class="tab <?= $tab==='past'?'active':'' ?>" href="?tab=past">Past</a>
            <a class="tab <?= $tab==='cancelled'?'active':'' ?>" href="?tab=cancelled">Cancelled</a>
        </div>

        <!-- Cards -->
        <div class="services-grid">
            <?php if (empty($rows)) { ?>
                <div class="empty">
                    <img src="<?= $BASE ?>/image/photo_2025-08-27_17-22-03.jpg" alt="No bookings">
                    <p>No bookings found for this tab.</p>
                </div>
            <?php } else { ?>
                <?php foreach ($rows as $r) {
                    $img  = img_url($r['service_img'], $BASE);
                    $dt   = (new DateTime($r['scheduled_at']))->format('M d, Y — h:i A');
                    $stat = $r['status'];
                    $display_stat = $r['display_status'];
                    $rate = number_format((float)$r['service_rating'],1);

                    $status_labels = [
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed', 
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled'
                    ];
                    $label = $status_labels[$stat] ?? ucfirst($stat);
                ?>
                <div class="service-card">
                    <img src="<?= htmlspecialchars($img) ?>" alt="Service" class="service-img"
                         onerror="this.src='<?= $BASE ?>/image/placeholder.jpg'">

                    <div class="service-info">
                        <h4>
                            <?= htmlspecialchars($r['service_title']) ?>
                            <span class="status <?= htmlspecialchars($display_stat) ?>">
                                <?= htmlspecialchars($label) ?>
                            </span>
                        </h4>
                        <p>Service provider: <a href="#"><?= htmlspecialchars($r['provider_name'] ?: 'Unknown') ?></a></p>
                        <p>Location: <a href="#"><?= htmlspecialchars($r['address'] ?: '—') ?></a></p>
                        <p>Date &amp; Time: <a href="#"><?= htmlspecialchars($dt) ?></a></p>
                        <p class="rating-line">
                            <i class="fa-solid fa-star"></i>
                            <span><?= htmlspecialchars($rate) ?></span>
                        </p>

                        <!-- الأزرار حسب التبويب والحالة -->
                        <div class="card-actions <?= $tab === 'cancelled' ? 'tab-cancelled-actions' : '' ?>">
                            <?php if ($tab === 'all') { ?>
                                <!-- في تبويب All - كل الحالات تظهر الزرين -->
                                <button class="btn primary-action lg js-reschedule"
                                        data-booking-id="<?= (int)$r['id'] ?>"
                                        data-current-dt="<?= htmlspecialchars($r['scheduled_at']) ?>">
                                    Reschedule
                                </button>
                                <button class="btn white-red lg js-cancel"
                                        data-booking-id="<?= (int)$r['id'] ?>">
                                    Cancel
                                </button>

                            <?php } elseif ($tab === 'upcoming') { ?>
                                <?php if ($display_stat === 'upcoming' || $display_stat === 'pending') { ?>
                                    <button class="btn primary-action lg js-reschedule"
                                            data-booking-id="<?= (int)$r['id'] ?>"
                                            data-current-dt="<?= htmlspecialchars($r['scheduled_at']) ?>">
                                        Reschedule
                                    </button>
                                    <button class="btn white-red lg js-cancel"
                                            data-booking-id="<?= (int)$r['id'] ?>">
                                        Cancel
                                    </button>
                                <?php } ?>

                            <?php } elseif ($tab === 'past') { ?>
                                <?php if (!in_array((int)$r['id'], $reviewed, true)) { ?>
                                    <button class="btn dark-blue lg"
                                            onclick="location.href='<?= $BASE ?>/php/rating.php?booking_id=<?= (int)$r['id'] ?>'">
                                        Rate
                                    </button>
                                <?php } else { ?>
                                    <button class="btn soft-gray lg" disabled>
                                        Rated
                                    </button>
                                <?php } ?>
                                
                                <a class="btn light-green lg" href="<?= $BASE ?>/php/service.php?id=<?= (int)$r['service_id'] ?>">
                                    Book Again
                                </a>

                            <?php } elseif ($tab === 'cancelled') { ?>
                                <!-- في تبويب Cancelled - زر واحد فقط -->
                                <a class="btn light-red lg" href="<?= $BASE ?>/php/service.php?id=<?= (int)$r['service_id'] ?>" 
                                   style="width: 100%; max-width: 350px; display: block; margin: 0 auto; padding: 14px 20px; line-height: 1.2;">
                                    Book Again
                                </a>
                            <?php } elseif ($tab === 'pending') { ?>
                                <button class="btn soft-gray lg" disabled style="width: 100%; max-width: 350px;">
                                    Waiting for Confirmation
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</section>

<!-- مودال إعادة الجدولة -->
<div class="modal" id="resModal" hidden>
    <div class="modal__backdrop" data-close="1"></div>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="resTitle">
        <div class="modal__header">
            <h3 id="resTitle">Reschedule Booking</h3>
            <button class="modal__close" aria-label="Close" data-close="1">&times;</button>
        </div>
        <div class="modal__body">
            <form id="resForm">
                <input type="hidden" name="booking_id" id="resBookingId">
                <label class="label" for="resDate">New Date &amp; Time</label>
                <input type="datetime-local" id="resDate" name="new_datetime" required>
                <p class="hint">Choose a suitable date and time.</p>
            </form>
            <div id="resError" class="modal__error" hidden></div>
        </div>
        <div class="modal__footer">
            <button class="btn soft-gray" data-close="1" type="button">Cancel</button>
            <button class="btn primary-action" id="resSubmit" type="button">Save Changes</button>
        </div>
    </div>
</div>

<!-- مودال إلغاء الحجز -->
<div class="modal" id="cancelModal" hidden>
    <div class="modal__backdrop" data-close="1"></div>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
        <div class="modal__header">
            <h3 id="cancelTitle">Cancel Booking</h3>
            <button class="modal__close" aria-label="Close" data-close="1">&times;</button>
        </div>
        <div class="modal__body">
            <p>Are you sure you want to cancel this booking?</p>
            <form id="cancelForm">
                <input type="hidden" name="booking_id" id="cancelBookingId">
            </form>
            <div id="cancelError" class="modal__error" hidden></div>
        </div>
        <div class="modal__footer">
            <button class="btn soft-gray" data-close="1" type="button">Keep Booking</button>
            <button class="btn white-red" id="cancelConfirm" type="button">Yes, Cancel</button>
        </div>
    </div>
</div>

<script>
// Dropdown البروفايل
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
  document.addEventListener('click', ()=> openMenu(false));
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') openMenu(false); });
  card.addEventListener('click', (e)=> e.stopPropagation());
})();
</script>

<script>
/* ==== Reschedule ==== */
(function(){
  const modal = document.getElementById('resModal');
  const resForm = document.getElementById('resForm');
  const resDate = document.getElementById('resDate');
  const resBookingId = document.getElementById('resBookingId');
  const resSubmit = document.getElementById('resSubmit');
  const resError = document.getElementById('resError');

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.js-reschedule');
    if (!btn) return;
    const bookingId = btn.dataset.bookingId;
    const currentDt = btn.dataset.currentDt;
    resBookingId.value = bookingId || '';
    resError.hidden = true; resError.textContent = '';
    if (currentDt && currentDt.length >= 16) {
      const dtLocal = currentDt.replace(' ', 'T').slice(0,16);
      resDate.value = dtLocal;
    } else {
      resDate.value = '';
    }
    modal.hidden = false;
  });

  modal?.addEventListener('click', (e)=>{
    if (e.target.dataset.close === '1') modal.hidden = true;
  });

  resSubmit?.addEventListener('click', async ()=>{
    if (!resDate.value || !resBookingId.value){
      resError.textContent = 'Please fill the date/time.';
      resError.hidden = false;
         return;
    }
    const fd = new FormData(resForm);
    try{
      const resp = await fetch('reschedule.php', { method:'POST', body:fd });
      const data = await resp.json().catch(()=>({ok:false,error:'Invalid response'}));
      if (data.ok){
        location.reload();
      }else{
        resError.textContent = data.error || 'Failed to reschedule.';
        resError.hidden = false;
      }
    }catch(err){
      console.error(err);
      resError.textContent = 'Network error.';
      resError.hidden = false;
    }
  });
})();
</script>

<script>
/* ==== Cancel (تأكيد + حذف فوري بدون ريفرش) ==== */
(function(){
  const modal = document.getElementById('cancelModal');
  if (!modal) return;

  const cancelForm      = document.getElementById('cancelForm');
  const cancelBookingId = document.getElementById('cancelBookingId');
  const cancelError     = document.getElementById('cancelError');
  const cancelConfirm   = document.getElementById('cancelConfirm');

  let cardToRemove = null;
  let busy = false;

  function showModal(){
    modal.hidden = false;
    modal.style.display = 'grid';
  }
  function hideModal(){
    modal.hidden = true;
    modal.style.display = '';
    cardToRemove = null;
  }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.js-cancel');
    if (!btn) return;

    e.preventDefault();
    cancelBookingId.value = btn.dataset.bookingId || '';
    cancelError.hidden = true; 
    cancelError.textContent = '';
     cardToRemove = btn.closest('.service-card');
    showModal();
  });

  modal.addEventListener('click', (e)=>{
    if (e.target.dataset.close === '1') hideModal();
  });

  cancelConfirm?.addEventListener('click', async ()=>{
    if (busy) return;
    busy = true;
    cancelConfirm.disabled = true;
    cancelConfirm.textContent = 'Deleting...';

    const fd = new FormData(cancelForm);
    try{
      const resp = await fetch('cancel-booking.php', { method:'POST', body: fd });
      const data = await resp.json().catch(()=>({ok:false,error:'Invalid response'}));
      if (data.ok){
        if (cardToRemove) {
          cardToRemove.style.opacity = '0.6';
          cardToRemove.style.transition = 'opacity .15s ease, height .25s ease, margin .25s ease';
          const h = cardToRemove.getBoundingClientRect().height + 'px';
          cardToRemove.style.height = h;
          requestAnimationFrame(()=>{
            cardToRemove.style.height = '0px';
            cardToRemove.style.margin = '0';
            setTimeout(()=> cardToRemove.remove(), 250);
          });
        }
        hideModal();
      } else {
        cancelError.textContent = data.error || 'Failed to delete booking.';
        cancelError.hidden = false;
      }
    }catch(err){
      console.error(err);
      cancelError.textContent = 'Network error.';
      cancelError.hidden = false;
    } finally {
      busy = false;
      cancelConfirm.disabled = false;
      cancelConfirm.textContent = 'Yes, Delete';
    }
  });
})();
</script>

<script>
(function(){
  const btn    = document.getElementById('notifButton');
  const dd     = document.getElementById('notifDropdown');
  const listEl = document.getElementById('notifList');
  const badge  = document.getElementById('notifCount');

  const API_LIST = 'get notifications.php';        // عدّلي المسار اذا لزم
  const API_MARK = 'mark notifications reed.php';

  function setBadge(n){
    n = Number(n)||0;
    badge.textContent = n;
    badge.style.display = n>0 ? 'flex' : 'none';
  }

  function escapeHTML(s){
    const div = document.createElement('div'); div.textContent = s ?? '';
    return div.innerHTML;
  }

  async function fetchNotifications(){
    const r = await fetch(API_LIST, {cache:'no-store', credentials:'include'});
    const data = await r.json();
    if (!data.success) return;

    setBadge(data.unread_count);

    const arr = data.notifications || [];
    if (!listEl) return;

    if (arr.length === 0){
      listEl.innerHTML = `
        <div class="notif-empty">
          <div class="empty-icon">🔔</div>
          <div class="empty-text">لا توجد إشعارات</div>
          <div class="empty-sub">سيظهر هنا أي إشعار جديد</div>
        </div>`;
    } else {
      listEl.innerHTML = arr.map(n => `
        <div class="notif-item ${Number(n.is_read)?'read':'unread'}">
          <div class="notif-icon">${n.type==='new_booking'?'📅':'🔔'}</div>
          <div class="notif-content">
            <div class="notif-title">${escapeHTML(n.title)}</div>
            <div class="notif-message">${escapeHTML(n.message)}</div>
            <div class="notif-time">${n.created_at}</div>
          </div>
          ${Number(n.is_read)?'':'<div class="notif-dot"></div>'}
        </div>`).join('');
    }
  }

  async function markAllAsRead(){
    // تصفير متفائل فوري
    setBadge(0);
    // طلب السيرفر
    try {
      await fetch(API_MARK, {method:'POST', credentials:'include'});
    } catch(e) {}
    // حدث القائمة والعداد من المصدر بعد ما يكمّل
    fetchNotifications();
  }
  window.markAllAsRead = markAllAsRead;

  // فتح/إغلاق القائمة
  if (btn && dd){
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      const open = dd.style.display === 'block';
      if (open){
        dd.style.display = 'none';
      } else {
        // جب القائمة أولاً
        await fetchNotifications();
        dd.style.display = 'block';
        // واعتبر كل الموجود “مقروء” حالًا
        await markAllAsRead();
      }
    });

    // إغلاق عند الضغط خارج
    document.addEventListener('click', (e)=>{
      if (!dd.contains(e.target) && !btn.contains(e.target)){
        dd.style.display = 'none';
      }
    });
  }

  // Poll للعداد (يرجع الرقم لو وصل إشعار جديد بعد الإطلاع)
  async function fetchCountOnly(){
    try{
      const r = await fetch(API_LIST, {cache:'no-store', credentials:'include'});
      const d = await r.json();
      if (d.success) setBadge(d.unread_count);
    }catch(e){}
  }

  // أول تحميل + تكرار
  fetchNotifications();
  setInterval(fetchCountOnly, 15000); // كل 15 ثانية

})();
</script>



<script>
  // فتح البوب عند الضغط على الزر
  (function () {
    const btn = document.getElementById('openAvailabilityBtn');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      // الدالة موجودة عندك فوق وبتفتح + بتعمل تحميل آخر حفظة
      openAvailabilityAndLoad();
    });
  })();
</script>
</body>
</html>