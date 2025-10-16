<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = (int)$_POST['booking_id'];
    $uid = (int)$_SESSION['user_id'];
    
    // تحقق من أن الحجز يعود للمستخدم
    $check = $conn->query("SELECT id FROM bookings WHERE id = $booking_id AND customer_id = $uid");
    if ($check->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'Booking not found']);
        exit;
    }
    
    // تحديث الحالة في قاعدة البيانات
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to reject booking']);
    }
}
?>