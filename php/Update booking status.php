<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: '.$conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

$id     = (int)$_POST['id'];
$status = trim($_POST['status']);

// ✅ اسمح فقط بالقيم الموجودة في bookings.status (مع إضافة cancelled)
$allowed = ['pending','confirmed','in_progress','completed','cancelled'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['success'=>false, 'message'=>"Invalid status '$status'"]);
    $conn->close();
    exit;
}

/* 1) تحديث حالة الحجز */
$u = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
if (!$u) {
    echo json_encode(['success'=>false, 'message'=>'Prepare failed: '.$conn->error]);
    $conn->close();
    exit;
}
$u->bind_param("si", $status, $id);
if (!$u->execute()) {
    echo json_encode(['success'=>false, 'message'=>'Update failed: '.$u->error]);
    $u->close();
    $conn->close();
    exit;
}
$u->close();

/* ملاحظة: require_once 'notify.php'; مش مستخدم هون، فيكِ تشيليه. */
// require_once 'notify.php';

/* 2) خريطة نوع الإشعار بما يوافق ENUM جدول notifications.type */
$notifTypeMap = [
    'confirmed'   => 'booking_confirmed',
    'in_progress' => 'job_started',
    'completed'   => 'job_completed',
    'cancelled'   => 'booking_cancelled',
    // 'pending'    => لا إشعار
];

$notif_id = null;

/* 3) لو الحالة تستحق إشعار → أرسل للعميل فقط */
if (isset($notifTypeMap[$status])) {
    // هات صاحب الحجز واسم الخدمة
    $q = $conn->prepare("
        SELECT b.customer_id, s.title
        FROM bookings b
        JOIN services s ON s.id = b.service_id
        WHERE b.id = ? LIMIT 1
    ");
    if (!$q) {
        echo json_encode(['success'=>false, 'message'=>'Lookup prepare failed: '.$conn->error]);
        $conn->close();
        exit;
    }
    $q->bind_param("i", $id);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$res) {
        echo json_encode(['success'=>false, 'message'=>'Booking not found for notification']);
        $conn->close();
        exit;
    }

    $customer_id  = (int)$res['customer_id'];
    $serviceTitle = $res['title'] ?? 'الخدمة';

    // نصوص الإشعارات
    $titleMap = [
      'confirmed'   => 'تم تأكيد الحجز',
      'in_progress' => 'بدأ تنفيذ الخدمة',
      'completed'   => 'تم إنهاء الخدمة',
      'cancelled'   => 'تم إلغاء الحجز'
    ];
    $msgMap = [
      'confirmed'   => "تم تأكيد حجزك لخدمة '{$serviceTitle}'.",
      'in_progress' => "بدأ تنفيذ حجزك لخدمة '{$serviceTitle}'.",
      'completed'   => "تم إنهاء حجزك لخدمة '{$serviceTitle}'. شكراً لك.",
      'cancelled'   => "تم إلغاء حجزك لخدمة '{$serviceTitle}'."
    ];

    $notif_sql = "INSERT INTO notifications
        (user_id, booking_id, type, title, message, is_read, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, 0, 1, NOW())";
    $stmt = $conn->prepare($notif_sql);
    if (!$stmt) {
        echo json_encode(['success'=>false, 'message'=>'Notif prepare failed: '.$conn->error]);
        $conn->close();
        exit;
    }

    $notif_type = $notifTypeMap[$status];
    $title      = $titleMap[$status];
    $message    = $msgMap[$status];

    $stmt->bind_param("iisss", $customer_id, $id, $notif_type, $title, $message);
    if (!$stmt->execute()) {
        echo json_encode(['success'=>false, 'message'=>'Notif insert failed: '.$stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $notif_id = $conn->insert_id;
    $stmt->close();
}

/* 4) رد واحد وواضح */
echo json_encode([
    'success'    => true,
    'message'    => 'تم التحديث بنجاح',
    'status'     => $status,
    'notif_id'   => $notif_id,
    'notif_type' => $notifTypeMap[$status] ?? null
]);

$conn->close();