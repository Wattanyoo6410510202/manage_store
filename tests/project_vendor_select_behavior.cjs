const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const source = fs.readFileSync("add_project.php", "utf8");
const scriptStart = source.indexOf("$(document).ready(function () {", source.indexOf("vendor-tax-defaults.js"));
const scriptEnd = source.indexOf("</script>", scriptStart);
assert.ok(scriptStart >= 0 && scriptEnd > scriptStart, "project Vendor initialization script is present");
assert.match(source, /SELECT id, customer_name, contact_person, entity_type/, "Vendor query includes contact person");
assert.match(source, /data-customer-name=/, "Vendor option exposes its saved name");
assert.match(source, /data-contact-name=/, "Vendor option exposes its contact name");
assert.match(source, /name="contractor_name" id="contractor_name"/, "contractor field has a stable target");

const handlers = new Map();
const select = {
  selectedIndex: 0,
  options: [
    { dataset: { customerName: "Vendor fallback", contactName: "Contact from saved data" } },
    { dataset: { customerName: "Vendor fallback when contact is empty", contactName: "   " } },
  ],
  on(event, handler) {
    handlers.set(event, handler);
    return this;
  },
};
const contractorName = { value: "" };
const document = {
  getElementById(id) {
    return id === "contractor_name" ? contractorName : null;
  },
};
function $(target) {
  if (target === document) return { ready(callback) { callback(); } };
  assert.equal(target, "#customer_select");
  return select;
}

const context = vm.createContext({
  $,
  document,
  window: {
    VendorTaxDefaults: {
      fromDataset: () => ({}),
      applyToProjectForm() {},
    },
  },
  calculateNetValue() {},
});

vm.runInContext(source.slice(scriptStart, scriptEnd), context);
const changeHandler = handlers.get("change");
assert.equal(typeof changeHandler, "function", "Vendor change handler binds without Select2");
changeHandler.call(select);
assert.equal(contractorName.value, "Contact from saved data", "Vendor contact name fills contractor field");

select.selectedIndex = 1;
changeHandler.call(select);
assert.equal(contractorName.value, "Vendor fallback when contact is empty", "Vendor name is the contractor fallback");

console.log("project Vendor select fallback: PASS");
