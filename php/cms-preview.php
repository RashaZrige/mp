<?php
session_start();

$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;

$page = null;

if ($id > 0) {
  if ($st = $conn->prepare("SELECT title, content, seo_title, seo_desc FROM cms_pages WHERE id=? LIMIT 1")) {
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $page = $res ? $res->fetch_assoc() : null;
    $st->close();
  }
} elseif ($slug !== '') {
  if ($st = $conn->prepare("SELECT title, content, seo_title, seo_desc FROM cms_pages WHERE slug=? LIMIT 1")) {
    $st->bind_param("s", $slug);
    $st->execute();
    $res = $st->get_result();
    $page = $res ? $res->fetch_assoc() : null;
    $st->close();
  }
}

$conn->close();

if (!$page) { http_response_code(404); die("Page not found"); }
?>
<!doctype html>
<html lang="en" dir="ltr"> <!-- نخليها LTR عشان ما يسحب لليمين -->
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page['seo_title'] ?: $page['title']) ?></title>
  <?php if (!empty($page['seo_desc'])): ?>
    <meta name="description" content="<?= h($page['seo_desc']) ?>">
  <?php endif; ?>
  <style>
    /* ستايل مستقل خفيف */
    *{box-sizing:border-box}
    body{
      margin:0; padding:32px;
      font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
      background:#f5f7fb; color:#0b0f1a;
      direction:ltr; text-align:left;   /* يكسر أي RTL خارجي */
    }
    .container{
      max-width: 920px;
      margin: 0 auto;
      background:#fff;
      border:1px solid #e5e7eb;
      border-radius:14px;
      padding:28px;
      box-shadow:0 6px 18px rgba(0,0,0,.06);
    }
    h1{margin:0 0 10px; font-size:28px}
    .content{line-height:1.7; font-size:16px}
    .content p{margin:0 0 12px}
  </style>
</head>
<body>
<div class="container">
  <h1><?= h($page['title']) ?></h1>

  <?php if (!empty($page['seo_title'])): ?>
    <h3>SEO Title: <?= h($page['seo_title']) ?></h3>
  <?php endif; ?>

  <?php if (!empty($page['seo_desc'])): ?>
    <p><strong>SEO Description:</strong> <?= h($page['seo_desc']) ?></p>
  <?php endif; ?>

  <div class="content">
    <?= $page['content'] ?>
  </div>
</div>
</body>
</html>