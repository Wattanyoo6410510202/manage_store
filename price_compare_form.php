<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('header.php');

if (!can('compare')) {
    echo '<div class="p-8 text-center text-slate-500">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
    include 'footer.php';
    exit;
}
?>

<div class="px-2 py-2 md:px-4 md:py-4">
    <iframe id="priceCompareFrame" src="price_compare_form_app.php" title="ทำใบเปรียบเทียบราคา"
        class="w-full rounded-2xl border border-slate-200 bg-white shadow-sm"
        style="height: calc(100vh - 120px); min-height: 600px;"></iframe>
</div>

<?php include 'footer.php'; ?>
