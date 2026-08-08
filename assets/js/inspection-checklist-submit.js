(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    root.InspectionChecklistSubmit = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    const assignmentFields = [
        'inspector_1_user_id',
        'inspector_2_user_id',
        'procurement_user_id',
        'md_user_id',
        'gmacc_user_id',
    ];

    function validateChecklistSubmission(items, assignments) {
        if (!Array.isArray(items) || items.length === 0) {
            return { message: 'กรุณาเพิ่มรายการตรวจอย่างน้อย 1 ข้อ' };
        }

        const blankTitleIndex = items.findIndex(item => !String(item && item.title || '').trim());
        if (blankTitleIndex !== -1) {
            return {
                message: `รายการตรวจข้อที่ ${blankTitleIndex + 1} ยังไม่มีหัวข้อ`,
                itemIndex: blankTitleIndex,
            };
        }

        const missingField = assignmentFields.find(field => !String(assignments && assignments[field] || '').trim());
        if (missingField) {
            return {
                message: 'กรุณากำหนดผู้รับผิดชอบให้ครบทุกขั้นตอนของ Flow ก่อนบันทึก Checklist',
                fieldName: missingField,
            };
        }

        return null;
    }

    function getChecklistRequestError(xhr) {
        const apiMessage = xhr && xhr.responseJSON && xhr.responseJSON.message;
        if (typeof apiMessage === 'string' && apiMessage.trim()) {
            return apiMessage.trim();
        }
        const status = Number(xhr && xhr.status || 0);
        return status > 0
            ? `ไม่สามารถเชื่อมต่อระบบได้ (HTTP ${status})`
            : 'ไม่สามารถเชื่อมต่อระบบได้ กรุณาลองใหม่อีกครั้ง';
    }

    function setChecklistSubmitting(button, isSubmitting) {
        if (!button) return;
        if (isSubmitting) {
            if (typeof button.__checklistOriginalHtml === 'undefined') {
                button.__checklistOriginalHtml = button.innerHTML;
            }
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>กำลังบันทึก...';
            return;
        }
        button.disabled = false;
        if (typeof button.__checklistOriginalHtml !== 'undefined') {
            button.innerHTML = button.__checklistOriginalHtml;
        }
    }

    return {
        validateChecklistSubmission,
        getChecklistRequestError,
        setChecklistSubmitting,
    };
}));
