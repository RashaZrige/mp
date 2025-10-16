<?php
/**
 * mp/php/notify.php
 * الإصدارة الموافقة تمامًا لقيم ENUM الموجودة في الجدول.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
  try {
    $pdo = new PDO(
      "mysql:host=localhost;dbname=fixora;charset=utf8mb4",
      "root",
      "",
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  } catch (Throwable $e) {
    http_response_code(500);
    die("DB Error (notify.php): " . $e->getMessage());
  }
}

/* ===== أنواع مطابقة للـ DB ===== */
// للمزوّد:
const N_NEW_BOOKING        = 'new_booking';         // حجز جديد يصل للمزوّد

// للعميل:
const N_BOOKING_CONFIRMED  = 'booking_confirmed';
const N_JOB_STARTED        = 'job_started';
const N_JOB_COMPLETED      = 'job_completed';       // موجودة بالمنطق حتى لو لسه مش مستخدمة بكثرة
const N_BOOKING_CANCELLED  = 'booking_cancelled';

// عام:
const N_SYSTEM             = 'system';

/**
 * إدراج إشعار واحد للمستخدم المحدد
 */
function notify_pdo(PDO $pdo, int $toUserId, string $type, string $title, string $message, ?int $bookingId = null): int {
  $toUserId = (int)$toUserId;
  $type     = trim($type);
  $title    = trim($title);
  $message  = trim($message);

  if ($toUserId <= 0 || $type === '' || $title === '' || $message === '') {
    return 0;
  }

  $stmt = $pdo->prepare("
    INSERT INTO notifications
      (user_id, booking_id, type, title, message, is_read, is_active, created_at)
    VALUES
      (:uid, :bid, :type, :title, :msg, 0, 1, NOW())
  ");
  $stmt->execute([
    ':uid'   => $toUserId,
    ':bid'   => $bookingId,
    ':type'  => $type,
    ':title' => $title,
    ':msg'   => $message,
  ]);

  return (int)$pdo->lastInsertId();
}

/* ===== اختصارات واضحة ===== */

// للمزوّد: حجز جديد
function notify_provider_new_booking(PDO $pdo, int $providerId, int $bookingId, string $serviceName, string $customerName): int {
  $title   = "حجز جديد 🎉";
  $message = "تم حجز خدمتك '{$serviceName}' بواسطة {$customerName}. رقم الحجز #{$bookingId}";
  return notify_pdo($pdo, $providerId, N_NEW_BOOKING, $title, $message, $bookingId);
}

// للعميل: تأكيد / بدء / إنهاء / إلغاء
function notify_customer_booking_confirmed(PDO $pdo, int $customerId, int $bookingId, string $serviceName): int {
  return notify_pdo($pdo, $customerId, N_BOOKING_CONFIRMED, 'تم تأكيد الحجز', "تم تأكيد حجزك لخدمة '{$serviceName}'.", $bookingId);
}
function notify_customer_job_started(PDO $pdo, int $customerId, int $bookingId, string $serviceName): int {
  return notify_pdo($pdo, $customerId, N_JOB_STARTED, 'بدأ تنفيذ الخدمة', "بدأ تنفيذ حجزك لخدمة '{$serviceName}'.", $bookingId);
}
function notify_customer_job_completed(PDO $pdo, int $customerId, int $bookingId, string $serviceName): int {
  return notify_pdo($pdo, $customerId, N_JOB_COMPLETED, 'تم إنهاء الخدمة', "تم إنهاء حجزك لخدمة '{$serviceName}'. شكراً لك.", $bookingId);
}
function notify_customer_booking_cancelled(PDO $pdo, int $customerId, int $bookingId, string $serviceName): int {
  return notify_pdo($pdo, $customerId, N_BOOKING_CANCELLED, 'تم إلغاء الحجز', "تم إلغاء حجزك لخدمة '{$serviceName}'.", $bookingId);
}