const assert = require("node:assert/strict");
const defaults = require("../assets/js/vendor-tax-defaults.js");

assert.deepEqual(
  defaults.toControlState({ vatMode: "inclusive", whtEnabled: true, whtPercent: 3 }),
  { vatEnabled: true, vatIncluded: true, whtEnabled: true, whtPercent: 3 },
);
assert.deepEqual(
  defaults.toControlState({ vatMode: "exclusive", whtEnabled: false, whtPercent: 5 }),
  { vatEnabled: true, vatIncluded: false, whtEnabled: false, whtPercent: 5 },
);
assert.deepEqual(
  defaults.toControlState({ vatMode: "none", whtEnabled: false, whtPercent: 3 }),
  { vatEnabled: false, vatIncluded: false, whtEnabled: false, whtPercent: 3 },
);

assert.deepEqual(defaults.fromDataset({}), {
  entityType: "juristic",
  vatMode: "none",
  whtEnabled: false,
  whtPercent: 3,
});

assert.deepEqual(defaults.fromDataset({
  entityType: "individual",
  vatMode: "inclusive",
  whtEnabled: "1",
  whtPercent: "125",
}), {
  entityType: "individual",
  vatMode: "inclusive",
  whtEnabled: true,
  whtPercent: 100,
});
assert.deepEqual(defaults.fromDataset({
  entityType: "company",
  vatMode: "inside",
  whtEnabled: "maybe",
  whtPercent: "",
}), {
  entityType: "juristic",
  vatMode: "none",
  whtEnabled: false,
  whtPercent: 3,
});

const elements = {
  vat_toggle: { checked: false, dispatchEvent() { throw new Error("unexpected event"); } },
  vat_include_check: { checked: true, dispatchEvent() { throw new Error("unexpected event"); } },
  wht_toggle: { checked: false, dispatchEvent() { throw new Error("unexpected event"); } },
  wht_percent: { value: "0", dispatchEvent() { throw new Error("unexpected event"); } },
  wht_percent_label: { innerText: "0", textContent: "0" },
};
const fakeDocument = {
  getElementById(id) {
    return elements[id] || null;
  },
};

defaults.applyToProjectForm(fakeDocument, {
  vatMode: "inclusive",
  whtEnabled: true,
  whtPercent: 7.5,
});

assert.equal(elements.vat_toggle.checked, true);
assert.equal(elements.vat_include_check.checked, true);
assert.equal(elements.wht_toggle.checked, true);
assert.equal(elements.wht_percent.value, "7.5");
assert.equal(elements.wht_percent_label.innerText, "7.5");

console.log("vendor tax defaults behavior: PASS");
