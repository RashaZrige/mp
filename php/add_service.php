


<?php
/* ==== DB ==== */
$conn = mysqli_connect("localhost", "root", "", "fixora");
if (!$conn) die("DB Error: ".mysqli_connect_error());
mysqli_set_charset($conn, "utf8mb4");

$msg = "";

/* ==== POST ==== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $title      = trim($_POST["title"] ?? "");
  $price_from = floatval($_POST["price_from"] ?? 0);
  $price_to   = floatval($_POST["price_to"] ?? 0);
  $duration   = ($_POST["duration"] === "" ? "NULL" : intval($_POST["duration"]));
  $category   = trim($_POST["category"] ?? "");
  $includes   = $_POST["includes"] ?? "";

  if ($title === "" || $category === "") {
    $msg = "❌ اكتب اسم الخدمة واختر الفئة.";
  } elseif ($price_from > $price_to) {
    $msg = "❌ السعر الأدنى يجب أن يكون أقل من الأعلى.";
  } elseif (empty($_FILES["image"]["name"])) {
    $msg = "❌ اختر صورة.";
  } else {
    /* ==== رفع الصورة بأبسط طريقة ==== */
    $uploadDir = "image/"; // ← لو مجلد الصور خارج مجلد هذا الملف: "../image/"
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

    $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg","jpeg","png","webp"];
    if (!in_array($ext, $allowed)) {
      $msg = "❌ مسموح فقط: JPG / PNG / WEBP.";
    } elseif ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
      $msg = "❌ خطأ رفع (كود: ".$_FILES["image"]["error"].")";
    } else {
      $newname = "svc_".uniqid().".".$ext;
      $destFs  = $uploadDir . $newname;     // مسار الحفظ على نفس المجلد
      if (!move_uploaded_file($_FILES["image"]["tmp_name"], $destFs)) {
        $msg = "❌ فشل نقل الملف. جرّب إعطاء صلاحيات للمجلد image/";
      } else {
        $img_path = $destFs; // هذا اللي بندخله في DB (نسبي)

        // ==== إدخال الخدمة ====
        $title_sql    = mysqli_real_escape_string($conn, $title);
        $category_sql = mysqli_real_escape_string($conn, $category);
        $img_sql      = mysqli_real_escape_string($conn, $img_path);

        $sql = "
          INSERT INTO services
            (title, img_path, price_from, price_to, duration_minutes, category, is_active, description)
          VALUES
            ('$title_sql', '$img_sql', $price_from, $price_to, $duration, '$category_sql', 1, '')
        ";
        if (!mysqli_query($conn, $sql)) {
          $msg = "❌ DB Error (services): ".mysqli_error($conn);
        } else {
          $service_id = mysqli_insert_id($conn);

          // ==== إدخال النقاط (كل سطر = نقطة) ====
          $lines = preg_split("/\r\n|\n|\r/", $includes);
          if ($lines) {
            foreach ($lines as $i => $line) {
              $line = trim($line);
              if ($line === "") continue;
              $txt = mysqli_real_escape_string($conn, $line);
              mysqli_query($conn, "INSERT INTO service_includes (service_id, text, sort) VALUES ($service_id, '$txt', ".($i+1).")");
            }
          }

          $msg = "✅ تم حفظ الخدمة بنجاح.";
        }
      }
    }
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Service | Fixora</title>
  <!-- نفس ستايل صفحة اللوجين -->
  <link rel="stylesheet" href="../css/add_servies.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="page-wrap">
    <div class="register-card">
      <!-- Left (Brand Panel) -->
      <div class="register-left">
        <div class="left-content">
          <img src="../image/home-logo.png" alt="Fixora Logo" class="brand-logo">
          <h2 class="left-title">Add New Service ✨</h2>
          <p class="left-sub">
            Your home’s comfort starts here —<br>
            keep your catalog consistent, fast, and professional.
          </p>
        </div>
      </div>

      <!-- Right (Form) -->
      <div class="register-right">
        <form class="form-box" method="post" enctype="multipart/form-data">
          <h3 class="form-title">Add New Services</h3>

          <?php if ($msg): ?>
            <div class="alert <?= strpos($msg,'✅')!==false ? 'success' : 'error' ?>">
              <?= htmlspecialchars($msg) ?>
            </div>
          <?php endif; ?>


<!-- اسم الخدمة -->
          <div class="field">
            <label class="field-label">Choose Service</label>
            <i class="fa-solid fa-pen-to-square field-icon"></i>
            <input type="text" name="title" placeholder="e.g. Home Cleaning" required>
          </div>

          <!-- رفع صورة -->
          <!-- <div class="field">
            <label class="field-label">Upload Image</label>
            <div class="upload-box">
              <input type="file" name="image" accept="image/png,image/jpeg" required>
              <div class="upload-hint">Click to Upload and Drop<br>PNG , JPG (MAX 800 × 400)</div>
            </div>
          </div> -->
         

          
<!-- <div class="field">
  <label class="field-label">Upload Image (optional)</label>
  <div class="upload-box">
    <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
    <div class="upload-hint">Drag & Drop or Click<br>PNG , JPG , WebP</div>
  </div>
</div> -->


<div class="field">
  <label class="field-label">Upload Image (optional)</label>
  <div class="upload-box">
    <input type="file" id="imageInput" name="image" accept="image/png,image/jpeg,image/webp">
    <div id="uploadHint" class="upload-hint">
      Drag & Drop or Click<br>PNG , JPG , WebP
    </div>
    <!-- صورة المعاينة -->
    <img id="preview" style="width:100%; height:100%; object-fit:cover; display:none; border-radius:8px;" alt="Preview">
  </div>
</div>

<!-- أو: رابط صورة مباشر -->
<div class="field">
  <label class="field-label">Image URL (optional)</label>
  <input type="url" name="image_url" placeholder="https://example.com/photo.jpg">
  <!-- <small style="color:#6b7480">لو وضعت رابط، ما في داعي ترفعي صورة.</small> -->
</div>
          <!-- الأسعار -->
          <div class="form-grid-2">
            <div class="field">
              <label class="field-label">Price Range (Min)</label>
              <div class="with-prefix">
                <span class="prefix">$</span>
                <input type="number" name="price_from" step="0.01" value="0.00">
              </div>
            </div>
            <div class="field">
              <label class="field-label">Price Range (Max)</label>
              <div class="with-prefix">
                <span class="prefix">$</span>
                <input type="number" name="price_to" step="0.01" value="100">
              </div>
            </div>
          </div>

          <!-- المدة / الفئة -->
          <div class="form-grid-2">
            <div class="field">
              <label class="field-label">Duration</label>
              <select name="duration">
                <option value="">Select Duration</option>
                <option value="60">1 Hour</option>
                <option value="90">1.5 Hours</option>
                <option value="120">2 Hours</option>
                <option value="180">3 Hours</option>
              </select>
            </div>
            <div class="field">
              <label class="field-label">Category</label>
              <select name="category" required>
                <option value="">Select Category</option>
                <option value="Cleaning">Cleaning</option>
                <option value="Plumbing">Plumbing</option>
                <option value="Electrical">Electrical</option>
              </select>
            </div>
          </div>

          <!-- النقاط -->
          <!-- <div class="field">
            <label class="field-label">What Does The Service Include?</label>
            <textarea name="includes" rows="5" placeholder="Write in Bullet Points What The Service Will Include, For Ex

Sweep, Mop, And Vacuum All Floors

Dust And Wipe Furniture & Surfaces "></textarea>
          </div> -->


          <div class="field">
  <label class="field-label">What Does The Service Include?</label>
  <textarea name="includes" rows="5"
    placeholder="Write in Bullet Points What The Service Will Include, For Ex&#10;• Sweep, Mop, And Vacuum All Floors&#10;• Dust And Wipe Furniture & Surfaces"></textarea>
</div>

          <div class="actions">
  <button class="btn-primary" type="submit">Save Service</button>
  <button type="reset" class="btn cancel-btn">Cancel</button>
</div>

          <!-- <button class="btn-primary" type="submit">Save Service</button> --> 
        </form>
      </div>
    </div>
  </div>
</body>
</html>




<script>
document.getElementById('imageInput').addEventListener('change', function(e){
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(ev) {
      const preview = document.getElementById('preview');
      const hint = document.getElementById('uploadHint');
      preview.src = ev.target.result;
      preview.style.display = 'block';   // عرض الصورة
      hint.style.display = 'none';       // إخفاء النص
    };
    reader.readAsDataURL(file);
  }
});
</script>