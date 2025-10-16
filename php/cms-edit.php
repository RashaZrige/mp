<?php
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }

$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) die("DB failed: ".$conn->connect_error);
$conn->set_charset("utf8mb4");

$id = (int)($_GET['id'] ?? 0);
$page = null;

if ($id > 0) {
  $res = $conn->query("SELECT * FROM cms_pages WHERE id=$id LIMIT 1");
  $page = $res ? $res->fetch_assoc() : null;
}
$conn->close();

if (!$page) { die("Page not found"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Page</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link rel="stylesheet" href="../css/cms.css"> <!-- استدعاء CSS -->
</head>
<body>

<section id="cms" class="cms-wrap">
  <aside class="cms-right">
    <div class="card sticky">
      <h3 class="card-title">Page Editor</h3>
      <form method="post" action="cms_save.php">
        <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
        
        <label class="field">
          <span class="label">Page Title</span>
          <input type="text" class="input" name="title" value="<?= htmlspecialchars($page['title']) ?>">
        </label>

        <label class="field">
          <span class="label">Content</span>
          <textarea class="textarea" name="content" rows="6"><?= htmlspecialchars($page['content']) ?></textarea>
        </label>

        <label class="field">
          <span class="label">Seo Meta Title</span>
          <input type="text" class="input" name="seo_title" value="<?= htmlspecialchars($page['seo_title'] ?? '') ?>">
        </label>

        <label class="field">
          <span class="label">Seo Meta Title Description</span>
          <input type="text" class="input" name="seo_desc" value="<?= htmlspecialchars($page['seo_desc'] ?? '') ?>">
        </label>

        <div class="btns">
             <a href="#" class="btn btn-primary" onclick="savePage(this)">Save Changes</a>
        <button type="button" class="btn btn-outline" onclick="window.location='cms-preview.php?id=<?= $page['id'] ?>'">Preview Page</button>
        </div>
      </form>
    </div>
  </aside>
</section>

</body>
</html>




<style>

/* ===== Page Editor (cms_edit.php) ===== */

/* الحاوية العامة: كارد كبير ومتمركز */
#cms.cms-wrap{
  --card-bg:#fff;
  --muted:#6b7280;
  --border:#e5e7eb;
  --primary:#2b79ff;

  display:flex;
  justify-content:center;          /* توسيط أفقي */
  align-items:flex-start;          /* يبقى قريب من الأعلى */
  padding:48px 24px;               /* تنفّس حول الكارد */
  min-height:calc(100vh - 96px);
  background:#fafafa;              /* (اختياري) خفيف يبرز الكارد */
}

/* الكارد: أعرض وأشيك */
#cms .card{
  width:min(92vw, 920px);          /* أعرض بس ما يتعدى الشاشة */
  background:var(--card-bg);
  border:1px solid var(--border);
  border-radius:16px;
  padding:28px;
  box-shadow:0 6px 24px rgba(16,24,40,.06);
}

/* عنوان الكارد */
#cms .card-title{
  font-size:20px;
  font-weight:800;
  margin:0 0 18px;
  color:#0b0f1a;
}

/* الحقول */
#cms .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
#cms .label{ font-size:13px; color:var(--muted); }

#cms .input,
#cms .textarea{
  box-sizing:border-box;           /* مهم لعدم الخروج خارج العرض */
  width:100%;
  border:1px solid var(--border);
  border-radius:12px;
  padding:12px 14px;
  font-size:15px;
  outline:0;
  background:#fff;
}
#cms .input{ height:42px; }
#cms .input:focus, #cms .textarea:focus{
  border-color:#c7d2fe; box-shadow:0 0 0 3px rgba(59,130,246,.12);
}
#cms .textarea{ min-height:160px; resize:vertical; }

/* الأزرار */
#cms .btns{
  display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;
}
#cms .btn{
  box-sizing:border-box;
  height:42px;
  padding:0 16px;
  border-radius:12px;
  border:1px solid var(--border);
  background:#fff;
  font-size:15px;
  font-weight:700;
  cursor:pointer;
  line-height:42px;
  text-align:center;
  white-space:nowrap;
}
#cms .btn-primary{ background:var(--primary); color:#fff; border-color:transparent; }
#cms .btn-outline{ background:#eef2ff; color:#111827; border-color:#e0e7ff; }
#cms .btn-disabled{ background:#eef2f7; color:#94a3b8; cursor:not-allowed; }

/* موبايل */
@media (max-width: 680px){
  #cms .card{ padding:20px; }
  #cms .btn{ flex:1 1 100%; }      /* الأزرار بعرض كامل السطر */
}

</style>