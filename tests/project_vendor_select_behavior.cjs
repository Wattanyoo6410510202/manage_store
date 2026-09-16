const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const source = fs.readFileSync("add_project.php", "utf8");
const scriptStart = source.indexOf("$(document).ready(function () {", source.indexOf("vendor-tax-defaults.js"));
const scriptEnd = source.indexOf("</script>", scriptStart);
assert.ok(scriptStart >= 0 && scriptEnd > scriptStart, "project Vendor initialization script is present");

const handlers = new Map();
const select = {
  on(event, handler) {
    handlers.set(event, handler);
    return this;
  },
};
const document = {};
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
assert.equal(typeof handlers.get("change"), "function", "Vendor change handler binds without Select2");

console.log("project Vendor select fallback: PASS");
