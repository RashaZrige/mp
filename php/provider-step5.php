<?php
session_start();
if (!isset($_SESSION['user_id'])) { die('No user logged in'); }
$user_id = (int)$_SESSION['user_id'];

try {
  $pdo = new PDO("mysql:host=localhost;dbname=fixora;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch(Exception $e){
  die("DB Error: ".$e->getMessage());
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['go']) && $_POST['go']==='prev') {
    // رجوع للستيب 4
    header("Location: provider-step4.php");
    exit;
  }

  if (isset($_POST['go']) && $_POST['go']==='next') {
    // لازم يوافق
    if (empty($_POST['agree'])) {
      $error = "⚠️ لازم توافق على الشروط للمتابعة.";
    } else {
      // تحديث DB
      // $st = $pdo->prepare("
      //   INSERT INTO provider_profiles (user_id, terms_accepted, updated_at, created_at)
      //   VALUES (:id, 1, NOW(), NOW())
      //   ON DUPLICATE KEY UPDATE terms_accepted=1, updated_at=NOW()
      // ");
      // $st->execute([':id'=>$user_id]);


      $st = $pdo->prepare("
  INSERT INTO provider_profiles (user_id, terms_accepted, step5_done, created_at, updated_at)
  VALUES (:id, 1, 1, NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    terms_accepted = 1,
    step5_done     = 1,
    updated_at     = NOW()
");
$st->execute([':id' => $user_id]);
      // يروح على الداشبورد
      header("Location: dashboard.php");
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Terms & Conditions</title>
  <link rel="stylesheet" href="../css/provider-step5.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>
  <main class="page">
    <section class="card">
      <h2 class="section-title">
        <i class="fa-solid fa-file-contract"></i>
        Terms & Conditions
      </h2>
     
<?php
// اتصال DB
$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// دوال مساعدة
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// جلب محتوى FAQ
$slug = 'Terms & Conditions';
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

        <form method="post">
      <div class="accordion">
        <details open>
          <summary>
            <span>Introduction</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            Welcome to [Platform Name]. By registering as a service provider, you agree to comply with
            all terms, rules, and policies described here. These terms protect both the platform and clients.
          </p>
        </details>

        <details>
          <summary>
            <span>Eligibility</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Providers must be 18 years or older.<br>
            • Provide valid personal identification (ID).<br>
            • Submit accurate personal, contact, and professional information.<br>
            • Comply with local laws for the services offered.
          </p>
        </details>

        <details>
          <summary>
            <span>Account Information</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Keep your account credentials secure.<br>
            • You are responsible for all activity under your account.<br>
            • The platform may suspend accounts showing suspicious activity.
          </p>
        </details>

        <details>
          <summary>
            <span>Services</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Services must be legal, safe, and accurately described.<br>
            • Deliver services professionally and ethically.<br>
            • The platform does not guarantee bookings; providers act independently.
          </p>
        </details>

        <details>
          <summary>
            <span>Subscription & Availability</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Choose a subscription plan to receive bookings.<br>
            • Plans may limit how many requests you can receive.<br>
            • Keep your availability (Online/Offline) accurate.<br>
            • Poor availability may affect receiving new requests.
          </p>
        </details>

        <details>
          <summary>
            <span>Payments</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Payments are handled in cash between provider and client.<br>
            • Proof of payment may be required for records.<br>
            • Service fees/commissions may apply based on plan.<br>
            • The platform is not responsible for cash handled outside its system.
          </p>
        </details>

        <details>
          <summary>
            <span>Cancellations & Refunds</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Respect confirmed bookings.<br>
            • Follow platform policy for cancellations.<br>
            • Frequent unjustified cancellations may lead to suspension.
          </p>
        </details>

        <details>
          <summary>
            <span>Reviews & Ratings</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Clients can leave reviews and ratings.<br>
            • Reviews won’t be removed unless they violate platform rules.<br>
            • Inappropriate content may be removed by the platform.
          </p>
        </details>

<details>
          <summary>
            <span>Code of Conduct</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Be professional, respectful, and lawful.<br>
            • Harassment, discrimination, or illegal activity leads to suspension or termination.<br>
            • The platform is not liable for actions outside agreed services.
          </p>
        </details>

        <details>
          <summary>
            <span>Liability & Indemnity</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • You are responsible for the quality and safety of your services.<br>
            • The platform is not liable for injuries, damages, or losses you cause.<br>
            • You agree to indemnify the platform from claims arising from your services.
          </p>
        </details>

        <details>
          <summary>
            <span>Termination</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Accounts may be suspended or terminated for violations.<br>
            • You may close your account at any time; unpaid dues must be settled.
          </p>
        </details>

        <details>
          <summary>
            <span>Updates</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            • Terms may be updated periodically.<br>
            • Continued use after updates means you accept the changes.
          </p>
        </details>

        <details>
          <summary>
            <span>Acceptance</span>
            <i class="chev fa-solid fa-chevron-down"></i>
          </summary>
          <p>
            By creating an account, you confirm you have read, understood, and agree to these terms.
          </p>
        </details>
      </div>

      <label class="consent">
        <!-- <input type="checkbox" required> -->
         <input type="checkbox" name="agree" value="1" required>
        By signing in, you agree to our <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a>.
      </label>

          <!-- <div class="actions">
      <button type="submit" class="btn prev"
              formaction="provider-step4.php"
              formnovalidate>
        Previous
      </button>
      <button type="submit" class="btn next"
              formaction="dashboard.php">
        Next
      </button> -->

 <div class="actions">
  <button type="submit" class="btn prev" name="go" value="prev" formnovalidate>
    Previous
  </button>
  <button type="submit" class="btn next" name="go" value="next">
    Next
  </button>
</div>
    </div>
        </form>
    </section>
  </main>
</body>
</html>