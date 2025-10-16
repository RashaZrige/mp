
<?php
/* ---------- boot ---------- */
session_start();
if (!isset($_SESSION['user_id'])) { die('No user logged in'); }
$user_id = (int)$_SESSION['user_id'];

/* ---------- DB ---------- */
try {
  $pdo = new PDO("mysql:host=localhost;dbname=fixora;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (Exception $e) {
  die("DB Error: ".$e->getMessage());
}

/* ---------- load existing ---------- */
$pp = [];
$st = $pdo->prepare("SELECT national_id FROM provider_profiles WHERE user_id=? LIMIT 1");
$st->execute([$user_id]);
$pp = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$val_id_number = htmlspecialchars($pp['national_id'] ?? '');

/* ---------- handle post ---------- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_type   = $_POST['id_type']   ?? '';
  $id_number = trim($_POST['id_number'] ?? '');
  $id_country= $_POST['id_country'] ?? ''; // اختياري (غير مخزّن حاليًا)

  if ($id_type === '')   { $errors[] = "Please select an ID type."; }
  if ($id_number === '') { $errors[] = "ID number is required."; }
  if ($id_number !== '' && !preg_match('/^[A-Za-z0-9\-]{6,20}$/', $id_number)) {
    $errors[] = "ID number must be 6–20 letters/numbers.";
  }

if (empty($errors)) {
    $sql = "
      INSERT INTO provider_profiles
        (user_id, national_id, id_type, id_country, step2_done, created_at, updated_at)
      VALUES
        (:uid, :nid, :idtype, :idcountry, 1, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        national_id = VALUES(national_id),
        id_type     = VALUES(id_type),
        id_country  = VALUES(id_country),
        step2_done  = 1,
        updated_at  = NOW()
    ";
    $q = $pdo->prepare($sql);
    $q->execute([
      ':uid'       => $user_id,
      ':nid'       => $id_number,
      ':idtype'    => $id_type,
      ':idcountry' => $id_country
    ]);

    header("Location: provider-step3.php");
    exit;
} else {
    $val_id_number = htmlspecialchars($id_number);
}
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Complete Your Provider Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../css/provider-step2.css" />
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
          <li class="step is-active"><div class="dot">02</div><small>Identification</small></li>
          <li class="step"><div class="dot">03</div><small>Services</small></li>
          <li class="step"><div class="dot">04</div><small>Security</small></li>
        </ol>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="alert error">
        <strong>Please fix the following:</strong>
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?=htmlspecialchars($e)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Body/Form -->
      <form class="body" method="post" autocomplete="off">
        <h2 class="section-title">Identification</h2>

        <div class="field">
          <label for="id_type">ID Type</label>
          <select id="id_type" name="id_type" required>
            <option value="">Select ID type</option>
            <option value="national_id"   <?= (($_POST['id_type']??'')==='national_id')?'selected':''; ?>>National ID</option>
            <option value="passport"      <?= (($_POST['id_type']??'')==='passport')?'selected':''; ?>>Passport</option>
            <option value="driver_license"<?= (($_POST['id_type']??'')==='driver_license')?'selected':''; ?>>Driver’s License</option>
          </select>
        </div>

        <div class="field">
          <label for="id_number">ID Number</label>


<div class="with-icon">
            <i class="fa-regular fa-id-card"></i>
            <input id="id_number" type="text" name="id_number"
                   placeholder="ex: 4023480030"
                   value="<?=$val_id_number?>"
                   required
                   pattern="[A-Za-z0-9\-]{6,20}"
                   title="6–20 letters/numbers (A–Z, 0–9, -)">
          </div>
          <small class="hint">Enter your National ID or Passport number (no spaces).</small>
        </div>

        <div class="field">
          <label for="id_country">Issuing Country <span class="muted">(optional)</span></label>
          <input id="id_country" type="text" name="id_country" placeholder="ex: Palestine / Jordan / Egypt"
                 value="<?= isset($_POST['id_country']) ? htmlspecialchars($_POST['id_country']) : '' ?>">
        </div>

        <div class="actions">
<button type="button" class="btn prev" onclick="location.href='provider-step1.php'">Previous</button>

          <button type="submit" class="btn next">Next</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>



