<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';
require_once '../customer_tax_defaults.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการประมวลผล'];
$user = $_SESSION['username'] ?? 'System';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    try {
        if (!$conn) throw new Exception("เชื่อมต่อฐานข้อมูลไม่ได้");

        if ($action === 'add' || $action === 'edit') {
            // รับค่าจากฟอร์ม (รวม Phone และ Email)
            $customer_name = trim($_POST['customer_name'] ?? '');
            $tax_id = trim($_POST['tax_id'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? ''); // เพิ่มใหม่
            $email = trim($_POST['email'] ?? ''); // เพิ่มใหม่
            $address = trim($_POST['address'] ?? '');
            $taxDefaults = customer_tax_defaults_from_input($_POST);

            if (empty($customer_name)) throw new Exception("กรุณากรอกชื่อ Vendor");

            if ($action === 'add') {
                // INSERT ข้อมูล (เพิ่ม phone และ email เข้าไปใน SQL)
                $sql = "INSERT INTO customers (customer_name, tax_id, contact_person, phone, email, address, entity_type, default_vat_mode, default_wht_enabled, default_wht_percent, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                // "ssssssss" หมายถึง String 8 ตัว
                $stmt->bind_param(
                    'ssssssssidss',
                    $customer_name,
                    $tax_id,
                    $contact_person,
                    $phone,
                    $email,
                    $address,
                    $taxDefaults['entity_type'],
                    $taxDefaults['default_vat_mode'],
                    $taxDefaults['default_wht_enabled'],
                    $taxDefaults['default_wht_percent'],
                    $user,
                    $user
                );
                
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'เพิ่มข้อมูล Vendor สำเร็จ', 'id' => $conn->insert_id, 'vendor' => $taxDefaults];
                    $_SESSION['flash_msg'] = 'add_success';
                } else {
                    throw new Exception("ไม่สามารถเพิ่มข้อมูลได้: " . $conn->error);
                }
            } else {
                // UPDATE ข้อมูล (เพิ่ม phone และ email เข้าไปใน SQL)
                $id = intval($_POST['customer_id'] ?? 0);
                if ($id <= 0) throw new Exception("ID ไม่ถูกต้อง");

                $sql = "UPDATE customers SET customer_name=?, tax_id=?, contact_person=?, phone=?, email=?, address=?, entity_type=?, default_vat_mode=?, default_wht_enabled=?, default_wht_percent=?, updated_by=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                // "sssssssi" หมายถึง String 7 ตัว และ Integer 1 ตัว (id)
                $stmt->bind_param(
                    'ssssssssidsi',
                    $customer_name,
                    $tax_id,
                    $contact_person,
                    $phone,
                    $email,
                    $address,
                    $taxDefaults['entity_type'],
                    $taxDefaults['default_vat_mode'],
                    $taxDefaults['default_wht_enabled'],
                    $taxDefaults['default_wht_percent'],
                    $user,
                    $id
                );
                
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'อัปเดตข้อมูล Vendor สำเร็จ', 'vendor' => $taxDefaults];
                    $_SESSION['flash_msg'] = 'update_success';
                } else {
                    throw new Exception("ไม่สามารถอัปเดตข้อมูลได้: " . $conn->error);
                }
            }
        } else if ($action === 'delete') {
            $id = intval($_GET['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'ลบข้อมูลเรียบร้อยแล้ว'];
                $_SESSION['flash_msg'] = 'delete_success';
            }
        } else if ($action === 'bulk_delete') {
            $ids_array = json_decode($_POST['ids'] ?? '[]', true);
            $clean_ids = array_filter($ids_array, 'is_numeric');
            if (empty($clean_ids)) throw new Exception("กรุณาเลือกรายการ");

            $placeholders = implode(',', array_fill(0, count($clean_ids), '?'));
            $stmt = $conn->prepare("DELETE FROM customers WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($clean_ids)), ...$clean_ids);
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'ลบข้อมูลที่เลือกสำเร็จ'];
                $_SESSION['flash_msg'] = 'delete_success';
            }
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
        $_SESSION['flash_msg'] = 'error'; 
    }
}

// บังคับบันทึก Session ก่อนส่ง JSON
session_write_close(); 
if (ob_get_length()) ob_clean(); 
echo json_encode($response);
exit();
