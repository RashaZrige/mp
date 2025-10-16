
<?php

mysqli_report(MYSQLI_REPORT_OFF);

$BASE   = '/mp'; 
$ASSETS = '..';  

$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error){ http_response_code(500); die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0){ http_response_code(404); die("Not found"); }


function fmt_price($n){ $s=number_format((float)$n,2,'.',''); return rtrim(rtrim($s,'0'),'.'); }
function db_img_to_url($dbPath, $base){
  if (!$dbPath) return '';
  if (preg_match('~^https?://~i', $dbPath)) return $dbPath; 
  $dbPath = ltrim($dbPath,'/');               
  $dir  = dirname($dbPath);
  $file = basename($dbPath);
  return $base . '/' . $dir . '/' . rawurlencode($file);
}

$sql = "SELECT
          s.*,
          u.full_name                                AS provider_name,
          u.phone, u.address,
          pp.avatar_path                             AS user_avatar,
          pp.age                                     AS provider_age,
          pp.years_experience
        FROM services s
        LEFT JOIN users u              ON s.provider_id = u.id
        LEFT JOIN provider_profiles pp ON pp.user_id    = s.provider_id
        WHERE s.id = ? AND s.is_active = 1";
$stmt = $conn->prepare($sql);
if (!$stmt) { die("Prepare failed: ".$conn->error."\nSQL: ".$sql); }
$stmt->bind_param("i",$id);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$svc){ $conn->close(); http_response_code(404); die("Service not found"); }


$title        = (string)$svc['title'];
$cat          = (string)$svc['category'];
$desc         = (string)$svc['description'];
$prov         = $svc['provider_name'] ?: 'Unknown';
$phone        = $svc['phone'] ?: '-';
$addr         = $svc['address'] ?: '-';
$pf           = (float)$svc['price_from'];
$pt           = (float)$svc['price_to'];
$rating       = (float)$svc['rating'];
$img_db       = (string)$svc['img_path'];
$provAvatarDb = (string)($svc['user_avatar'] ?? '');
$yearsExp     = isset($svc['years_experience']) ? (int)$svc['years_experience'] : null;
$age          = isset($svc['provider_age']) ? (int)$svc['provider_age'] : null;

$mainImg    = db_img_to_url($img_db, $BASE);    
$provAvatar = db_img_to_url($provAvatarDb, $BASE);  


$includes = [];
$sql = "SELECT text FROM service_includes WHERE service_id = ? ORDER BY sort ASC, id ASC";
$incStmt = $conn->prepare($sql);
if (!$incStmt) { die("Prepare failed (includes): ".$conn->error."\nSQL: ".$sql); }
$incStmt->bind_param("i",$id);
$incStmt->execute();
$incRes = $incStmt->get_result();
while($row=$incRes->fetch_assoc()){
  $t = trim((string)$row['text']);
  if ($t!=='') $includes[] = $t;
}
$incStmt->close();

$conn->close();


$sideImg = $BASE . "/image/inc_general.jpg";
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="icon" href="<?= $ASSETS ?>/images/logo.png" />
    <meta name="description" content="Fixora project that contain html,css,and java script" />
    <meta name="keywords" content="HTML, CSS, example" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="<?= $BASE ?>/css/service.css" />
    <script src="<?= $BASE ?>/js/service.js?v=1" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Galada&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


<title><?= htmlspecialchars($title) ?> Service</title>
  </head>
  <body>

    <section class="section-hero">
      <header class="navbar">
        <div class="logo-wrap">
          <img src="<?= $BASE ?>/image/home-logo.png" class="logo" alt="Fixora logo" />
        </div>
        <nav class="nav-links">
          <a href="<?= $BASE ?>/index.html">Home</a>
          <a href="<?= $BASE ?>/aboutUs.html">About Us</a>
          <a href="<?= $BASE ?>/contact.html">Contact</a>
          <a href="<?= $BASE ?>/php/viewmore.php">Service</a>
        </nav>
        <div class="auth">
          <a href="<?= $BASE ?>/login.html" class="btn login">login</a>
          <a href="<?= $BASE ?>/register.html" class="btn signup">Signup</a>
        </div>
      </header>

      <div class="hero-wrap container">
        <div class="hero-image">
          <img src="<?= htmlspecialchars($mainImg) ?>" alt="service image"
               onerror="this.src='<?= $BASE ?>/image/service-image.jpg'">
        </div>
      </div>
    </section>



    <section class="booking-section">
  <div class="details-card">
    <form action="<?= $BASE ?>/php/book_create.php" method="post">

      <input type="hidden" name="service_id"  value="<?= (int)$id ?>">
      <input type="hidden" name="provider_id" value="<?= isset($svc['provider_id']) ? (int)$svc['provider_id'] : 0 ?>">

      <div class="contact-info-fields">
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-phone form-icon"></i>
            <input id="phone" name="phone" type="tel" placeholder="ex: +972592643752" required>
          </div>
        </div>

        <div class="form-group">
          <label for="address">Address</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-location-dot form-icon"></i>
            <input id="address" name="address" type="text" placeholder="Address (City, Street, Details)" required>
          </div>
        </div>

        <div class="form-group">
          <label for="date">Date</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-calendar-days form-icon"></i>
            <input id="date" name="date" type="date" class="picker" required>
          </div>
        </div>

        <div class="form-group">
          <label for="time">Time</label>
          <div class="input-wrapper">
            <i class="fa-regular fa-clock form-icon"></i>
            <input id="time" name="time" type="time" class="picker" required>
          </div>
        </div>

        <div class="form-group full">
          <label for="problem">Explain the Problem</label>
          <div class="input-wrapper icon-top">
            <i class="fa-solid fa-pen-to-square form-icon"></i>
            <textarea id="problem" name="problem" rows="4" placeholder="Write here..." required></textarea>
          </div>
        </div>
      </div>

      <div class="btn-wrapper book-btn-wrapper">
        <button type="submit" class="btn book-btn">Book Now</button>
        <div class="btn-bg book-btn-bg"></div>
      </div>
    </form>
  </div>
</section>

   
    <section id="serviceDetails">
      <header class="sp-header">
        <h2 class="sp-title">Who is the service provider</h2>
      </header>

      <div class="sp-card">
        <div class="sp-image">
          <img
            src="<?= htmlspecialchars($provAvatar ?: ($ASSETS.'/images/service-provider-image1.jpg')) ?>"
            alt="service provider"
            onerror="this.src='<?= $ASSETS ?>/images/service-provider-image1.jpg'"/>
        </div>

        <div class="sp-details">
          <ul class="sp-labels">
            <li><span>Name :</span></li>
            <li><span>Age :</span></li>


<li><span>Role/Service :</span></li>
            <li><span>Rating :</span></li>
            <li><span>Experience :</span></li>
          </ul>
          <ul class="sp-info">
            <li><span><?= htmlspecialchars($prov) ?></span></li>
            <li><span><?= $age !== null ? (int)$age : '—' ?></span></li>
            <li><span><?= htmlspecialchars($title) ?></span></li>
          <li class="sp-rating">
  <i class="fa-solid fa-star" style="color:#f5c518;"></i>
  <span><?= $rating > 0 ? number_format($rating,1) : 'No rating yet' ?></span>
</li>
            <li><span><?= $yearsExp !== null ? ($yearsExp.' years') : '—' ?></span></li>
          </ul>
        </div>

        <div class="sp-change-btn">
          <a href="<?= $BASE ?>/php/viewmore.php?q=<?= urlencode($title) ?>">Change Provider</a>
        </div>
      </div>
    </section>


    <section id="included">
      <h2 class="inc-title">What's Included in a <?= htmlspecialchars($title) ?> Service?</h2>

      <div class="inc-wrap">
        <div class="inc-media">
       
          <img src="<?= htmlspecialchars($mainImg ?: $sideImg) ?>"
               alt="service image"
               onerror="this.src='<?= $BASE ?>/image/inc_general.jpg'">
        </div>

        <div class="inc-list-wrap">
          <ul class="inc-list">
            <?php if (!empty($includes)): ?>
              <?php foreach ($includes as $it): ?>
                <li><span><?= htmlspecialchars($it) ?></span></li>
              <?php endforeach; ?>
            <?php else: ?>
              <li><span>No items provided for this service.</span></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </section> 

<?php
// اتصال DB
$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// دوال مساعدة
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// جلب محتوى FAQ
$slug = 'faq';
$page = null;
if ($st = $conn->prepare("SELECT title, content, seo_title, seo_desc FROM cms_pages WHERE slug=? LIMIT 1")) {
  $st->bind_param("s", $slug);
  $st->execute();
  $res = $st->get_result();
  $page = $res ? $res->fetch_assoc() : null;
  $st->close();
}
$conn->close();

// تهيئة عناوين الـ SEO
$page_title   = $page['title']     ?? 'FAQ';
$page_content = trim($page['content'] ?? '');
$seo_title    = $page['seo_title'] ?? $page_title;
$seo_desc     = $page['seo_desc']  ?? '';
?>
  <section class="FQA-section">
  <div class="container">
    <header class="header">
      <h2>Quick fire answers</h2>
      <span class="header-label">FAQ</span>
    </header>

    <div class="questions">
      <!-- item 1 -->
      <div class="question-details">
        <button class="q-btn" type="button">
          <span class="icon" aria-hidden="true"></span>
          <span class="q-text">Do you provide a service guarantee?</span>
        </button>
        <div class="question-answer">
          <p>Yes! We offer a warranty on our work to ensure quality and customer satisfaction.</p>
        </div>
      </div>

      <!-- item 2 -->
      <div class="question-details">
        <button class="q-btn" type="button">
          <span class="icon" aria-hidden="true"></span>
          <span class="q-text">Can I reschedule or cancel my booking?</span>
        </button>
        <div class="question-answer">
          <p>Yes, you can reschedule or cancel within our flexible policy windows.</p>
        </div>
      </div>

      <!-- item 3 -->
      <div class="question-details">
        <button class="q-btn" type="button">
          <span class="icon" aria-hidden="true"></span>
          <span class="q-text">Do I need to provide cleaning tools or spare parts?</span>
        </button>
        <div class="question-answer">
          <p>Tools are included. If a special spare part is needed, we’ll confirm before purchase.</p>
        </div>
      </div>

      <!-- item 4 -->
      <div class="question-details">
        <button class="q-btn" type="button">
          <span class="icon" aria-hidden="true"></span>
          <span class="q-text">Will I receive a confirmation after booking?</span>
        </button>
        <div class="question-answer">
          <p>Yes, you’ll get instant confirmation via SMS and email.</p>
        </div>
      </div>
    </div>
  </div>


  
 

     <div class="decor-bubbles"></div>
<img src="../image/Group 46.png" alt="decor lines" class="decor-lines right">
<img src="../image/Group 46.png" alt="decor lines" class="decor-lines left">
</section>
</section>










<section class="testimonials">
<img src="../image/photo_2025-08-21_13-18-36.jpg" alt="decor" class="decor-full">
  <div class="container">
    <h2 class="title">What Our Customers Say</h2>
    <a class="tag" href="#">Happy Customers</a>

    <div class="slides">
      

<div class="cards is-active" data-index="0">
  <article class="card">
    <a class="avatar-link" href="https://example.com/cleaning" target="_blank" rel="noopener">
      <img class="avatar" src="https://randomuser.me/api/portraits/men/71.jpg" alt="Cleaner">
    </a>
    <h3 class="name">Arlene McCoy</h3>
    <p class="role">Cleaning</p>
    <p class="desc">Quick, detailed, and super friendly. My place looks brand new.</p>
    <div class="stars">
      <span class="star fill"></span><span class="star fill"></span>
      <span class="star fill"></span><span class="star fill"></span>
      <span class="star"></span>
    </div>
  </article>

  <article class="card">
    <a class="avatar-link" href="https://example.com/plumbing" target="_blank" rel="noopener">
      <img class="avatar" src="https://randomuser.me/api/portraits/women/71.jpg" alt="Plumber">
    </a>
    <h3 class="name">Courtney Henry</h3>
    <p class="role">Plumbing</p>
    <p class="desc">On-time, tidy work, and no more leaks. Highly recommended.</p>
    <div class="stars">
      <span class="star fill"></span><span class="star fill"></span>
      <span class="star fill"></span><span class="star"></span>
      <span class="star"></span>
    </div>
  </article>

  <article class="card">
    <a class="avatar-link" href="https://example.com/electrical" target="_blank" rel="noopener">
      <img class="avatar" src="https://randomuser.me/api/portraits/men/72.jpg" alt="Electrician">
    </a>
    <h3 class="name">Esther Howard</h3>
    <p class="role">Electrical</p>
    <p class="desc">Explained everything and fixed it safely. Great experience.</p>
    <div class="stars">
      <span class="star fill"></span><span class="star fill"></span>
      <span class="star fill"></span><span class="star fill"></span>
      <span class="star"></span>
    </div>
  </article>
</div>



      <div class="cards" data-index="1">
        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/45.jpg" alt="Plumber">
          <h3 class="name">Michael Johnson</h3>
          <p class="role">Plumber</p>
          <p class="desc">I had a major leak at home, and Michael fixed it quickly and professionally. The work was neat and clean. I finally feel safe with my plumbing system.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/46.jpg" alt="Electrician">
          <h3 class="name">Robert Fox</h3>
          <p class="role">Electrician</p>
          <p class="desc">Robert provided excellent electrical service. He was on time, friendly, and fixed the issue safely. Highly recommended for quality work.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/women/32.jpg" alt="Cleaner">
          <h3 class="name">Jenny Wilson</h3>
          <p class="role">Cleaner</p>
          <p class="desc">Jenny did an outstanding cleaning job. She was fast, efficient, and used eco-friendly products. The place looks brighter and smells fresh.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>
      </div>


      <div class="cards" data-index="2">
        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/40.jpg" alt="Electrician">
          <h3 class="name">Cody Fisher</h3>
          <p class="role">Electrician</p>
          <p class="desc">Cody was very professional and quick to respond. The booking process was smooth and he explained how to maintain safety at home.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/women/65.jpg" alt="Gardener">
          <h3 class="name">Eleanor Pena</h3>
          <p class="role">Gardener</p>
          <p class="desc">Eleanor transformed our backyard beautifully. She’s detail-oriented, hardworking, and always cheerful. Highly recommended!</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/74.jpg" alt="Plumber">
          <h3 class="name">Darlene Robertson</h3>
          <p class="role">Plumber</p>
          <p class="desc">Darlene quickly identified the leak and fixed it without any mess. Efficient, neat, and affordable plumbing service.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>
      </div>



      <div class="cards" data-index="3">
        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/women/22.jpg" alt="Painter">
          <h3 class="name">Leslie Alexander</h3>
          <p class="role">Painter</p>
          <p class="desc">Leslie did a fantastic job painting our living room. The finish was spotless and she left the place perfectly clean. Truly professional.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/19.jpg" alt="Carpenter">
          <h3 class="name">Jacob Jones</h3>
          <p class="role">Carpenter</p>
          <p class="desc">Jacob built a custom shelf and repaired our table. Quality was top-notch and he finished faster than expected.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/81.jpg" alt="HVAC Technician">
          <h3 class="name">Wade Warren</h3>
          <p class="role">HVAC Technician</p>
          <p class="desc">Wade inspected and repaired our AC system. Worked efficiently and explained everything clearly. Excellent service!</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>
      </div>


      <div class="cards" data-index="4">
        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/men/13.jpg" alt="Mover">
          <h3 class="name">Albert Flores</h3>
          <p class="role">Mover</p>
          <p class="desc">Albert and his team handled our furniture with care. Quick, organized, and stress-free moving service.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/women/56.jpg" alt="Housekeeper">
          <h3 class="name">Savannah Nguyen</h3>
          <p class="role">Housekeeper</p>
          <p class="desc">Savannah did an amazing job cleaning our apartment. Spotless, polite, and highly professional.</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>

        <article class="card">
          <img class="avatar" src="https://randomuser.me/api/portraits/women/2.jpg" alt="Window Cleaner">
          <h3 class="name">Kathryn Murphy</h3>
          <p class="role">Window Cleaner</p>
          <p class="desc">Kathryn made our windows shine like new. No streaks and crystal clear. Fantastic job!</p>
          <div class="stars">
            <span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star fill"></span><span class="star"></span>
          </div>
        </article>
      </div>

    </div>

    <div class="dots">
      <span class="dot active" data-index="0"></span>
      <span class="dot" data-index="1"></span>
      <span class="dot" data-index="2"></span>
      <span class="dot" data-index="3"></span>
       <span class="dot" data-index="4"></span>
    </div>

  </div>
</section>












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