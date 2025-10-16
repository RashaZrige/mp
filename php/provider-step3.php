
<?php
session_start();
if (!isset($_SESSION['user_id'])) { die('No user logged in'); }
$user_id = (int)$_SESSION['user_id'];

try {
  $pdo = new PDO('mysql:host=localhost;dbname=fixora;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (Exception $e) { die('DB Error: '.$e->getMessage()); }

$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  // 1) اجمع القيم من الفورم
  $title       = trim($_POST['service'] ?? '');
  $years       = ($_POST['years_experience'] ?? '') !== '' ? (int)$_POST['years_experience'] : null;
  $price_from  = $_POST['price_min'] ?? null;
  $price_to    = $_POST['price_max'] ?? null;
  $durationLbl = $_POST['duration'] ?? '';
  $category    = trim($_POST['category'] ?? '');
  $details     = trim($_POST['details'] ?? '');       // قائمة النقاط
  $description = trim($_POST['description'] ?? '');   // الوصف الحر

  // 2) حوّل المدة إلى دقائق
  $durMap = ['1 Hour'=>60,'1.5 Hours'=>90,'2 Hours'=>120,'3 Hours'=>180];
  $duration_minutes = $durMap[$durationLbl] ?? null;

  // 3) فحوصات بسيطة
  if ($title==='')               $errors[]='Choose Service is required';
  if ($years===null)             $errors[]='Years of experience is required';
  if ($price_from===''||$price_to==='') $errors[]='Price range is required';
  if ($duration_minutes===null)  $errors[]='Duration is required';
  if ($category==='')            $errors[]='Category is required';

  // 4) رفع صورة الخدمة (اختياري)
  $img_path = null;
  if (!empty($_FILES['svcImage']['name']) && $_FILES['svcImage']['error']===UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['svcImage']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
      $newName  = 'svc_'.$user_id.'_'.time().'.'.$ext;
      $relPath  = 'uploads/services/'.$newName;   // يُخزَّن في DB
      $savePath = '../'.$relPath;                 // مسار فعلي (الملف داخل /php)
      if (!is_dir(dirname($savePath))) { @mkdir(dirname($savePath),0777,true); }
      if (move_uploaded_file($_FILES['svcImage']['tmp_name'], $savePath)) {
        $img_path = $relPath;
      }
    }
  }

  if (!$errors) {
    // نستخدم ترانزاكشن: خدمة + عناصرها
    $pdo->beginTransaction();
    try {
      // 5) حدّث سنوات الخبرة في provider_profiles
      if ($years !== null) {
        $sql = "INSERT INTO provider_profiles (user_id, years_experience, step3_done, created_at, updated_at)
                VALUES (:uid, :y, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE years_experience=VALUES(years_experience), step3_done=1, updated_at=NOW()";
        $st  = $pdo->prepare($sql);
        $st->execute([':uid'=>$user_id, ':y'=>$years]);
      }

      // 6) خزّن الخدمة في services
      $sql = "INSERT INTO services
                (title, description, category, img_path, price_from, price_to, duration_minutes, is_active, provider_id, created_at)
              VALUES
                (:title, :desc, :cat, :img, :pf, :pt, :dur, 1, :pid, NOW())";
      $st = $pdo->prepare($sql);
      $st->execute([
        ':title' => $title,
        ':desc'  => ($description !== '' ? $description : $details), // إن ما كتب وصف، نحفظ details
        ':cat'   => $category,
        ':img'   => $img_path,
        ':pf'    => $price_from,
        ':pt'    => $price_to,
        ':dur'   => $duration_minutes,
        ':pid'   => $user_id,
      ]);

      //  7) حفظ عناصر "What Does The Service Include?" في service_includes 
      $service_id = (int)$pdo->lastInsertId();
      if ($service_id && $details !== '') {
        $lines = preg_split("/\r\n|\r|\n/", $details);
        $insInc = $pdo->prepare("INSERT INTO service_includes (service_id, text) VALUES (:sid, :txt)");
        foreach ($lines as $ln) {
          $txt = trim($ln);
          // شيل أي علامات نقطية في بداية السطر
          $txt = ltrim($txt, "-•* \t");
          if ($txt !== '') {
            $insInc->execute([':sid'=>$service_id, ':txt'=>$txt]);
          }
        }
      }

      $pdo->commit();


// 8) وجّه حسب الزر
      $go = $_POST['go'] ?? (isset($_POST['formaction']) ? $_POST['formaction'] : 'next');
      header('Location: '.($go==='prev' ? 'provider-step2.php' : 'provider-step4.php'));
      exit;

    } catch(Exception $e){
      $pdo->rollBack();
      $errors[] = "Save failed: ".$e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Complete Your Provider Profile</title>
  <link rel="stylesheet" href="../css/provider-step3.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>
<main class="page">
  <section class="card">
    <!-- Title + Stepper -->
    <div class="card-title">
      <h1>Complete Your Provider Profile</h1>
      <ol class="steps">
        <li class="step is-done"><div class="dot">01</div><small>Basic Information</small></li>
        <li class="step is-done"><div class="dot">02</div><small>Identification</small></li>
        <li class="step is-activ"><div class="dot">03</div><small>Services</small></li>
        <li class="step"><div class="dot">04</div><small>Security</small></li>
      </ol>
    </div>

    <?php if (!empty($errors)): ?>
      <div style="margin:0 24px 12px;padding:10px 12px;border:1px solid #fecaca;background:#fee2e2;color:#7f1d1d;border-radius:8px;">
        <strong>Please fix the following:</strong>
        <ul style="margin:8px 0 0 18px;">
          <?php foreach ($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form class="body grid" method="post" enctype="multipart/form-data">
      <!-- LEFT -->
      <div class="left">
        <h2 class="section-title">Add Service</h2>

        <div class="field">
          <label>Choose Service</label>
          <select name="service" required>
            <option value="">Choose Service</option>
            <option>Home Cleaning</option>
            <option>Plumbing</option>
            <option>Electrical</option>
          </select>
        </div>

        <!-- Years directly under Choose Service -->
        <div class="field">
          <label>Years of Experience</label>
          <div class="with-prefix">
            <span class="prefix"><i class="fa-regular fa-clock"></i></span>
            <input type="number" name="years_experience" min="0" max="60" placeholder="e.g. 5" required>
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Price Range (Min)</label>
            <div class="with-prefix">
              <span class="prefix">$</span>
              <input type="number" step="0.01" placeholder="0.00" name="price_min" required>
            </div>
          </div>
          <div class="field">
            <label>Price Range (Max)</label>
            <div class="with-prefix">
              <span class="prefix">$</span>
              <input type="number" step="0.01" placeholder="100" name="price_max" required>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Duration</label>
            <select name="duration" required>
              <option value="">Select Duration</option>
              <option>1 Hour</option>
              <option>1.5 Hours</option>
              <option>2 Hours</option>
              <option>3 Hours</option>
            </select>
          </div>
          <div class="field">
            <label>Category</label>
            <select name="category" required>
              <option value="">Select Category</option>
              <option>Cleaning</option>
              <option>Plumbing</option>
              <option>Electrical</option>
              <option>Appliances</option>
            </select>
          </div>
        </div>

<div class="field">
          <label>What Does The Service Include?</label>
          <textarea rows="4" name="details"
            placeholder="Write in bullet points what the service will include (e.g., • Sweep floors
• Mop floors
• Vacuum carpets
• Dust & wipe surfaces)"
            required></textarea>
        </div>
      </div>

      <!-- RIGHT -->
      <aside class="right">
        <h3 class="mini-title">Add A Display Image For The Service</h3>
        <label class="upload-box">
          <input id="svcImage" type="file" name="svcImage" accept="image/png,image/jpeg,image/webp" hidden>
          <img id="svcPreview" alt="" style="display:none;">
          <div class="upload-hint" id="svcHint">
            <i class="fa-regular fa-image"></i>
            <p>Click To Upload And Drop</p>
            <small>PNG , JPG ( MAX 800 × 400 )</small>
          </div>
        </label>

        <!-- Description same width as image -->
        <div class="field">
          <label>Service Description</label>
          <textarea class="tall-like-upload" name="description"
            placeholder="Briefly describe your service, tools, and specialties..." required></textarea>
        </div>
      </aside>

      <!-- Centered actions -->
      <div class="actions actions-center">
  <!-- Previous يرجّع للخطوة 2 بدون حفظ -->
  <button type="submit" class="btn prev" formaction="provider-step2.php" formnovalidate>
    Previous
  </button>

  <!-- Next: يرسل لنفس الصفحة ليحفظ ثم يعمل redirect -->
  <button type="submit" class="btn next" name="go" value="next">
    Next
  </button>
</div>
    </form>
  </section>
</main>

<script>
  // Image preview
  const box  = document.querySelector('.upload-box');
  const inp  = document.getElementById('svcImage');
  const img  = document.getElementById('svcPreview');
  const hint = document.getElementById('svcHint');
  box?.addEventListener('click', () => inp.click());
  inp?.addEventListener('change', e => {
    const f = e.target.files?.[0]; if (!f) return;
    const r = new FileReader();
    r.onload = ev => { img.src = ev.target.result; img.style.display='block'; hint.style.display='none'; };
    r.readAsDataURL(f);
  });
</script>
</body>
</html>