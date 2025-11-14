<?php

require_once '../config/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized Access'
    ]); 
    exit;
}

$userId = $_SESSION['user_id'];

require_once '../config/db.php';
require_once '../model/fetch_stud_proj_model.php';

try {

    //get post and combine the tech and no. of likes and comments
    $projects = get_student_project($pdo, $userId);

    foreach ($projects as &$project) {
        // Fetch technologies
        $stmtTech = $pdo->prepare("SELECT technology_name FROM project_technologies WHERE project_id = ?");
        $stmtTech->execute([$project['id']]);
        $project['technologies'] = $stmtTech->fetchAll(PDO::FETCH_COLUMN);

        // Fetch team members
        $stmtMembers = $pdo->prepare("SELECT member_name FROM project_team_members WHERE project_id = ?");
        $stmtMembers->execute([$project['id']]);
        $project['team_members'] = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);

        $stmtImages = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ?");
        $stmtImages->execute([$project['id']]);
        $project['images'] = $stmtImages->fetchAll(PDO::FETCH_COLUMN);
    }

    // Shuffle the array before returning
shuffle($projects);

 
    echo json_encode([
        'success' => true,
        'posts' => $projects
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'server error'
    ]);
}
