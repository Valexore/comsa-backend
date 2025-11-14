<?php

declare(strict_types=1);

function add_student(object $pdo, string $id, string $name, string $email, string $password, ?string $profile, string $year_level, string $section)
{
    $defaultPhoto = "uploads/profile/default_user.png";
    $profile = $profile ?? $defaultPhoto;

    try {
        $stmt = $pdo->prepare("INSERT INTO students (student_number, name, email, password, profile_photo, year_level, section)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id,
            $name,
            $email,
            $password,
            $profile,
            $year_level,
            $section
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === "23000") { // Duplicate entry error
            if (strpos($e->getMessage(), "email") !== false) {
                throw new Exception("Email already exists");
            }
            if (strpos($e->getMessage(), "student_number") !== false) {
                throw new Exception("Student ID already exists");
            }
        }
        throw $e; // rethrow other DB errors
    }
}

function update_student_profile(object $pdo, int $studentId, string $imagePath)
{
    $stmt = $pdo->prepare("UPDATE students SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$imagePath, $studentId]);
}
