<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ids'])) {
    
    $ids = array_map('intval', $_POST['ids']);
    $ids_string = implode(',', $ids);

    // 1. ดึงชื่อไฟล์แนบของทุก ID ที่จะลบออกมาก่อน
    $find_files_sql = "SELECT claim_attachment FROM project_milestones 
                       WHERE id IN ($ids_string) AND status = 'pending'";
    $files_res = mysqli_query($conn, $find_files_sql);
    
    $files_to_delete = [];
    while ($row = mysqli_fetch_assoc($files_res)) {
        if (!empty($row['claim_attachment'])) {
            $files_to_delete[] = $row['claim_attachment'];
        }
    }

    // 2. เช็คความปลอดภัย: ห้ามลบรายการ 'paid' (กันเหนียวอีกชั้น)
    $check_paid = mysqli_query($conn, "SELECT id FROM project_milestones WHERE id IN ($ids_string) AND status = 'paid'");
    if (mysqli_num_rows($check_paid) > 0) {
        echo "จารครับ! มีรายการที่จ่ายเงินแล้วปนอยู่ ลบไม่ได้นะครับ";
        exit;
    }

    // 3. ลบข้อมูลใน Database
    $delete_sql = "DELETE FROM project_milestones WHERE id IN ($ids_string) AND status = 'pending'";
    
    if (mysqli_query($conn, $delete_sql)) {
        // 4. ถ้าลบ DB สำเร็จ ค่อยตามไปลบไฟล์ในเครื่อง (Storage Cleanup)
        $upload_path = "../uploads/claims/";
        foreach ($files_to_delete as $filename) {
            $full_path = $upload_path . $filename;
            if (file_exists($full_path)) {
                @unlink($full_path); // ลบไฟล์ทิ้ง
            }
        }
        echo "success";
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }

} else {
    echo "Invalid Request";
}
?>