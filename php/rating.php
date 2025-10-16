
<?php
// mp/php/rating.php
session_start();
$BASE = "/mp";

if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html");
  exit;
}
$uid = (int)$_SESSION['user_id'];

// booking_id من GET (أو POST كاحتياط)
$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
if ($bookingId <= 0) { http_response_code(400); die("Missing booking_id"); }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

/* نجلب بيانات الحجز + الخدمة + اسم المزوّد
   مع التأكد أن الحجز يخص المستخدم الحالي */
$sql = "
  SELECT
    b.id           AS booking_id,
    b.customer_id,
    b.service_id,
    b.provider_id,
    b.status,
    s.title        AS service_title,
    u.full_name    AS provider_name
  FROM bookings b
  JOIN services s   ON s.id = b.service_id
  JOIN users   u    ON u.id = s.provider_id
  WHERE b.id = ? AND b.customer_id = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
if(!$stmt){ $conn->close(); http_response_code(500); die("Prepare failed: ".$conn->error); }
$stmt->bind_param("ii", $bookingId, $uid);
$stmt->execute();
$bk = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$bk) { http_response_code(404); die("Booking not found"); }

$serviceId    = (int)$bk['service_id'];
$providerId   = (int)$bk['provider_id'];
$providerName = $bk['provider_name'] ?: "Provider";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rate – <?= htmlspecialchars($providerName) ?></title>

  <link rel="stylesheet" href="<?= $BASE ?>/css/rating.css?v=2">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
</head>
<body>

<!-- ===== Navbar (نفس ستايلك المختصر) ===== -->
<header class="navbar">
  <div class="navbar-inner">
    <div class="logo-wrap">
      <img src="<?= $BASE ?>/image/home-logo.png" class="logo" alt="Fixora logo" />
    </div>
    <nav class="nav-links">
      <a href="<?= $BASE ?>/index.html">Home</a>
      <a href="<?= $BASE ?>/aboutUs.html">About Us</a>
      <a href="<?= $BASE ?>/contact.html">Contact</a>
      <a href="<?= $BASE ?>/php/viewmore.php">Services</a>
    </nav>
    <div class="profile-menu">
      <button class="profile-trigger" aria-expanded="false">
      <img class="avatar" src="/mp/image/avater.jpg" alt="Profile">
        <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <div class="menu-card" hidden>
        <a class="menu-item" href="<?= $BASE ?>/php/my_booking.php"><span>My Bookings</span></a>
        <hr class="divider">
        <a class="menu-item" href="<?= $BASE ?>/php/account-personal.php"><span>Account Settings</span></a>
        <hr class="divider">
        <a class="menu-item danger" href="<?= $BASE ?>/php/logout.php"><span>Log Out</span></a>
      </div>
    </div>
  </div>
</header>

<!-- ===== Rating Card ===== -->
<section class="rating-section">
  <div class="rating-card">
    <h2 class="rating-title">Rate Your Experience With <?= htmlspecialchars($providerName) ?></h2>
    <p class="rating-subtitle">Your Feedback Helps Us Improve</p>

    <!-- النجوم -->
    <div class="stars" id="stars">
      <i class="fa-regular fa-star" data-value="1"></i>
      <i class="fa-regular fa-star" data-value="2"></i>
      <i class="fa-regular fa-star" data-value="3"></i>
      <i class="fa-regular fa-star" data-value="4"></i>
      <i class="fa-regular fa-star" data-value="5"></i>
    </div>

    <!-- التعليق -->
    <textarea id="comment" class="rating-textarea" rows="5"
      placeholder="Write A Short Comment About Your Experience (Optional)"></textarea>


<!-- الأزرار -->
    <div class="rating-actions">
      <button id="btnSubmit" class="btn btn-submit" type="button">Submit</button>
      <button id="btnSkip"   class="btn btn-skip"   type="button">Skip</button>
    </div>
  </div>
</section>



<!-- Thanks Modal -->
<div id="thanksModal" class="thx-overlay" hidden>
  <div class="thx-card">
    <!-- حط مسار صورتك هون -->
    <img class="thx-illustration" src="<?= $BASE ?>/image/photo_2025-08-26_20-44-37.jpg" alt="Success"/>

    <h3 class="thx-title">Thank You! Your Review Has Been Submitted</h3>

    <button id="thxHome" class="thx-btn" type="button">Back To Home</button>
  </div>
</div>






<style>

/* ====== Thank You Modal ====== */
.thx-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.35);
  display: none;          /* أولها none */
  place-items: center;
  z-index: 9999;
}
.thx-overlay.show {
  display: grid;          /* لما نضيف class show */
}
.thx-card{
  width: min(560px, 92vw);
  background: #fff;
  border-radius: 22px;
  box-shadow: 0 18px 50px rgba(0,0,0,.18);
  padding: 36px 28px 28px;
  text-align: center;
}
.thx-illustration{
  width: 140px; height: auto; display:block; margin: 0 auto 16px;
}
.thx-title{
  margin: 0 0 22px;
  font-weight: 800;
  font-size: 20px;
  line-height: 1.35;
  color: #0f172a; /* لون نص واضح */
}
.thx-btn{
  width: 360px; max-width: 100%;
  height: 44px; border: 0; cursor: pointer;
  border-radius: 12px; font-weight: 800; font-size: 15px;
  color: #fff; background: #1e90ff;
  box-shadow: 0 8px 18px rgba(30,144,255,.22);
}
.thx-btn:active{ transform: translateY(1px); }
body.no-scroll{ overflow: hidden; }

</style>


<script>
// ===== Toggle profile dropdown (كما هو) =====


// ===== نجوم التقييم =====
let currentRating = 0;
const stars = document.querySelectorAll("#stars i");
stars.forEach(star => {
  star.addEventListener("click", () => {
    currentRating = parseInt(star.dataset.value);
    stars.forEach(s => {
      const v = parseInt(s.dataset.value);
      if (v <= currentRating){
        s.classList.add("active");
        s.classList.replace("fa-regular","fa-solid");
        s.style.color = "#f5c518";
      } else {
        s.classList.remove("active");
        s.classList.replace("fa-solid","fa-regular");
        s.style.color = "#bbb";
      }
    });
  });
});

// ===== إرسال التقييم =====
document.getElementById('btnSubmit').addEventListener('click', async () => {
  if (currentRating === 0){
    alert("Please select a rating.");
    return;
  }

  const fd = new FormData();
  fd.append('booking_id',  <?= (int)$bookingId ?>);
  fd.append('service_id',  <?= (int)$serviceId ?>);
  fd.append('provider_id', <?= (int)$providerId ?>);
  fd.append('rating',      String(currentRating));
  fd.append('comment',     document.getElementById('comment').value.trim());

  try {
    const resp = await fetch('<?= $BASE ?>/php/review_create.php', {
      method: 'POST',
      body: fd,
      cache: 'no-store',
      credentials: 'same-origin'
    });

    const text = await resp.text(); // نقرأ كنص أولاً
    let data;
    try {
      data = JSON.parse(text);
    } catch(e){
      alert('invalid_json:\n' + text);
      return;
    }

    if (data && data.ok){
      const m = document.getElementById('thanksModal');
      if (m){
        m.classList.add('show');     // لازم CSS فيه .thx-overlay.show { display:grid }
        m.removeAttribute('hidden'); // يشيل hidden
        document.body.classList.add('no-scroll');
      }
    } else {
      alert(data.error || 'Failed to submit review.');
    }
  } catch(e){
    console.error(e);
    alert('Network error.');
  }
});

// تخطّي
document.getElementById('btnSkip').addEventListener('click', () => {
  window.location.href = '<?= $BASE ?>/php/my_booking.php';
});
</script>




<script>

// تفعيل النجوم التفاعلية
const stars = document.querySelectorAll("#stars i");

stars.forEach(star => {
  star.addEventListener("click", () => {
    const value = parseInt(star.getAttribute("data-value"));
    // مر على كل النجوم وفعّل اللون حسب القيمة
    stars.forEach(s => {
      if (parseInt(s.getAttribute("data-value")) <= value) {
        s.classList.add("active");
        s.classList.replace("fa-regular", "fa-solid");
        s.style.color = "#f5c518"; // أصفر مثل التصميم
      } else {
        s.classList.remove("active");
        s.classList.replace("fa-solid", "fa-regular");
        s.style.color = "#bbb"; // يرجع رمادي
      }
    });
  });
});

</script>


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
  // زر العودة للصفحة الرئيسية
  document.getElementById('thxHome')?.addEventListener('click', ()=>{
    window.location.href = '<?= $BASE ?>/index.html';
  });

  // خيار اختياري: Esc يغلق المودال بدون زر إضافي
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape'){
      const m = document.getElementById('thanksModal');
      if (m && !m.hidden){
        m.hidden = true;
        document.body.classList.remove('no-scroll');
      }
    }
  });
</script>

</body>
</html>
