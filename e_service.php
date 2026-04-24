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
                'HR' => ['icon' => 'fas fa-users', 'color' => 'blue', 'title' => 'งานบุคคล (HR)', 'items' => ['ลงเวลาทำงาน', 'ยื่นใบลาออนไลน์', 'ตรวจสอบวันลา', 'เบิกสวัสดิการ', 'ประวัติพนักงาน', 'อัปเดตข้อมูลส่วนตัว']],
                'PROCUREMENT' => ['icon' => 'fas fa-shopping-cart', 'color' => 'indigo', 'title' => 'จัดซื้อ & การเงิน', 'items' => ['สร้างใบขอซื้อ (PR)', 'ติดตามสถานะอนุมัติ', 'ใบแจ้งหนี้', 'รายงานจัดซื้อ', 'เปรียบเทียบราคา', 'เช็คสถานะ PO']],
                'IT' => ['icon' => 'fas fa-tools', 'color' => 'amber', 'title' => 'ไอที & แจ้งซ่อม', 'items' => ['แจ้งซ่อมคอมพิวเตอร์', 'แจ้งปัญหา Network', 'แจ้งซ่อมแอร์/อาคาร', 'เบิกวัสดุสำนักงาน', 'จองห้องประชุม', 'จองรถบริษัท']],
                'DOCS' => ['icon' => 'fas fa-file-pdf', 'color' => 'rose', 'title' => 'คลังเอกสาร', 'items' => ['ฟอร์มใบลา', 'ระเบียบพนักงาน', 'แผนผังองค์กร', 'คู่มือปฏิบัติงาน', 'มาตรฐานงานตรวจรับ', 'นโยบายความปลอดภัย']]
            ];

            foreach ($services as $key => $s): ?>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <h3
                        class="font-bold text-<?= $s['color'] ?>-600 mb-4 flex items-center gap-2 border-b border-<?= $s['color'] ?>-50 pb-2">
                        <i class="<?= $s['icon'] ?>"></i> <?= $s['title'] ?>
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($s['items'] as $item): ?>
                            <a href="#"
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