# Vendor Tax Defaults Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** เปลี่ยนหน้าจัดการ `customers` ให้ผู้ใช้มองเห็นเป็น “Vendor”, เก็บประเภทบุคคลและค่าเริ่มต้น VAT/WHT ต่อ Vendor และนำค่าเหล่านั้นไปตั้งต้นอัตโนมัติเมื่อเลือก Vendor ในหน้าสร้างโครงการ โดยยังแก้ค่าเฉพาะโครงการได้

**Architecture:** คงชื่อตาราง `customers`, foreign key `customer_id` และชื่อฟังก์ชันเดิมไว้เพื่อไม่ให้เอกสาร Quotation/PR/PO/Invoice ที่ใช้งานอยู่เสียหาย เพิ่มเฉพาะคอลัมน์ tax-default ลงใน `customers` และส่งค่าผ่าน `data-*` ของ `<option>` ไปยัง JavaScript ฝั่งหน้าสร้างโครงการโดยไม่เพิ่ม endpoint ใหม่ แก้ชนิด `projects.has_vat` ให้ตรงกับสัญญา `NULL = ไม่คิด VAT`, `0 = VAT ใน`, `1 = VAT นอก` ก่อนเปิดใช้ค่าเริ่มต้นจาก Vendor

**Tech Stack:** PHP 8, MariaDB/MySQL (`mysqli`), JavaScript/jQuery/Select2, Tailwind CSS, Node.js smoke tests, PHP CLI smoke tests

**Spec:** [Functional Specification ในเอกสารนี้](./2026-09-16-vendor-tax-defaults.md#functional-specification)

## Global Constraints

- เปลี่ยนเฉพาะข้อความบน UI เป็น “Vendor”; ห้าม rename ตาราง `customers`, คอลัมน์ `customer_id`, route `api/process_customer.php` หรือฟังก์ชัน JavaScript เดิม เพราะมีเอกสารหลายโมดูลอ้างอิงอยู่
- ค่า `entity_type` ใช้ได้เฉพาะ `juristic` และ `individual`; ค่าเริ่มต้นข้อมูลเดิมคือ `juristic`
- ค่า `default_vat_mode` ใช้ได้เฉพาะ `none`, `exclusive`, `inclusive`; ค่าเริ่มต้นข้อมูลเดิมคือ `none`
- VAT คงอัตรา 7% ตามระบบเดิม; ฟีเจอร์นี้เลือกเพียง “ไม่คิด / VAT นอก / VAT ใน”
- WHT เก็บ `default_wht_enabled` แยกจาก `default_wht_percent`; ค่าเริ่มต้นคือปิดและ 3.00%
- เมื่อเลือก Vendor ในหน้าสร้างโครงการ ให้เติมค่า default แล้วเรียกคำนวณใหม่ทันที แต่ผู้ใช้ต้องยังเปิด/ปิด VAT, VAT ใน และ WHT เองได้หลังจากนั้น
- เมื่อเลือก option ว่าง ให้คืนค่าเป็นไม่คิด VAT, ไม่หัก WHT และ WHT 3.00%
- API ฝั่ง PHP ต้อง normalize/validate ค่าเอง ห้ามเชื่อค่าจาก browser โดยตรง
- การ migration `projects.has_vat` ต้องเก็บความหมายของข้อมูลเก่า: `NULL = ไม่มี VAT`, `0 = VAT ใน`, `1 = VAT นอก`
- ห้ามเปลี่ยนพฤติกรรมของ `suppliers` ซึ่งเป็น “ผู้ว่าจ้าง/บริษัทเจ้าของเอกสาร” ในหน้าโครงการ

---

## Functional Specification

### Vendor management

หน้าปัจจุบัน `index.php` เปลี่ยนคำที่ผู้ใช้เห็นจาก “ลูกค้า” เป็น “Vendor” และเพิ่มฟิลด์:

1. ประเภท Vendor: `นิติบุคคล` หรือ `บุคคลธรรมดา`
2. VAT เริ่มต้น: `ไม่คิด VAT`, `VAT นอก`, `VAT ใน`
3. หัก ณ ที่จ่ายเริ่มต้น: เปิด/ปิด
4. อัตราหัก ณ ที่จ่าย: ตัวเลข 0.00–100.00, เริ่มต้น 3.00

การเพิ่มและแก้ไข Vendor ต้องบันทึกทั้งสี่ค่า ตารางรายการ Vendor ควรแสดง badge สั้น ๆ ใต้ชื่อ เช่น `นิติบุคคล · VAT ใน · WHT 3%` เพื่อให้ตรวจสอบค่า default ได้โดยไม่ต้องเปิดฟอร์มแก้ไข

### Project creation

เมื่อ `#customer_select` เปลี่ยนค่า:

- `none` → ปิด `#vat_toggle`, ปิด `#vat_include_check`
- `exclusive` → เปิด `#vat_toggle`, ปิด `#vat_include_check`
- `inclusive` → เปิด `#vat_toggle`, เปิด `#vat_include_check`
- `default_wht_enabled = 1` → เปิด `#wht_toggle`; ค่าอื่นปิด
- ใส่เปอร์เซ็นต์ใน hidden input `#wht_percent` และอัปเดตข้อความเปอร์เซ็นต์บนหน้าจอ
- เรียก `calculateNetValue()` หลังตั้งค่าทั้งหมดหนึ่งครั้ง

การเปลี่ยน toggle หลังเลือก Vendor เป็น override ระดับโครงการและไม่ย้อนกลับไปแก้ค่า default ของ Vendor

### Backward compatibility

ข้อมูล Vendor เดิมเป็น `juristic + none + WHT off + 3.00%` หลัง migration เอกสารเดิมยังใช้ `customer_id` ได้เหมือนเดิม โครงการเดิมต้องอ่าน VAT mode ได้ตรงกันทั้งหน้าเพิ่ม หน้าแก้ไข และ API

---

## File Map

**Create**

- `add_vendor_tax_defaults.sql` — เพิ่มคอลัมน์ default ภาษีใน `customers` และแปลง `projects.has_vat` เป็น nullable tinyint พร้อม migrate ข้อมูลเดิม
- `customer_tax_defaults.php` — normalize และ validate payload ภาษีของ Vendor ที่ใช้ร่วมกันใน API
- `assets/js/vendor-tax-defaults.js` — แปลง dataset ของ `<option>` เป็นสถานะ control และ apply ลงหน้าสร้างโครงการ
- `tests/vendor_tax_schema_smoke.php` — ตรวจ schema/default และความหมาย `projects.has_vat`
- `tests/vendor_tax_defaults_smoke.php` — unit test helper PHP
- `tests/vendor_tax_defaults_behavior.cjs` — unit test logic JavaScript
- `tests/vendor_tax_form_smoke.php` — ตรวจ form fields, option datasets และ wiring ที่จำเป็น

**Modify**

- `index.php` — เปลี่ยน copy เป็น Vendor, เพิ่ม form controls และ badge ค่า default
- `assets/js/index.js` — ส่ง/เติม/reset ค่าใหม่และเปลี่ยนข้อความที่ผู้ใช้เห็นเป็น Vendor
- `assets/js/tutorial.js` — เปลี่ยนข้อความ tutorial จากลูกค้าเป็น Vendor
- `api/process_customer.php` — validate และ persist ค่า default ใน add/edit
- `add_project.php` — query ค่า default, ใส่ `data-*`, เพิ่ม WHT percent hidden field และ apply default เมื่อเลือก Vendor
- `edit_project.php` — อ่าน `projects.has_vat` แบบ nullable tinyint ให้ถูกต้องหลัง migration
- `api/save_project.php` — validate WHT percent และบันทึก VAT mode ตามสัญญา nullable tinyint
- `api/update_project.php` — คำนวณ VAT/WHT/net ใหม่ฝั่ง server และบันทึก VAT mode ตามสัญญาเดียวกับหน้าเพิ่ม

---

### Task 1: Database migration and schema contract

**Files:**

- Create: `add_vendor_tax_defaults.sql`
- Create: `tests/vendor_tax_schema_smoke.php`

**Interfaces:**

- Produces: `customers.entity_type`, `customers.default_vat_mode`, `customers.default_wht_enabled`, `customers.default_wht_percent`
- Produces: `projects.has_vat TINYINT(1) NULL` with `NULL/0/1` semantics

- [ ] **Step 1: Write the failing schema smoke test**

Create `tests/vendor_tax_schema_smoke.php` that connects through `config.php`, reads `SHOW COLUMNS`, and asserts these exact contracts:

```php
<?php
require_once __DIR__ . '/../config.php';

function fail_schema(string $message): void {
    fwrite(STDERR, "vendor tax schema: FAIL - {$message}\n");
    exit(1);
}

function column_map(mysqli $conn, string $table): array {
    $result = $conn->query("SHOW COLUMNS FROM {$table}");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row;
    }
    return $columns;
}

$customers = column_map($conn, 'customers');
$projects = column_map($conn, 'projects');

$expectedCustomerColumns = [
    'entity_type' => "enum('juristic','individual')",
    'default_vat_mode' => "enum('none','exclusive','inclusive')",
    'default_wht_enabled' => 'tinyint(1)',
    'default_wht_percent' => 'decimal(5,2)',
];

foreach ($expectedCustomerColumns as $name => $type) {
    if (!isset($customers[$name]) || strtolower($customers[$name]['Type']) !== $type) {
        fail_schema("customers.{$name} must be {$type}");
    }
}

if (strtolower($projects['has_vat']['Type'] ?? '') !== 'tinyint(1)' || ($projects['has_vat']['Null'] ?? '') !== 'YES') {
    fail_schema('projects.has_vat must be nullable tinyint(1)');
}

$invalid = $conn->query("SELECT COUNT(*) AS total FROM projects WHERE has_vat IS NOT NULL AND has_vat NOT IN (0, 1)")->fetch_assoc();
if ((int)$invalid['total'] !== 0) {
    fail_schema('projects.has_vat contains values outside NULL/0/1');
}

echo "vendor tax schema: PASS\n";
```

- [ ] **Step 2: Run the schema test and verify it fails before migration**

Run: `C:\xampp\php\php.exe tests\vendor_tax_schema_smoke.php`

Expected: FAIL because the four `customers` columns do not exist and `projects.has_vat` is still `enum('no','yes')`.

- [ ] **Step 3: Write the idempotent migration**

Create `add_vendor_tax_defaults.sql` with `information_schema` guards or MariaDB-supported `ADD COLUMN IF NOT EXISTS`. The project VAT conversion must temporarily use `VARCHAR` so existing `''`, `no`, `yes`, and `NULL` values can be classified:

```sql
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS entity_type ENUM('juristic','individual') NOT NULL DEFAULT 'juristic' AFTER customer_name,
    ADD COLUMN IF NOT EXISTS default_vat_mode ENUM('none','exclusive','inclusive') NOT NULL DEFAULT 'none' AFTER entity_type,
    ADD COLUMN IF NOT EXISTS default_wht_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER default_vat_mode,
    ADD COLUMN IF NOT EXISTS default_wht_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00 AFTER default_wht_enabled;

ALTER TABLE projects MODIFY has_vat VARCHAR(10) NULL;

UPDATE projects
SET has_vat = CASE
    WHEN COALESCE(total_vat_amount, 0) = 0 THEN NULL
    WHEN has_vat = '' THEN '0'
    WHEN ABS(total_vat_amount - (contract_value * 0.07)) <= 0.02 THEN '1'
    ELSE '0'
END;

ALTER TABLE projects MODIFY has_vat TINYINT(1) NULL DEFAULT NULL;
```

Before executing, add a read-only audit query to the SQL comments showing project id, stored amounts, old `has_vat`, and the computed target for rows with VAT. This allows the implementer to inspect ambiguous legacy rows before applying the conversion.

- [ ] **Step 4: Apply the migration locally**

Run: `C:\xampp\mysql\bin\mysql.exe -u root procurement_system -e "SOURCE C:/xampp/htdocs/manage_store/add_vendor_tax_defaults.sql"`

Expected: command exits 0.

- [ ] **Step 5: Run the schema test**

Run: `C:\xampp\php\php.exe tests\vendor_tax_schema_smoke.php`

Expected: `vendor tax schema: PASS`.

- [ ] **Step 6: Commit the schema change**

```bash
git add add_vendor_tax_defaults.sql tests/vendor_tax_schema_smoke.php
git commit -m "feat: add vendor tax default schema"
```

---

### Task 2: Shared PHP normalization and API persistence

**Files:**

- Create: `customer_tax_defaults.php`
- Create: `tests/vendor_tax_defaults_smoke.php`
- Modify: `api/process_customer.php`

**Interfaces:**

- Produces: `customer_tax_defaults_from_input(array $input): array{entity_type:string,default_vat_mode:string,default_wht_enabled:int,default_wht_percent:float}`
- Produces: API success JSON containing `vendor` with normalized tax-default fields

- [ ] **Step 1: Write failing unit tests for normalization**

Create `tests/vendor_tax_defaults_smoke.php` covering:

```php
<?php
require_once __DIR__ . '/../customer_tax_defaults.php';

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$juristic = customer_tax_defaults_from_input([
    'entity_type' => 'juristic',
    'default_vat_mode' => 'inclusive',
    'default_wht_enabled' => '1',
    'default_wht_percent' => '3.00',
]);
assert_same('juristic', $juristic['entity_type'], 'entity type');
assert_same('inclusive', $juristic['default_vat_mode'], 'VAT mode');
assert_same(1, $juristic['default_wht_enabled'], 'WHT enabled');
assert_same(3.0, $juristic['default_wht_percent'], 'WHT percent');

$defaults = customer_tax_defaults_from_input([]);
assert_same('juristic', $defaults['entity_type'], 'default entity type');
assert_same('none', $defaults['default_vat_mode'], 'default VAT mode');
assert_same(0, $defaults['default_wht_enabled'], 'default WHT enabled');
assert_same(3.0, $defaults['default_wht_percent'], 'default WHT percent');

foreach ([
    ['entity_type' => 'company'],
    ['default_vat_mode' => 'inside'],
    ['default_wht_percent' => '-1'],
    ['default_wht_percent' => '101'],
] as $invalid) {
    try {
        customer_tax_defaults_from_input($invalid);
        throw new RuntimeException('invalid payload must throw');
    } catch (InvalidArgumentException $expected) {
    }
}

echo "vendor tax defaults: PASS\n";
```

- [ ] **Step 2: Run the unit test and verify it fails**

Run: `C:\xampp\php\php.exe tests\vendor_tax_defaults_smoke.php`

Expected: FAIL because `customer_tax_defaults.php` does not exist.

- [ ] **Step 3: Implement the normalization helper**

Create `customer_tax_defaults.php` with strict allowlists. Treat an absent checkbox as disabled and reject non-numeric percentages or values outside 0–100:

```php
<?php
function customer_tax_defaults_from_input(array $input): array {
    $entityType = (string)($input['entity_type'] ?? 'juristic');
    $vatMode = (string)($input['default_vat_mode'] ?? 'none');
    $whtEnabled = (string)($input['default_wht_enabled'] ?? '0') === '1' ? 1 : 0;
    $rawPercent = $input['default_wht_percent'] ?? '3.00';

    if (!in_array($entityType, ['juristic', 'individual'], true)) {
        throw new InvalidArgumentException('ประเภท Vendor ไม่ถูกต้อง');
    }
    if (!in_array($vatMode, ['none', 'exclusive', 'inclusive'], true)) {
        throw new InvalidArgumentException('รูปแบบ VAT ไม่ถูกต้อง');
    }
    if (!is_numeric($rawPercent)) {
        throw new InvalidArgumentException('อัตราหัก ณ ที่จ่ายต้องเป็นตัวเลข');
    }

    $whtPercent = round((float)$rawPercent, 2);
    if ($whtPercent < 0 || $whtPercent > 100) {
        throw new InvalidArgumentException('อัตราหัก ณ ที่จ่ายต้องอยู่ระหว่าง 0 ถึง 100');
    }

    return [
        'entity_type' => $entityType,
        'default_vat_mode' => $vatMode,
        'default_wht_enabled' => $whtEnabled,
        'default_wht_percent' => $whtPercent,
    ];
}
```

- [ ] **Step 4: Update add/edit SQL in `api/process_customer.php`**

Require the helper, normalize once, and add the four columns to both prepared statements. Use these exact bind signatures:

```php
// INSERT: 8 strings, integer, double, 2 strings
$stmt->bind_param(
    'ssssssssidss',
    $customer_name,
    $tax_id,
    $contact_person,
    $phone,
    $email,
    $address,
    $taxDefaults['entity_type'],
    $taxDefaults['default_vat_mode'],
    $taxDefaults['default_wht_enabled'],
    $taxDefaults['default_wht_percent'],
    $user,
    $user
);

// UPDATE: 8 strings, integer, double, string, integer
$stmt->bind_param(
    'ssssssssidsi',
    $customer_name,
    $tax_id,
    $contact_person,
    $phone,
    $email,
    $address,
    $taxDefaults['entity_type'],
    $taxDefaults['default_vat_mode'],
    $taxDefaults['default_wht_enabled'],
    $taxDefaults['default_wht_percent'],
    $user,
    $id
);
```

Return the normalized values in `response.vendor` for both add and edit so the DataTable uses server-accepted data rather than raw form values. Change user-visible API messages from “ลูกค้า” to “Vendor”; keep route and internal variable names unchanged.

- [ ] **Step 5: Run PHP tests and lint**

Run:

```text
C:\xampp\php\php.exe -l customer_tax_defaults.php
C:\xampp\php\php.exe -l api\process_customer.php
C:\xampp\php\php.exe tests\vendor_tax_defaults_smoke.php
```

Expected: both lint commands report no syntax errors and the test prints `vendor tax defaults: PASS`.

- [ ] **Step 6: Commit the PHP domain/API change**

```bash
git add customer_tax_defaults.php api/process_customer.php tests/vendor_tax_defaults_smoke.php
git commit -m "feat: persist vendor tax defaults"
```

---

### Task 3: Vendor management UI and terminology

**Files:**

- Modify: `index.php`
- Modify: `assets/js/index.js`
- Modify: `assets/js/tutorial.js`
- Create: `tests/vendor_tax_form_smoke.php`

**Interfaces:**

- Consumes: normalized `response.vendor` from `api/process_customer.php`
- Produces: form field names matching the four `customers` columns

- [ ] **Step 1: Write the failing form/source smoke test**

Create `tests/vendor_tax_form_smoke.php` that reads the three UI sources and asserts:

```php
<?php
$index = file_get_contents(__DIR__ . '/../index.php');
$js = file_get_contents(__DIR__ . '/../assets/js/index.js');
$tutorial = file_get_contents(__DIR__ . '/../assets/js/tutorial.js');

$requiredFields = [
    'name="entity_type"',
    'name="default_vat_mode"',
    'name="default_wht_enabled"',
    'name="default_wht_percent"',
];

foreach ($requiredFields as $field) {
    if (strpos($index, $field) === false) {
        throw new RuntimeException("missing vendor field {$field}");
    }
}

foreach (['เพิ่ม Vendor', 'รายชื่อ Vendor', 'ชื่อ Vendor'] as $copy) {
    if (strpos($index . $js . $tutorial, $copy) === false) {
        throw new RuntimeException("missing Vendor copy: {$copy}");
    }
}

foreach (['entity_type', 'default_vat_mode', 'default_wht_enabled', 'default_wht_percent'] as $key) {
    if (strpos($js, $key) === false) {
        throw new RuntimeException("index.js does not preserve {$key}");
    }
}

echo "vendor tax form: PASS\n";
```

- [ ] **Step 2: Run the form test and verify it fails**

Run: `C:\xampp\php\php.exe tests\vendor_tax_form_smoke.php`

Expected: FAIL on the first missing field.

- [ ] **Step 3: Add Vendor tax controls to `index.php`**

Add the four controls after the Vendor name/tax id section. Use a select for entity type, a select for VAT mode, a checkbox for WHT, and a number input with `min="0"`, `max="100"`, `step="0.01"`, `value="3.00"`. Keep IDs identical to column names.

Add a badge renderer beside each row’s contact line using the values already returned by `SELECT * FROM customers`. Map display text exactly:

```php
$entityLabel = ($row['entity_type'] ?? 'juristic') === 'individual' ? 'บุคคลธรรมดา' : 'นิติบุคคล';
$vatLabels = ['none' => 'ไม่คิด VAT', 'exclusive' => 'VAT นอก', 'inclusive' => 'VAT ใน'];
$vatLabel = $vatLabels[$row['default_vat_mode'] ?? 'none'] ?? 'ไม่คิด VAT';
$whtLabel = !empty($row['default_wht_enabled'])
    ? 'WHT ' . number_format((float)$row['default_wht_percent'], 2) . '%'
    : 'ไม่หัก WHT';
```

Change only user-facing copy on this management screen from ลูกค้า to Vendor. Keep DOM IDs such as `customerForm`, `customerTable`, and PHP/JS function names unchanged.

- [ ] **Step 4: Update `assets/js/index.js` add/edit/reset behavior**

Build `rowData` from `res.vendor`, fill the four controls in `editCustomer(data)`, and reset them explicitly:

```javascript
$("#entity_type").val(data.entity_type || "juristic");
$("#default_vat_mode").val(data.default_vat_mode || "none");
$("#default_wht_enabled").prop("checked", Number(data.default_wht_enabled) === 1);
$("#default_wht_percent").val(Number(data.default_wht_percent ?? 3).toFixed(2));
```

On reset, restore `juristic`, `none`, unchecked, and `3.00`. Disable only the percentage input visually when WHT is unchecked, but do not remove its name so 3.00 remains stored for later activation. Replace confirmation/form-title/validation messages shown to users with Vendor wording.

- [ ] **Step 5: Update tutorial copy**

Change the tutorial titles/descriptions to `ตารางรายชื่อ Vendor` and `จัดการข้อมูล Vendor`. Do not change tutorial selectors.

- [ ] **Step 6: Run UI source test and PHP lint**

Run:

```text
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe tests\vendor_tax_form_smoke.php
```

Expected: lint passes and the test prints `vendor tax form: PASS`.

- [ ] **Step 7: Commit the Vendor UI change**

```bash
git add index.php assets/js/index.js assets/js/tutorial.js tests/vendor_tax_form_smoke.php
git commit -m "feat: add vendor tax settings UI"
```

---

### Task 4: Project default behavior module

**Files:**

- Create: `assets/js/vendor-tax-defaults.js`
- Create: `tests/vendor_tax_defaults_behavior.cjs`

**Interfaces:**

- Produces: `VendorTaxDefaults.fromDataset(dataset)`
- Produces: `VendorTaxDefaults.toControlState(defaults)`
- Produces: `VendorTaxDefaults.applyToProjectForm(document, defaults)`

- [ ] **Step 1: Write the failing Node behavior test**

Create `tests/vendor_tax_defaults_behavior.cjs`:

```javascript
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

console.log("vendor tax defaults behavior: PASS");
```

- [ ] **Step 2: Run the Node test and verify it fails**

Run: `node tests\vendor_tax_defaults_behavior.cjs`

Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement the UMD-style JavaScript module**

The module must export through `module.exports` under Node and assign `window.VendorTaxDefaults` in the browser. `applyToProjectForm` must set `checked` on `#vat_toggle`, `#vat_include_check`, `#wht_toggle`, set `#wht_percent`, update `#wht_percent_label`, and dispatch no synthetic change events. The caller will run `calculateNetValue()` exactly once after applying all controls.

Normalize invalid or absent values to `juristic`, `none`, `false`, `3`; clamp WHT percentage to 0–100.

- [ ] **Step 4: Run the Node behavior test**

Run: `node tests\vendor_tax_defaults_behavior.cjs`

Expected: `vendor tax defaults behavior: PASS`.

- [ ] **Step 5: Commit the behavior module**

```bash
git add assets/js/vendor-tax-defaults.js tests/vendor_tax_defaults_behavior.cjs
git commit -m "feat: add project vendor default behavior"
```

---

### Task 5: Wire Vendor defaults into project creation and harden project tax persistence

**Files:**

- Modify: `add_project.php`
- Modify: `edit_project.php`
- Modify: `api/save_project.php`
- Modify: `api/update_project.php`
- Modify: `tests/vendor_tax_form_smoke.php`

**Interfaces:**

- Consumes: the four `customers` tax-default columns
- Consumes: `window.VendorTaxDefaults`
- Produces: project POST fields `include_vat`, `vat_type_status`, `include_wht`, `wht_percent`

- [ ] **Step 1: Extend the failing form smoke test for project wiring**

Add assertions that `add_project.php` contains all four option datasets, loads `assets/js/vendor-tax-defaults.js`, and includes `name="wht_percent"`:

```php
$project = file_get_contents(__DIR__ . '/../add_project.php');
foreach ([
    'data-entity-type=',
    'data-vat-mode=',
    'data-wht-enabled=',
    'data-wht-percent=',
    'assets/js/vendor-tax-defaults.js',
    'name="wht_percent"',
] as $needle) {
    if (strpos($project, $needle) === false) {
        throw new RuntimeException("project form missing {$needle}");
    }
}
```

- [ ] **Step 2: Run the form smoke test and verify the new assertions fail**

Run: `C:\xampp\php\php.exe tests\vendor_tax_form_smoke.php`

Expected: FAIL because the project options do not expose datasets yet.

- [ ] **Step 3: Query and render Vendor defaults in `add_project.php`**

Change the customer query to select the four new columns. Rename the visible label and placeholder from Supplier to Vendor, leaving `name="customer_id"` intact. Render escaped datasets:

```php
<option
    value="<?= (int)$c['id'] ?>"
    data-entity-type="<?= htmlspecialchars($c['entity_type'], ENT_QUOTES, 'UTF-8') ?>"
    data-vat-mode="<?= htmlspecialchars($c['default_vat_mode'], ENT_QUOTES, 'UTF-8') ?>"
    data-wht-enabled="<?= (int)$c['default_wht_enabled'] ?>"
    data-wht-percent="<?= htmlspecialchars(number_format((float)$c['default_wht_percent'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
>
```

Add `<input type="hidden" name="wht_percent" id="wht_percent" value="3.00">` and wrap the visible WHT percent in `<span id="wht_percent_label">3</span>`.

- [ ] **Step 4: Apply defaults on explicit Vendor selection**

Load `assets/js/vendor-tax-defaults.js` before the project page’s inline logic. In the Select2 `change` handler, read the selected option dataset, call `applyToProjectForm`, then call `calculateNetValue()` once. Selecting blank applies module defaults.

Change `calculateNetValue()` from hard-coded `actualBase * 0.03` to:

```javascript
const whtPercent = Math.min(100, Math.max(0, parseFloat(document.getElementById('wht_percent')?.value) || 0));
whtAmount = isWht ? actualBase * (whtPercent / 100) : 0;
```

- [ ] **Step 5: Make project VAT state consistent on create/edit**

With `projects.has_vat` now nullable tinyint, both APIs must use:

```php
$hasVat = null;
if ($isVatEnabled) {
    $hasVat = $isVatIncluded ? 0 : 1;
}
```

Generate SQL through a prepared statement or a safely constructed `NULL`/integer expression; never quote `NULL`. In `edit_project.php`, check VAT enabled with `$pj['has_vat'] !== null` and VAT included with `(int)$pj['has_vat'] === 0`.

In `api/update_project.php`, stop trusting posted `total_vat_amount`, `total_wht_amount`, and `net_contract_value`. Recompute all three from `contract_value`, `include_vat`, `vat_type_status`, `include_wht`, and validated `wht_percent` using the same formulas as `api/save_project.php`.

- [ ] **Step 6: Add WHT percentage validation on project APIs**

After parsing, reject values outside 0–100 with HTTP 422 and a Thai error message. Keep 3.00 as the fallback when the field is absent for backward compatibility.

- [ ] **Step 7: Run behavior, form, schema, and syntax tests**

Run:

```text
node tests\vendor_tax_defaults_behavior.cjs
C:\xampp\php\php.exe tests\vendor_tax_form_smoke.php
C:\xampp\php\php.exe tests\vendor_tax_schema_smoke.php
C:\xampp\php\php.exe -l add_project.php
C:\xampp\php\php.exe -l edit_project.php
C:\xampp\php\php.exe -l api\save_project.php
C:\xampp\php\php.exe -l api\update_project.php
```

Expected: all tests print PASS and all four lint commands report no syntax errors.

- [ ] **Step 8: Commit project integration**

```bash
git add add_project.php edit_project.php api/save_project.php api/update_project.php tests/vendor_tax_form_smoke.php
git commit -m "feat: apply vendor tax defaults to projects"
```

---

### Task 6: End-to-end verification and regression pass

**Files:**

- Test: `tests/vendor_tax_schema_smoke.php`
- Test: `tests/vendor_tax_defaults_smoke.php`
- Test: `tests/vendor_tax_defaults_behavior.cjs`
- Test: `tests/vendor_tax_form_smoke.php`
- Verify: existing `tests/*.php` and `tests/*.cjs`

**Interfaces:**

- Consumes: completed migration, Vendor UI/API, and project default behavior
- Produces: verified handoff with no database fixtures left behind

- [ ] **Step 1: Run the focused automated suite**

```text
C:\xampp\php\php.exe tests\vendor_tax_schema_smoke.php
C:\xampp\php\php.exe tests\vendor_tax_defaults_smoke.php
C:\xampp\php\php.exe tests\vendor_tax_form_smoke.php
node tests\vendor_tax_defaults_behavior.cjs
```

Expected: four PASS lines and exit code 0 for every command.

- [ ] **Step 2: Run all existing PHP smoke tests**

From PowerShell:

```powershell
Get-ChildItem tests\*.php | ForEach-Object { & C:\xampp\php\php.exe $_.FullName; if ($LASTEXITCODE -ne 0) { throw "Failed: $($_.Name)" } }
```

Expected: no thrown failure and every existing smoke test exits 0.

- [ ] **Step 3: Run all existing Node behavior tests**

From PowerShell:

```powershell
Get-ChildItem tests\*.cjs | ForEach-Object { node $_.FullName; if ($LASTEXITCODE -ne 0) { throw "Failed: $($_.Name)" } }
```

Expected: no thrown failure and every behavior test exits 0.

- [ ] **Step 4: Perform browser QA without saving production-like records**

Use an existing expendable test Vendor or create a clearly named temporary fixture and delete it through a transaction/CLI cleanup after verification. Check these cases:

1. Juristic + VAT inclusive + WHT 3% → selecting Vendor checks VAT, VAT ใน, WHT and shows 3%
2. Juristic + VAT exclusive + WHT 5% → selecting Vendor checks VAT, unchecks VAT ใน, checks WHT and shows 5%
3. Individual + no VAT + no WHT → all tax toggles are off
4. After auto-fill, manually changing any toggle affects only the current project form
5. Editing the Vendor restores all four saved defaults
6. Editing an existing VAT-in project keeps VAT-in selected and does not convert it to VAT-out

For a 10,700 VAT-inclusive contract with WHT 3%, verify the project summary displays base 10,000.00, VAT 700.00, WHT 300.00, net 10,400.00.

- [ ] **Step 5: Review the final diff and database invariants**

Run:

```text
git diff --check
git status --short
C:\xampp\php\php.exe tests\vendor_tax_schema_smoke.php
```

Expected: no whitespace errors, only intended files changed, and schema smoke passes.

- [ ] **Step 6: Commit verification adjustments if any test-only correction was required**

```bash
git add tests
git commit -m "test: cover vendor tax default workflow"
```

Skip this commit when `git status --short` is clean after Step 5.

---

## Acceptance Criteria

- หน้าจัดการเดิมแสดงคำว่า Vendor แทนลูกค้าในหัวข้อ ฟอร์ม ข้อความยืนยัน และ tutorial ที่เกี่ยวข้อง
- เพิ่ม/แก้ไข Vendor สามารถเลือกนิติบุคคลหรือบุคคลธรรมดา, VAT mode, WHT on/off และ WHT percent ได้
- Vendor เดิมยังเปิดและใช้สร้างเอกสารได้โดยไม่ต้องแก้ข้อมูล
- เลือก Vendor ในหน้าสร้างโครงการแล้ว toggle ภาษีตรงกับ default ทันที
- ผู้ใช้ override ค่าในโครงการได้โดยไม่แก้ default ของ Vendor
- หน้าแก้ไขโครงการอ่าน VAT ใน/นอกตรงกับข้อมูลที่บันทึกไว้
- API สร้างและแก้ไขโครงการคำนวณ VAT/WHT/net ใหม่ฝั่ง server
- ไม่มีค่า `projects.has_vat` นอกเหนือจาก `NULL`, `0`, `1`
- focused tests, PHP smoke tests, Node behavior tests และ `git diff --check` ผ่านทั้งหมด
