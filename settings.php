<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="max-w-7xl mx-auto space-y-6 p-4">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">ตั้งค่ารายชื่อผู้ขาย</h2>
        <button type="button" onclick="resetSupplierForm()" class="text-sm text-indigo-600 hover:underline">
            <i class="fas fa-plus-circle mr-1"></i> เพิ่มผู้ขายใหม่
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-4">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 id="form-title" class="font-bold text-slate-700">เพิ่มผู้ขายใหม่</h3>
                </div>

                <form id="supplier-form" action="api/process_settings.php" method="POST" enctype="multipart/form-data"
                    class="p-6 space-y-4">
                    <input type="hidden" name="supplier_id" id="form-supplier-id" value="">

                    <div class="relative group">
                        <div id="preview-container"
                            class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-slate-200 rounded-2xl min-h-[160px]">
                            <img id="image-preview" src="#" alt="Preview" class="hidden max-h-32 rounded-lg mb-2">
                            <div id="upload-placeholder" class="text-center">
                                <i class="fas fa-cloud-upload-alt text-2xl text-slate-300"></i>
                                <p class="text-[10px] text-slate-400">อัปโหลดโลโก้</p>
                            </div>
                        </div>
                        <input type="file" name="logo" id="logo-input" accept="image/*"
                            class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>

                    <input type="text" name="company_name" id="form-name" required
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-indigo-500 outline-none"
                        placeholder="ชื่อบริษัท *">
                    <input type="text" name="tax_id" id="form-tax"
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border"
                        placeholder="เลขประจำตัวผู้เสียภาษี">

                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="contact_name" id="form-contact" placeholder="ผู้ติดต่อ"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border">
                        <input type="text" name="phone" id="form-phone" placeholder="เบอร์โทร"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border">
                    </div>

                    <input name="email" id="form-email" placeholder="อีเมลบริษัท"
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border">

                    <textarea name="address" id="form-address" rows="3" placeholder="ที่อยู่บริษัท"
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border"></textarea>

                    <button type="submit" name="save_supplier" id="form-submit-btn"
                        class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-bold py-3 rounded-xl transition shadow-lg">
                        บันทึกข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                <table id="supplierTable" class="table table-hover w-full">
                    <thead>
                        <tr class="text-slate-400 text-[11px] uppercase">
                            <th>Logo</th>
                            <th>รายละเอียด</th>
                            <th>การติดต่อ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php
                        $query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id DESC");
                        while ($row = mysqli_fetch_assoc($query)):
                            ?>
                            <tr class="align-middle">
                                <td><img src="uploads/<?php echo $row['logo_path']; ?>"
                                        class="w-10 h-10 rounded-lg border object-cover"
                                        onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($row['company_name']); ?>'">
                                </td>
                                <td>
                                    <div class="font-bold text-slate-700"><?php echo $row['company_name']; ?></div>
                                    <div class="text-[10px] text-slate-400">Tax ID: <?php echo $row['tax_id'] ?: '-'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-slate-600"><?php echo $row['contact_name']; ?></div>
                                    <div class="text-indigo-500 font-medium"><?php echo $row['phone']; ?></div>
                                </td>
                                <td class="text-center space-x-2">
                                    <button type="button" class="text-indigo-400 hover:text-indigo-600 transition"
                                        onclick="editSupplier(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="api/process_settings.php?delete_id=<?php echo $row['id']; ?>"
                                        onclick="return confirm('ยืนยันการลบ?')" class="text-red-400"><i
                                            class="fas fa-trash-alt"></i></a>
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
        $('#supplierTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' }
        });

        // Image Preview
        $('#logo-input').on('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#image-preview').attr('src', e.target.result).removeClass('hidden');
                    $('#upload-placeholder').addClass('hidden');
                }
                reader.readAsDataURL(file);
            }
        });
    });

    // ฟังก์ชันส่งข้อมูลไปที่ฟอร์มเพื่อแก้ไข
    function editSupplier(data) {
        $('#form-title').text('แก้ไขข้อมูล: ' + data.company_name);
        $('#form-supplier-id').val(data.id);
        $('#form-name').val(data.company_name);
        $('#form-tax').val(data.tax_id);
        $('#form-contact').val(data.contact_name);
        $('#form-phone').val(data.phone);
        $('#form-address').val(data.address);
        $('#form-email').val(data.email);

        // --- เพิ่มส่วนนี้เพื่อให้แสดงภาพเดิม ---
        if (data.logo_path) {
            $('#image-preview')
                .attr('src', 'uploads/' + data.logo_path) // เปลี่ยน src ไปที่ไฟล์ในโฟลเดอร์ uploads
                .removeClass('hidden');
            $('#upload-placeholder').addClass('hidden');
        }
        // ----------------------------------

        // เปลี่ยนชื่อปุ่มและ name ของปุ่มเพื่อให้ API ทราบว่าเป็นสถานะ Update
        $('#form-submit-btn').text('อัปเดตข้อมูล').attr('name', 'update_supplier').addClass('bg-indigo-600').removeClass('bg-slate-900');

        // เลื่อนหน้าจอไปที่ฟอร์ม (สำหรับมือถือ)
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ฟังก์ชันรีเซ็ตฟอร์มกลับเป็นสถานะเพิ่มใหม่
    function resetSupplierForm() {
        $('#form-title').text('เพิ่มผู้ขายใหม่');
        $('#form-supplier-id').val('');
        $('#supplier-form')[0].reset();
        $('#image-preview').addClass('hidden');
        $('#upload-placeholder').removeClass('hidden');
        $('#form-submit-btn').text('บันทึกข้อมูล').attr('name', 'save_supplier').addClass('bg-slate-900').removeClass('bg-indigo-600');
    }
</script>

<?php include 'footer.php'; ?>