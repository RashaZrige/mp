<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// تفعيل تصحيح الأخطاء للتdebug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) التحقق من هوية المزوّد
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false,'msg'=>'Not logged in: missing provider session']); 
    exit;
}
$provider_id = (int)$_SESSION['user_id'];

// 2) اتصال قاعدة البيانات
try {
    $pdo = new PDO('mysql:host=localhost;dbname=fixora;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'msg'=>'DB error: ' . $e->getMessage()]); 
    exit;
}

// 3) جلب بيانات المزوّد
$prov = $pdo->prepare("SELECT years_experience FROM provider_profiles WHERE user_id = :u LIMIT 1");
$prov->execute([':u'=>$provider_id]);
$provRow = $prov->fetch(PDO::FETCH_ASSOC);
if (!$provRow) { 
    echo json_encode(['ok'=>false,'msg'=>'Provider profile not found']); 
    exit; 
}
$years_experience = (int)$provRow['years_experience'];

// 4) القيم القادمة من الواجهة
$service      = trim($_POST['service']      ?? '');
$sub_section  = trim($_POST['sub_service']  ?? '');
$price_min    = ($_POST['price_min'] ?? '') !== '' ? (float)$_POST['price_min'] : null;
$price_max    = ($_POST['price_max'] ?? '') !== '' ? (float)$_POST['price_max'] : null;
$duration_min = ($_POST['duration']  ?? '') !== '' ? (int)$_POST['duration']    : null;
$includes_raw = trim($_POST['includes']     ?? '');

// تحقق أساسي
if ($service === '' || $sub_section === '') {
    echo json_encode(['ok'=>false,'msg'=>'Service and Sub-Section are required']); 
    exit;
}
if ($price_min !== null && $price_max !== null && $price_min > $price_max) {
    echo json_encode(['ok'=>false,'msg'=>'Min price cannot be greater than Max']); 
    exit;
}

// 5) رفع الصورة وحفظ المسار - مع تصحيح الأخطاء
$img_path = null;
if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    
    // طباعة معلومات الصورة للتdebug
    error_log("Image upload attempt: " . print_r($_FILES['image'], true));
    
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
        echo json_encode(['ok'=>false,'msg'=>'Invalid image type: ' . $ext]); 
        exit;
    }
    
    // تأكيد المسار
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/mp/uploads/services';
    error_log("Upload directory: " . $dir);
    
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Cannot create upload directory']); 
            exit;
        }
    }
    
    // التحقق من صلاحيات الكتابة
    if (!is_writable($dir)) {
        echo json_encode(['ok'=>false,'msg'=>'Upload directory is not writable']); 
        exit;
    }
    
    $name = time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    $full_path = $dir . '/' . $name;
    
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $full_path)) {
        $error = error_get_last();
        echo json_encode(['ok'=>false,'msg'=>'Upload failed: ' . ($error['message'] ?? 'Unknown error')]); 
        exit;
    }
    
    // التحقق أن الملف تم حفظه
    if (!file_exists($full_path)) {
        echo json_encode(['ok'=>false,'msg'=>'File was not saved after upload']); 
        exit;
    }
    
    $img_path = 'uploads/services/' . $name;
    error_log("Image saved successfully: " . $img_path);
    
} else {
    // إذا لم يتم رفع صورة، سجل السبب
    $upload_error = $_FILES['image']['error'] ?? 'No file uploaded';
    error_log("No image uploaded or upload error: " . $upload_error);
}

// 6) إدخال الخدمة
try {
    $ins = $pdo->prepare(
        "INSERT INTO services
         (title, description, category, sub_section, img_path,
          price_from, price_to, duration_minutes, rating, is_active, provider_id, created_at)
         VALUES
         (:t, '', :c, :s, :img, :pf, :pt, :dm, 0.0, 1, :uid, NOW())"
    );
    
    $ok = $ins->execute([
        ':t'   => $service,
        ':c'   => $service,
        ':s'   => $sub_section,
        ':img' => $img_path,
        ':pf'  => $price_min,
        ':pt'  => $price_max,
        ':dm'  => $duration_min,
        ':uid' => $provider_id,
    ]);
    
    if (!$ok) { 
        throw new Exception('Insert service failed'); 
    }
    
    $service_id = (int)$pdo->lastInsertId();
    
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'msg'=>'Database insert failed: ' . $e->getMessage()]); 
    exit;
}

// 7) حفظ What Include
$includes_saved = 0;
if ($includes_raw !== '') {
    try {
        $lines = preg_split('/\r\n|\r|\n/', $includes_raw);
        $insInc = $pdo->prepare("INSERT INTO service_includes (service_id, text, sort) VALUES (:sid, :txt, :srt)");
        $sort = 1;
        foreach ($lines as $ln) {
            $txt = trim(preg_replace('/^[\-\*\•\·\s]+/u', '', $ln));
            if ($txt === '') continue;
            $insInc->execute([':sid'=>$service_id, ':txt'=>$txt, ':srt'=>$sort++]);
            $includes_saved++;
        }
    } catch (Exception $e) {
        // لا نوقف العملية إذا فشل حفظ includes
        error_log("Includes save failed: " . $e->getMessage());
    }
}

// 8) رد JSON مع معلومات إضافية للتdebug
echo json_encode([
    'ok'               => true,
    'service_id'       => $service_id,
    'provider_id'      => $provider_id,
    'provider_exps'    => $years_experience,
    'img_path_saved'   => $img_path,
    'includes_count'   => $includes_saved,
    'debug' => [
        'image_uploaded' => !empty($_FILES['image']),
        'image_error' => $_FILES['image']['error'] ?? 'No file',
        'image_path' => $img_path
    ]
]);
?>