<?php
/* =========================================
   /mp/admin-api-provider-status.php
   تحديث حالة مزوّد — يدعم JSON و x-www-form-urlencoded
   يرجّع JSON دائمًا ويشرح سبب الفشل
========================================= */
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // يمكن تحصير الوصول للأدمن فقط (اختياري)
    if (empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // اتصال DB
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli("localhost","root","","fixora");
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>'DB failed','error'=>$conn->connect_error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $conn->set_charset("utf8mb4");

    // قراءة المدخلات: JSON أو x-www-form-urlencoded أو GET/POST
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    $body  = file_get_contents('php://input');
    $input = [];

    if ($body && stripos($ctype, 'application/json') !== false) {
        $input = json_decode($body, true) ?? [];
    } elseif ($body && stripos($ctype, 'application/x-www-form-urlencoded') !== false) {
        parse_str($body, $input);
    } else {
        $input = $_POST ?: $_GET; // fallback
    }

    $idRaw  = $_GET['id'] ?? $input['id'] ?? '';
    $status = $input['status'] ?? null;

    if ($idRaw === '' || $idRaw === null) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'Invalid id: missing'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowed = ['active','suspended'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'message'=>'Invalid status value','allowed'=>$allowed], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // users.id عندك رقم (int) — لو UUID، استبدل bind "i" بـ "s"
    if (!ctype_digit((string)$idRaw)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'Invalid id: must be integer'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $id = (int)$idRaw;

    // تأكد أن المستخدم مزوّد
    $stmt = $conn->prepare("SELECT id, status FROM users WHERE id = ? AND role = 'provider' LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'message'=>'Provider not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // لو الحالة نفسها، رجّع note
    if (strcasecmp((string)$current['status'], (string)$status) === 0) {
        echo json_encode(['ok'=>true,'id'=>$id,'status'=>$status,'note'=>'status unchanged'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // التحديث
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param("si", $status, $id);
    $ok = $stmt->execute();
    $aff = $stmt->affected_rows;
    $err = $stmt->error;
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>'Update failed','error'=>$err], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($aff === 0) {
        // نادرًا: تريجر/صلاحيات/قيمة لم تتغير (غطيناه فوق)، بس نخلي رسالة مفيدة
        echo json_encode(['ok'=>false,'message'=>'No rows affected'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok'=>true,'id'=>$id,'status'=>$status,'affected'=>$aff], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Server error','error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}