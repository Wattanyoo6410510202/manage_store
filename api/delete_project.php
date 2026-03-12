<?php
require_once '../config.php';
session_start();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 1. ดึงข้อมูลไฟล์แนบก่อนลบ Record
    $sql_file = "SELECT attachment_path FROM projects WHERE id = $id";
    $res_file = mysqli_query($conn, $sql_file);
    $pj = mysqli_fetch_assoc($res_file);

    // 2. ดึงข้อมูลไฟล์แนบของ "งวดงาน" (ถ้ามี)
    $sql_milestones = "SELECT claim_attachment FROM project_milestones WHERE project_id = $id";
    $res_milestones = mysqli_query($conn, $sql_milestones);

    // เริ่มการลบ
    mysqli_begin_transaction($conn);

    try {
        // --- ลบไฟล์ในเครื่อง ---
        // ลบไฟล์สัญญาโครงการ
        if (!empty($pj['attachment_path'])) {
            @unlink("../uploads/projects/" . $pj['attachment_path']);
        }

        // ลบไฟล์ใบเบิกงวดงาน
        while ($ms = mysqli_fetch_assoc($res_milestones)) {
            if (!empty($ms['claim_attachment'])) {
                @unlink("../uploads/claims/" . $ms['claim_attachment']);
            }
        }

        // --- ลบข้อมูลใน Database ---
        // เนื่องจากเราทำ Foreign Key แบบ ON DELETE CASCADE ไว้ในตอนสร้างตาราง 
        // เมื่อลบ projects ข้อมูลใน project_documents และ project_milestones จะถูกลบอัตโนมัติครับ
        $sql_delete = "DELETE FROM projects WHERE id = $id";
        
        if (!mysqli_query($conn, $sql_delete)) {
            throw new Exception("ลบข้อมูลไม่สำเร็จ");
        }

        mysqli_commit($conn);
        $_SESSION['flash_msg'] = 'delete_success';
        header("Location: ../projects.php");

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}