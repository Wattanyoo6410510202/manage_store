<?php
require_once 'config.php';
include('header.php');
?>

<div class="space-y-8">

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- ซ้าย: รายการแผนก (3/4 ของจอ) -->
        <div class="lg:col-span-3 space-y-6">
            <?php
            $services = [
                'HR' => ['icon' => 'fas fa-users', 'color' => 'blue', 'title' => 'บุคคล', 'items' => ['ลง OT พนักงาน', 'ยื่นใบลาออนไลน์', 'ตารางการทำงาน', 'เบิกสวัสดิการ', 'ประวัติพนักงาน', 'อัปเดตข้อมูลส่วนตัว']],
                'PURCHASING' => ['icon' => 'fas fa-shopping-cart', 'color' => 'indigo', 'title' => 'จัดซื้อ', 'items' => ['สร้างใบขอซื้อ (PR)', 'ติดตามสถานะอนุมัติ', 'รายงานจัดซื้อ', 'เปรียบเทียบราคา', 'เช็คสถานะ PO']],
                'ACCOUNTING' => ['icon' => 'fas fa-file-invoice-dollar', 'color' => 'emerald', 'title' => 'บัญชีการเงิน', 'items' => ['ใบแจ้งหนี้', 'ตรวจสอบการชำระเงิน', 'รายงานบัญชี', 'เบิกค่าใช้จ่าย']],
                'CATERING' => ['icon' => 'fas fa-utensils', 'color' => 'orange', 'title' => 'จัดเลี้ยง', 'items' => ['จองอาหาร/เครื่องดื่ม', 'รายการเมนู', 'ตารางการจัดเลี้ยง']],
                'MAINTENANCE' => ['icon' => 'fas fa-tools', 'color' => 'amber', 'title' => 'ช่าง', 'items' => ['แจ้งซ่อมคอมพิวเตอร์', 'แจ้งซ่อมแอร์/อาคาร', 'แจ้งปัญหา Network', 'เบิกวัสดุอุปกรณ์']],
                'SALES' => ['icon' => 'fas fa-chart-line', 'color' => 'rose', 'title' => 'พนักงานขาย', 'items' => ['บันทึกยอดขาย', 'จัดการลูกค้า', 'ใบเสนอราคา']],
                'MARKETING' => ['icon' => 'fas fa-bullhorn', 'color' => 'purple', 'title' => 'การตลาด', 'items' => ['โปรโมชั่น', 'แคมเปญ', 'สื่อโฆษณา']],
                'RESTAURANT' => ['icon' => 'fas fa-store', 'color' => 'red', 'title' => 'ร้านอาหาร', 'items' => ['จัดการโต๊ะ', 'สั่งอาหาร', 'สต็อกสินค้า']]
            ];

            foreach ($services as $key => $s): ?>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <h3
                        class="font-bold text-<?= $s['color'] ?>-600 mb-4 flex items-center gap-2 border-b border-<?= $s['color'] ?>-50 pb-2">
                        <i class="<?= $s['icon'] ?>"></i> <?= $s['title'] ?>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                        <?php foreach ($s['items'] as $item): ?>
                            <?php
                            $link = "#";
                            if ($item === 'ยื่นใบลาออนไลน์')
                                $link = "e-service/HR/leave_request_public.php";
                            ?>
                            <a href="<?= $link ?>"
                                class="block p-3 bg-slate-50 hover:bg-<?= $s['color'] ?>-50 rounded-lg text-xs font-bold text-slate-600 hover:text-<?= $s['color'] ?>-700 transition">
                                <i class="fas fa-chevron-right mr-2 opacity-50"></i> <?= $item ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ขวา: รายการสำคัญ (1/4 ของจอ) -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm sticky top-6">
                <h3 class="font-black text-slate-800 mb-4 border-b pb-2 flex items-center gap-2">
                    <i class="fas fa-bullhorn text-amber-500"></i> ประกาศบริษัท
                </h3>
                <div class="space-y-4 text-sm text-slate-600">
                    <?php
                    $announcements = [
                        ['title' => 'ปิดปรับปรุงระบบชั่วคราว', 'date' => '26 เม.ย. 69', 'desc' => 'ระบบจะปิดปรับปรุงเวลา 22:00 - 02:00 น. เพื่ออัปเดตฟีเจอร์ใหม่'],
                        ['title' => 'แจ้งวันหยุดนักขัตฤกษ์', 'date' => '01 พ.ค. 69', 'desc' => 'เนื่องในวันแรงงานแห่งชาติ บริษัทฯ ปิดทำการ 1 วัน'],
                        ['title' => 'นโยบายการเบิกจ่ายใหม่', 'date' => '20 เม.ย. 69', 'desc' => 'ขอแจ้งปรับเปลี่ยนระเบียบการเบิกค่าใช้จ่ายประจำเดือน'],
                        ['title' => 'ต้อนรับพนักงานใหม่', 'date' => '15 เม.ย. 69', 'desc' => 'ยินดีต้อนรับพนักงานใหม่เข้าสู่ทีมฝ่ายจัดซื้อและฝ่ายบัญชี'],
                        ['title' => 'อบรมความปลอดภัย', 'date' => '10 เม.ย. 69', 'desc' => 'เชิญพนักงานทุกท่านเข้าร่วมอบรมความปลอดภัยประจำปี']
                    ];
                    foreach ($announcements as $ann): ?>
                        <div class="border-l-2 border-amber-400 pl-3 py-1">
                            <p class="font-bold text-slate-800"><?= $ann['title'] ?></p>
                            <p class="text-[10px] text-slate-400 uppercase"><?= $ann['date'] ?></p>
                            <p class="text-xs text-slate-500 mt-1"><?= $ann['desc'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>