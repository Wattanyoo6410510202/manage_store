const assert = require('node:assert/strict');
const {
  retentionReturnDaysRemaining,
  retentionReturnLabel,
  showRetentionReturnReminder,
} = require('../assets/js/retention_return_reminder.js');

assert.equal(retentionReturnDaysRemaining('2026-10-24', '2026-10-19'), 5);
assert.equal(retentionReturnDaysRemaining('2026-10-19', '2026-10-19'), 0);
assert.equal(retentionReturnDaysRemaining('2026-10-18', '2026-10-19'), -1);
assert.match(retentionReturnLabel(5), /อีก 5 วัน/);
assert.match(retentionReturnLabel(0), /วันนี้/);
assert.match(retentionReturnLabel(-1), /เกินกำหนด 1 วัน/);

const storage = {
  values: new Map(),
  getItem(key) { return this.values.get(key) ?? null; },
  setItem(key, value) { this.values.set(key, value); },
};
const alerts = [];
const swal = {
  fire(options) { alerts.push(options); },
};
const items = [{
  projectId: 12,
  projectName: 'งานปรับปรุงอาคาร A',
  milestoneName: 'งวดที่ 2',
  dueDate: '2026-10-24',
  daysRemaining: 5,
}];

assert.equal(showRetentionReturnReminder(items, storage, swal, '2026-10-19'), true);
assert.equal(alerts.length, 1);
assert.match(alerts[0].html, /งานปรับปรุงอาคาร A/);
assert.match(alerts[0].html, /งวดที่ 2/);
assert.equal(showRetentionReturnReminder(items, storage, swal, '2026-10-19'), false);
assert.equal(alerts.length, 1, 'same browser day should not show the reminder twice');
assert.equal(showRetentionReturnReminder(items, storage, swal, '2026-10-20'), true);
assert.equal(alerts.length, 2, 'a new browser day may show the reminder again');

console.log('retention return reminder behavior: PASS');
