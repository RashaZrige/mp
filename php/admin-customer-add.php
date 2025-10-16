<?php
/* admin_customer_add.php — Add Customer (Admin) */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$flash_err = isset($_GET['err']) ? $_GET['err'] : '';
$flash_ok  = isset($_GET['ok'])  ? $_GET['ok']  : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Fixora – Add Customer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="../css/rating_dashbord.css">
<style>
.page{max-width:900px;margin:0 auto;padding:18px 24px 60px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
.page-title{font:700 20px/1.2 "Inter",system-ui;margin:6px 0 16px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.field{display:flex;flex-direction:column;gap:6px}
.label{font-size:13px;color:#6b7280}
.input,.select{
  height:44px;border:1px solid #e5e7eb;border-radius:10px;padding:0 12px;background:#fff;font-size:14px}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:14px}
.btn{height:42px;padding:0 16px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-weight:600;cursor:pointer}
.btn-primary{background:#2b79ff;color:#fff;border-color:transparent}
.btn-danger{background:#fff;color:#dc2626;border-color:#fecaca}
.flash-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;margin-bottom:12px}
.flash-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:12px;padding:10px 12px;margin-bottom:12px}
.note{font-size:12px;color:#6b7280}
@media (max-width:760px){.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<section class="topbar">
  <div class="tb-inner">
    <div class="tb-left">
      <div class="brand"><img src="/mp/image/home-logo.png" alt="Fixora logo" class="brand-logo"></div>
    </div>
    <div class="tb-center"></div>
    <div class="tb-right"><button class="notif-pill"><i class="fa-solid fa-bell"></i></button></div>
  </div>
</section>

<div class="page">
  <h2 class="page-title">Add customer</h2>

  <?php if($flash_ok): ?><div class="flash-ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if($flash_err): ?><div class="flash-err"><?= h($flash_err) ?></div><?php endif; ?>

  <div class="card">
    <form method="post" action="admin-customer-add-save.php" id="addForm" autocomplete="off">
      <div class="form-grid">
        <label class="field">
          <span class="label">Full name *</span>
          <input class="input" type="text" name="full_name" required placeholder="Ex: Sara Ahmed">
        </label>

        <label class="field">
          <span class="label">Phone</span>
          <input class="input" type="text" name="phone" placeholder="+9705XXXXXXXX">
        </label>

        <label class="field">
          <span class="label">Email</span>
          <input class="input" type="email" name="email" placeholder="sara@example.com">
        </label>

        <label class="field">
          <span class="label">Status</span>
          <select class="select" name="status">
            <option value="active" selected>active</option>
            <option value="suspended">suspended</option>
          </select>
        </label>

        <label class="field">
          <span class="label">Password (optional)</span>
          <input class="input" type="password" name="password" placeholder="Leave empty to auto-generate">
          <span class="note">If left empty, a strong password will be generated.</span>
        </label>

        <label class="field">
          <span class="label">Confirm password</span>
          <input class="input" type="password" name="password2" placeholder="Confirm password">
        </label>
      </div>

      <div class="actions">
        <button class="btn btn-danger" type="button" onclick="window.location='admin-customers.php'">Cancel</button>
        <button class="btn btn-primary" type="submit">Save customer</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>