const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const modulePath = path.join(__dirname, '..', 'assets', 'js', 'inspection-checklist-submit.js');
if (!fs.existsSync(modulePath)) {
    console.error('FAIL: Checklist submit behavior module is missing');
    process.exit(1);
}

const {
    validateChecklistSubmission,
    getChecklistRequestError,
    setChecklistSubmitting,
} = require(modulePath);

const completeAssignments = {
    inspector_1_user_id: '24',
    inspector_2_user_id: '12',
    procurement_user_id: '31',
    md_user_id: '41',
    gmacc_user_id: '21',
};

assert.deepEqual(
    validateChecklistSubmission([], completeAssignments),
    { message: 'กรุณาเพิ่มรายการตรวจอย่างน้อย 1 ข้อ' },
    'An empty checklist must be rejected before the request'
);

assert.deepEqual(
    validateChecklistSubmission(
        [{ title: 'ตรวจ BOQ' }, { title: '   ' }],
        completeAssignments
    ),
    { message: 'รายการตรวจข้อที่ 2 ยังไม่มีหัวข้อ', itemIndex: 1 },
    'The first checklist row with a blank title must be identified'
);

assert.deepEqual(
    validateChecklistSubmission(
        [{ title: 'ตรวจ BOQ' }],
        { ...completeAssignments, md_user_id: '' }
    ),
    {
        message: 'กรุณากำหนดผู้รับผิดชอบให้ครบทุกขั้นตอนของ Flow ก่อนบันทึก Checklist',
        fieldName: 'md_user_id',
    },
    'The first unassigned workflow step must be identified'
);

assert.equal(
    validateChecklistSubmission([{ title: 'ตรวจ BOQ' }], completeAssignments),
    null,
    'A complete Checklist payload must pass client validation'
);

assert.equal(
    getChecklistRequestError({ responseJSON: { message: 'คุณไม่มีสิทธิ์กำหนด Checklist' }, status: 403 }),
    'คุณไม่มีสิทธิ์กำหนด Checklist',
    'The API JSON message must be shown for non-2xx responses'
);

assert.equal(
    getChecklistRequestError({ status: 500 }),
    'ไม่สามารถเชื่อมต่อระบบได้ (HTTP 500)',
    'A safe HTTP fallback must be shown when the response is not JSON'
);

const button = { disabled: false, innerHTML: '<i class="fas fa-save"></i>บันทึก' };
setChecklistSubmitting(button, true);
assert.equal(button.disabled, true, 'The submit button must be disabled while saving');
assert.match(button.innerHTML, /กำลังบันทึก/, 'The submit button must show a saving state');
setChecklistSubmitting(button, false);
assert.equal(button.disabled, false, 'The submit button must be enabled after a failed request');
assert.equal(button.innerHTML, '<i class="fas fa-save"></i>บันทึก', 'The original button label must be restored');

console.log('inspection checklist submit behavior: PASS');
