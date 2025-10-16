<?php
// get_availability.php
header('Content-Type: application/json');

try {
  $pdo = new PDO('mysql:host=localhost;dbname=fixora;charset=utf8mb4','root','', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  session_start();
  $user_id = $_SESSION['user_id'] ?? 1;

  // آخر set للمستخدم
  $set = $pdo->prepare("SELECT id FROM availability_sets WHERE user_id=? ORDER BY id DESC LIMIT 1");
  $set->execute([$user_id]);
  $row = $set->fetch(PDO::FETCH_ASSOC);

  if (!$row) { echo json_encode(['ok'=>true,'data'=>null]); exit; }

  $sid = (int)$row['id'];
  $slots = $pdo->prepare("SELECT day_name, from_time, to_time FROM availability_slots WHERE set_id=? ORDER BY FIELD(day_name,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), from_time");
  $slots->execute([$sid]);

  // حضّر payload بنفس شكل الواجهة
  $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  $payload = [];
  foreach ($days as $d) $payload[$d] = ['enabled'=>false,'slots'=>[]];

  while ($s = $slots->fetch(PDO::FETCH_ASSOC)) {
    $payload[$s['day_name']]['enabled'] = true;
    $payload[$s['day_name']]['slots'][] = [
      'from' => substr($s['from_time'],0,5),
      'to'   => substr($s['to_time'],0,5)
    ];
  }

  echo json_encode(['ok'=>true,'data'=>$payload]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}