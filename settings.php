<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="container-fluid p-0">


    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-4">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <form id="supplier-form" action="api/process_settings.php" method="POST" enctype="multipart/form-data"
                    class="p-6 space-y-4">
                    <input type="hidden" name="supplier_id" id="form-supplier-id" value="">

                    <div class="relative group">
                        <div id="preview-container"
                            class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-slate-200 rounded-2xl min-h-[140px]">
                            <img id="image-preview" src="#" alt="Preview" class="hidden max-h-28 rounded-lg mb-2">
                            <div id="upload-placeholder" class="text-center">
                                <i class="fas fa-cloud-upload-alt text-2xl text-slate-300"></i>
                                <p class="text-[12px] text-slate-700">อัปโหลดโลโก้บริษัท</p>
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

                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-[11px] font-bold text-slate-800 uppercase mb-2">ข้อมูลการชำระเงิน</p>
                        <input type="text" name="bank_name" id="form-bank-name" placeholder="ชื่อธนาคาร"
                            class="w-full border-slate-200 rounded-xl p-2.5 text-sm border mb-3">

                        <div class="grid grid-cols-2 gap-4">
                            <input type="text" name="bank_account_name" id="form-acc-name" placeholder="ชื่อบัญชี"
                                class="w-full border-slate-200 rounded-xl p-2.5 text-sm border">
                            <input type="text" name="bank_account_number" id="form-acc-no" placeholder="เลขที่บัญชี"
                                class="w-full border-slate-200 rounded-xl p-2.5 text-sm border">
                        </div>
                    </div>

                    <div class="pt-2">
                        <p class="text-[11px] font-bold text-slate-700 uppercase mb-2">QR Code ชำระเงิน (PromptPay)</p>
                        <div class="relative group">
                            <div id="qr-preview-container"
                                class="flex flex-col items-center justify-center p-3 border-2 border-dashed border-slate-200 rounded-2xl min-h-[100px] bg-slate-50">
                                <img id="qr-preview" src="#" alt="QR Preview" class="hidden max-h-24 rounded-lg mb-1">
                                <div id="qr-upload-placeholder" class="text-center">
                                    <i class="fas fa-qrcode text-xl text-slate-300"></i>
                                    <p class="text-[12px] text-slate-700">อัปโหลด QR Code</p>
                                </div>
                            </div>
                            <input type="file" name="qr_code" id="qr-input" accept="image/*"
                                class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-[11px] font-bold text-slate-800 uppercase mb-2">การแจ้งเตือน (Notifications)</p>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                                <i class="fab fa-line text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Line Notify Token</label>
                                <input type="text" name="line_token" id="form-line-token" placeholder="ใส่ Token สำหรับแจ้งเตือน"
                                    class="w-full border-slate-200 rounded-xl p-2 text-sm border focus:ring-2 focus:ring-green-500 outline-none">
                            </div>
                        </div>
                        <p class="text-[9px] text-slate-400 italic leading-tight">* ใช้สำหรับการแจ้งเตือนเมื่อมีการขอซื้อ (PR) หรืออนุมัติงบประมาณ</p>
                    </div>

                    <button type="submit" name="save_supplier" id="form-submit-btn"
                        class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-bold py-3 rounded-xl transition shadow-lg">
                        บันทึกข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 overflow-x-auto">
                <div class="flex items-center justify-between">
                    <button type="button" onclick="resetSupplierForm()" class="text-sm text-indigo-600 hover:underline">
                        <i class="fas fa-plus-circle mr-1"></i> เพิ่มผู้ขายใหม่
                    </button>
                </div>
                <table id="supplierTable" class="table table-hover w-full">
                    <thead>
                        <tr class="text-slate-700 text-[11px] uppercase">
                            <th class="w-16">Logo</th>
                            <th>บริษัท / Tax ID</th>
                            <th>ข้อมูลติดต่อ</th>
                            <th>ธนาคาร / Line</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php
                        $query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id DESC");
                        while ($row = mysqli_fetch_assoc($query)):
                            ?>
                            <tr class="align-middle">
                                <td>
                                    <img src="uploads/<?php echo $row['logo_path']; ?>"
                                        class="w-10 h-10 rounded-lg border object-cover"
                                        onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($row['company_name']); ?>'">
                                </td>
                                <td>
                                    <div class="font-bold text-slate-700"><?php echo $row['company_name']; ?></div>
                                    <div class="text-[12px] text-slate-700">Tax ID: <?php echo $row['tax_id'] ?: '-'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-indigo-600 font-medium text-[12px]"><i
                                            class="fas fa-phone-alt mr-1"></i><?php echo $row['phone'] ?: '-'; ?></div>
                                    <div class="text-slate-700 text-[11px]"><i
                                            class="fas fa-user mr-1"></i><?php echo $row['contact_name'] ?: '-'; ?></div>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <div class="text-slate-700 font-bold text-[11px]">
                                                <?php echo $row['bank_name'] ?: '-'; ?></div>
                                            <?php if (!empty($row['qr_code_path'])): ?>
                                                <i class="fas fa-qrcode text-indigo-400 text-[10px]" title="มี QR Code"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500">
                                            <?php echo $row['bank_account_number'] ?: '-'; ?></div>
                                        <?php if (!empty($row['line_token'])): ?>
                                            <div class="flex items-center gap-1 text-green-600 text-[10px] font-bold">
                                                <i class="fab fa-line"></i> Line Enabled
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            class="w-8 h-8 flex items-center justify-center text-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 rounded-full transition"
                                            onclick='editSupplier(<?php echo json_encode($row); ?>)' title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="api/process_settings.php?delete_id=<?php echo $row['id']; ?>"
                                            onclick="return confirm('ยืนยันการลบ?')"
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
        $('#supplierTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' }
        });

        // Preview Logo
        $('#logo-input').on('change', function () {
            readURL(this, '#image-preview', '#upload-placeholder');
        });

        // Preview QR Code
        $('#qr-input').on('change', function () {
            readURL(this, '#qr-preview', '#qr-upload-placeholder');
        });
    });

    function readURL(input, previewId, placeholderId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $(previewId).attr('src', e.target.result).removeClass('hidden');
                $(placeholderId).addClass('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function editSupplier(data) {
        $('#form-title').text('แก้ไขข้อมูล: ' + data.company_name);
        $('#form-supplier-id').val(data.id);
        $('#form-name').val(data.company_name);
        $('#form-tax').val(data.tax_id);
        $('#form-contact').val(data.contact_name);
        $('#form-phone').val(data.phone);
        $('#form-address').val(data.address);
        $('#form-email').val(data.email);
        $('#form-bank-name').val(data.bank_name);
        $('#form-acc-name').val(data.bank_account_name);
        $('#form-acc-no').val(data.bank_account_number);
        $('#form-line-token').val(data.line_token || '');

        // จัดการ Preview Logo
        if (data.logo_path && data.logo_path !== 'default-logo.png') {
            $('#image-preview').attr('src', 'uploads/' + data.logo_path).removeClass('hidden');
            $('#upload-placeholder').addClass('hidden');
        } else {
            $('#image-preview').addClass('hidden');
            $('#upload-placeholder').removeClass('hidden');
        }

        // จัดการ Preview QR Code
        if (data.qr_code_path) {
            $('#qr-preview').attr('src', 'uploads/' + data.qr_code_path).removeClass('hidden');
            $('#qr-upload-placeholder').addClass('hidden');
        } else {
            $('#qr-preview').addClass('hidden');
            $('#qr-upload-placeholder').removeClass('hidden');
        }

        $('#form-submit-btn').text('อัปเดตข้อมูล').attr('name', 'update_supplier').addClass('bg-indigo-600').removeClass('bg-slate-900');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetSupplierForm() {
        $('#form-title').text('เพิ่มผู้ขายใหม่');
        $('#form-supplier-id').val('');
        $('#supplier-form')[0].reset();

        $('#image-preview, #qr-preview').addClass('hidden');
        $('#upload-placeholder, #qr-upload-placeholder').removeClass('hidden');

        $('#form-submit-btn').text('บันทึกข้อมูล').attr('name', 'save_supplier').addClass('bg-slate-900').removeClass('bg-indigo-600');
    }
</script>

<?php include 'footer.php'; ?>