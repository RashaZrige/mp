<?php
session_start();
header('Content-Type: application/json');

// تحقق من التسجيل
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// تحقق من البيانات
if (empty($_POST['id']) || empty($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
    exit;
}

$booking_id = (int)$_POST['id'];
$new_status = $_POST['status'];

// اتصال الداتابيز
$conn = new mysqli("localhost", "root", "", "fixora");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال']);
    exit;
}

// 1. التحقق من صلاحيات المزود - التصحيح هنا ✅
$check_sql = "SELECT b.id, b.customer_id, b.provider_id, b.service_id, s.title as service_title 
              FROM bookings b 
              JOIN services s ON b.service_id = s.id 
              WHERE b.id = ? AND b.provider_id = ?"; // التصحيح: b.provider_id بدل s.provider_id
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'لا تملك صلاحية']);
    $conn->close();
    exit;
}

$booking_data = $result->fetch_assoc();
$customer_id = $booking_data['customer_id'];
$service_title = $booking_data['service_title'];

// 2. تحديث حالة الحجز
$update_sql = "UPDATE bookings SET status = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $new_status, $booking_id);

if (!$update_stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'فشل التحديث']);
    $conn->close();
    exit;
}

// 3. إضافة الإشعار للعميل فقط
$notifications_config = [
    'confirmed' => [
        'type' => 'booking_confirmed',
        'title' => 'تم تأكيد حجزك',
        'message' => 'تم تأكيد حجزك لخدمة "' . $service_title . '" - سنتواصل معك قريباً'
    ],
    'in_progress' => [
        'type' => 'job_started', 
        'title' => 'بدأ العمل على طلبك',
        'message' => 'فريقنا بدأ بتنفيذ خدمة "' . $service_title . '" الآن'
    ],
    'completed' => [
        'type' => 'job_completed',
        'title' => 'تم الانتهاء من العمل', 
        'message' => 'تم الانتهاء من خدمة "' . $service_title . '" - شكراً لثقتك'
    ],
    'cancelled' => [
        'type' => 'booking_cancelled',
        'title' => 'تم إلغاء الحجز',
        'message' => 'تم إلغاء حجزك لخدمة "' . $service_title . '"'
    ]
];

if (isset($notifications_config[$new_status])) {
    $notif = $notifications_config[$new_status];
    
    $notif_sql = "INSERT INTO notifications (user_id, booking_id, type, title, message) 
                  VALUES (?, ?, ?, ?, ?)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("iisss", $customer_id, $booking_id, $notif['type'], $notif['title'], $notif['message']);
    $notif_stmt->execute();
}

$conn->close();
echo json_encode(['success' => true, 'message' => 'تم التحديث بنجاح']);
?>