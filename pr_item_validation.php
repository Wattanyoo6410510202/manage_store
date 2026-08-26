<?php

function pr_validate_item_descriptions(array $descriptions): array
{
    if ($descriptions === []) {
        throw new DomainException('กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ');
    }

    $validated = [];
    foreach ($descriptions as $description) {
        $description = trim((string)$description);
        if ($description === '') {
            throw new DomainException('กรุณาระบุรายละเอียดสินค้าให้ครบทุกแถว');
        }
        $validated[] = $description;
    }
    return $validated;
}
