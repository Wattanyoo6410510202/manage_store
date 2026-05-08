<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$project_id = intval($_POST['project_id']);
$milestone_id = intval($_POST['milestone_id']);
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

$fields = [
    'inspection_date', 'is_boq_complete', 'is_drawing_match', 'is_spec_match', 
    'is_quantity_ok', 'is_on_schedule', 'is_electrical_ok', 'is_plumbing_ok', 
    'is_drainage_ok', 'is_hvac_ok', 'other_system_note', 'is_surface_ok', 
    'is_paint_ok', 'is_cleaned', 'is_defect_fixed', 'is_safety_ok', 
    'result_status', 'punch_list', 'fix_within_days', 'inspector_name_1', 
    'is_inspector_1_approved', 'inspector_name_2', 'is_inspector_2_approved',
    'procurement_officer', 'is_md_approved', 'inspection_attachment'
];

$data = [];
foreach ($fields as $field) {
    if (strpos($field, 'is_') === 0) {
        $data[$field] = isset($_POST[$field]) ? 1 : 0;
    } else {
        $data[$field] = $_POST[$field] ?? null;
    }
}

// --- จัดการไฟล์แนบ ---
if (isset($_FILES['inspection_attachment']) && $_FILES['inspection_attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/inspections/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['inspection_attachment']['name'], PATHINFO_EXTENSION));
    $new_filename = 'INS_' . $milestone_id . '_' . time() . '.' . $file_ext;
    $target_file = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['inspection_attachment']['tmp_name'], $target_file)) {
        $data['inspection_attachment'] = $new_filename;
    }
}
// ------------------

// Validation
if (empty($data['inspection_date'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุวันที่ตรวจรับงาน']);
    exit;
}

if ($id > 0) {
    // Update
    $update_parts = [];
    $params = [];
    $types = "";
    
    foreach ($data as $key => $val) {
        $update_parts[] = "`$key` = ?";
        $params[] = $val;
        $types .= is_int($val) ? "i" : "s";
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE milestone_inspections SET " . implode(', ', $update_parts) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
} else {
    // Insert
    $cols = array_merge(['project_id', 'milestone_id'], array_keys($data));
    $placeholders = array_fill(0, count($cols), '?');
    $params = array_merge([$project_id, $milestone_id], array_values($data));
    $types = "ii" . str_repeat("s", count($data)); // simplified, mostly strings or ints that bind fine as s
    
    // Adjust types for accuracy if needed
    $types = "ii";
    foreach ($data as $val) {
        $types .= (is_int($val) || is_null($val)) ? "i" : "s";
    }

    $sql = "INSERT INTO milestone_inspections (" . implode(', ', array_map(fn($c) => "`$c`", $cols)) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลการตรวจรับงานเรียบร้อยแล้ว']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
