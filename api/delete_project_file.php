<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    $field = mysqli_real_escape_string($conn, $_POST['field']); 

    $pj_res = mysqli_query($conn, "SELECT project_no, $field FROM projects WHERE id = '$project_id'");
    $pj = mysqli_fetch_assoc($pj_res);

    if ($pj && !empty($pj[$field])) {
        $file_path = "../uploads/projects/" . $pj['project_no'] . "/" . $pj[$field];
        if (file_exists($file_path)) unlink($file_path);
        mysqli_query($conn, "UPDATE projects SET $field = NULL WHERE id = '$project_id'");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์']);
    }
}
?>