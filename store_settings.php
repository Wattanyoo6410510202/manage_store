<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-0">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
            <i class="fas fa-store-alt text-emerald-600"></i>
        </div>
        <div>
            <h1 class="text-lg font-black text-slate-800">จัดการร้านค้า</h1>
            <p class="text-[11px] text-slate-500">เพิ่ม แก้ไข หรือลบข้อมูลร้านค้าที่ใช้เปรียบเทียบราคา</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- ฟอร์มเพิ่ม/แก้ไขร้านค้า -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <form id="store-form" action="api/process_store.php" method="POST"
                    class="p-6 space-y-4">
                    <input type="hidden" name="store_id" id="form-store-id" value="">

                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i class="fas fa-store-alt"></i>
                        </div>
                        <span id="form-title" class="text-sm font-black text-slate-800 uppercase tracking-wider">เพิ่มร้านค้าใหม่</span>
                    </div>

                    <input type="text" name="store_name" id="form-name" required
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none"
                        placeholder="ชื่อร้านค้า *">

                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="contact_person" id="form-contact" placeholder="ชื่อผู้ติดต่อ"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none">
                        <input type="text" name="phone" id="form-phone" placeholder="เบอร์โทร"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <input type="email" name="email" id="form-email" placeholder="อีเมล"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none">
                        <input type="text" name="line_id" id="form-line" placeholder="Line ID"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>

                    <textarea name="address" id="form-address" rows="2" placeholder="ที่อยู่ร้านค้า"
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none resize-none"></textarea>

                    <textarea name="notes" id="form-notes" rows="2" placeholder="หมายเหตุเพิ่มเติม"
                        class="w-full border-slate-200 rounded-xl p-2.5 text-sm border focus:ring-2 focus:ring-emerald-500 outline-none resize-none"></textarea>

                    <button type="submit" name="save_store" id="form-submit-btn"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-lg">
                        บันทึกข้อมูลร้านค้า
                    </button>
                </form>
            </div>
        </div>

        <!-- ตารางรายการร้านค้า -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 overflow-x-auto">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest">
                        <i class="fas fa-list mr-2 text-emerald-500"></i>รายการร้านค้าทั้งหมด
                    </span>
                    <button type="button" onclick="resetStoreForm()"
                        class="px-4 py-2 bg-emerald-600 text-white text-[11px] font-black rounded-xl hover:bg-emerald-700 transition-all">
                        <i class="fas fa-plus-circle mr-1"></i> เพิ่มร้านค้าใหม่
                    </button>
                </div>
                <table id="storeTable" class="table table-hover w-full">
                    <thead>
                        <tr class="text-slate-700 text-[11px] uppercase">
                            <th>ชื่อร้านค้า</th>
                            <th>ผู้ติดต่อ / เบอร์โทร</th>
                            <th>อีเมล / Line</th>
                            <th>ที่อยู่</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php
                        $query = mysqli_query($conn, "SELECT * FROM stores ORDER BY id DESC");
                        while ($row = mysqli_fetch_assoc($query)):
                            ?>
                            <tr class="align-middle">
                                <td>
                                    <div class="font-bold text-slate-700"><?php echo htmlspecialchars($row['store_name']); ?></div>
                                    <?php if (!empty($row['notes'])): ?>
                                        <div class="text-[11px] text-slate-400 italic"><?php echo htmlspecialchars($row['notes']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-slate-700 font-medium text-[12px]">
                                        <i class="fas fa-user mr-1 text-slate-400"></i><?php echo $row['contact_person'] ?: '-'; ?>
                                    </div>
                                    <div class="text-emerald-600 font-medium text-[12px]">
                                        <i class="fas fa-phone-alt mr-1"></i><?php echo $row['phone'] ?: '-'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-slate-700 text-[12px]">
                                        <i class="fas fa-envelope mr-1 text-slate-400"></i><?php echo $row['email'] ?: '-'; ?>
                                    </div>
                                    <?php if (!empty($row['line_id'])): ?>
                                        <div class="text-green-600 text-[11px] font-bold">
                                            <i class="fab fa-line mr-1"></i><?php echo htmlspecialchars($row['line_id']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-[12px] text-slate-500 max-w-[200px] truncate"><?php echo $row['address'] ?: '-'; ?></div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            class="w-8 h-8 flex items-center justify-center text-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 rounded-full transition"
                                            onclick='editStore(<?php echo json_encode($row); ?>)' title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="api/process_store.php?delete_id=<?php echo $row['id']; ?>"
                                            onclick="return confirm('ยืนยันการลบร้านค้านี้?')"
                                            class="w-8 h-8 flex items-center justify-center text-red-400 hover:bg-red-50 hover:text-red-600 rounded-full transition"
                                            title="ลบ">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
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
        $('#storeTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' }
        });
    });

    function editStore(data) {
        $('#form-store-id').val(data.id);
        $('#form-name').val(data.store_name);
        $('#form-contact').val(data.contact_person);
        $('#form-phone').val(data.phone);
        $('#form-email').val(data.email);
        $('#form-line').val(data.line_id || '');
        $('#form-address').val(data.address);
        $('#form-notes').val(data.notes);

        $('#form-title').text('แก้ไขร้านค้า: ' + data.store_name);
        $('#form-submit-btn').text('อัปเดตร้านค้า').attr('name', 'update_store').removeClass('bg-emerald-600').addClass('bg-indigo-600');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetStoreForm() {
        $('#form-store-id').val('');
        $('#store-form')[0].reset();
        $('#form-title').text('เพิ่มร้านค้าใหม่');
        $('#form-submit-btn').text('บันทึกข้อมูลร้านค้า').attr('name', 'save_store').removeClass('bg-indigo-600').addClass('bg-emerald-600');
    }
</script>

<?php include 'footer.php'; ?>
