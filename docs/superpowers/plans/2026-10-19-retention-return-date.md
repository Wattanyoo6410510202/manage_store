# Retention Return Date and Reminder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** เพิ่มวันครบกำหนดคืนเงินประกัน ตัวนับถอยหลัง การแจ้งเตือน SweetAlert ที่หน้า `projects.php` และสถานะยืนยันคืนเงินสำหรับงวดงานที่มี retention

**Architecture:** เก็บวันครบกำหนดและเวลาคืนเงินจริงใน `project_milestones` ผ่าน migration เดียว ใช้ helper PHP สำหรับตรวจสอบวันที่และใช้ API เป็น source of truth สำหรับการบันทึก/ยืนยัน การ์ดโครงการและ SweetAlert ใช้ข้อมูลที่ server render จาก query เดียวกับสิทธิ์การมองเห็นของ `projects.php` แล้วให้ JavaScript คำนวณจำนวนวันและกันแจ้งเตือนซ้ำวันละหนึ่งครั้งด้วย `localStorage`

**Tech Stack:** PHP 8, MariaDB/MySQL ผ่าน `mysqli`, JavaScript/jQuery, SweetAlert2 ที่โหลดอยู่ใน `header.php`, Tailwind CSS, PHP CLI smoke tests, Node.js behavior tests

**Spec:** [2026-10-19-retention-return-date-design.md](../specs/2026-10-19-retention-return-date-design.md)

## Global Constraints

- แจ้งเตือนเฉพาะเมื่อเปิด `projects.php`; ไม่เพิ่ม cron, worker, LINE หรือ external notification
- แจ้งเตือนรายการที่ครบกำหนดภายใน 5 วัน รวมรายการที่เลยกำหนดแล้ว
- แสดงชื่อโครงการและชื่องวดงานใน SweetAlert
- การ์ด grid แสดงวันที่คืนเงินประกันของ schedule ที่ค้างอยู่ใกล้ที่สุด
- ใช้ `retention_amount > 0` เป็นเงื่อนไขว่ามีเงินประกัน และไม่เชื่อยอดเงิน/วันที่จาก browser โดยไม่ validate ฝั่ง API
- ต้องใช้สิทธิ์เดิมของ `project_require_management_access()` และ visibility ของ `projects.php`
- ห้าม commit หรือสร้าง branch ตามคำสั่งของผู้ใช้

## Review Focus

- Retention เปิดแต่ไม่เลือกวันที่: API ต้องตอบ 422 และไม่บันทึกงวด — ทดสอบใน Task 2
- วันที่ผิดรูปแบบหรือวันที่ว่างจาก request ที่แก้ไขเอง: API ต้องปฏิเสธ — ทดสอบใน Task 2
- VAT ใน/นอกของงวดไม่ควรเปลี่ยนฐาน retention ที่แก้ไปก่อนหน้า — รัน regression tests เดิมใน Task 5
- โครงการมีหลายงวด: การ์ดและ alert ต้องเลือก/แสดงทุก schedule ใน alert แต่เลือกวันที่ค้างอยู่ที่ใกล้ที่สุดบนการ์ด — ทดสอบใน Task 3
- ยืนยันคืนเงินซ้ำ: API ต้องปฏิเสธและไม่แก้ยอดซ้ำ — ทดสอบใน Task 4

### Task 1: Schema and date/state helper

**Files:**
- Create: `add_retention_return_fields.sql`
- Create: `retention_return_schedule.php`
- Test: `tests/retention_return_schedule_smoke.php`

**Interfaces:**
- Produces `project_milestones.retention_return_due_date DATE NULL`
- Produces `project_milestones.retention_returned_at DATETIME NULL`
- Produces `retention_return_date_is_valid(?string $value): bool`

- [ ] **Step 1: Write the failing PHP helper tests**

  Add literal cases for a valid `Y-m-d`, invalid calendar date, empty value, and null. Assert the valid case returns `true` and all invalid cases return `false`. Add schema assertions that the two columns exist with the exact nullable types.

- [ ] **Step 2: Run the tests to verify they fail**

  Run: `C:\xampp\php\php.exe tests\retention_return_schedule_smoke.php`

  Expected: FAIL because the helper and new columns do not exist.

- [ ] **Step 3: Implement the helper and migration**

  Implement `retention_return_date_is_valid(?string $value): bool` using `DateTimeImmutable::createFromFormat('!Y-m-d', ...)` plus an exact round-trip comparison. Create an idempotent MariaDB-compatible migration using `ADD COLUMN IF NOT EXISTS`; do not alter existing retention or VAT columns.

- [ ] **Step 4: Run the tests to verify they pass**

  Run the same PHP test. Expected: PASS after the migration has been applied to the local database; if the local database has not been migrated, report that external prerequisite explicitly.

### Task 2: Create/edit milestone date input and persistence

**Files:**
- Modify: `partials/milestone_deduction_card.php`
- Modify: `add_milestone.php`
- Modify: `edit_milestone.php`
- Modify: `api/save_milestone.php`
- Modify: `api/update_milestone_full.php`
- Test: `tests/milestone_retention_return_form_smoke.php`

**Interfaces:**
- Consumes `retention_return_date_is_valid(?string): bool` from Task 1
- Form field: `retention_return_due_date`
- API persistence fields: `retention_return_due_date`, `retention_returned_at`

- [ ] **Step 1: Write the failing form/API smoke test**

  Assert the deduction card renders a date input named `retention_return_due_date`, both milestone pages expose the field and wire its required/enabled state to the retention checkbox, and both APIs reference the helper plus the two persistence columns.

- [ ] **Step 2: Run the test to verify it fails**

  Run: `C:\xampp\php\php.exe tests\milestone_retention_return_form_smoke.php`

  Expected: FAIL because the field and API persistence do not exist.

- [ ] **Step 3: Implement the form and server validation**

  Add the date control beside the retention percentage/note. On the client, toggle visibility and `required` with `use_deduction`. In both APIs, parse/validate `retention_return_due_date`; require it only when retention is enabled, set it to `NULL` when retention is disabled, and keep the current project-value retention calculation unchanged. When retention is enabled during edit, clear `retention_returned_at` so a newly scheduled deduction cannot inherit a returned state.

- [ ] **Step 4: Run the targeted tests and PHP lint**

  Run the form smoke test and:

  `C:\xampp\php\php.exe -l add_milestone.php`

  `C:\xampp\php\php.exe -l edit_milestone.php`

  `C:\xampp\php\php.exe -l api\save_milestone.php`

  `C:\xampp\php\php.exe -l api\update_milestone_full.php`

  Expected: PASS with no syntax errors.

### Task 3: Project card and five-day reminder on `projects.php`

**Files:**
- Create: `assets/js/retention_return_reminder.js`
- Modify: `projects.php`
- Test: `tests/retention_return_reminder_behavior.cjs`
- Test: `tests/projects_retention_card_smoke.php`

**Interfaces:**
- Server JSON payload: `window.retentionReturnAlerts`, each item `{ projectId, projectName, milestoneName, dueDate, daysRemaining }`
- JavaScript functions: `retentionReturnDaysRemaining(dueDate, today)`, `retentionReturnLabel(daysRemaining)`, `showRetentionReturnReminder(items, storage)`

- [ ] **Step 1: Write failing JS and PHP rendering tests**

  Test exact boundary values: 5 days → upcoming, 0 days → due today, -1 day → overdue. Test that the alert payload preserves project and milestone names and that the storage guard suppresses a second alert on the same date. The PHP smoke test must assert grid cards render the due-date/status marker and `projects.php` renders the JSON payload.

- [ ] **Step 2: Run tests to verify they fail**

  Run:

  `node tests\retention_return_reminder_behavior.cjs`

  `C:\xampp\php\php.exe tests\projects_retention_card_smoke.php`

  Expected: FAIL because the reminder module, query fields, and card marker do not exist.

- [ ] **Step 3: Implement query, card marker, and reminder module**

  Extend both project-list query branches with the nearest outstanding due date and latest returned timestamp for the card. Add a visibility-matched alert query for outstanding schedules due on or before `CURRENT_DATE + 5 days`, including project and milestone names. Render the card line under the project end date using the nearest schedule. Implement the browser-day storage key and one grouped SweetAlert on page load; fall back to showing on the current load if localStorage is unavailable. Keep table view unchanged.

- [ ] **Step 4: Run the behavior and rendering tests**

  Run both targeted tests. Expected: PASS, including the project name in each alert item.

### Task 4: Return confirmation state and detail-page display

**Files:**
- Modify: `detail_project.php`
- Modify: `api/update_refund_milestone.php`
- Test: `tests/retention_refund_state_smoke.php`

**Interfaces:**
- API action `refund_retention` sets `retention_returned_at = NOW()` and returns JSON `{ status, message }`
- Detail rows consume `retention_return_due_date` and `retention_returned_at`

- [ ] **Step 1: Write the failing state tests**

  Assert the endpoint checks for a positive retention amount, writes the returned timestamp, preserves the due date, and rejects an already returned row. Assert detail markup displays the planned date/countdown for outstanding rows and a returned label/date for returned rows.

- [ ] **Step 2: Run the tests to verify they fail**

  Run: `C:\xampp\php\php.exe tests\retention_refund_state_smoke.php`

  Expected: FAIL because returned state is not persisted or rendered.

- [ ] **Step 3: Implement idempotent confirmation and UI state**

  Restrict the existing refund button to paid, positive-retention, not-returned rows. Update the endpoint query/update to validate the row, clear the existing deduction amounts, set `retention_returned_at`, and keep `retention_return_due_date`. Render the planned date, overdue/countdown state, or returned date in the milestone detail row.

- [ ] **Step 4: Run the state test and PHP lint**

  Run the targeted smoke test and `C:\xampp\php\php.exe -l detail_project.php`, `C:\xampp\php\php.exe -l api\update_refund_milestone.php`. Expected: PASS and no syntax errors.

### Task 5: Full verification and handoff

**Files:**
- Modify only if verification reveals a regression in the files above

- [ ] **Step 1: Run all focused retention tests**

  Run the new PHP/Node tests plus the existing `tests/milestone_retention_php_smoke.php` and `tests/milestone_retention_behavior.cjs`.

- [ ] **Step 2: Run the repository test suite**

  Run all `tests/*.php` with `C:\xampp\php\php.exe` and all `tests/*.cjs` with `node`. Record unrelated pre-existing failures separately; do not silently omit them.

- [ ] **Step 3: Run final static checks**

  Run PHP lint on every modified PHP file and `git diff --check`. Confirm `git status --short` shows only the intended uncommitted files and no commit was created.
