<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="max-w-7xl mx-auto space-y-6 p-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">ตั้งค่าผู้ใช้งาน</h2>
        </div>
        <button type="button" onclick="resetUserForm()" class="text-sm text-indigo-600 hover:underline">
            <i class="fas fa-plus-circle mr-1"></i> เพิ่มผู้ใช้งานใหม่
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-4">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 id="form-title" class="font-bold text-slate-700">ลงทะเบียนผู้ใช้งาน</h3>
                </div>

                <form id="user-form" action="api/process_user.php" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="user_id" id="form-user-id" value="">

                    <div>
                        <input type="text" name="name" id="form-name" required
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition"
                            placeholder="ชื่อ-นามสกุล *">
                    </div>

                    <div>
                        <input type="text" name="username" id="form-username" required
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition"
                            placeholder="Username *">
                    </div>

                    <div>
                        <input type="password" name="password" id="form-password"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none transition"
                            placeholder="กำหนดรหัสผ่าน">
                        <p id="pw-hint" class="text-[10px] text-indigo-400 mt-1 hidden italic">
                            <i class="fas fa-info-circle mr-1"></i> เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่านเดิม
                        </p>
                    </div>

                    <div>
                        <select name="role" id="form-role"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none bg-white appearance-none">
                            <option value="staff">Staff (พนักงานทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                            <option value="gm">GM (ผู้จัดการ)</option>
                             <option value="gmhok">GM HOK (ผู้จัดการ HOK)</option>
                            <option value="hok">HOK (ผู้ปฏิบัติงานพิเศษ)</option>
                            <option value="procure">Procurement (ผู้จัดซื้อ)</option>
                            <option value="fin">Finance (ผู้จัดการการเงิน)</option>
                            <option value="viewer">Viewer (ผู้ชม)</option>
                        </select>
                    </div>

                    <div class="pt-2">
                        <button type="submit" name="save_user" id="form-submit-btn"
                            class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-bold py-3 rounded-xl transition shadow-lg flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                <table id="userTable" class="table table-hover w-full">
                    <thead>
                        <tr class="text-slate-400 text-[11px] uppercase">
                            <th>ผู้ใช้งาน</th>
                            <th>Username</th>
                            <th>ระดับสิทธิ์</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php
                        $query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
                        while ($row = mysqli_fetch_assoc($query)):
                            $json_data = htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE));
                            ?>
                            <tr class="align-middle border-b border-slate-50 last:border-0 hover:bg-slate-50/30 transition">
                                <td class="py-4">
                                    <div class="flex items-center">
                                        <div
                                            class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center mr-3">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div class="font-bold text-slate-700"><?= $row['name'] ?></div>
                                    </div>
                                </td>
                                <td class="text-slate-500"><?= $row['username'] ?></td>
                                <td>
                                    <?php
                                    // กำหนดสีตาม Role (แบบตัวเต็มครบทุกสถานะ)
                                    $role_styles = [
                                        'admin' => 'bg-purple-100 text-purple-600',   // ผู้ดูแลระบบ (สีม่วง) - เหมือนเดิม
                                        'gm' => 'bg-amber-100 text-amber-600',     // ผู้จัดการ (สีส้ม/ทอง) - เหมือนเดิม
                                        'staff' => 'bg-blue-100 text-blue-600', 
                                         'procure' => 'bg-blue-100 text-blue-600',       // พนักงาน (สีฟ้า) - เหมือนเดิม
                                         'fin' => 'bg-green-100 text-green-600',       // ผู้จัดการการเงิน (สีเขียว) - เพิ่มใหม่

                                        // --- จุดที่เปลี่ยนใหม่ให้ต่างกันครับจาร ---
                                
                                        'gmhok' => 'bg-rose-100 text-rose-600',       // GM HOK (สีชมพูเข้ม/แดง) - ให้ดูเด่นระดับผู้บริหาร
                                        'hok' => 'bg-emerald-100 text-emerald-600', // HOK (สีเขียวมรกต) - แยกออกมาให้ดูเป็นฝ่ายปฏิบัติการพิเศษ
                                        'viewer' => 'bg-slate-100 text-slate-500',     // ผู้ชม (สีเทา) - เหมือนเดิม (เน้นเรียบๆ เพราะแค่อ่านอย่างเดียว)
                                    ];

                                    // ดึง Class ตาม Role ถ้าไม่มีในรายการให้ใช้สีเทามาตรฐาน
                                    $current_style = $role_styles[$row['role']] ?? 'bg-slate-100 text-slate-400';
                                    ?>

                                    <span
                                        class="px-3 py-1 rounded-full text-[10px] font-bold uppercase <?= $current_style ?>">
                                        <i class="fas fa-circle text-[6px] mr-1 opacity-50"></i>
                                        <?= htmlspecialchars($row['role']) ?>
                                    </span>
                                </td>
                                <td class="text-center space-x-2">
                                    <button type="button" class="text-indigo-400 hover:text-indigo-600 transition p-2"
                                        onclick='editUser(<?= $json_data ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="api/process_user.php?delete_id=<?= $row['id'] ?>"
                                        onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?')"
                                        class="text-red-300 hover:text-red-500 transition p-2">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#userTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' },
            ordering: false,
            pageLength: 10
        });
    });

    function editUser(data) {
        $('#form-title').text('แก้ไขข้อมูล: ' + data.username);
        $('#form-user-id').val(data.id);
        $('#form-name').val(data.name);
        $('#form-username').val(data.username);
        $('#form-role').val(data.role);

        // สำหรับการแก้ไข รหัสผ่านไม่ต้อง Required
        $('#form-password').val('').attr('placeholder', 'ป้อนรหัสใหม่ถ้าต้องการเปลี่ยน');
        $('#pw-hint').removeClass('hidden');

        // เปลี่ยนสถานะปุ่ม
        $('#form-submit-btn')
            .text('อัปเดตข้อมูล')
            .attr('name', 'update_user')
            .addClass('bg-indigo-600')
            .removeClass('bg-slate-900');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetUserForm() {
        $('#form-title').text('ลงทะเบียนผู้ใช้งาน');
        $('#form-user-id').val('');
        $('#user-form')[0].reset();

        $('#form-password').attr('placeholder', 'กำหนดรหัสผ่าน');
        $('#pw-hint').addClass('hidden');

        $('#form-submit-btn')
            .text('บันทึกข้อมูล')
            .attr('name', 'save_user')
            .addClass('bg-slate-900')
            .removeClass('bg-indigo-600');
    }
</script>

<?php include 'footer.php'; ?>