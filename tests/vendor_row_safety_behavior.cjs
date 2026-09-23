const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Exercise the actual submit callback, rendered DataTable cells and delegated
// edit callback. Only network/DataTable/jQuery surfaces are replaced.
const handlers = new Map();
const fields = new Map();
const document = {};
let cells;
let response;
const rowNode = {};
const rowApi = { data(value) { cells = value; return this; }, draw() { return this; }, node() { return rowNode; } };
const row = () => rowApi;
row.add = value => { cells = value; return rowApi; };
const dataTable = { row, on() {}, rows: () => ({ invalidate() {} }), table: () => ({ body: () => ({}) }) };
function $(key) {
  if (!fields.has(key)) fields.set(key, {});
  const state = fields.get(key);
  const chain = {
    0: { reset() {}, scrollIntoView() {} },
    ready(fn) { fn(); return this; },
    DataTable() { return dataTable; },
    on(event, selector, handler) {
      handlers.set(`${event}:${typeof selector === 'string' ? selector : key}`, handler || selector);
      return this;
    },
    val(value) { if (arguments.length) { state.value = value; return this; } return state.value; },
    prop(name, value) { if (arguments.length === 2) { state[name] = value; return this; } return state[name]; },
    attr(name, value) { if (arguments.length === 2) { state[name] = value; return this; } return key?.dataset?.customerId ?? state[name]; },
    addClass() { return this; }, removeClass() { return this; }, toggleClass() { return this; },
    prepend() { return this; }, html() { return this; },
  };
  return chain;
}
const context = vm.createContext({ $, document, FormData: class {}, fetch: async () => ({ json: async () => response }), setTimeout() {} });
vm.runInContext(fs.readFileSync(require.resolve('../assets/js/index.js'), 'utf8'), context);
let edited;
const originalEdit = context.editCustomer;
context.editCustomer = data => { edited = data; originalEdit(data); };
context.renderAlert = type => { assert.notEqual(type, 'error', 'submit callback must succeed'); };
context.resetForm = () => {};

async function submit(action, vendor) {
  $('#formAction').val(action);
  $('#customer_id').val('42');
  response = { status: 'success', id: 42, vendor };
  handlers.get('submit:#customerForm').call({}, { preventDefault() {} });
  await new Promise(resolve => setImmediate(resolve));
}

(async () => {
  const vendor = {
    id: '42', customer_name: `O'Neil <img src=x onerror="alert(1)">`,
    contact_person: '<script>alert(2)</script>', phone: '"<svg onload=alert(3)>',
    tax_id: '<b>123</b>', email: "o'neil@example.test", address: '<p>Address & office</p>',
    entity_type: 'individual', default_vat_mode: 'inclusive', default_wht_enabled: '1', default_wht_percent: '3.00',
    created_by: "O'Neil", updated_by: "'><script>alert(4)</script>",
  };
  await submit('add', vendor);
  assert.ok(!/\bonclick\s*=/i.test(cells[3]), 'dynamic actions must not embed executable inline handlers');
  assert.ok(!/<(?:img|script|svg|b)\b/i.test(cells[1] + cells[2]), 'stored text must not become HTML elements');
  assert.ok(cells[1].includes('O&#39;Neil &lt;img'), 'name must be escaped once');
  assert.equal(cells[2], '&lt;b&gt;123&lt;/b&gt;');
  const edit = handlers.get('click:.edit-customer');
  assert.equal(typeof edit, 'function', 'dynamic edit action must be delegated');
  edit.call({ dataset: { customerId: '42' } });
  assert.equal(edited.created_by, undefined, 'audit fields must not enter UI state');
  assert.equal(edited.updated_by, undefined);
  for (const field of ['customer_name', 'contact_person', 'phone', 'tax_id', 'email', 'address', 'entity_type', 'default_vat_mode', 'default_wht_percent']) {
    assert.equal($('#' + field).val(), vendor[field], `edit preserves raw ${field} as a field value`);
  }
  assert.equal($('#default_wht_enabled').prop('checked'), true);
  await submit('edit', { ...vendor, customer_name: 'Updated <em>Vendor</em>', default_vat_mode: 'none' });
  edit.call({ dataset: { customerId: '42' } });
  assert.equal($('#customer_name').val(), 'Updated <em>Vendor</em>', 'subsequent edits use fresh server state');
  assert.equal($('#default_vat_mode').val(), 'none');
  assert.ok(cells[1].includes('Updated &lt;em&gt;Vendor&lt;/em&gt;'));
  console.log('vendor row safety (add/edit, apostrophe and HTML): PASS');
})().catch(error => { console.error(error); process.exitCode = 1; });
