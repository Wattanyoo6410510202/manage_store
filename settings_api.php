<?php
require_once 'config.php'; // ตรวจสอบว่าในนี้ใช้ $conn = new mysqli(...)

$msg_status = "";

// 1. Logic สำหรับการบันทึก/อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update') {
        $id = intval($_POST['id']);
        $value = $_POST['value'];

        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        
        if ($stmt->execute()) { $msg_status = "success_update"; }
        $stmt->close();
    } 
    
    // Logic สำหรับเพิ่มค่าตั้งค่าใหม่ (Add New)
    if ($_POST['action'] == 'add') {
        $key = $_POST['setting_key'];
        $val = $_POST['setting_value'];
        $cat = $_POST['category'];
        $desc = $_POST['description'];

        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $key, $val, $cat, $desc);
        
        if ($stmt->execute()) { $msg_status = "success_add"; }
        $stmt->close();
    }
}

// 2. Logic สำหรับการลบ
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM system_settings WHERE id = $id");
    header("Location: settings.php?msg=deleted");
    exit;
}

// 3. ดึงข้อมูลมาแสดงผล
$settings = $conn->query("SELECT * FROM system_settings ORDER BY category ASC, setting_key ASC");

include 'header.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800"><i class="fas fa-sliders-h mr-2"></i>ตั้งค่าระบบจัดซื้อ</h1>
            <button onclick="openAddModal()" class="bg-indigo-600 text-white px-5 py-2 rounded-2xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 text-sm">
                <i class="fas fa-plus mr-2"></i>เพิ่มค่าตั้งค่าใหม่
            </button>
        </div>

        <?php if(strpos($msg_status, 'success') !== false): ?>
            <script>Swal.fire({ icon: 'success', title: 'ดำเนินการสำเร็จ', timer: 1500, showConfirmButton: false });</script>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">หมวดหมู่</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">Setting Key</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">Value (ค่าที่ตั้งไว้)</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php while($row = $settings->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-4">
                            <span class="px-3 py-1 text-[10px] font-bold rounded-lg <?= $row['category'] == 'API' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600' ?>">
                                <?= $row['category'] ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="font-mono text-sm font-bold text-slate-700"><?= $row['setting_key'] ?></div>
                            <div class="text-[10px] text-slate-400 mt-1"><?= $row['description'] ?></div>
                        </td>
                        <td class="p-4">
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="text" name="value" value="<?= htmlspecialchars($row['setting_value']) ?>" 
                                       class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                <button type="submit" class="text-indigo-600 hover:text-indigo-800 font-bold text-sm px-2">Save</button>
                            </form>
                        </td>
                        <td class="p-4 text-center">
                            <button onclick="confirmDelete(<?= $row['id'] ?>)" class="text-slate-300 hover:text-red-500 transition">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-3xl w-full max-w-md p-8 shadow-2xl animate__animated animate__zoomIn">
            <h3 class="text-xl font-bold text-slate-800 mb-6">เพิ่มค่าตั้งค่าใหม่</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Category</label>
                    <select name="category" class="w-full bg-slate-50 border rounded-xl px-4 py-2 mt-1">
                        <option value="API">API</option>
                        <option value="SYSTEM">SYSTEM</option>
                        <option value="UI">UI</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Setting Key</label>
                    <input type="text" name="setting_key" required placeholder="เช่น GEMINI_API_KEY" class="w-full bg-slate-50 border rounded-xl px-4 py-2 mt-1">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Value</label>
                    <textarea name="setting_value" required class="w-full bg-slate-50 border rounded-xl px-4 py-2 mt-1" rows="3"></textarea>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                    <input type="text" name="description" class="w-full bg-slate-50 border rounded-xl px-4 py-2 mt-1">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeAddModal()" class="flex-1 py-3 text-slate-500 font-bold hover:bg-slate-50 rounded-2xl transition">ยกเลิก</button>
                    <button type="submit" class="flex-1 py-3 bg-indigo-600 text-white font-bold rounded-2xl shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
        function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }
        
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4F46E5',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) { window.location.href = 'settings.php?delete_id=' + id; }
            })
        }
    </script>
<?php include('footer.php'); ?>
