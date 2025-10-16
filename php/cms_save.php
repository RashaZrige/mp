<?php
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) { header("Location: {$BASE}/login.html"); exit; }
$editorId = (int)$_SESSION['user_id']; // مين عدّل

// اتصال DB
$conn = new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

// القيم القادمة من الفورم
$id        = (int)($_POST['id'] ?? 0);
$title     = trim($_POST['title'] ?? '');
$content   = trim($_POST['content'] ?? '');
$seo_title = trim($_POST['seo_title'] ?? '');
$seo_desc  = trim($_POST['seo_desc'] ?? '');

if ($id > 0) {
  // نخلي العمليتين مع بعض: تحديث الصفحة + حفظ نسخة
  $conn->begin_transaction();

  // 1) تحديث الصفحة
  $sql = "UPDATE cms_pages
          SET title=?, content=?, seo_title=?, seo_desc=?, updated_at=NOW()
          WHERE id=?";
  $st = $conn->prepare($sql);
  if (!$st) { $conn->rollback(); die("Prepare failed: " . $conn->error); }
  $st->bind_param("ssssi", $title, $content, $seo_title, $seo_desc, $id);
  $st->execute();
  $st->close();

  // 2) إضافة نسخة للأرشيف بعد التحديث (status الحالية تبقى كما هي في cms_pages)
  //  ملاحظة: نستخدم INSERT..SELECT لياخذ snapshot من الصفحة بعد التحديث
  $verSql = "
    INSERT INTO cms_page_versions
      (page_id, title, content, seo_title, seo_desc, status, action, editor_user_id, created_at)
    SELECT id, title, content, seo_title, seo_desc, status, ?, ?, NOW()
    FROM cms_pages WHERE id = ?
  ";
  $ver = $conn->prepare($verSql);
  if (!$ver) { $conn->rollback(); die("Prepare failed: " . $conn->error); }
  $action = 'save';                       // تمييز العملية (save)
  $ver->bind_param("sii", $action, $editorId, $id);
  $ver->execute();
  $ver->close();

  $conn->commit();
}

// إنهاء وتحويل لنفس الصفحة مع فلاش نجاح
$conn->close();
header("Location: content management.php?id={$id}&ok=1#editor");
exit;