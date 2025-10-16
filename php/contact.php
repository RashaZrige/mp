
<?php
// ==== DB ====
$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// نجيب بيانات صفحة Contact Us من cms_pages
$slug = 'contact-us';
$page = null;

if ($st = $conn->prepare("SELECT title, content, seo_title, seo_desc FROM cms_pages WHERE slug=? LIMIT 1")) {
  $st->bind_param("s", $slug);
  $st->execute();
  $res = $st->get_result();
  $page = $res ? $res->fetch_assoc() : null;
  $st->close();
}


$conn->close();

// قيم افتراضية لو ما كان في صف بالجدول
$page_title   = $page['title']     ?? 'Contact Us';
$page_content = $page['content']   ?? '';                 // مسموح HTML
$seo_title    = $page['seo_title'] ?? $page_title;
$seo_desc     = $page['seo_desc']  ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($seo_title) ?></title>
    <?php if ($seo_desc !== ''): ?>
      <meta name="description" content="<?= h($seo_desc) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="css/contact.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>

<section class="fx-hero">
  <header class="fx-nav">
    <div class="fx-brand">
      <img src="../image/home-logo.png" alt="Fixora logo" class="fx-logo">
    </div>
    <nav class="fx-links">
      <a href="#">Home</a>
      <a href="#">About Us</a>
      <a href="#" class="active">Contact</a>
      <a href="#">Service</a>
    </nav>
    <div class="fx-actions">
      <a href="login.html" class="fx-btn fx-btn--primary">Login</a>
      <a href="register.html" class="fx-btn fx-btn--ghost">Sign Up</a>
    </div>
  </header>

  <!-- ✦ Contact / Get Started Section (التصميم كما هو) -->
  <section class="contact-hero">
    <div class="ch-container">
      <div class="ch-text">
        <p class="ch-eyebrow">Get Started</p>
        <h1 class="ch-title">
          Get in touch with us. We’re<br/>
          here to assist you.
        </h1>
      </div>
      <ul class="ch-social">
        <li><a href="https://facebook.com" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
        <li><a href="https://instagram.com" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
        <li><a href="https://twitter.com" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a></li>
      </ul>
    </div>
  </section>
</section>

<section class="contact-bar">
  <div class="contact-container">
    <div class="contact-left">
      <p class="eyebrow">Contact Info</p>
      <h3>We are always<br>happy to assist you</h3>
    </div>
    <div class="contact-col">
      <h4>Email Address</h4>
      <a class="value" href="mailto:help@info.com">help@info.com</a>
      <p class="hours-title">Assistance hours:</p>
      <p class="hours">Monday – Friday 6 am to<br>8 pm EST</p>
    </div>
    <div class="contact-col">
      <h4>Number</h4>
      <a class="value" href="tel:80899834256">(808) 998-34256</a>
      <p class="hours-title">Assistance hours:</p>
      <p class="hours">Monday – Friday 6 am to<br>8 pm EST</p>
    </div>
  </div>
</section>

<section class="contact" id="contact">
  <div class="container">
    <!-- عنوان قسم الفورم يبقى كما هو -->
    <h2>Keep In Contact With Us</h2>

    <!-- ✦ كتلة محتوى ديناميكي من CMS (بدون تغيير شكل التصميم) -->
    <?php if ($page_content !== ''): ?>
      <div class="cms-chunk" style="margin:12px 0 20px; line-height:1.7">
        <?= $page_content /* HTML من الـ CMS يظهر هنا */ ?>
      </div>
    <?php endif; ?>

    <form class="contact-form" action="#">
      <div class="row three">

<!-- Full name -->
        <div class="field">
          <label for="fullName">Full name</label>
          <div class="input-wrap">
            <i class="fa-regular fa-user"></i>
            <input id="fullName" name="fullName" type="text" placeholder="Rasha ramez" />
          </div>
        </div>
        <!-- Email -->
        <div class="field">
          <label for="email">Email </label>
          <div class="input-wrap">
            <i class="fa-regular fa-envelope"></i>
            <input id="email" name="email" type="email" placeholder="ex: rasha@gmail.com (optional)" />
          </div>
        </div>
        <!-- Phone -->
        <div class="field">
          <label for="phone">Phone Number</label>
          <div class="input-wrap">
            <i class="fa-solid fa-phone"></i>
            <input id="phone" name="phone" type="tel" placeholder="ex: +970592643752" />
          </div>
        </div>
      </div>
      <!-- Message -->
      <div class="field full">
        <label for="message">Message</label>
        <textarea id="message" name="message" placeholder=""></textarea>
      </div>
      <button class="btn" type="submit">Send</button>
    </form>
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
        <li><i class="fa-solid fa-phone"></i> <a href="tel:+972597789185">+972 592643752</a></li>
      </ul>
    </div>
  </div>
  <p class="footer-copy">© 2025 All Rights Reserved — <span class="brand">Fixora</span></p>
</footer>

</body>
</html>





<style>


:root{
  --fx-blue:#1e73ff;
  --fx-blue-600:#1667d8;
  --fx-blue-100:#e6f0ff;
  --fx-text:#111827;
  --fx-muted:#6b7280;
   --light-blue: #4185f3;
}


.fx-hero{
  /* background:
    linear-gradient(90deg,#cfe2ff 0%, #eaf2ff 45%, #fde7c4 100%); */
  /* background: #fff; */
  width:100vw;
  margin-left:calc(50% - 50vw);
  margin-right:calc(50% - 50vw);
  padding: 18px 0 64px;
  position:relative;
  overflow:hidden;
  font-family: "Poppins", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  color:var(--fx-text);
}

/* ====== Navbar ====== */
.fx-nav{
  max-width:1200px;
  margin:auto;
  padding:18px 40px;
  display:flex;
  align-items:center;
  gap:18px;
}
.fx-brand{display:flex;align-items:center;gap:10px}
.fx-logo{width:220px;height:auto;object-fit:contain}
.fx-brand-name{font-weight:700;font-size:28px}

.fx-links{
  margin-left:auto;margin-right:auto;
  display:flex;align-items:center;gap:18px;
}
.fx-links a{
  position:relative;
  padding:10px 14px;
  font-weight:600;font-size:16px;
  text-decoration:none;color:var(--fx-text);
  border-radius:10px;transition:color .2s, background .2s;
}
.fx-links a::after{
  content:"";position:absolute;left:50%;bottom:6px;
  width:0;height:2px;background:var(--fx-blue);
  transform:translateX(-50%);transition:width .25s;
}
.fx-links a:hover{color:var(--fx-blue);background:rgba(30,115,255,.06)}
.fx-links a:hover::after{width:60%}
.fx-links a.active{color:var(--fx-blue);background:rgba(30,115,255,.10)}
.fx-links a.active::after{width:70%}

.fx-actions{display:flex;gap:12px}
.fx-btn{

    display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 14px 60px;     
  border-radius: 50px;   
  font-weight: 600;
  font-size: 18px;      
  cursor: pointer;
  transition: all 0.25s ease;
  text-decoration: none;
}
.fx-btn--primary{
  background:var(--fx-blue);color:#fff;border:none;
  box-shadow:0 6px 14px rgba(30,115,255,.25);
}
.fx-btn--primary:hover{background:var(--fx-blue-600);transform:translateY(-2px)}
.fx-btn--ghost{
  background:var(--fx-blue-100);color:#0b1324;border:2px solid var(--fx-blue);
}
.fx-btn--ghost:hover{background:#fff;transform:translateY(-2px)}









/* ===== Contact Hero ===== */
.contact-hero{
  /* جراديانت يغطي كامل السكشن */
  background: linear-gradient(90deg, #cfe2ff 0%, #eaf2ff 45%, #fde7c4 100%);
  /* يفتح بعرض الصفحة كله حتى لو داخل .container عامة */
  width: 100vw;
  margin-left: calc(50% - 50vw);
  margin-right: calc(50% - 50vw);

  padding: 72px 0 96px;
  position: relative;
  overflow: hidden;
}

.ch-container{
  max-width: 1150px;
  margin: 0 auto;
  padding: 0 24px;

  display: grid;
  grid-template-columns: 1fr auto; /* نص يسار + أيقونات يمين */
  align-items: center;
  gap: 24px;
}

.ch-eyebrow{
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
  opacity: .8;
  margin: 0 0 12px;
}

.ch-title{
  font-size: clamp(38px, 6.4vw, 72px); /* عنوان ضخم متجاوب */
  line-height: 1.05;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: .2px;
  margin: 0;
}


.ch-social{
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 18px;
  justify-items: end; /* محاذاة للأيمن */
}

.ch-social a{
  width: 56px;
  height: 56px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  text-decoration: none;
  color: #1e73ff;
  border: 2px solid rgba(30,115,255,.35);
  /* background: rgba(255,255,255,.55); */
  backdrop-filter: blur(2px);
  transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
}

.ch-social a:hover{
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(30,115,255,.22);
  background: #fff;
}

.ch-social i{
  font-size: 18px;
}


/* موبايل: الأيقونات تحت العنوان بدل يمين */
@media (max-width: 780px){
  .ch-container{
    grid-template-columns: 1fr;
    gap: 28px;
  }
  .ch-social{
    justify-items: start;
  }
}








/* شريط التواصل بخلفية كاملة للسيكشن */
.contact-bar{
  position: relative;
  padding: 100px 0;
  overflow: hidden;
  background: #fff;
}

/* زخرفة ناعمة (دوائر وتدرّج + خطوط خفيفة) تغطي كل السيكشن */
.contact-bar::before{
  content:"";
  position:absolute; inset:0;
  pointer-events:none;
  background:
    radial-gradient(650px 650px at -10% 80%, rgba(59,130,246,.12), rgba(59,130,246,0) 60%),
    radial-gradient(650px 650px at 110% -10%, rgba(250,204,21,.16), rgba(250,204,21,0) 60%),
    repeating-linear-gradient(135deg, rgba(0,0,0,.028) 0 14px, rgba(255,255,255,.028) 14px 28px);
}

/* الحاوية */
.contact-container{
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 24px;
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr;
  gap: 32px;
  align-items: start;

}

/* العناوين اليسار */
.eyebrow{
  margin: 0 0 8px;
  font-size: 14px;
  letter-spacing: .06em;
  color: #6b7280;
  margin-top: 40px;
}
.contact-left h3{
  margin: 0;
  font-size: 28px;
  line-height: 1.3;
  font-weight: 700;
  color: #111827;
}

/* الأعمدة اليمنى */
.contact-col h4{
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  margin-top: 40px;
}
.contact-col .value{
  display: inline-block;
  margin-bottom: 12px;
  font-weight: 600;
  color: #111827;
  text-decoration: none;
}
.contact-col .value:hover{ text-decoration: underline; }

.hours-title{
  margin: 0 0 2px;
  font-size: 13px;
  font-weight: 700;
  color: #6b7280;
}
.hours{
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  color: #6b7280;
}

/* استجابة */
@media (max-width: 900px){
  .contact-container{
    grid-template-columns: 1fr;
    gap: 22px;
  }
  .contact-left h3{ font-size: 26px; }
}





:root{
  --border:#000000;
  --muted:#6b7280;
  --text:#111827;
  --blue:#3B82F6;
  --bg:#ffffff;
  --radius:8px;
  --gap:24px;
}

body{margin:0;background:#fff;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;}
.contact{direction:ltr;background:var(--bg);}
.contact .container{max-width:1100px;margin:0 auto;padding:40px 20px;}
.contact h2{
  /* margin:0 0 20px 0; */
  margin-top: 40px;
  font-size:28px; font-weight:700; color:var(--text);
  text-align:left;
}

/* النموذج */
.contact-form{max-width:100%; text-align:left;}
.row.three{
  display:grid;
   grid-template-columns:repeat(3,1fr);
    gap:var(--gap);
  margin-bottom:18px;
}

.field{display:block;}
.field label{
  display:inline-flex; align-items:center; gap:8px;
  font-size:12px; color:var(--muted); margin-bottom:6px;
}
.field label .optional{
  font-size:11px; color:#8b8b8b; border:1px solid var(--border);
  padding:2px 8px; border-radius:999px;
}

.input-wrap{position:relative;}
.input-wrap i{
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  font-size:14px; color:#9ca3af; pointer-events:none;
}
/* .input-wrap input{
  width:100%; height:42px;
  padding:10px 12px 10px 38px;
  border:1px solid var(--border); border-radius:var(--radius);
  font-size:14px; color:var(--text); background:#fff; outline:none;
  transition:border-color .15s ease;
} */

.input-wrap input {
  width:100%; 
  height:42px;
  padding:10px 12px 10px 46px; /* 👈 زودت المسافة من اليسار */
  border:1px solid var(--border); 
  border-radius:var(--radius);
  font-size:14px; 
  color:var(--text); 
  background:#fff; 
  outline:none;
  transition:border-color .15s ease;
  box-sizing: border-box; /* 👈 يمنع التداخل */
}
.input-wrap input::placeholder{color:#9ca3af;}
.input-wrap input:focus{border-color:var(--blue);}

/* الرسالة */
.field.full{margin-top:6px;}


.field label {
  display: block;   /* 👈 يخلي الـ label ياخد سطر كامل */
  margin-bottom: 6px; /* 👈 مسافة صغيرة تحت الكلمة */
  font-size: 14px;
  font-weight: 500;
}
.field.full textarea {
  width: 600px; 
  height: 120px;     
  padding: 8px 10px;
  border: 1px solid #000;
  border-radius: 6px;
  font-size: 14px;
  resize: vertical; 
  outline: none;
  transition: border-color .15s ease;
}


.field.full textarea:focus{border-color:var(--blue);}




.btn {
  margin-top: 20px;
  height: 50px;
  padding: 0 60px;
  background: #1E90FF; /* 👈 أزرق فاتح */
  color: #fff;
  border: none;
  border-radius: 6px;  /* 👈 زوايا ناعمة */
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); /* 👈 ظل زي الصورة */
  transition: all 0.3s ease;
}

/* لما تمر الماوس */
.btn:hover {
  background: #4AAFFB; /* 👈 أزرق أفتح عند الهوفر */
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
}

/* لما ينضغط */
.btn:active {
  transform: translateY(0px);
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
}

/* استجابة الشاشات الصغيرة */
@media (max-width:900px){
  .row.three{grid-template-columns:1fr; }
}



















.site-footer {

  background: url("image/footer-bg.jpg") center/cover no-repeat,
              linear-gradient(135deg, #eaf2ff 0%, #fff3da 100%);
  padding: 48px 18px 32px;  
  margin-top: 0;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr 1.2fr;
  gap: 28px;
  align-items: start;
}


.brand-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.brand-logo {
  width: 160px;
  height: auto;
  object-fit: contain;
}
.brand-name {
  font-weight: 700;
  font-size: 18px;
  margin: 0;
}
.brand-desc {
  color: #555;
  line-height: 1.6;
  margin: 12px 0 10px;
  font-size: 14px;
}


.col-title {
  font-weight: 700;
  font-size: 16px;
  margin: 4px 0 10px;
  color: #1E40AF;  
}


.col-links {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}
.col-links a {
  color: #374151;
  text-decoration: none;
  transition: color .2s ease, transform .06s ease;
}
.col-links a:hover {
  color: #1E90FF;   
}
.col-links a:active {
  color: #145dbf;   
  transform: translateY(1px);
}


.contact-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}
.contact-list li {
  color: #374151;
  display: flex;
  align-items: center;
  gap: 10px;
}
.contact-list i {
  color: #1E90FF;
}
.contact-list a {
  color: #374151;
  text-decoration: none;
  transition: color .2s ease;
}
.contact-list a:hover {
  color: #1E90FF;
}
.contact-list a:active {
  color: #145dbf;
}


.social {
  display: flex;
  gap: 10px;
  margin-top: 12px;
  list-style: none;
  padding: 0;
}
.soc {
  --c: #999;
  --active: #1E90FF;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  text-decoration: none;
  outline: none;
  transition: background-color .3s ease;
}
.soc i {
  font-size: 16px;
  color: var(--c);
  transition: color .2s ease, transform .12s ease;
}
.soc:hover {
  background-color: rgba(30, 144, 255, 0.1);
}
.soc:hover i {
  color: #1E90FF;
}
.soc:active i {
  color: var(--active);
  transform: scale(.92);
}


.soc.fb { --c:#1877F2; --active:#145dbf; }  
.soc.ig { --c:#E4405F; --active:#b6314b; }  
.soc.x  { --c:#000000; --active:#333333; }  
.soc.li { --c:#0A66C2; --active:#084d96; }   


.footer-copy {
  margin-top: 24px;
  text-align: center;
  font-size: 12px;
  color: #9CA3AF;
}
.footer-copy .brand {
  color: #1E90FF;
  font-weight: 700;
}


@media (max-width: 900px) {
  .footer-container {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 560px) {
  .footer-container {
    grid-template-columns: 1fr;
  }
  .site-footer {
    border-radius: 12px;
  }
}

</style>