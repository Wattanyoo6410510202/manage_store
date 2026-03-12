<?php
session_start();

$product_name = $_POST['product_name'] ?? 'yaris 2018';
$selected_json = $_POST['selected_data'] ?? '[]';
$data = json_decode($selected_json, true);

// Logic ค้นหาร้านที่ราคาถูกที่สุดอัตโนมัติ
$min_price = null;
$cheapest_supplier = "ยังไม่มีข้อมูล";
foreach ($data as $item) {
    $current_price = floatval($item['price']);
    if ($min_price === null || ($current_price < $min_price && $current_price > 0)) {
        $min_price = $current_price;
        $cheapest_supplier = $item['supplier'];
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap');

    body {
        background-color: #f1f5f9;
        margin: 0;
        padding: 0;
        font-family: 'Sarabun', sans-serif;
        color: #1e293b;
    }

    /* Toolbar */
    .floating-toolbar {
        position: fixed;
        top: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        padding: 10px 20px;
        border-radius: 15px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }

    .btn-tool {
        border: none;
        padding: 8px 16px;
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 13px;
        transition: 0.2s;
    }

    .btn-print {
        background: #0f172a;
        color: white;
    }

    .btn-word {
        background: #2563eb;
        color: white;
    }

    .btn-excel {
        background: #10b981;
        color: white;
    }

    .btn-tool:hover {
        transform: translateY(-2px);
        filter: brightness(1.1);
    }

    /* Paper */
    .paper-a4 {
        background: white;
        width: 210mm;
        min-height: 297mm;
        padding: 15mm 20mm;
        margin: 30px auto;
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.05);
        position: relative;
    }

    /* Editable Text Styles */
    .dotted-line {
        border: none;
        border-bottom: 1px dotted #64748b;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        background: transparent;
        outline: none;
        padding: 0 5px;
    }

    .section-header p {
        margin: 6px 0;
        font-size: 13px;
        color: #475569;
    }

    .doc-title-box {
        border-bottom: 2px solid #0f172a;
        padding-bottom: 10px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    /* Table */
    .table-compare {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        border: 1px solid #000;
    }

    .table-compare th {
        background: #f8fafc;
        border: 1px solid #000;
        padding: 8px;
        font-size: 12px;
        font-weight: 800;
    }

    .table-compare td {
        border: 1px solid #000;
        padding: 8px;
        vertical-align: top;
    }

    .input-text {
        width: 100%;
        border: none;
        outline: none;
        font-family: inherit;
        font-size: 12px;
        background: transparent;
        font-weight: 600;
    }

    .input-note {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        font-family: inherit;
        font-size: 11px;
        resize: none;
        overflow: hidden;
        display: block;
        line-height: 1.5;
        color: #475569;
    }

    /* Summary Section */
    .summary-section {
        margin-top: 25px;
        padding: 15px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
    }

    .summary-section h3 {
        font-size: 14px;
        margin: 0 0 10px 0;
        text-decoration: underline;
    }

    .summary-row {
        margin-bottom: 8px;
        display: flex;
        align-items: baseline;
        font-size: 13px;
    }

    .summary-label {
        font-weight: 700;
        min-width: 240px;
    }

    /* Signatures */
    .signature-section {
        margin-top: 40px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        text-align: center;
    }

    .sig-box {
        margin-top: 35px;
        border-bottom: 1px solid #000;
        width: 80%;
        margin-left: auto;
        margin-right: auto;
    }

    .sig-text {
        font-size: 12px;
        font-weight: 700;
        margin-top: 8px;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 0;
        }

        .no-print {
            display: none !important;
        }

        .paper-a4 {
            margin: 0;
            border: none;
            box-shadow: none;
            width: 210mm;
            padding: 15mm;
        }

        .dotted-line {
            border-bottom: 1px dotted #000;
        }
    }
</style>

<div class="floating-toolbar no-print">
    <button onclick="window.print()" class="btn-tool btn-print"><i class="fas fa-print"></i> พิมพ์ / PDF</button>
    <button onclick="exportWord()" class="btn-tool btn-word"><i class="fas fa-file-word"></i> Word</button>
    <button onclick="exportExcel()" class="btn-tool btn-excel"><i class="fas fa-file-excel"></i> Excel</button>
</div>

<div class="paper-a4" id="printableArea">
    <div class="doc-header-container" style="font-family: 'Sarabun', sans-serif; color: #1e293b;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            <div style="flex: 1;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px;">SHotel
                    Hatyai</h2>
                <p style="margin: 0; font-size: 11px; color: #475569; line-height: 1.3;">
                    220 ถนนประชาธิปัตย์ ตำบลหาดใหญ่ อำเภอหาดใหญ่ จังหวัดสงขลา 90110
                </p>
                <div style="font-size: 10px; color: #64748b; display: flex; gap: 10px; margin-top: 2px;">
                    <span><b>Tax ID:</b> 0905566000446</span>
                    <span style="color: #cbd5e1;">|</span>
                    <span><b>Tel:</b> 088-899-0743</span>
                </div>
            </div>

            <div style="text-align: right; font-size: 11px;">
                <div style="padding-left: 5px;">
                    <h1
                        style="margin: 0; font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase;">
                        รายงานการเปรียบเทียบราคา</h1>
                    <p style="margin: 0; font-size: 9px; font-weight: 700; color: #64748b; letter-spacing: 0.5px;">
                        COMPARATIVE QUOTATION ANALYSIS</p>
                </div>
                <strong>วันที่ออกเอกสาร:</strong><br>
                <input type="text" id="docDate"
                    style="border:none; border-bottom: 1px dotted #94a3b8; width: 85px; text-align: center; outline: none; font-weight: bold; background: transparent;"
                    value="<?= date('d/m/Y') ?>">
            </div>
        </div>

        <div
            style="display: flex; justify-content: space-between; align-items: center; border-top: 2px solid  #0f172a; padding: 2px 0; background-color: #f8fafc;">
        </div>
    </div>

    <div class="section-header">
        <p><strong>ชื่อรายการ / โครงการ:</strong> <input type="text" id="projName" class="dotted-line"
                style="min-width: 450px;" value="<?= htmlspecialchars($product_name) ?>"></p>
        <p><strong>ผู้จัดทำ:</strong> <input type="text" id="author" class="dotted-line" style="min-width: 300px;"></p>
        <p><strong>รายการสินค้า:</strong> <input type="text" id="prodDetail" class="dotted-line"
                style="min-width: 535px;"></p>
        <p><strong>จำนวน:</strong> <input type="text" id="qty" class="dotted-line"
                style="min-width: 80px; text-align: center;"></p>
    </div>

    <table class="table-compare" id="compareTable">
        <thead>

        <tbody>
            <tr>
                <td class="row-label">ชื่อผู้ขาย / ร้านค้า</td>
                <?php foreach ($data as $item): ?>
                    <td>
                        <input type="text" class="input-text shop-name text-center font-bold"
                            value="<?= htmlspecialchars($item['supplier']) ?>">
                    </td>
                <?php endforeach; ?>
            </tr>

            <tr>
                <td class="row-label">รายละเอียด / เงื่อนไข</td>
                <?php foreach ($data as $item): ?>
                    <td>
                        <textarea class="input-note shop-note"
                            oninput="autoExpand(this)"><?= htmlspecialchars($item['note']) ?></textarea>
                    </td>
                <?php endforeach; ?>
            </tr>

            <tr>
                <td class="row-label">ราคาต่อหน่วย (บาท)</td>
                <?php foreach ($data as $item): ?>
                    <td>
                        <input type="text" class="input-text shop-price text-center"
                            style="font-weight: 800; color: #1e293b; font-size: 14px;"
                            value="<?= number_format($item['price'], 2) ?>">
                    </td>
                <?php endforeach; ?>
            </tr>

            <tr class="no-print-bg">
                <td class="row-label">เลือกร้านนี้</td>
                <?php foreach ($data as $index => $item): ?>
                    <td style="text-align: center; vertical-align: middle; background: #f8fafc;">
                        <label class="select-container">
                            <input type="checkbox" class="select-shop"
                                onchange="syncSelectedShop(this, '<?= htmlspecialchars($item['supplier']) ?>')"
                                style="width: 20px; height: 20px; cursor: pointer;">
                        </label>
                    </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <style>
        /* ปรับแต่งสไตล์ตารางแบบ Column Based */
        .table-compare {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* ล็อกความกว้างคอลัมน์ให้เท่ากัน */
        }

        .row-label {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 12px;
            padding: 10px;
            border: 1px solid #000;
        }

        .table-compare td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: 700;
        }

        /* ปรับปรุง textarea ให้พิมพ์ได้ดีขึ้นในแนวตั้ง */
        .shop-note {
            min-height: 80px;
            text-align: left;
        }

        @media print {
            .table-compare th {
                background: #e2e8f0 !important;
            }

            .row-label {
                background: #f1f5f9 !important;
            }
        }
    </style>


    <div class="summary-section" id="summaryArea">
        <h3>สรุปผลการเปรียบเทียบ</h3>
        <div class="summary-row">
            <span class="summary-label">ร้านค้าที่เสนอราคาเหมาะสมสุด :</span>
            <input type="text" class="dotted-line sum-val" style="flex-grow: 1;" value="<?= $cheapest_supplier ?>">
        </div>
        <div class="summary-row">
            <span class="summary-label">ร้านค้าที่น่าเชื่อถือที่สุด :</span>
            <input type="text" class="dotted-line sum-val" style="flex-grow: 1;">
        </div>
        <div class="summary-row">
            <span class="summary-label">ร้านค้าที่มีเงื่อนไขการจัดส่ง/บริการที่ดีที่สุด :</span>
            <input type="text" class="dotted-line sum-val" style="flex-grow: 1;">
        </div>
        <div class="summary-row" style="margin-top: 10px;">
            <span class="summary-label" style="font-size: 14px;">ร้านค้าที่เหมาะสมสุดที่เลือก :</span>
            <input type="text" class="dotted-line sum-val"
                style="flex-grow: 1; font-weight: 800; font-size: 14px; color: #2563eb;">
        </div>
    </div>

    <div class="signature-section">
        <div>
            <div class="sig-box"></div>
            <p class="sig-text">ผู้จัดทำ</p>
        </div>
        <div>
            <div class="sig-box"></div>
            <p class="sig-text">ผู้ตรวจสอบ</p>
        </div>
        <div>
            <div class="sig-box"></div>
            <p class="sig-text">ผู้อนุมัติ</p>
        </div>
    </div>
</div>

<script>
    function autoExpand(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }
    document.querySelectorAll('.input-note').forEach(el => autoExpand(el));

    // ส่งออก Word
    function exportWord() {
        let content = `<html><head><meta charset="utf-8"></head><body style="font-family:Sarabun">`;
        content += document.getElementById('printableArea').innerHTML;
        content += "</body></html>";
        const blob = new Blob(['\ufeff', content], { type: 'application/msword' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Report_Comparison.doc`;
        link.click();
    }

    // ส่งออก Excel
    function exportExcel() {
        const wb = XLSX.utils.book_new();

        // 1. เก็บข้อมูลจาก Header
        let wsData = [
            ["รายงานการเปรียบเทียบราคา"],
            ["โครงการ", document.getElementById('projName').value],
            ["ผู้จัดทำ", document.getElementById('author').value],
            ["สินค้า", document.getElementById('prodDetail').value],
            ["วันที่", document.getElementById('docDate').value],
            [],
            ["ลำดับ", "ผู้ขาย", "ราคา", "หมายเหตุ"]
        ];

        // 2. ดึงข้อมูลจากตาราง
        document.querySelectorAll('#compareTable tbody tr').forEach(row => {
            let idx = row.cells[0].innerText;
            let shop = row.querySelector('.shop-name').value;
            let price = row.querySelector('.shop-price').value;
            let note = row.querySelector('.shop-note').value;
            wsData.push([idx, shop, price, note]);
        });

        wsData.push([]);
        wsData.push(["สรุปผล"]);

        // 3. ดึงข้อมูลจากสรุปผล
        document.querySelectorAll('.summary-row').forEach(row => {
            let label = row.querySelector('.summary-label').innerText;
            let val = row.querySelector('.sum-val').value;
            wsData.push([label, val]);
        });

        const ws = XLSX.utils.aoa_to_sheet(wsData);
        XLSX.utils.book_append_sheet(wb, ws, "Comparison_Report");
        XLSX.writeFile(wb, `Comparison_${document.getElementById('projName').value}.xlsx`);
    }
</script>