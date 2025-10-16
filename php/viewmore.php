<?php
// ====== DB ======
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { $fatal_err = "DB connection failed: ".$conn->connect_error; }
else { $conn->set_charset("utf8mb4"); }

// ====== Search ======
$q = isset($_GET['q']) ? trim(strip_tags($_GET['q'])) : '';
if (mb_strlen($q) > 120) $q = mb_substr($q, 0, 120);

// ====== Query ======
$rows = [];
if (empty($fatal_err)) {
  $sql = "SELECT s.*, u.full_name AS provider_name
          FROM services s
          LEFT JOIN users u ON s.provider_id = u.id
          WHERE s.is_active = 1";
  if ($q !== '') {
    $safe = $conn->real_escape_string($q);
    $sql .= " AND (s.description LIKE '%$safe%' OR s.category LIKE '%$safe%')";


   }
  $res = $conn->query($sql);
  if ($res === false) { $fatal_err = "DB query error: ".$conn->error; }
  else { while($r=$res->fetch_assoc()) $rows[]=$r; $res->free(); }
  $conn->close();
}

// ====== Utils ======
function fmt_price($n){ $s=number_format((float)$n,2,'.',''); return rtrim(rtrim($s,'0'),'.'); }
$BASE = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Fixora landing: HTML, CSS, JS" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Galada&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
  <title>Fixora</title>
  <link rel="stylesheet" href="<?= $BASE ?>/css/viewmore.css" />
  <script defer src="<?= $BASE ?>/js/viewmore.js"></script>
</head>
<body>

<!-- ===================== HERO (كما هو من HTML) ===================== -->
<section class="section-hero">
  <header class="navbar">
    <div class="logo-wrap">
      <img src="<?= $BASE ?>/image/home-logo.png" class="logo" alt="Fixora logo" />
    </div>
    <nav class="nav-links">
      <a href="<?= $BASE ?>/index.html">Home</a>
      <a href="#">About Us</a>
      <a href="#">Contact</a>
      <a href="<?= $BASE ?>/php/viewmore.php">Service</a>
    </nav>
    <div class="auth">
      <a href="<?= $BASE ?>/login.html" class="btn login">login</a>
      <a href="<?= $BASE ?>/register.html" class="btn signup">Signup</a>
    </div>
  </header>

  <div class="hero">
    <div class="hero-left">
      <h1 class="title">
        <span class="big">Professional</span>
        <span class="hl">Cleaning</span>
        <span class="big">services</span>
      </h1>

      <p class="sub">
        Professional cleaning services for a spotless<br />
        and healthy home – from everyday tidying to<br />
        deep cleaning kitchen &amp; bathroom sanitizing,<br />
        and upholstery care
      </p>

      <!-- <button class="book-btn">
        Book Now
        <span class="btn-circle">
          <img src="<?= $BASE ?>/image/Vector.png" alt="arrow" class="btn-icon">
        </span>
      </button> -->

      <button class="book-btn" onclick="window.location.href='<?= $BASE ?>/php/book.php'">
  Book Now
  <span class="btn-circle">
    <img src="<?= $BASE ?>/image/Vector.png" alt="arrow" class="btn-icon">
  </span>
</button>
      <div class="vip">
        <div class="faces">
          <img src="<?= $BASE ?>/image/Ellipse%203.png" class="face" alt="">
          <img src="<?= $BASE ?>/image/Ellipse%204.png" class="face" alt="">
          <img src="<?= $BASE ?>/image/Ellipse%205.png" class="face" alt="">
          <span class="more">+100</span>
        </div>
        <span class="vip-text">Our VIP Clients</span>
      </div>

      <div class="stats">
        <div class="stat" data-bg="150">
          <div class="num">150%</div>
          <div class="label">Team Members By<br>Our Service</div>
        </div>
        <div class="stat" data-bg="2300">
          <div class="num">2,300+</div>
          <div class="label">Project Completed By<br>Our Service</div>
        </div>
      </div>
    </div>


<div class="hero-right">
      <svg class="dotted" width="612" height="612" viewBox="0 0 612 612" xmlns="http://www.w3.org/2000/svg">
        <circle cx="306" cy="306" r="305" fill="none" stroke="#9aa0a6" stroke-width="2" stroke-dasharray="30 30" opacity="0.35"/>
      </svg>

   
      <!-- <img src="<?= $BASE ?> image/pngegg - 2022-12-31T123205 1.png" class="hero-img" alt="hero">
      <div class="float fi1 shape-tri">
        <img src="<?= $BASE ?>/image/photo_2025-08-22_14-45-16.jpg" alt="">
      </div>
      <div class="float fi2 shape-circ">
        <img src="<?= $BASE ?>/image/Rectangle%2012.png" alt="">
      </div>
      <div class="float fi3 shape-sq">
        <img src="<?= $BASE ?>/image/Rectangle%2013.png" alt="">
      </div>
    </div> -->

    <!-- البطل -->
<img src="<?= $BASE ?>/image/pngegg -2022-12-31T123205 1.png" class="hero-img" alt="hero">
  
<!-- الفلوتس -->
<div class="float fi1 shape-tri">
  <img src="<?= $BASE ?>/image/photo_2025-08-22_14-45-16.jpg" alt="">
</div>
<div class="float fi2 shape-circ">
  <img src="<?= $BASE ?>/image/Rectangle%2012.png" alt="">
</div>
<div class="float fi3 shape-sq">
  <img src="<?= $BASE ?>/image/Rectangle%2013.png" alt="">
</div>
  </div>
</section>


<header class="topbar">
  <div class="container">
    <form class="search-bar" action="" method="get">
      <input type="search" class="search-input" name="q" value="<?= htmlspecialchars($q,ENT_QUOTES) ?>" placeholder="Find your service" />
      <button type="submit" class="filter-btn" aria-label="Filter">
        <i class="fa-solid fa-sliders"></i>
      </button>
    </form>
  </div>
</header>

<!-- ===================== LAYOUT ===================== -->
<main class="container layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <p class="sidebar-label">Popular</p>
    <details class="cat" <?= $q===''?'open':''?>>
      <summary>Cleaning</summary>
      <ul class="sublist">
     <li><a href="?q=home%20cleaning">Home Cleaning</a></li>
<li><a href="?q=bedroom">Bedroom Cleaning</a></li>
<li><a href="?q=office">Office Cleaning</a></li>
<li><a href="?q=holiday%20home">Holiday Home Cleaning</a></li>
<li><a href="?q=post-construction">Post-Construction Cleaning</a></li>
      </ul>
    </details>
    <details class="cat">
      <summary>Plumbing</summary>
      <ul class="sublist">
       <li><a href="?q=leak">Leak repair</a></li>
     <li><a href="?q=pipe%20installation">Pipe Installation & Replacement</a></li>
    <li><a href="?q=drain">Drain & Pipe Cleaning</a></li>
    <li><a href="?q=faucet">Faucet & Sink Installation/Repair</a></li>
    <li><a href="?q=water%20heater">Water Heater Services</a></li>
    <li><a href="?q=emergency%20plumbing">Emergency Plumbing</a></li>
      </ul>
    </details>
    <details class="cat">
      <summary>Electrical</summary>
      <ul class="sublist">
   <li><a href="?q=home%20electrical%20repair">Home Electrical Repair</a></li>
   <li><a href="?q=switch">Switch & Outlet Installation/Repair</a></li>
  <li><a href="?q=panel">Electrical Panel (Breaker) Maintenance</a></li>
  <li><a href="?q=lighting">Lighting Installation & Maintenance</a></li>
   <!-- <li><a href="?q=generator">Generator Maintenance</a></li> -->
   <li><a href="?q=voltage">Voltage Fluctuation Solutions</a></li>
      </ul>
    </details>
  </aside>

  <!-- Cards (من القاعدة) -->
  <section class="grid">
    <?php if (!empty($fatal_err)): ?>
      <div class="debug-msg"><?= htmlspecialchars($fatal_err,ENT_QUOTES) ?></div>
    <?php elseif (empty($rows)): ?>
      <div class="debug-msg">لا توجد نتائج<?= $q!==''?' لعبارة: '.htmlspecialchars($q,ENT_QUOTES):'' ?>.</div>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <?php
          $imgDb = $row['img_path']; // مثال: image/Bedroom Cleaning.jpg
          $imgUrl = $BASE . '/' . dirname($imgDb) . '/' . rawurlencode(basename($imgDb));
          $pFrom = fmt_price($row['price_from']);
          $pTo   = fmt_price($row['price_to']);
          $prov  = $row['provider_name'] ?: 'Unknown';
        ?>
        <article class="card">
          <img class="thumb"
               src="<?= htmlspecialchars($imgUrl,ENT_QUOTES) ?>"
               alt="<?= htmlspecialchars($row['description'],ENT_QUOTES) ?>"
               onerror="this.src='<?= $BASE ?>/image/placeholder.jpg'">
          <div class="card-body">
            <h3 class="title"><?= htmlspecialchars($row['description']) ?></h3>
            <p class="line-1">Service provider: <a href="#" class="name-emp"><?= htmlspecialchars($prov) ?></a></p>
            <p class="line-2">Start from <span class="price"><?= $pFrom ?>–<?= $pTo ?>$</span></p>


              <div class="rating">
  <i class="fa-solid fa-star" style="color:#f5c518;"></i>
  <span><?= htmlspecialchars((string)$row['rating']) ?></span>
</div>
            </div>
          </div>
          <!-- <a class="view" href="<?= $BASE ?>/details.html">View More</a> -->
         <!-- <a class="view" href="php/services.php?id=<?= (int)$row['id'] ?>">View More</a> -->
          <!-- <a class="view" href="<?= $BASE ?>/php/services.php?id=<?= (int)$row['id'] ?>">View More</a> -->
           <a class="view" href="<?= $BASE ?>/php/service.php?id=<?= (int)$row['id'] ?>">View More</a>

        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>



<footer class="site-footer">
  <div class="footer-container">


    <div class="footer-col footer-brand">
      <div class="brand-row">
        <img src="../image/home-logo.png" alt="Fixora logo" class="brand-logo">
      </div>

      <p class="brand-desc">
        Our Go-To Platform For Cleaning, Plumbing, And Electrical Maintenance
        Services With Live Tracking And Special Discounts.
      </p>

      <ul class="social">
        <li>
          <a class="soc fb" href="https://facebook.com/yourpage" target="_blank" rel="noopener" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
        </li>
        <li>
          <a class="soc ig" href="https://instagram.com/yourhandle" target="_blank" rel="noopener" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
          </a>
        </li>
        <li>
          <a class="soc x" href="https://x.com/yourhandle" target="_blank" rel="noopener" aria-label="X">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
        </li>
        <li>
          <a class="soc li" href="https://www.linkedin.com/company/yourcompany" target="_blank" rel="noopener" aria-label="LinkedIn">
            <i class="fa-brands fa-linkedin-in"></i>
          </a>
        </li>
      </ul>
    </div>

    <div class="footer-col">
      <h4 class="col-title">Company</h4>
      <ul class="col-links">
        <li><a href="#">About Us</a></li>
        <li><a href="#">Careers</a></li>
        <li><a href="#">Contact Us</a></li>
        <li><a href="#">Terms Of Service</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4 class="col-title">Services</h4>
      <ul class="col-links">
        <li><a href="#">About Us</a></li>
        <li><a href="#">Careers</a></li>
        <li><a href="#">Contact Us</a></li>
        <li><a href="#">How It Works</a></li>
        <li><a href="#">Terms Of Service</a></li>
      </ul>
    </div>

  
    <div class="footer-col">
      <h4 class="col-title">Contact Information</h4>
      <ul class="contact-list">
        <li><i class="fa-solid fa-location-dot"></i> Gaza – Palestine</li>
        <li>
          <i class="fa-solid fa-envelope"></i>
          <a href="mailto:Fixora2025@gmail.com">Fixora2025@gmail.com</a>
        </li>
        <li>
          <i class="fa-solid fa-phone"></i>
          <a href="tel:+972597789185">+972 592643752</a>
        </li>
      </ul>
    </div>

  </div>

  <p class="footer-copy">© 2025 All Rights Reserved — <span class="brand">Fixora</span></p>
</footer>


</body>
</html>