const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

function extractCalculateMoney(source) {
  const start = source.indexOf("function calculateMoney()");
  const open = source.indexOf("{", start);
  assert.ok(start >= 0 && open > start, "calculateMoney function is present");

  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === "{") depth += 1;
    if (source[index] === "}") {
      depth -= 1;
      if (depth === 0) return source.slice(start, index + 1);
    }
  }
  throw new Error("calculateMoney function is not balanced");
}

function runCalculation(page, { milestoneVatIncluded, projectRetentionBase }) {
  let source = fs.readFileSync(page, "utf8");
  source = extractCalculateMoney(source)
    .replace(/<\?=\s*\(float\)\s*\$pj\['contract_value'\]\s*\?>/g, "100000")
    .replace(/<\?=\s*\(float\)\s*\$(?:collected|collectedOther)\s*\?>/g, "0")
    .replace(/let projectRetentionBase = <\?=.*?\?>;/g, `let projectRetentionBase = ${projectRetentionBase};`);

  const elements = new Map();
  const add = (id, value = "", checked = false) => {
    const element = { value: String(value), checked, innerText: "", style: {} };
    elements.set(id, element);
    return element;
  };

  add("amount", 10000);
  add("use_vat", "", true);
  add("vat_include_check", "", milestoneVatIncluded);
  add("use_wht", "", false);
  add("use_deduction", "", true);
  add("retention_percent", 5);
  [
    "vat_type_status",
    "vat_amount_val",
    "wht_amount_val",
    "retention_amount_val",
    "other_deduction_amount_val",
    "total_request_amount_val",
    "remaining_balance_val",
  ].forEach((id) => add(id));
  [
    "vat_display",
    "wht_display",
    "deduction_total_display",
    "total_request_display",
    "current_claim_display",
    "remaining_balance_display",
  ].forEach((id) => add(id));
  add("deduction_card");

  const document = { getElementById: (id) => elements.get(id) || null };
  const context = vm.createContext({ document });
  vm.runInContext(`${source}\ncalculateMoney();`, context);

  return {
    retention: Number(elements.get("retention_amount_val").value),
    totalRequest: Number(elements.get("total_request_amount_val").value),
  };
}

for (const page of ["add_milestone.php", "edit_milestone.php"]) {
  assert.deepEqual(
    runCalculation(page, { milestoneVatIncluded: true, projectRetentionBase: 107000 }),
    { retention: 5350, totalRequest: 4650 },
    `${page}: retention uses the VAT-exclusive project total, not the milestone amount`,
  );
  assert.deepEqual(
    runCalculation(page, { milestoneVatIncluded: false, projectRetentionBase: 100000 }),
    { retention: 5000, totalRequest: 5700 },
    `${page}: retention uses the VAT-inclusive project value without adding project VAT twice`,
  );
}

console.log("milestone retention project-value behavior: PASS");
