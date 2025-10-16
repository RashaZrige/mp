<?php
/* cms_publish.php */
session_start();
$BASE = '/mp';
if (empty($_SESSION['user_id'])) {
  header("Location: {$BASE}/login.html"); exit;
}

$id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$uid = (int)$_SESSION['user_id'];
if ($id <= 0) {
  header("Location: content management.php?err=nopage"); exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost","root","","fixora");
if ($conn->connect_error) { die("DB failed: ".$conn->connect_error); }
$conn->set_charset("utf8mb4");

try {
  // نضمن إن أي خطأ يرجّع كل شيء
  $conn->begin_transaction();

  /* 1) ننشر الصفحة: status=published و visible=1 */
  $sql = "UPDATE cms_pages
          SET status='published', visible=1, updated_at=NOW()
          WHERE id=?";
  $st = $conn->prepare($sql);
  if (!$st) { throw new Exception("Prepare failed: ".$conn->error); }
  $st->bind_param("i", $id);
  $ok = $st->execute();
  $st->close();
  if (!$ok || $conn->affected_rows < 0) {
    throw new Exception("Update failed.");
  }

  /* 2) نسجّل لقطة في الأرشيف (Version History) */
  // ملاحظة: نأخذ Snapshot بعد ما صارت الصفحة Published
  $verSql = "
    INSERT INTO cms_page_versions
      (page_id, title, content, seo_title, seo_desc, status, action, editor_user_id, created_at)
    SELECT id, title, content, seo_title, seo_desc, status, 'publish', ?, NOW()
    FROM cms_pages WHERE id = ?
  ";
  $ver = $conn->prepare($verSql);
  if ($ver) {
    $ver->bind_param("ii", $uid, $id);
    $ver->execute();   // لو جدول الأرشيف ناقص، بإمكانك تلغيه ببساطة أو تلتقط الخطأ
    $ver->close();
  }
  // لو بدك تتجاهل خطأ الأرشيف تمامًا بدون ما تقطع العملية:
  // else { /* لا شيء */ }

  $conn->commit();

} catch (Throwable $e) {
  // رجوع لكل شيء لو صار خطأ
  if ($conn && $conn->errno === 0) {
    $conn->rollback();
  }
  // رجّع مع رسالة خطأ مبسطة
  $conn->close();
  header("Location: content management.php?id={$id}&pub=0&err=publish_failed#editor");
  exit;
}

$conn->close();

/* 3) رجوع لنفس صفحة الإدارة مع فلاش نجاح */
header("Location: content management.php?id={$id}&pub=1#editor");
exit;