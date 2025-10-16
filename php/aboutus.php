<?php
// ==== DB ====
$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// نجيب بيانات صفحة About Us من cms_pages
$slug = 'about-us';
$page = null;

if ($st = $conn->prepare("SELECT title, content, seo_title, seo_desc FROM cms_pages WHERE slug=? LIMIT 1")) {
  $st->bind_param("s", $slug);
  $st->execute();
  $res = $st->get_result();
  $page = $res ? $res->fetch_assoc() : null;
  $st->close();
}
$conn->close();

$page_title   = $page['title']     ?? 'About Us';
$page_content = trim($page['content'] ?? '');  // HTML مسموح
$seo_title    = $page['seo_title'] ?? $page_title;
$seo_desc     = $page['seo_desc']  ?? '';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($seo_title) ?></title>
  <?php if ($seo_desc !== ''): ?>
    <meta name="description" content="<?= h($seo_desc) ?>">
  <?php endif; ?>

  <link rel="icon" href="./images/logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Quicksand:wght@300..700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root{
      --main-font:"Poppins", sans-serif;
      --secondary-font:"Nunito", sans-serif;
      --light-blue:#3b82f6;
      --dark-blue:#1e40af;

      --fx-blue:#1e73ff;
      --fx-blue-600:#1667d8;
      --fx-blue-100:#e6f0ff;
      --fx-text:#111827;
    }
    *{ box-sizing:border-box; margin:0; padding:0 }
    body{ font-family:var(--secondary-font); color:#000 }
    .container{ max-width:1250px; margin:0 auto; padding:0 2.5rem }
    section{ padding-block:20px }
    .header{ text-align:center; padding:30px 0 }
    .header h2{ font-weight:600; font-size:24px }
    /* .header .header-label{ background:var(--light-blue); color:#fff; border-radius:5px; font:600 24px/1 var(--secondary-font); padding:.4rem .8rem; display:inline-block; margin-top:10px } */
      .header-label {
  background: var(--light-blue);
  color: #fff;
  border-radius: 8px;
  font: 600 18px/1 var(--secondary-font);
  padding: 8px 20px;   /* زودت padding داخلي */
  display: inline-block;
  margin-top: 14px;    /* زودت مسافة بين النص والمربع */
}
    /* ===================== Navbar ===================== */
    .fx-hero{ width:100vw; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw); padding:18px 0 10px; overflow:hidden; color:var(--fx-text); font-family:var(--main-font) }
    .fx-nav{ max-width:1200px; margin:auto; padding:18px 40px; display:flex; align-items:center; gap:18px }
    .fx-brand{ display:flex; align-items:center; gap:10px }
    .fx-logo{ width:220px; height:auto; object-fit:contain }
    .fx-links{ margin-left:auto; margin-right:auto; display:flex; align-items:center; gap:18px }
    .fx-links a{ position:relative; padding:10px 14px; border-radius:10px; font:600 16px/1 var(--main-font); color:var(--fx-text); text-decoration:none; transition:color .2s, background .2s; }
    .fx-links a::after{ content:""; position:absolute; left:50%; bottom:6px; transform:translateX(-50%); width:0; height:2px; background:var(--fx-blue); transition:width .25s }
    .fx-links a:hover{ color:var(--fx-blue); background:rgba(30,115,255,.06) }
    .fx-links a:hover::after{ width:60% }
    .fx-links a.active{ color:var(--fx-blue); background:rgba(30,115,255,.10) }
    .fx-links a.active::after{ width:70% }
    .fx-actions{ display:flex; gap:12px }
    .fx-btn{ display:inline-flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; padding:14px 60px; border-radius:50px; font:600 18px/1 var(--main-font); transition:all .25s ease }
    .fx-btn--primary{ background:var(--fx-blue); color:#fff; border:none; box-shadow:0 6px 14px rgba(30,115,255,.25) }
    .fx-btn--primary:hover{ background:var(--fx-blue-600); transform:translateY(-2px) }
    .fx-btn--ghost{ background:var(--fx-blue-100); color:#0b1324; border:2px solid var(--fx-blue) }
    .fx-btn--ghost:hover{ background:#fff; transform:translateY(-2px) }

    /* ===================== Footer ===================== */
    .site-footer{ background:url("image/footer-bg.jpg") center/cover no-repeat, linear-gradient(135deg,#eaf2ff 0%,#fff3da 100%); padding:48px 18px 32px }
    .footer-container{ max-width:1200px; margin:0 auto; display:grid; gap:28px; align-items:start; grid-template-columns:1.4fr 1fr 1fr 1.2fr }
    .brand-row{ display:flex; align-items:center; gap:10px }
    .brand-logo{ width:160px; height:auto; object-fit:contain }
    .brand-desc{ color:#555; line-height:1.6; margin:12px 0 10px; font-size:14px }
    .col-title{ font-weight:700; font-size:16px; margin:4px 0 10px; color:#1E40AF }
    .col-links{ list-style:none; display:grid; gap:8px }
    .col-links a{ color:#374151; text-decoration:none; transition:color .2s, transform .06s }
    .col-links a:hover{ color:#1E90FF }
    .col-links a:active{ color:#145dbf; transform:translateY(1px) }
    .contact-list{ list-style:none; display:grid; gap:8px }
    .contact-list li{ color:#374151; display:flex; align-items:center; gap:10px }
    .contact-list i{ color:#1E90FF }
    .contact-list a{ color:#374151; text-decoration:none; transition:color .2s }
    .contact-list a:hover{ color:#1E90FF }
    .contact-list a:active{ color:#145dbf }
    .social{ display:flex; gap:10px; margin-top:12px; list-style:none }
    .soc{ --c:#999; --active:#1E90FF; display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; text-decoration:none; transition:background-color .3s }
    .soc i{ font-size:16px; color:var(--c); transition:color .2s, transform .12s }
    .soc:hover{ background-color:rgba(30,144,255,.1) } 
    .soc:hover i{ color:#1E90FF }
    .soc:active i{ color:var(--active); transform:scale(.92) }
    .soc.fb{ --c:#1877F2; --active:#145dbf } 
    .soc.ig{ --c:#E4405F; --active:#b6314b }
    .soc.x{ --c:#000000; --active:#333333 } 
    .soc.li{ --c:#0A66C2; --active:#084d96 }
    .footer-copy{ margin-top:24px; text-align:center; font-size:12px; color:#9CA3AF }
    .footer-copy .brand{ color:#1E90FF; font-weight:700 }

    @media (max-width:900px){.footer-container{grid-template-columns:1fr 1fr}}
    @media (max-width:560px){.footer-container{grid-template-columns:1fr}.site-footer{border-radius:12px}}

    /* ===================== Sections (unchanged content) ===================== */
    .about-us-section{ background:linear-gradient(93.03deg,rgba(30,144,255,.25) 2.34%,rgba(250,203,97,.25) 99.49%); padding-top:1.25rem; padding-bottom:2.5rem }
    .About-us{ display:flex; justify-content:space-between; align-items:end; gap:24px }
    .about-us-desc p{ font-size:20px; line-height:45px }
    .about-us-desc p span{ font-weight:700 }
    .about-us-image{ position:relative; width:545px; height:410px; flex:0 0 545px }
    .about-us-image .image-border{ border-width:5px 2px 0 0; border-style:solid; border-color:var(--light-blue); border-radius:20px; position:absolute; inset:0 }
    .about-us-image img{ width:100%; height:100%; object-fit:cover; border-radius:20px; padding-block:10px; padding-inline:8px }

    .fixora-goals-section{ padding-block:60px }
    .fixora-goals-section .header h2{ color:var(--light-blue); font-size:30px; font-weight:700 }
    .goals-grid{ display:flex; gap:20px; flex-wrap:wrap }
    .goals-card{ flex:1 1 calc(25% - 15px); min-width:220px; position:relative; display:flex; flex-direction:column; box-shadow:0 10px 22px rgba(0,0,0,.06); border:1px solid var(--light-blue); border-radius:7px; overflow:hidden }
    .upper-card{ width:100%; height:36px; background:var(--light-blue); position:absolute; top:0; left:0 }
    .goals-card-inner{ flex:1; display:flex; flex-direction:column; align-items:center; text-align:center; justify-content:flex-start; background:#fff; border-radius:7px; padding:50px 15px 20px }
    .goals-card:hover .upper-card{ background:#fff }
    .goals-card:hover .goals-card-inner{ background:var(--light-blue) }
    .goals-card:hover .goals-card-inner h3,.goals-card:hover .goals-card-inner p{ color:#fff }
    .goals-card-inner h3{ font-size:24px; font-weight:700; color:#000 }
    .goals-card-inner p{ margin-top:5px; color:#68686a; font-size:15px; line-height:20px }
    .goals-icon img{ width:75px; height:75px; object-fit:contain }

    .why-choose-us{ background:linear-gradient(93.03deg,rgba(30,144,255,.25) 2.34%,rgba(250,203,97,.25) 99.49%); border-radius:20px; position:relative; padding-bottom:50px; overflow:hidden }
    .wc-grid{ display:flex; flex-wrap:wrap; gap:20px; padding-top:6rem; align-items:stretch }
    .wc-card{ flex:1 1 calc(25% - 15px); min-width:220px; position:relative }
    .wc-card-inner{ display:flex; flex-direction:column; gap:15px; justify-content:flex-start; text-align:center; background:#fff; border-radius:16px; padding:30px 15px; box-shadow:0 10px 22px rgba(0,0,0,.06); -webkit-mask-image:radial-gradient(circle 55px at 50% -35px,transparent 60px,black 56px); -webkit-mask-repeat:no-repeat; -webkit-mask-position:top center; -webkit-mask-composite:destination-out; mask-image:radial-gradient(circle 55px at 50% -35px,transparent 60px,black 56px); mask-repeat:no-repeat; mask-position:top center; mask-composite:exclude }
    .wc-card:hover .wc-card-inner{ box-shadow:0 14px 28px rgba(0,0,0,.09) }
    .wc-card-inner h3{ margin:6px 0 8px; font-size:16px; font-weight:600; color:#000 }
    .wc-card-inner p{ margin:0; color:#68686a; line-height:1.55; font-size:15px }
    .wc-icon{ position:absolute; top:-87px; left:50%; transform:translateX(-50%); width:106px; height:106px; border-radius:50%; background:radial-gradient(circle at 35% 30%,#f5f8ff,#e9eef7); box-shadow:0 6px 14px rgba(0,0,0,.08); display:grid; place-items:center; z-index:10 }
    .wc-icon img{ width:42px; height:42px; object-fit:contain; filter:drop-shadow(0 2px 2px rgba(0,0,0,.08)) }
    .top-right-circles{ position:absolute; top:0; right:0; z-index:10 }
    .bottom-left-circles{ position:absolute; bottom:0; left:0; z-index:10 }

    .how-we-work-section{ position:relative; margin-block:10px; background-image:url("images/bg.png"); background-repeat:no-repeat; padding-bottom:0 }
    /* .how-we-work-section .header{ position:absolute; top:0; left:50%; transform:translateX(-50%) }
    .works{ display:flex; width:100%; gap:24px; padding-top:90px } */

    .how-we-work-section .header {
  position: relative;       /* بدل absolute */
  text-align: center;
  margin-bottom: 25px;      /* مسافة بين العنوان والمربعات */
}

.works {
  display: flex;
  width: 100%;
  gap: 24px;
  /* padding-top:90px;  <-- اشيلها لأنك مش محتاج */
}
    .image-side{ width:50%; display:flex; align-items:end; justify-content:center }
    .image-side img{ width:70%; height:auto }
    .content-side{ flex:1 }
    .how-we-work-icon{ width:77px; height:77px }
    .icon{ position:relative }
    .icon::before{ content:""; position:absolute; top:50%; right:-15px; transform:translateY(-50%); width:2px; height:70%; background:#84c8ff }
    /* .step{ display:flex; align-items:center; gap:30px; padding:5px 15px; margin-bottom:15px; border-radius:16px; background:linear-gradient(#fff,#fff) padding-box, linear-gradient(90deg,rgba(30,144,255,.25) 0%,rgba(250,203,97,.25) 100%) border-box; border:1px solid transparent; transition:background .2s } */
    .how-we-work-icon {
  width: 70px;
  height: 70px;
  margin-right: 10px;  /* تبعد الأيقونة عن النص */
}

.step {
  display: flex;
  align-items: center;
  gap: 25px;
  padding: 18px 22px;    /* padding أوسع جوه المربع */
  margin-bottom: 20px;   /* زودت المسافة بين كل مربع والتاني */
  border-radius: 16px;
  background: linear-gradient(#fff,#fff) padding-box, 
              linear-gradient(90deg,rgba(30,144,255,.25) 0%,rgba(250,203,97,.25) 100%) border-box;
  border: 1px solid transparent;
  transition: background .2s;
}
    .step h3{ margin:0; font-size:18px; font-weight:500; color:#000 }
    .step p{ margin:5px 0 0; font-size:16px; color:#68686a }
    .step:hover{ background:linear-gradient(to top,var(--dark-blue),var(--light-blue)) }
    .step:hover h3,.step:hover p{ color:#fff }

    /* responsiveness */
    @media (max-width:992px){
      .About-us{flex-direction:column;align-items:flex-start}
      .about-us-image{width:100%;height:auto;flex:none}
      .about-us-image img{height:auto}
      .works{flex-direction:column;gap:20px}
      .image-side{width:100%}
      .wc-card,.goals-card{flex:1 1 calc(50% - 10px)}
    }
    @media (max-width:540px){
      .wc-card,.goals-card{flex:1 1 100%}
    }






















    /* === Fixora Responsive Additions (non-destructive) === */

/* عام: منع سكرول أفقي بسبب عناصر 100vw */
html, body { overflow-x: hidden; }

/* حاوية أريح على الموبايل */
@media (max-width: 992px){
  .container{ padding: 0 1.25rem; }
}
@media (max-width: 560px){
  .container{ padding: 0 1rem; }
}

/* ===== Navbar ===== */
@media (max-width: 1200px){
  .fx-nav{ padding: 14px 24px; }
  .fx-logo{ width: 180px; }
}
@media (max-width: 992px){
  .fx-logo{ width: 160px; }
  .fx-links a{ padding: 8px 10px; font-size: 15px; }
  .fx-btn{ padding: 12px 28px; font-size: 16px; }
}
/* على الموبايل: نخليها صفين بدون ما نضيف HTML جديد */
@media (max-width: 768px){
  /* الغي حيلة الـ 100vw لتمنع سكرول */
  .fx-hero{ width: 100%; margin-left: 0; margin-right: 0; padding: 10px 0; }
  .fx-nav{
    gap: 10px;
    flex-wrap: wrap;                 /* ✅ يسمح بالنزول لسطرين */
    justify-content: center;
  }
  .fx-links{
    order: 3;                        /* تحت */
    width: 100%;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 6px 0 0;
  }
  .fx-links a{
    padding: 8px 10px;
    font-size: 14px;
  }
  .fx-actions{
    order: 2;                        /* بالنص بين اللوجو واللينكات */
    gap: 8px;
  }
  .fx-btn{ padding: 10px 16px; font-size: 14px; border-radius: 12px; }
  .fx-links a::after{ display:none; } /* تبسيط الهوفر على الشاشات الصغيرة */
}

/* ===== About Us ===== */
@media (max-width: 992px){
  .about-us-desc p{ font-size: 18px; line-height: 34px; }
}
@media (max-width: 768px){
  .About-us{ gap: 16px; }
  .about-us-desc p{ font-size: 16px; line-height: 28px; }
  .about-us-image{ width: 100%; height: auto; }
  .about-us-image img{ width: 100%; height: auto; }
}

/* ===== Goals (الكروت) ===== */
@media (max-width: 1200px){
  .goals-card{ flex:1 1 calc(33.333% - 14px); }
}
@media (max-width: 992px){
  .goals-grid{ gap: 14px; }
  .goals-card{ flex:1 1 calc(50% - 10px); }
  .goals-card-inner h3{ font-size: 20px; }
  .goals-card-inner p{ font-size: 14px; line-height: 20px; }
}
@media (max-width: 560px){
  .goals-card{ flex:1 1 100%; }
}

/* ===== Why Choose Us ===== */
@media (max-width: 1200px){
  .wc-card{ flex:1 1 calc(33.333% - 14px); }
}
@media (max-width: 992px){
  .wc-grid{ gap: 14px; }
  .wc-card{ flex:1 1 calc(50% - 10px); }
  .wc-icon{ top: -76px; width: 92px; height: 92px; }
  .wc-card-inner{ padding: 24px 14px; }
  .wc-card-inner h3{ font-size: 15px; }
  .wc-card-inner p{ font-size: 14px; }
}
@media (max-width: 560px){
  .wc-card{ flex:1 1 100%; }
  .wc-icon{ top: -70px; width: 86px; height: 86px; }
}

/* ===== How We Work ===== */
@media (max-width: 1200px){
  .works{ gap: 18px; }
}
@media (max-width: 992px){
  .works{ flex-direction: column; }
  .image-side{ width: 100%; justify-content: center; }
  .image-side img{ width: 86%; max-width: 540px; }
  .content-side{ width: 100%; }
}
@media (max-width: 768px){
  .header h2{ font-size: 22px; }
  .header-label{ font-size: 16px; padding: 6px 14px; }
  .step{ gap: 16px; padding: 14px 16px; }
  .how-we-work-icon{ width: 56px; height: 56px; }
  .icon::before{ display: none; }         /* شيل الخط الواصل بين الأيقونات بالموبايل */
  .step h3{ font-size: 16px; }
  .step p{ font-size: 14px; }
}

/* ===== Footer ===== */
@media (max-width: 1200px){
  .brand-logo{ width: 140px; }
}
@media (max-width: 900px){
  .footer-container{ grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px){
  .footer-container{ grid-template-columns: 1fr; gap: 18px; }
  .brand-desc{ font-size: 13px; }
  .col-title{ font-size: 15px; }
  .col-links a, .contact-list li, .contact-list a{ font-size: 14px; }
  .footer-copy{ font-size: 11px; }
}

/* تحسينات صغيرة للصور عامة */
img{ max-width: 100%; height: auto; }
  </style>
</head>
<body>

  <!-- Navbar -->
  <section class="fx-hero">
    <header class="fx-nav">
      <div class="fx-brand">
        <img src="../image/home-logo.png" alt="Fixora logo" class="fx-logo">
      </div>

      <nav class="fx-links" aria-label="Primary">
        <a href="../index.html">Home</a>
        <a href="aboutus.php">About Us</a>
        <a href="../contact.html">Contact</a>
        <a href="viewmore.php">Service</a>
      </nav>

      <div class="fx-actions">
        <a href="login.html" class="fx-btn fx-btn--primary">Login</a>
        <a href="register.html" class="fx-btn fx-btn--ghost">Sign Up</a>
      </div>
    </header>
  </section>

  <!-- About Us -->
  <section class="about-us-section">
    <div class="container">
      <div class="About-us">
        <div class="about-us-desc">
          <?php if ($page_content !== ''): ?>
            <!-- محتوى الـCMS (HTML) يظهر هنا بدون ما نغيّر الستايل -->
            <?= $page_content ?>
          <?php else: ?>
            <!-- الفقرة الأصلية تبعتك (fallback إذا ما في محتوى في CMS) -->
            <p>
              In today’s fast-paced world, comfort and well-being at<br />
              home matter more than ever. That’s why <span>Fixora</span>
              <br />launched “Home Services” – your smart platform for<br />
              all home maintenance and personal services.<br />
              From repairs and cleaning to delivery and personal <br />care, we
              connect you with trusted professionals for a<br />
              seamless, high-quality experience. <span>At Fixora</span> , we<br />
              make home services simple, reliable, and stress-free.
            </p>
          <?php endif; ?>
        </div>
        <div class="about-us-image">
          <img src="../image/Rectangle 51.png" alt="about us image" />
          <div class="image-border"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Goals -->
  <section class="fixora-goals-section">
    <div class="container">
      <header class="header">
        <h2>Why Our Fixora services are the best</h2>
      </header>

      <div class="goals-grid">
        <div class="goals-card">
          <div class="goals-card-inner">
            <div class="goals-icon">
              <img src="../image/opportunity 1.png" alt="goalImage1" />
            </div>
            <h3>Our Vision</h3>
            <p>to be the leading and most trusted digital marketplace for home services in Palestine, making quality home care effortless and secure.</p>
          </div>
          <div class="upper-card"></div>
        </div>

        <div class="goals-card">
          <div class="goals-card-inner">
            <div class="goals-icon">
              <img src="../image/opportunity 1(1).png" alt="goalImage2" />
            </div>
            <h3>Our Mission</h3>
            <p>to offer a transparent, efficient platform that connects homeowners with top-rated professionals for a reliable booking experience.</p>
          </div>
          <div class="upper-card"></div>
        </div>

        <div class="goals-card">
          <div class="goals-card-inner">
            <div class="goals-icon">
              <img src="../image/Lasting quality.png" alt="goalImage3" />
            </div>
            <h3>Our Values</h3>
            <p>We are built on trust, quality, and convenience—ensuring seamless service every time. Driven by innovation and community support .</p>
          </div>
          <div class="upper-card"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Why Choose Us -->
  <section class="why-choose-us">
    <div class="container">
      <header class="header">
        <h2>Why Choose Us</h2>
        <a class="header-label">Fixora</a>
      </header>

      <div class="wc-grid">
        <div class="wc-card">
          <div class="wc-icon">
            <img src="../image/chosee 1.png" alt="chooseIcon1" />
          </div>
          <div class="wc-card-inner">
            <h3>Licensed &amp; Professional Plumbers</h3>
            <p>Our Skilled Plumbers Are Fully Licensed, Ensuring Reliable And High-Quality Service Every Time.</p>
          </div>
        </div>

        <div class="wc-card">
          <div class="wc-icon">
            <img src="../image/choose 3.png" alt="chooseIcon2" />
          </div>
          <div class="wc-card-inner">
            <h3>Transparent Pricing, No Hidden Fees</h3>
            <p>Know Exactly What You Pay—Fair, Honest Pricing With No Surprises.</p>
          </div>
        </div>

        <div class="wc-card">
          <div class="wc-icon">
            <img src="../image/choose 2.png" alt="chooseIcon3" />
          </div>
          <div class="wc-card-inner">
            <h3>Track Your Service In Real Time</h3>
            <p>Track your service status in real time, ensuring full transparency and reliability.</p>
          </div>
        </div>

        <div class="wc-card">
          <div class="wc-icon">
            <img src="../image/chosee 1.png" alt="chooseIcon4" />
          </div>
          <div class="wc-card-inner">
            <h3>Guaranteed Repairs</h3>
            <p>All Repairs Are Backed By Our Guarantee, Giving You Peace Of Mind And Lasting Results.</p>
          </div>
        </div>
      </div>
    </div>

    <img src="../image/Group 46.png" alt="top-right-circles" class="top-right-circles" />
    <img src="../image/Group 46.png" alt="bottom-left-circles" class="bottom-left-circles" />
  </section>

  <!-- How We Work -->
  <section class="how-we-work-section">
    <div class="container">
      <div class="header">
        <h2>How We Work</h2>
        <span class="header-label">Fixora</span>
      </div>

      <div class="works">
        <div class="image-side">
          <img src="../image/work .jpg" alt="Worker" />
        </div>

        <div class="content-side">
          <div class="step">
            <span class="icon"><img src="../image/booking.png" alt="HowWeWork1" class="how-we-work-icon" /></span>
            <div>
              <h3>Book Your Service</h3>
              <p>Fill Out The Quick Form Or Call Us To Request Service</p>
            </div>
          </div>

          <div class="step">
            <span class="icon"><img src="../image/work3.jpg" alt="HowWeWork2" class="how-we-work-icon" /></span>
            <div>
              <h3>Inspection & Quote</h3>
              <p>We Assess The Issue And Provide A Clear, Transparent Quote</p>
            </div>
          </div>

          <div class="step">
            <span class="icon"><img src="../image/work4.jpg" alt="HowWeWork3" class="how-we-work-icon" /></span>
            <div>
              <h3>Professional Service</h3>
              <p>Our Licensed Plumbers Fix Or Install With Skill And Speed</p>
            </div>
          </div>

          <div class="step">
            <span class="icon"><img src="../image/work5.jpg" alt="HowWeWork4" class="how-we-work-icon" /></span>
            <div>
              <h3>Satisfaction Guaranteed</h3>
              <p>Final Check To Ensure Everything Works Perfectly And You're Happy.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="footer-container">
      <div class="footer-col footer-brand">
        <div class="brand-row"><img src="../image/home-logo.png" alt="Fixora logo" class="brand-logo"></div>
        <p class="brand-desc">Our Go-To Platform For Cleaning, Plumbing, And Electrical Maintenance Services With Live Tracking And Special Discounts.</p>
        <ul class="social">
          <li><a class="soc fb" href="https://facebook.com/yourpage" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
          <li><a class="soc ig" href="https://instagram.com/yourhandle" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
          <li><a class="soc x"  href="https://x.com/yourhandle" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a></li>
          <li><a class="soc li" href="https://www.linkedin.com/company/yourcompany" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
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
          <li><i class="fa-solid fa-envelope"></i> <a href="mailto:Fixora2025@gmail.com">Fixora2025@gmail.com</a></li>
          <li><i class="fa-solid fa-phone"></i> <a href="tel:+972592643752">+972 592643752</a></li>
        </ul>
      </div>
    </div>
    <p class="footer-copy">© 2025 All Rights Reserved — <span class="brand">Fixora</span></p>
  </footer>

</body>
</html>