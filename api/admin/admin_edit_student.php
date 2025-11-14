<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/db.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$student_id = $_POST["editStudentId"] ?? null;
$name = trim($_POST["editFirstName"] ?? "");
$email = trim($_POST["editEmail"] ?? "");
$student_number = trim($_POST["editStudentID"] ?? "");
$year_level = trim($_POST["editYearLevel"] ?? "");
$section = trim($_POST["editSection"] ?? "");

if (!$student_id) {
    echo json_encode(["success" => false, "message" => "Student ID is required"]);
    exit;
}

// Validate required fields
if (empty($name) || empty($email) || empty($student_number)) {
    echo json_encode(["success" => false, "message" => "Name, email, and student number cannot be empty"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT profile_photo FROM students WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit;
    }

    // Handle image upload if provided
    $newPhotoPath = $student["profile_photo"]; // keep current unless replaced

    if (!empty($_FILES["editAvatar"]["name"])) {
        $uploadDir = "../../uploads/profile/";
        $oldPhotoPath = $student["profile_photo"];

        // Delete old file if it exists
        if (!empty($oldPhotoPath) && file_exists($uploadDir . basename($oldPhotoPath))) {
            unlink($uploadDir . basename($oldPhotoPath));
        }

        // Generate new file name
        $ext = pathinfo($_FILES["editAvatar"]["name"], PATHINFO_EXTENSION);
        $newFileName = "student_" . $student_id . "_" . time() . "." . $ext;
        $targetPath = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($_FILES["editAvatar"]["tmp_name"], $targetPath)) {
            $newPhotoPath = "uploads/profile/" . $newFileName; // relative path for DB
        } else {
            echo json_encode(["success" => false, "message" => "Failed to upload new profile photo"]);
            exit;
        }
    }

    // Update student info
    $stmt = $pdo->prepare("
        UPDATE students 
        SET name = :name, email = :email, student_number = :student_number, profile_photo = :profile_photo, year_level = :year_level, section = :section
        WHERE id = :id
    ");

    $stmt->execute([
        ":name" => $name,
        ":email" => $email,
        ":student_number" => $student_number,
        ":profile_photo" => $newPhotoPath,
        ":year_level" => $year_level,
        ":section" => $section,
        ":id" => $student_id    
    ]);

    echo json_encode(["success" => true, "message" => "Student updated successfully"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
