<?php

require_once __DIR__ . '/../pr_item_validation.php';

function pr_item_assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException $exception) {
        return;
    }
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$validated = pr_validate_item_descriptions([' ปากกา สีน้ำเงิน 0.7 มม. ', 'กระดาษ A4 80 แกรม']);
if ($validated !== ['ปากกา สีน้ำเงิน 0.7 มม.', 'กระดาษ A4 80 แกรม']) {
    fwrite(STDERR, "FAIL: valid purchase item details must be trimmed and retained\n");
    exit(1);
}

pr_item_assert_throws(fn () => pr_validate_item_descriptions([]), 'a PR must contain at least one item detail');
pr_item_assert_throws(fn () => pr_validate_item_descriptions(['']), 'an empty item detail must be rejected');
pr_item_assert_throws(fn () => pr_validate_item_descriptions(['สินค้า', '   ']), 'every added PR row must contain item details');

$formSource = file_get_contents(__DIR__ . '/../request_buy.php');
if (substr_count($formSource, 'data-required-item-details') < 2 || strpos($formSource, 'สี / ขนาด / รุ่น / วัสดุ / สเปก') === false) {
    fwrite(STDERR, "FAIL: initial and dynamic PR rows must show the required specification guidance\n");
    exit(1);
}

echo "PR item details required: PASS\n";
