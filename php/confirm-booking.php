<?php
session_start();
$conn = new mysqli($host, $user, $pass, $db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = (int)$_POST['booking_id'];
    $uid = (int)$_SESSION['user_id'];
    
    // تحقق من أن الحجز يعود للمستخدم
    $check = $conn->query("SELECT id FROM bookings WHERE id = $booking_id AND customer_id = $uid");
    if ($check->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'Booking not found']);
        exit;
    }
    




// ✅ تحقق أن المزوّد للحجز Active
$provSql = "
    SELECT u.status 
    FROM bookings b
    JOIN users u ON u.id = b.provider_id
    WHERE b.id = $booking_id
    LIMIT 1
";
$provRes = $conn->query($provSql);
if ($provRes && $provRes->num_rows > 0) {
    $provRow = $provRes->fetch_assoc();
    if (strtolower($provRow['status']) !== 'active') {
        echo json_encode(['ok' => false, 'error' => 'Provider is suspended; booking cannot be confirmed']);
        exit;
    }
}





    // تحديث الحالة في قاعدة البيانات
    $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to confirm booking']);
    }
}
?>