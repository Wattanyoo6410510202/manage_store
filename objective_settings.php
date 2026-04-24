<?php
require 'config.php';

// 2. ส่วนของ API (จัดการ AJAX) - ต้องอยู่ก่อน include header
if (isset($_GET['action'])) {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    
    // API: ดึงข้อมูล
    if ($_GET['action'] == 'fetch') {
        $sql = "SELECT obj.*, s.company_name 
                FROM pr_objectives obj 
                JOIN suppliers s ON obj.sup_id = s.id 
                ORDER BY s.company_name ASC, obj.name ASC";
        $result = mysqli_query($conn, $sql);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode($data);
        exit;
    }

    // API: บันทึกข้อมูล
    if ($_GET['action'] == 'save') {
        $id = $_POST['id'] ?? '';
        $sup_id = intval($_POST['sup_id'] ?? 0);
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');

        if ($sup_id == 0 || empty($name)) {
            echo json_encode(['status' => 'error', 'msg' => 'กรุณากรอกข้อมูลให้ครบ']);
            exit;
        }

        if (empty($id)) {
            $sql = "INSERT INTO pr_objectives (sup_id, name, is_active) VALUES ($sup_id, '$name', 1)";
        } else {
            $sql = "UPDATE pr_objectives SET sup_id = $sup_id, name = '$name' WHERE id = " . intval($id);
        }
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
        }
        exit;
    }

    // API: ลบข้อมูล
    if ($_GET['action'] == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $sql = "DELETE FROM pr_objectives WHERE id = $id";
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

<div class="container p-0">
    <div class="flex justify-between items-center">
        <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl transition shadow-lg text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> เพิ่มวัตถุประสงค์
        </button>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">บริษัท / Supplier</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">วัตถุประสงค์</th>
                    <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="objTableBody" class="divide-y divide-slate-50"></tbody>
        </table>
    </div>
</div>

<div id="objModal" class="fixed inset-0 bg-slate-900/60 hidden backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 id="modalTitle" class="font-bold text-slate-700 text-lg">เพิ่มรายการ</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form id="objForm" class="p-6 space-y-5">
            <input type="hidden" name="id" id="obj-id">
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">เลือกบริษัท</label>
                <select name="sup_id" id="obj-sup-id" required class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white">
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
                <label class="block text-sm font-semibold text-slate-700 mb-2">ชื่อวัตถุประสงค์</label>
                <input type="text" name="name" id="obj-name" required placeholder="เช่น เพื่อสำรองอะไหล่, เพื่อใช้ในโครงการ"
                       class="w-full border-slate-200 rounded-xl p-3 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 rounded-2xl hover:bg-slate-50 font-medium transition">ยกเลิก</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 font-bold shadow-lg shadow-indigo-100 transition">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    fetchObj();

    $('#objForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '?action=save',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    closeModal();
                    fetchObj();
                } else { alert('Error: ' + res.msg); }
            },
            error: function(xhr) { console.error(xhr.responseText); alert('Server Error!'); }
        });
    });
});

function fetchObj() {
    $.get('?action=fetch', function(data) {
        let html = '';
        if(!data || data.length === 0) {
            html = '<tr><td colspan="3" class="p-12 text-center text-slate-400 font-medium">ไม่พบข้อมูล</td></tr>';
        } else {
            data.forEach(item => {
                html += `
                <tr class="hover:bg-slate-50 transition text-sm">
                    <td class="p-4 font-bold text-slate-700">${item.company_name}</td>
                    <td class="p-4 text-slate-600">${item.name}</td>
                    <td class="p-4 text-center space-x-2">
                        <button onclick='editObj(${JSON.stringify(item)})' class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition shadow-sm">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button onclick="deleteObj(${item.id})" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition shadow-sm">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                    </td>
                </tr>`;
            });
        }
        $('#objTableBody').html(html);
    });
}

function openModal() {
    $('#objForm')[0].reset();
    $('#obj-id').val('');
    $('#modalTitle').text('เพิ่มวัตถุประสงค์');
    $('#objModal').removeClass('hidden');
}

function closeModal() { $('#objModal').addClass('hidden'); }

function editObj(item) {
    $('#modalTitle').text('แก้ไขวัตถุประสงค์');
    $('#obj-id').val(item.id);
    $('#obj-sup-id').val(item.sup_id);
    $('#obj-name').val(item.name);
    $('#objModal').removeClass('hidden');
}

function deleteObj(id) {
    if(confirm('ยืนยันการลบ?')) {
        $.post('?action=delete', { id: id }, function(res) {
            if(res.status === 'success') fetchObj();
            else alert(res.msg);
        });
    }
}
</script>

<?php include 'footer.php'; ?>