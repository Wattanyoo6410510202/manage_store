<?php
if (!function_exists('supplier_display_name')) {
    function supplier_display_name($name): string
    {
        $supplierName = (string)$name;
        $normalizedName = strtolower((string)(preg_replace('/[^a-z0-9]+/i', '', $supplierName) ?? ''));
        $displayNames = [
            'manonta' => 'บริษัท นรพล กรุ๊ป จำกัด',
            'nijuni' => 'บริษัท 2 ธันวา 2497 จำกัด',
            'shotel' => 'บริษัท ดับเบิ้ลเอส กรุ๊ป จำกัด',
        ];

        foreach ($displayNames as $alias => $displayName) {
            if (strpos($normalizedName, $alias) !== false) {
                return $displayName;
            }
        }

        return $supplierName;
    }
}
