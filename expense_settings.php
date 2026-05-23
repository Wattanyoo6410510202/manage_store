<?php
require_once 'config.php';

// 2. ส่วนของ API (จัดการ AJAX) - ต้องอยู่ก่อน include header
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // ดึงข้อมูลทั้งหมด
    if ($_GET['action'] == 'fetch') {
        $sql = "SELECT ec.*, s.company_name 
                FROM expense_categories ec 
                JOIN suppliers s ON ec.sup_id = s.id 
                ORDER BY s.company_name ASC, ec.name ASC";
        $result = mysqli_query($conn, $sql);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode($data);
        exit;
    }

    // บันทึกข้อมูล (Save/Update)
    if ($_GET['action'] == 'save') {
        $id = $_POST['id'] ?? '';
        $sup_id = intval($_POST['sup_id'] ?? 0);
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $roles = isset($_POST['roles']) ? implode(',', $_POST['roles']) : '';

        if ($sup_id == 0 || empty($name)) {
            echo json_encode(['status' => 'error', 'msg' => 'กรุณากรอกข้อมูลให้ครบ']);
            exit;
        }

        if (empty($id)) {
            $sql = "INSERT INTO expense_categories (sup_id, name, roles, is_active) VALUES ($sup_id, '$name', '$roles', 1)";
        } else {
            $sql = "UPDATE expense_categories SET sup_id = $sup_id, name = '$name', roles = '$roles' WHERE id = " . intval($id);
        }
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
        }
        exit;
    }

    // ลบข้อมูล
    if ($_GET['action'] == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $sql = "DELETE FROM expense_categories WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
        }
        exit;
    }
}

// 3. เริ่มส่วนแสดงผล HTML
include('header.php'); 
?>

<div class="container-fluid p-0">
    <div class="flex justify-between items-center">
        <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl transition shadow-lg text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> เพิ่มหมวดหมู่
        </button>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase">บริษัท / Supplier</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase">ชื่อรายการค่าใช้จ่าย</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase">สิทธิ์ที่เห็นได้</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="expenseTableBody" class="divide-y divide-slate-50">
                </tbody>
        </table>
    </div>
</div>

<div id="expenseModal" class="fixed inset-0 bg-slate-900/60 hidden backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 id="modalTitle" class="font-bold text-slate-700 text-lg">เพิ่มรายการ</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form id="expenseForm" class="p-6 space-y-5">
            <input type="hidden" name="id" id="item-id">
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">เลือกบริษัท</label>
                <select name="sup_id" id="item-sup-id" required class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white">
                    <option value="">-- เลือกบริษัท --</option>
                    <?php
                    $sups = mysqli_query($conn, "SELECT id, company_name FROM suppliers ORDER BY company_name ASC");
                    while($s = mysqli_fetch_assoc($sups)) {
                        echo "<option value='{$s['id']}'>{$s['company_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">ชื่อรายการค่าใช้จ่าย</label>
                <input type="text" name="name" id="item-name" required placeholder="เช่น ค่าอะไหล่, ค่าไฟ"
                       class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">กำหนดสิทธิ์ (Roles)</label>
                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 border border-slate-200 rounded-xl bg-slate-50">
                    <?php
                    $roles_list = [
                        'staff' => 'Staff', 'admin' => 'Admin', 'gm' => 'GM', 'mgr' => 'Manager',
                        'mgr2' => 'Manager 2', 'acc' => 'Account', 'procure' => 'Procurement',
                        'fin' => 'Finance', 'viewer' => 'Viewer', 'gmhok' => 'GM HOK',
                        'gmhr' => 'GM HR', 'staff_hr' => 'Staff HR', 'gmacc' => 'GM ACC',
                        'gmshotel' => 'GM SHotel', 'gmmanonta' => 'GM Manonta',
                        'gmnijuni' => 'GM Nijuni', 'hok' => 'HOK', 'staff_shotel' => 'Staff SHotel',
                        'staff_manonta' => 'Staff Manonta', 'staff_nijuni' => 'Staff Nijuni'
                    ];
                    foreach($roles_list as $val => $label): ?>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="roles[]" value="<?= $val ?>" class="role-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs text-slate-600"><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-[10px] text-slate-400 mt-1 italic">* หากไม่เลือกเลย จะถือว่าเห็นได้ทุกคน</p>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 rounded-2xl hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-100">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    fetchData();

    // บันทึกข้อมูล
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '?action=save',
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if(res.status === 'success') {
                    closeModal();
                    fetchData();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + res.msg);
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                alert('Server Error: กรุณาเช็ค Console (F12)');
            }
        });
    });
});

function fetchData() {
    $.get('?action=fetch', function(data) {
        let html = '';
        if(!data || data.length === 0) {
            html = '<tr><td colspan="4" class="p-12 text-center text-slate-400 font-medium">ไม่พบข้อมูลรายการค่าใช้จ่าย</td></tr>';
        } else {
            data.forEach(item => {
                let rolesDisplay = item.roles ? item.roles.split(',').map(r => `<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] mr-1">${r}</span>`).join('') : '<span class="text-slate-400 italic text-[10px]">ทั้งหมด</span>';
                html += `
                <tr class="hover:bg-slate-50 transition text-sm">
                    <td class="p-4 font-bold text-slate-700">${item.company_name}</td>
                    <td class="p-4 text-slate-600">${item.name}</td>
                    <td class="p-4">${rolesDisplay}</td>
                    <td class="p-4 text-center space-x-2">
                        <button onclick='editItem(${JSON.stringify(item)})' class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button onclick="deleteItem(${item.id})" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                    </td>
                </tr>`;
            });
        }
        $('#expenseTableBody').html(html);
    });
}

function openModal() {
    $('#expenseForm')[0].reset();
    $('.role-checkbox').prop('checked', false);
    $('#item-id').val('');
    $('#modalTitle').text('เพิ่มรายการค่าใช้จ่าย');
    $('#expenseModal').removeClass('hidden');
}

function closeModal() {
    $('#expenseModal').addClass('hidden');
}

function editItem(item) {
    $('#modalTitle').text('แก้ไขรายการ');
    $('#item-id').val(item.id);
    $('#item-sup-id').val(item.sup_id);
    $('#item-name').val(item.name);
    
    $('.role-checkbox').prop('checked', false);
    if(item.roles) {
        let roles = item.roles.split(',');
        roles.forEach(r => {
            $(`.role-checkbox[value="${r}"]`).prop('checked', true);
        });
    }

    $('#expenseModal').removeClass('hidden');
}

function deleteItem(id) {
    if(confirm('ยืนยันการลบรายการนี้?')) {
        $.post('?action=delete', { id: id }, function(res) {
            if(res.status === 'success') {
                fetchData();
            } else {
                alert(res.msg);
            }
        });
    }
}
</script>

<?php include 'footer.php'; ?>