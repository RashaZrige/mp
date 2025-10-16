<?php
// save_availability.php
header('Content-Type: application/json');

try {
  // 1) الاتصال
  $pdo = new PDO('mysql:host=localhost;dbname=fixora;charset=utf8mb4','root','', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);

  // 2) هوية المستخدم (عدّل حسب نظامك)
  session_start();
  $user_id = $_SESSION['user_id'] ?? 1; // للتجربة

  // 3) استلام JSON من الواجهة
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Invalid JSON']);
    exit;
  }

  // 4) أنشئ set جديد (ببساطة نحفظ نسخة كاملة كل مرة)
  $tz = 'Asia/Hebron'; // عدّل إذا تريد تمريره من الواجهة
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("INSERT INTO availability_sets (user_id, tz) VALUES (?, ?)");
  $stmt->execute([$user_id, $tz]);
  $set_id = (int)$pdo->lastInsertId();

  // 5) حضّر إدخال السلوطات
  $ins = $pdo->prepare("
    INSERT INTO availability_slots (set_id, day_name, from_time, to_time)
    VALUES (:set_id, :day_name, :from_time, :to_time)
  ");

  // $data = { Sunday:{enabled:true, slots:[{from:'12:00',to:'19:00'}, ...]}, Monday: {...}, ... }
  $validDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

  foreach ($data as $day => $info) {
    if (!in_array($day, $validDays, true)) continue;
    if (empty($info['enabled'])) continue; // لو اليوم معطّل ما نحفظه

    if (!empty($info['slots']) && is_array($info['slots'])) {
      foreach ($info['slots'] as $slot) {
        $from = $slot['from'] ?? null;
        $to   = $slot['to']   ?? null;
        if (!$from || !$to) continue;

        // تحقق بسيط: from < to
        if (strtotime($from) >= strtotime($to)) continue;

        $ins->execute([
          ':set_id'    => $set_id,
          ':day_name'  => $day,
          ':from_time' => $from,
          ':to_time'   => $to,
        ]);
      }
    }
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'set_id'=>$set_id]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
}