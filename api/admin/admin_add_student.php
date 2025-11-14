<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../../config/session.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "errors" => "Unauthorized Request",
    ]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        'errors' => 'Unauthorized Access'
    ]);
    exit;
}

try {
    $fn = $_POST["studentFirstName"] ?? "";
    $ln = $_POST["studentLastName"] ?? "";
    $email = $_POST["studentEmail"] ?? "";
    $studentId = $_POST["studentID"] ?? "";
    $yearLevel = $_POST["yearLevel"] ?? "";
    $section = $_POST["section"] ?? "";

    require_once '../../config/db.php';
    require_once '../../model/admin/add_student_model.php';
    require_once '../../controller/admin/add_student_contr.php';


// Ensure default profile photo exists
$defaultPhotoDir = __DIR__ . "/../../uploads/profile/";
$defaultPhotoPath = $defaultPhotoDir . "default_user.png";

if (!is_dir($defaultPhotoDir)) {
    mkdir($defaultPhotoDir, 0777, true);
}

if (!file_exists($defaultPhotoPath)) {
    // Copy default avatar from assets to uploads
    $sourceDefaultPhoto = __DIR__ . "/../../../COMSA-NOW/assets/img/team/default_user.png";
    if (file_exists($sourceDefaultPhoto)) {
        copy($sourceDefaultPhoto, $defaultPhotoPath);
    }
}

    $errors = is_input_empty($fn, $ln, $email, $studentId);

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "errors" => $errors
        ]);
        exit;
    }

    $name = "$fn $ln";
    $password = strtoupper($ln);

    // If no avatar uploaded, use default
    $profilePhoto = "uploads/profile/default_user.png";

    $studentDataId = add_student($pdo, $studentId, $name, $email, $password, null, $yearLevel, $section);

    // Handle custom avatar upload if provided
    if (!empty($_FILES['studentAvatar']['name'])) {
    $fileTmpPath = $_FILES['studentAvatar']['tmp_name'];
    $fileName = basename($_FILES['studentAvatar']['name']);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedExts)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "errors" => ["image" => "Invalid file type. Allowed: jpg, jpeg, png, gif, webp"]
        ]);
        exit;
    }

    $safeFileName = uniqid("img_", true) . "." . $fileExtension;
    $uploadDir = __DIR__ . "/../../uploads/profile/";
    $destPath = $uploadDir . $safeFileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $studentImagePath = "uploads/profile/" . $safeFileName;
        // Update profile with custom image
        update_student_profile($pdo, (int)$studentDataId, $studentImagePath);
        $profilePhoto = $studentImagePath;
    }
}

    echo json_encode([
        "success" => true
    ]);
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "errors" => ["general" => $e->getMessage()]
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "errors" => ["server" => "Internal server error. Please try again later."]
    ]);
    exit;
}
