<?php
// เนื้อเอกสารใบขอซื้อ A4 (ต้อง include partials/pr_document_data.php ก่อน)
?>
<style>
    :root {
        --row-padding: <?= $dynamic_padding ?>;
        --item-font-size: <?= $dynamic_font_size ?>;
        --primary-color: #2563eb;
        --border-color: #f1f5f9;
    }
    .table-items tbody td { padding: var(--row-padding) !important; font-size: var(--item-font-size); }
    .page-container { background: white; padding: 40px 40px 20px 40px; min-height: 297mm; margin: 0 auto; width: 210mm; }
    
    .sig-box { border-bottom: 1px dashed #cbd5e1; width: 160px; height: 60px; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; }
    .sig-box img { max-height: 50px; object-fit: contain; }
    
    .sig-wrapper { text-align: center; margin-bottom: 10px; padding: 0 10px; }
    .sig-title { font-weight: bold; font-size: 12px; margin: 0; color: #334155; }
    .sig-name { font-size: 11px; color: #64748b; margin: 4px 0 0; }
    .sig-date { font-size: 9px; color: #94a3b8; margin: 2px 0 0; }
    
    /* การตั้งค่าการปริ้น */
    @page { 
        size: A4; 
        margin: 8mm; /* ลดขอบกระดาษโดยรวมเหลือ 8 มิลลิเมตร */
    }
    @media print {
        body { margin: 0; padding: 0; background: none; }
        .no-print { display: none !important; }
        .page-container { 
            padding: 5mm !important; /* ลดขอบด้านในขณะสั่งพิมพ์ */
            width: 100% !important; 
            margin: 0 !important;
            min-height: 0 !important;
            box-shadow: none !important;
            border: none !important;
        }
        .floating-toolbar { display: none !important; }
    }
</style>

<link rel="stylesheet" href="assets/css/style_quotation.css">
<div id="quotation-content" class="page-container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" style="width: 65px; height: 65px; object-fit: contain;">
            <?php elseif (!empty($data['my_company']) && $data['my_company'] !== 'ไม่ระบุซัพพลายเออร์'): ?>
                <div style="width: 65px; height: 65px; background: #0f172a; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                    <?= mb_substr(supplier_display_name($data['my_company']), 0, 1, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['my_company']) && $data['my_company'] !== 'ไม่ระบุ'): ?>
                <div style="font-size: 10px; line-height: 1.4;">
                    <h1 style="margin: 0 0 2px; font-size: 16px; color: #0f172a;"><?= htmlspecialchars(supplier_display_name($data['my_company']), ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if (!empty($data['my_address'])): ?><p style="margin: 0; color: #64748b;"><?= $data['my_address'] ?></p><?php endif; ?>
                    <p style="margin: 1px 0 0; color: #64748b;">
                        <?php if (!empty($data['my_tax'])): ?><b>Tax ID:</b> <?= $data['my_tax'] ?><?php endif; ?>
                        <?php if (!empty($data['my_tax']) && !empty($data['my_phone'])): ?> | <?php endif; ?>
                        <?php if (!empty($data['my_phone'])): ?><b>Tel:</b> <?= $data['my_phone'] ?><?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 24px; color: var(--primary-color); font-weight: 900;">ใบขอซื้อ</h2>
            <p style="margin: 0; font-size: 9px; letter-spacing: 2px; color: #94a3b8; text-transform: uppercase;">PURCHASE REQUISITION</p>
        </div>
    </div>

    <!-- Info Section -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; gap: 10px;">
        <div style="flex: 1; border: 1px solid #f1f5f9; border-radius: 6px; padding: 8px; background: #f8fafc; ">
            <h3 style="margin: 0; font-size: 13px; color: #0f172a;"><?= $data['customer_name'] ?></h3>
            <div style="margin-top: 5px; font-size: 10px; line-height: 1.5;">                            <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ร้านค้า:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['store_name'] ?? '-') ?></span></div>
                                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ประเภทค่าใช้จ่าย:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['expense_cat_name'] ?? '-') ?></span></div>
                                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ประเภทงบประมาณ:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['budget_type_name'] ?? '-') ?></span></div>
                                <?php if ($budget_project): ?>
                                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">โครงการ:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars(trim(($budget_project['project_no'] ? $budget_project['project_no'] . ' — ' : '') . $budget_project['name'])) ?></span></div>
                                <?php endif; ?>
                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">วัตถุประสงค์:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['objective_name'] ?? '-') ?></span></div>
                <div><b style="color: #64748b; min-width: 90px; display: inline-block;">ความคาดหวัง:</b> <span style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($data['expectation'] ?? '-') ?></span></div>
            </div>
        </div>
        
        <!-- Budget Status Box -->
        <?php if ($budget_info): ?>
            <?php 
            $remaining = $budget_info['total_budget'] - $budget_info['spent_before'];
            $is_over = ($data['grand_total'] > $remaining);
            ?>
            <div style="width: 180px; border: 1px solid <?= $is_over ? '#fee2e2' : '#dcfce7' ?>; border-radius: 6px; padding: 8px; background: <?= $is_over ? '#fef2f2' : '#f0fdf4' ?>;">
                <h4 style="margin: 0 0 5px; font-size: 11px; color: <?= $is_over ? '#991b1b' : '#166534' ?>; border-bottom: 1px solid <?= $is_over ? '#fecaca' : '#bbf7d0' ?>; padding-bottom: 3px;">สถานะงบประมาณ</h4>
                <div style="font-size: 10px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;"><?= $budget_project ? 'งบโครงการ:' : 'งบทั้งหมด:' ?></span>
                        <span style="font-weight: bold;"><?= number_format($budget_info['total_budget'], 2) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 2px;">
                        <span style="color: #64748b;">คงเหลือ:</span>
                        <span style="font-weight: bold; color: <?= $is_over ? '#ef4444' : '#10b981' ?>;"><?= number_format($remaining, 2) ?></span>
                    </div>
                    <?php if ($is_over): ?>
                        <div style="margin-top: 5px; color: #ef4444; font-weight: bold; font-size: 9px; text-align: center; background: white; border-radius: 4px; padding: 2px; border: 1px solid #fecaca;">
                            <i class="fas fa-exclamation-triangle"></i> ยอดเกินงบประมาณ!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="width: 240px; font-size: 11px;">
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">เลขที่ / No.</span> <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['doc_no']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">วันที่ / Date</span> <span style="color: #0f172a; font-weight: bold;"><?= date('d/m/Y', strtotime($data['doc_date'])) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">ความสำคัญ</span> <span style="color: #0f172a; font-weight: bold;"><?= htmlspecialchars($data['priority'] ?? 'ปานกลาง') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">วันที่ต้องการสินค้า</span> <span style="color: #0f172a; font-weight: bold;"><?= (!empty($data['due_date']) && $data['due_date'] !== '0000-00-00') ? date('d/m/Y', strtotime($data['due_date'])) : '-' ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">เลขที่อ้างอิง</span> <span style="color: #0f172a; font-weight: bold;"><?= $data['reference_no'] ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f1f5f9;">
                <span style="color: #64748b;">วิธีการชำระ</span> <span style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data['payment_method_name'] ?? '-') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0;">
                <span style="color: #64748b;">รับของ</span>
                <span style="font-weight: bold; color: <?= $data['received_status'] === 'received' ? '#10b981' : ($data['received_status'] === 'partial' ? '#f59e0b' : '#94a3b8') ?>;">
                    <?php if ($data['received_status'] === 'received'): ?>
                        <i class="fas fa-check-circle"></i> รับแล้ว <?= $data['received_at'] ? date('d/m/Y', strtotime($data['received_at'])) : '' ?>
                    <?php elseif ($data['received_status'] === 'partial'): ?>
                        <i class="fas fa-minus-circle"></i> รับบางส่วน
                    <?php else: ?>
                        <i class="far fa-circle"></i> ยังไม่ได้รับ
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($data['payment_slip'])): ?>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; border-top: 1px solid #f1f5f9; margin-top: 4px;">
                <span style="color: #64748b;">สลิปชำระเงิน</span>
                <a href="uploads/payments/<?= htmlspecialchars($data['payment_slip']) ?>" target="_blank" style="color: #2563eb; font-weight: bold; text-decoration: underline;">
                    <i class="fas fa-receipt"></i> ดูสลิป
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <div class="item-section">
        <table class="table-items">
            <thead>
                <tr>
                    <th width="3%">#</th>
                    <th align="left">รายการ / Description</th>
                    <th width="5%" align="center">จำนวน</th>
                    <th width="8%" align="right">หน่วย</th>
                    <th width="12%" align="right">ราคา</th>
                    <?php if ($total_discount > 0): ?><th width="10%" align="right">ส่วนลด</th><?php endif; ?>
                    <th width="12%" align="right">ยอดรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                mysqli_data_seek($res_items, 0);
                while ($item = mysqli_fetch_assoc($res_items)):
                    ?>
                    <tr>
                        <td align="center" style="color: #64748b;"><?= $count++ ?></td>
                        <td style="font-weight: 400; vertical-align: top; line-height: 1.4; color: #334155; max-width: 300px; word-break: break-word; overflow-wrap: break-word;">
                            <?= nl2br(htmlspecialchars($item['item_desc'])) ?>
                        </td>
                        <td align="center"><?= number_format($item['item_qty'], 0) ?></td>
                        <td align="right"><?= htmlspecialchars($item['item_unit']) ?></td>
                        <td align="right"><?= number_format($item['item_price'], 2) ?></td>
                        <?php if ($total_discount > 0): ?><td align="right"><?= number_format($item['item_discount'], 2) ?></td><?php endif; ?>
                        <td align="right" style="font-weight: 600;"><?= number_format($item['item_total'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer Summary & Signatures -->
    <div class="doc-footer" style="margin-top: 10px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div style="width: 55%; padding-right: 20px;">
                <p style="font-size: 10px; font-weight: bold; text-decoration: underline; margin-bottom: 3px;">หมายเหตุ:</p>
                <p style="font-size: 10px; color: #64748b; line-height: 1.4;"><?= nl2br($data['notes']) ?: '-' ?></p>
            </div>
            <div style="width: 40%;">
                <div style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #f1f5f9;">
                    <table width="100%" style="font-size: 11px;">
                        <tr><td style="padding-bottom: 3px;">รวมเป็นเงิน</td><td align="right" style="padding-bottom: 3px;"><?= number_format($data['subtotal'], 2) ?></td></tr>
                        <tr><td style="padding-bottom: 3px; ">ภาษีมูลค่าเพิ่ม <?= (float) ($data['vat_percent'] ?? 7) ?>%</td><td align="right" style="padding-bottom: 3px; font-weight: 600;"><?= number_format($data['vat'], 2) ?></td></tr>
                        <tr><td style="padding-bottom: 3px;">หัก ณ ที่จ่าย <?= number_format($data['wht_percent'], 0) ?>%</td><td align="right" style="padding-bottom: 3px;"><?= number_format($data['wht_amount'], 2) ?></td></tr>
                        <tr style="font-size: 13px; font-weight: 900; color: var(--primary-color);"><td style="padding-top: 5px; border-top: 1px solid #e2e8f0;">ยอดสุทธิ</td><td align="right" style="padding-top: 5px; border-top: 1px solid #e2e8f0;"><?= number_format($data['grand_total'], 2) ?></td></tr>
                        <tr><td colspan="2" align="right" style="padding-top: 5px; font-size: 9px; color: #64748b; font-style: italic;">( <?= BahtText($data['grand_total']) ?> )</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div style="display: flex; flex-wrap: wrap; gap: 5px 0; margin-top: 10px;">
            <?php foreach($display_list as $item): ?>
                <div style="width: <?= $col_width ?>;" class="sig-wrapper">
                    <div class="sig-box">
                        <?php if (!$item['is_empty'] && !empty($item['sig'])): ?>
                            <img src="<?= htmlspecialchars($item['sig'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <p class="sig-title"><?= $item['label'] ?></p>
                    <p class="sig-name">(<?= (!$item['is_empty'] && !empty($item['name'])) ? $item['name'] : '..................................' ?>)</p>
                    <?php if (!$item['is_empty'] && !empty($item['date'])): ?>
                        <p class="sig-date">วันที่: <?= date('d/m/Y', strtotime($item['date'])) ?></p>
                    <?php else: ?>
                        <p class="sig-date">วันที่: ....................</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
