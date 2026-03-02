
let table;

$(document).ready(function () {
    // 1. Initialize DataTable
    table = $('#customerTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json" },
        "pageLength": 10,
        "columnDefs": [{ "orderable": false, "targets": [0, 3] }],
        // เพิ่มส่วนนี้เพื่อรองรับการ Scroll
        "scrollY": "500px",         // ความสูงของตาราง
        "scrollCollapse": true,    // ถ้าข้อมูลน้อยกว่า 500px ให้หดตารางลงมา
        "paging": true,             // ยังคงมีปุ่มเปลี่ยนหน้า
        // รองรับการเลื่อนซ้าย-ขวาบนมือถือ
    });

    // สำคัญ: เมื่อเปลี่ยนหน้าหรือค้นหา ให้ปรับขนาดคอลัมน์ให้ตรงกันเสมอ
    table.on('draw', function () {
        table.columns.adjust();
    });

    // 2. Form Submission (Add/Edit)
    $('#customerForm').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const action = $('#formAction').val();
        const customerId = $('#customer_id').val();

        fetch('api/process_customer.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const rowData = {
                        id: action === 'add' ? res.id : customerId,
                        customer_name: $('#customer_name').val(),
                        tax_id: $('#tax_id').val() || '-',
                        contact_person: $('#contact_person').val() || '-',
                        phone: $('#phone').val() || '-',
                        email: $('#email').val() || '-',
                        address: $('#address').val()
                    };

                    const actionBtns = `
                        <div class="flex justify-center gap-1">
                            <button onclick='editCustomer(${JSON.stringify(rowData)})' class="w-8 h-8 flex items-center justify-center text-indigo-600 hover:bg-indigo-50 rounded-lg">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteCustomer(${rowData.id})" class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;

                    const nameDisplay = `
                            <div class="font-bold text-slate-700">${rowData.customer_name}</div>
                            <div class="text-[10px] text-slate-400">
                                <i class="fas fa-user mr-1"></i>${rowData.contact_person} | <i class="fas fa-phone mr-1"></i>${rowData.phone}
                            </div>
                        `;

                    if (action === 'add') {
                        // 1. สร้างแถวข้อมูลใหม่
                        const newRowNode = table.row.add([
                            `<input type="checkbox" value="${res.id}" class="customer-checkbox rounded border-slate-300">`,
                            nameDisplay,
                            rowData.tax_id,
                            actionBtns
                        ]).node();

                        // 2. ปรับแต่งแถว (Add Class/Attribute)
                        $(newRowNode)
                            .addClass('hover:bg-slate-50 transition-colors cursor-pointer customer-row bg-emerald-50') // เพิ่มสีเขียวอ่อนชั่วคราวให้รู้ว่ามาใหม่
                            .attr('data-id', res.id);

                        // 3. ย้ายแถวไปไว้บนสุดของ <tbody>
                        $(table.table().body()).prepend(newRowNode);

                        // 4. สั่งให้ DataTable อัปเดตภายใน (ไม่ใช้ .draw() เพราะ draw จะดีดแถวกลับไปตาม sorting เดิม)
                        table.rows().invalidate();

                        // (Optional) ลบสี Highlight ออกหลังจาก 2 วินาที
                        setTimeout(() => {
                            $(newRowNode).removeClass('bg-emerald-50');
                        }, 2000);

                    } else {
                        // โค้ดส่วน Edit เดิมของคุณ (อันนี้ไม่ต้องเปลี่ยน)
                        const targetRow = $(`tr[data-id="${customerId}"]`);
                        table.row(targetRow).data([
                            `<input type="checkbox" value="${customerId}" class="customer-checkbox rounded border-slate-300">`,
                            nameDisplay,
                            rowData.tax_id,
                            actionBtns
                        ]).draw(false);
                    }

                    renderAlert(action === 'add' ? 'add_success' : 'update_success');
                    resetForm();
                } else {
                    renderAlert('error');
                }
            })
            .catch(err => renderAlert('error'));
    });

    // 3. จัดการ "เลือกทั้งหมด" (Select All)
    $(document).on('change', '#selectAll', function () {
        const isChecked = $(this).prop('checked');
        // ใช้ table.$ เพื่อหา checkbox ทุกหน้าที่ถูก render โดย DataTable
        $('.customer-checkbox').prop('checked', isChecked);
        updateDeleteUI();
    });

    // 4. คลิกที่แถว (Row) เพื่อเลือก Checkbox (เว้นการคลิกโดนปุ่มหรือตัว checkbox เอง)
    $(document).on('click', '.customer-row', function (e) {
        if ($(e.target).closest('button, input[type="checkbox"]').length) return;

        const checkbox = $(this).find('.customer-checkbox');
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    });

    // 5. คลิกที่ Checkbox โดยตรง (ใช้ 'change' จะเสถียรกว่า 'click')
    $(document).on('change', '.customer-checkbox', function () {
        updateDeleteUI();
    });
});

/**
 * ฟังก์ชันหลักในการคุม UI ปุ่มลบ (แก้ไขให้เสถียรขึ้น)
 */
function updateDeleteUI() {
    // เลือกเฉพาะ checkbox ที่อยู่ใน tbody ที่ถูกติ๊ก
    const checkedBoxes = $('#customerTable tbody .customer-checkbox:checked');
    const count = checkedBoxes.length;

    if (count > 0) {
        // แสดงปุ่มและอัปเดตตัวเลข
        $('#btnDeleteSelected').removeClass('hidden').show();
        $('#selectedCount').text(count);
    } else {
        // ซ่อนปุ่ม
        $('#btnDeleteSelected').addClass('hidden').hide();
        $('#selectedCount').text(0);
        // ถ้าไม่มีใครถูกเลือกเลย ให้เอาติ๊กที่หัวตารางออกด้วย
        $('#selectAll').prop('checked', false);
    }
}

function renderAlert(type) {
    const configs = {
        'add_success': { msg: "บันทึกข้อมูลเรียบร้อยแล้ว", color: "#10b981", icon: "bi-check-lg" },
        'update_success': { msg: "อัปเดตข้อมูลสำเร็จ", color: "#6366f1", icon: "bi-pencil-square" },
        'delete_success': { msg: "ลบรายการเรียบร้อยแล้ว", color: "#f43f5e", icon: "bi-trash3" },
        'duplicate': { msg: "ชื่อนี้มีในระบบแล้ว!", color: "#f59e0b", icon: "bi-exclamation-circle" },
        'error': { msg: "เกิดข้อผิดพลาดบางอย่าง", color: "#64748b", icon: "bi-x-circle" }
    };

    const config = configs[type] || configs['error'];
    const container = document.getElementById('alert-container');
    if (!container) return;

    const alertDiv = document.createElement('div');
    alertDiv.className = 'custom-alert alert-dismissible fade show mb-3';
    alertDiv.role = 'alert';
    alertDiv.style.color = config.color;
    alertDiv.innerHTML = `
            <div class="alert-icon-wrap" style="background-color: ${config.color}20;">
                <i class="bi ${config.icon} fs-4"></i>
            </div>
            <div class="flex-grow-1">
                <div class="text-dark fw-bold mb-0" style="font-size: 0.95rem;">${config.msg}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.7rem;"></button>
        `;

    container.appendChild(alertDiv);
    setTimeout(() => {
        alertDiv.classList.add('slide-out-right');
        setTimeout(() => alertDiv.remove(), 600);
    }, 4000);
}

function deleteCustomer(id) {
    if (!confirm('ยืนยันการลบลูกค้ารายนี้?')) return;
    fetch(`api/process_customer.php?action=delete&id=${id}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                table.row($(`tr[data-id="${id}"]`)).remove().draw(false);
                renderAlert('delete_success');
                updateDeleteUI();
            }
        });
}

function deleteSelected() {
    const ids = [];
    $('.customer-checkbox:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length === 0) return;
    if (!confirm(`⚠️ ยืนยันลบลูกค้าที่เลือกทั้งหมด ${ids.length} รายการ?\nการกระทำนี้ไม่สามารถย้อนกลับได้`)) return;

    const fd = new FormData();
    fd.append('action', 'bulk_delete');
    fd.append('ids', JSON.stringify(ids));

    fetch('api/process_customer.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                ids.forEach(id => {
                    table.row($(`tr[data-id="${id}"]`)).remove();
                });
                table.draw(false);
                renderAlert('delete_success');
                updateDeleteUI();
            } else {
                alert('เกิดข้อผิดพลาด: ' + (res.message || 'ไม่สามารถลบได้'));
            }
        })
        .catch(err => renderAlert('error'));
}

function editCustomer(data) {
    $('#formTitle').html('<i class="fas fa-user-edit text-orange-500"></i> แก้ไขข้อมูลลูกค้า');
    $('#formAction').val('edit');
    $('#customer_id').val(data.id);
    $('#customer_name').val(data.customer_name);
    $('#tax_id').val(data.tax_id === '-' ? '' : data.tax_id);
    $('#contact_person').val(data.contact_person === '-' ? '' : data.contact_person);
    $('#phone').val(data.phone === '-' ? '' : data.phone);
    $('#email').val(data.email === '-' ? '' : data.email);
    $('#address').val(data.address);

    $('#cancelBtn').removeClass('hidden');
    $('#customerFormSection')[0].scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    $('#customerForm')[0].reset();
    $('#formTitle').html('<i class="fas fa-user-plus text-indigo-500"></i> เพิ่มลูกค้า');
    $('#formAction').val('add');
    $('#customer_id').val('');
    $('#cancelBtn').addClass('hidden');
}

function createDoc(type) {
    const selectedCheckboxes = $('.customer-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('กรุณาเลือกลูกค้าในตารางก่อนทำรายการครับ');
        return;
    }
    if (selectedCheckboxes.length > 1) {
        alert('กรุณาเลือกเพียง 1 รายการเพื่อทำเอกสารครับ');
        return;
    }

    const customerId = selectedCheckboxes.val();
    let targetPage = '';
    switch (type) {
        case 'quotation': targetPage = 'quotation_settings.php'; break;
        case 'pr': targetPage = 'pr_settings.php'; break;
        case 'po': targetPage = 'po_settings.php'; break;
        default: targetPage = 'other.php';
    }
    window.location.href = `${targetPage}?customer_id=${customerId}`;
}




function fillMockCustomer() {
    // ดึงข้อมูลสุ่มมาจากไฟล์แยก
    const data = MockHelper.customer();

    // Mapping เข้ากับ ID ของ Input ในหน้า Customer
    $('#customer_name').val(data.name);
    $('#tax_id').val(data.taxId);
    $('#contact_person').val(data.contact);
    $('#phone').val(data.phone);
    $('#email').val(data.email);
    $('#address').val(data.address);

    // ทำ Effect สั่นเล็กน้อยที่ปุ่มเพื่อให้รู้ว่าเปลี่ยนแล้ว (Optional)
    $('#customerFormSection').addClass('ring-2 ring-amber-400');
    setTimeout(() => $('#customerFormSection').removeClass('ring-2 ring-amber-400'), 500);
}
