<?php
require_once 'config.php';
include('header.php');
include('assets/alert.php');

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if (empty($id)) {
    die("ไม่พบรหัสเอกสาร");
}

require __DIR__ . '/partials/pr_document_data.php';
if (!$data) {
    die("ไม่พบเอกสาร");
}
require_once __DIR__ . '/pr_sign_lib.php';
$prs_can_send = pr_sign_pr_closed_reason($data) === null
    && pr_sign_can_manage($data, (int)($_SESSION['user_id'] ?? 0), (string)($approval_actor_context['role'] ?? ''));
?>

<link rel="stylesheet" href="assets/css/style_quotation.css">
<div class="floating-toolbar no-print">
    <div class="tool-group">
        <button onclick="history.back()" class="btn-tool btn-back" title="ย้อนกลับ"><i class="fas fa-chevron-left"></i></button>
    </div>
    <div class="tool-group main-tools">
        <button onclick="window.print()" class="btn-tool btn-print" title="พิมพ์เอกสาร"><i class="fas fa-print"></i> <span>Print</span></button>
        <button onclick="toggleSignature()" class="btn-tool btn-sig" id="sigBtn" title="เปิด/ปิด ลายเซ็น"><i class="fas fa-pen-nib"></i> <span>Sign</span></button>
        <?php
        $prq_count = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pr_quotes WHERE pr_id = " . (int)$data['id']))[0] ?? 0);
        if ($prq_count > 0): ?>
        <a href="print_pr_quotes.php?id=<?= (int)$data['id'] ?>" target="_blank" class="btn-tool" style="background:#4f46e5;color:#fff;text-decoration:none;" title="เปิด/พิมพ์ใบเทียบราคา (A4 แนวนอน)">
            <i class="fas fa-balance-scale"></i> <span>พิมพ์ใบเทียบราคา (<?= $prq_count ?>)</span>
        </a>
        <?php endif; ?>
        <?php if ($prs_can_send): ?>
        <button onclick="prsOpen()" class="btn-tool" style="background:#059669;color:#fff;" title="ส่งลิงก์ให้ผู้อนุมัติเซ็น (ไม่ต้อง login)">
            <i class="fas fa-link"></i> <span>ส่งลิงก์ให้อนุมัติ</span>
        </button>
        <?php endif; ?>
        <?php if ($data['status'] === 'approved' && $data['received_status'] !== 'received'): ?>
        <button onclick="receivePR(<?= $data['id'] ?>)" class="btn-tool bg-emerald-500 hover:bg-emerald-600 text-white border-emerald-400" title="ยืนยันรับของ">
            <i class="fas fa-check-double"></i> <span>รับของแล้ว</span>
        </button>
        <?php endif; ?>
    </div>
    <div class="tool-group export-tools">
        <button onclick="exportPDF()" class="btn-tool btn-pdf" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf"></i></button>
        <button onclick="exportWord()" class="btn-tool btn-word" title="ดาวน์โหลด Word"><i class="fas fa-file-word"></i></button>
    </div>
</div>

<?php include __DIR__ . '/partials/pr_document.php'; ?>

<?php include 'pr_quotes_view.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function receivePR(id) {
        Swal.fire({
            title: 'ยืนยันรับของ?',
            text: 'คุณต้องการยืนยันว่าได้รับสินค้าครบถ้วนแล้วใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'รับของแล้ว',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            heightAuto: false,
            showDenyButton: true,
            denyButtonText: 'รับบางส่วน',
            denyButtonColor: '#f59e0b'
        }).then((result) => {
            let action = '';
            if (result.isConfirmed) action = 'received';
            else if (result.isDenied) action = 'partial';
            else return;

            fetch(`api/receive_pr.php?id=${id}&action=${action}`).then(res => res.json()).then(res => {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                }
            });
        });
    }

    function exportPDF() {
        const element = document.getElementById('quotation-content');
        const opt = { 
            margin: [5, 5, 5, 5], // [top, left, bottom, right] in mm
            filename: 'PR_<?php echo $data['doc_no']; ?>.pdf', 
            image: { type: 'jpeg', quality: 1.0 }, 
            html2canvas: { scale: 3, useCORS: true, letterRendering: true }, 
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } 
        };
        html2pdf().set(opt).from(element).save();
    }
    function toggleSignature() { window.location.href = 'create_signature.php'; }
    function exportWord() {
        const sourceHTML = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'></head><body>" + document.getElementById("quotation-content").innerHTML + "</body></html>";
        const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
        const fileDownload = document.createElement("a");
        document.body.appendChild(fileDownload);
        fileDownload.href = source;
        fileDownload.download = 'PR_<?php echo $data["doc_no"]; ?>.doc';
        fileDownload.click();
        document.body.removeChild(fileDownload);
    }

</script>

<?php if ($prs_can_send) include __DIR__ . '/partials/pr_sign_modal.php'; ?>

<?php include('footer.php'); ?>
<?php /* repair-workflow-native-panel */ require_once __DIR__.'/repair-integration/native-panel.php'; ?>