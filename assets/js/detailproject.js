
$(document).ready(function () {
    // 1. จัดการ Select All
    $('#selectAll').on('click', function () {
        $('.ms-check').prop('checked', this.checked);
        toggleBulkActions();
    });

    // 2. จัดการ Checkbox รายตัว
    $(document).on('change', '.ms-check', function () {
        if ($('.ms-check:checked').length == $('.ms-check').length) {
            $('#selectAll').prop('checked', true);
        } else {
            $('#selectAll').prop('checked', false);
        }
        toggleBulkActions();
    });

    // แสดง/ซ่อน ปุ่มจัดการกลุ่ม
    function toggleBulkActions() {
        if ($('.ms-check:checked').length > 0) {
            $('#bulkActions').removeClass('hidden').addClass('flex');
        } else {
            $('#bulkActions').addClass('hidden').removeClass('flex');
        }
    }
});

// ฟังก์ชันลบหลายรายการ
function bulkDelete() {
    let ids = [];
    $('.ms-check:checked').each(function () {
        // กรองเฉพาะรายการที่ยังไม่จ่าย (ถ้าจารต้องการ)
        if ($(this).data('status') === 'pending') {
            ids.push($(this).val());
        }
    });

    if (ids.length === 0) {
        Swal.fire({ icon: 'warning', title: 'เลือกรายการที่ยังไม่ชำระเงินเท่านั้น', heightAuto: false });
        return;
    }

    Swal.fire({
        title: `ยืนยันการลบ ${ids.length} รายการ?`,
        text: "รายการที่เลือกจะถูกลบถาวร!",
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'ใช่, ลบทั้งหมด!',
        heightAuto: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api/bulk_delete_milestones.php', { ids: ids }, function (res) {
                if (res.trim() === 'success') {
                    Swal.fire({ title: 'ลบเรียบร้อย', icon: 'success', heightAuto: false }).then(() => location.reload());
                } else {
                    Swal.fire({ title: 'ผิดพลาด', text: res, icon: 'error', heightAuto: false });
                }
            });
        }
    });
}

function bulkPrint(type) { // เพิ่ม parameter รับค่าประเภทหน้า
    let ids = [];
    $('.ms-check:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length > 0) {
        // ส่ง ids พร้อมกับ type ไปที่ PHP
        window.location.href = 'view_milstones.php?ids=' + ids.join(',') + '&type=invoice';
    }
}

$(document).ready(function () {
    $('#milestoneTable').DataTable({
        "order": [[2, "desc"]], // เรียงตามวันที่ (คอลัมน์ที่ 3) ล่าสุดขึ้นก่อน
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" // ใช้ไฟล์ภาษาไทยมาตรฐานจาก CDN เลยครับจาร ง่ายดี
        }
    });
});
// ฟังก์ชันดูรูปภาพหลักฐาน
function viewProofImage(url) {
    Swal.fire({
        imageUrl: url,
        imageAlt: 'หลักฐานการโอนเงิน',
        heightAuto: false, // กันหน้าดีด
        showConfirmButton: false,
        showCloseButton: true,
        width: 'auto',
        background: 'transparent',
        padding: '0',
        customClass: {
            image: 'rounded-2xl -2xl max-h-[85vh] border-4 border-white',
            popup: 'bg-transparent -none'
        }
    });
}

// ฟังก์ชันลบรายการ
function deleteMilestone(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ข้อมูลจะหายไปถาวร ไม่สามารถเรียกคืนได้!",
        icon: 'error',
        heightAuto: false,
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, heightAuto: false, didOpen: () => { Swal.showLoading(); } });

            $.post('api/update_milestone_status.php', { id: id, action: 'delete' }, function (response) {
                if (response.trim() === 'success') {
                    Swal.fire({ title: 'ลบสำเร็จ!', icon: 'success', heightAuto: false }).then(() => { location.reload(); });
                } else {
                    Swal.fire({ title: 'ล้มเหลว', text: response, icon: 'error', heightAuto: false });
                }
            });
        }
    });
}

function updatePaymentStatus(milestoneId, newStatus) {
    Swal.fire({
        title: 'ยืนยันการชำระเงิน',
        text: 'กรุณาแนบภาพหลักฐานการโอนเงิน (ถ้ามี)',
        icon: 'info',
        heightAuto: false, // กันหน้าดีด
        input: 'file',
        inputAttributes: {
            'accept': 'image/*',
            'aria-label': 'อัปโหลดหลักฐานการโอน'
        },
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยันชำระเงิน',
        cancelButtonText: 'ยกเลิก',
        preConfirm: (file) => {
            return file;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let formData = new FormData();
            formData.append('id', milestoneId);
            formData.append('status', newStatus);
            if (result.value) {
                formData.append('claim_attachment', result.value);
            }

            // จุดที่ 2: ตอนแสดง Loading
            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                heightAuto: false, // กันหน้าดีด
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'api/update_milestone_status.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.trim() === 'success') {
                        // จุดที่ 3: ตอนแจ้งเตือนสำเร็จ
                        Swal.fire({
                            title: 'สำเร็จ!',
                            text: 'อัปเดตสถานะและอัปโหลดหลักฐานเรียบร้อย',
                            icon: 'success',
                            heightAuto: false // กันหน้าดีด
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        // จุดที่ 4: ตอนแจ้งเตือนข้อผิดพลาด
                        Swal.fire({
                            title: 'เกิดข้อผิดพลาด',
                            text: response,
                            icon: 'error',
                            heightAuto: false // กันหน้าดีด
                        });
                    }
                }
            });
        }
    });
}
function editMilestone(id) {
    // สมมติว่าหน้าแก้ไขจารชื่อ edit_milestone.php นะครับ
    window.location.href = 'edit_milestone.php?id=' + id;
}

