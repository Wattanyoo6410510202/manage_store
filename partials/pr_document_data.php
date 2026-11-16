<?php
/**
 * โหลดข้อมูลใบขอซื้อสำหรับแสดงเอกสาร (ใช้ร่วม view_pr_new.php และ pr_sign.php)
 * ต้องมี $conn และ $id (int) ก่อน include — ได้ $data, $res_items, $display_list ฯลฯ
 */
require_once __DIR__ . '/../supplier_display.php';
$id = (int)$id;

$sql = "SELECT p.*, 
               s.company_name as supplier_name, s.address as sup_address, s.tax_id as sup_tax, s.phone as sup_phone,
               IF(p.is_internal = 1, u_creator.name, c.customer_name) as customer_name,
               IF(p.is_internal = 1, 'ภายในองค์กร', c.address) as cust_address,
               IF(p.is_internal = 1, '-', c.tax_id) as cust_tax,
               IF(p.is_internal = 1, '-', c.phone) as cust_phone,
               IF(p.is_internal = 1, '-', c.email) as cust_email,
               u_creator.name as creator_name,
               sig_creator.path as creator_signature,
               
               u_app0.name as app0_name, sig_app0.path as app0_sig,
               u_app1.name as app1_name, sig_app1.path as app1_sig,
               u_app2.name as app2_name, sig_app2.path as app2_sig,
               u_app3.name as app3_name, sig_app3.path as app3_sig,
               u_app4.name as app4_name, sig_app4.path as app4_sig,

               own.company_name as my_company, own.tax_id as my_tax, own.phone as my_phone, 
               own.email as my_email, own.address as my_address, own.logo_path,
                ec.name as expense_cat_name,
                bt.name as budget_type_name,
                obj.name as objective_name,
                st.store_name as store_name,
                pm.name as payment_method_name
         FROM pr p
         LEFT JOIN payment_methods pm ON p.payment_method = pm.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN stores st ON p.store_id = st.id
        LEFT JOIN customers c ON p.customer_id = c.id 
        LEFT JOIN suppliers own ON p.supplier_id = own.id 
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN signatures sig_creator ON u_creator.id = sig_creator.users_id
        
        LEFT JOIN users u_app0 ON p.approved_by_0 = u_app0.id
        LEFT JOIN signatures sig_app0 ON u_app0.id = sig_app0.users_id

        LEFT JOIN users u_app1 ON p.approved_by = u_app1.id
        LEFT JOIN signatures sig_app1 ON u_app1.id = sig_app1.users_id
        
        LEFT JOIN users u_app2 ON p.approved_by_1 = u_app2.id
        LEFT JOIN signatures sig_app2 ON u_app2.id = sig_app2.users_id
        
        LEFT JOIN users u_app3 ON p.approved_by_2 = u_app3.id
        LEFT JOIN signatures sig_app3 ON u_app3.id = sig_app3.users_id

        LEFT JOIN users u_app4 ON p.approved_by_3 = u_app4.id
        LEFT JOIN signatures sig_app4 ON u_app4.id = sig_app4.users_id

        LEFT JOIN expense_categories ec ON p.expense_cat_id = ec.id
        LEFT JOIN budget_types bt ON p.budget_type_id = bt.id
        LEFT JOIN pr_objectives obj ON p.objective_id = obj.id
        WHERE p.id = '$id' LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

$sql_items = "SELECT * FROM pr_items WHERE pr_id = '$id' ORDER BY id ASC";
$res_items = mysqli_query($conn, $sql_items);
$num_rows = mysqli_num_rows($res_items);

$logo_path = !empty($data['logo_path']) ? 'uploads/' . $data['logo_path'] : '';

$sum_discount_res = mysqli_query($conn, "SELECT SUM(item_discount) as total_discount FROM pr_items WHERE pr_id = '" . $data['id'] . "'");
$row_discount = mysqli_fetch_assoc($sum_discount_res);
$total_discount = $row_discount['total_discount'] ?? 0;

$dynamic_padding = ($num_rows <= 5) ? '5px 8px' : (($num_rows <= 10) ? '3px 10px' : '2px 12px');
$dynamic_font_size = ($num_rows <= 5) ? '14px' : (($num_rows <= 10) ? '13px' : '12px');

$budget_info = null;
$budget_project = null;
if ($data['budget_type_id']) {
    require_once __DIR__ . '/budget_projects_lib.php';
    $bt_id = (int)$data['budget_type_id'];
    $pr_id_int = (int)$id;
    if (!empty($data['budget_project_id'])) {
        // PR ของโครงการ: เทียบกับเงินที่โครงการกันไว้
        $bp_id = (int)$data['budget_project_id'];
        $budget_project = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT bp.id, bp.project_no, bp.name, bp.amount as total_budget, " . budget_project_pr_used_sql($pr_id_int) . " as spent_before
             FROM budget_projects bp WHERE bp.id = $bp_id"));
        $budget_info = $budget_project;
    } else {
        // PR นอกโครงการ: ยอดคงเหลือ = งบรวม − ที่กันให้โครงการ − PR นอกโครงการใบอื่น
        $budget_info = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT " . BUDGET_TOTAL_SQL . " as total_budget,
                    (" . BUDGET_TOTAL_SQL . " - " . budget_free_sql(0, $pr_id_int) . ") as spent_before
             FROM budget_types bt WHERE bt.id = $bt_id"));
    }
}

// ลายเซ็นที่เซ็นผ่านลิงก์ (pr_signers) แทนลายเซ็นในระบบของขั้นนั้น + ผู้ลงนามที่ไม่ผูกขั้น
$link_signed = [];
try {
    $ls_res = mysqli_query($conn, "SELECT s.stage, s.signature_path, s.signed_at, s.decision, u.name AS user_name
                                   FROM pr_signers s LEFT JOIN users u ON u.id = s.user_id
                                   WHERE s.pr_id = $id AND s.signed_at IS NOT NULL ORDER BY s.step ASC");
    $link_signed = mysqli_fetch_all($ls_res, MYSQLI_ASSOC);
} catch (Throwable $e) {
    $link_signed = []; // ยังไม่ได้รัน migration ของ pr_signers
}

$sig_src = fn($file) => !empty($file) ? 'uploads/signatures/' . $file : '';
$link_by_stage = [];
foreach ($link_signed as $ls) {
    if (!empty($ls['stage']) && $ls['decision'] === 'approved') $link_by_stage[$ls['stage']] = $ls;
}

// จัดการรายการที่จะนำมาแสดงในส่วนท้ายเอกสาร ('sig' = path รูปจากรากเว็บ)
$display_list = [];
$display_list[] = ['label' => 'ผู้จัดทำ', 'name' => $data['creator_name'], 'sig' => $sig_src($data['creator_signature']), 'date' => $data['created_at'], 'is_empty' => false];

$real_apps = [];
$apps = [
    ['stage' => 'approved_by_0', 'id' => $data['approved_by_0'] ?? null, 'name' => $data['app0_name'] ?? '', 'sig' => $data['app0_sig'] ?? '', 'date' => $data['approved_at_0'] ?? '', 'role' => 'หัวหน้างาน'],
    ['stage' => 'approved_by', 'id' => $data['approved_by'] ?? null, 'name' => $data['app1_name'] ?? '', 'sig' => $data['app1_sig'] ?? '', 'date' => $data['approved_at'] ?? '', 'role' => 'จัดซื้อ'],
    ['stage' => 'approved_by_1', 'id' => $data['approved_by_1'] ?? null, 'name' => $data['app2_name'] ?? '', 'sig' => $data['app2_sig'] ?? '', 'date' => $data['approved_at_1'] ?? '', 'role' => 'บัญชี'],
    ['stage' => 'approved_by_2', 'id' => $data['approved_by_2'] ?? null, 'name' => $data['app3_name'] ?? '', 'sig' => $data['app3_sig'] ?? '', 'date' => $data['approved_at_2'] ?? '', 'role' => 'Mgr'],
    ['stage' => 'approved_by_3', 'id' => $data['approved_by_3'] ?? null, 'name' => $data['app4_name'] ?? '', 'sig' => $data['app4_sig'] ?? '', 'date' => $data['approved_at_3'] ?? '', 'role' => 'Mgr2']
];

foreach($apps as $app) {
    if(!empty($app['id'])) {
        // เซ็นผ่านลิงก์ = ใช้ลายเซ็นที่วาดตอนเซ็น
        $sig = !empty($link_by_stage[$app['stage']]['signature_path']) ? $link_by_stage[$app['stage']]['signature_path'] : $sig_src($app['sig']);
        $real_apps[] = ['label' => 'ผู้อนุมัติ (' . $app['role'] . ')', 'name' => $app['name'], 'sig' => $sig, 'date' => $app['date'], 'is_empty' => false];
    }
}
foreach ($link_signed as $ls) {
    if (empty($ls['stage']) && $ls['decision'] === 'approved') {
        $real_apps[] = ['label' => 'ผู้ลงนาม', 'name' => $ls['user_name'], 'sig' => $ls['signature_path'], 'date' => $ls['signed_at'], 'is_empty' => false];
    }
}
foreach($real_apps as $ra) { $display_list[] = $ra; }

if(count($real_apps) == 0) {
    $display_list[] = ['label' => 'แผนกบัญชี / จัดซื้อ', 'name' => '', 'sig' => '', 'date' => '', 'is_empty' => true];
    $display_list[] = ['label' => 'ผู้อนุมัติ', 'name' => '', 'sig' => '', 'date' => '', 'is_empty' => true];
} elseif(count($real_apps) == 1) {
    $display_list[] = ['label' => 'ผู้อนุมัติ', 'name' => '', 'sig' => '', 'date' => '', 'is_empty' => true];
}

$count_total = count($display_list);
$col_width = ($count_total <= 3) ? '33.33%' : '50%';
if (!function_exists('BahtText')) {
function BahtText($amount) {
    $amount_number = number_format($amount, 2, '.', '');
    $pt = strpos($amount_number, '.');
    $number = ($pt === false) ? $amount_number : substr($amount_number, 0, $pt);
    $fraction = ($pt === false) ? "00" : substr($amount_number, $pt + 1);
    $ret = "";
    $baht = ReadNumber($number);
    if ($baht != "") $ret .= $baht . "บาท";
    if ($fraction == "00") { $ret .= "ถ้วน"; } else { $ret .= ReadNumber($fraction) . "สตางค์"; }
    return $ret;
}
function ReadNumber($number) {
    $position_call = array("แสน", "หมื่น", "พัน", "ร้อย", "สิบ", "");
    $number_call = array("", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
    $number = intval($number);
    if ($number == 0) return "";
    $ret = "";
    if ($number > 1000000) { $ret .= ReadNumber(intval($number / 1000000)) . "ล้าน"; $number = $number % 1000000; }
    $t = sprintf("%06d", $number);
    $adjective = false;
    for ($i = 0; $i < 6; $i++) {
        $n = substr($t, $i, 1);
        if ($n != "0") {
            if ($i == 4 && $n == "1") $ret .= "สิบ";
            elseif ($i == 4 && $n == "2") $ret .= "ยี่สิบ";
            elseif ($i == 5 && $n == "1" && $adjective) $ret .= "เอ็ด";
            else $ret .= $number_call[$n] . $position_call[$i];
            $adjective = true;
        } else { $adjective = false; }
    }
    return $ret;
}
}
