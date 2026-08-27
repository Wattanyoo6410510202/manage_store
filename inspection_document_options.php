<?php

if (!function_exists('inspection_document_options')) {
    function inspection_document_options(): array
    {
        return [
            'boq' => 'BOQ',
            'drawing' => 'แบบก่อสร้าง (Drawing)',
            'progress' => 'รายงานความคืบหน้า',
            'site_photo' => 'รูปถ่ายหน้างาน',
            'invoice' => 'ใบแจ้งหนี้ (Invoice)',
            'other' => 'อื่น ๆ',
        ];
    }
}

if (!function_exists('inspection_normalize_document_selection')) {
    function inspection_normalize_document_selection($rawDocuments, string $otherDetail = ''): array
    {
        $documents = is_array($rawDocuments) ? $rawDocuments : json_decode((string)$rawDocuments, true);
        $documents = is_array($documents) ? $documents : [];
        $allowedKeys = array_keys(inspection_document_options());
        $selected = [];

        foreach ($documents as $documentKey) {
            $documentKey = (string)$documentKey;
            if (in_array($documentKey, $allowedKeys, true) && !in_array($documentKey, $selected, true)) {
                $selected[] = $documentKey;
            }
        }

        $otherDetail = trim($otherDetail);
        if (!in_array('other', $selected, true)) {
            $otherDetail = '';
        }

        return [
            'selected' => $selected,
            'other_detail' => $otherDetail,
        ];
    }
}

if (!function_exists('inspection_document_selection_from_json')) {
    function inspection_document_selection_from_json($rawSelection): array
    {
        $selection = is_array($rawSelection) ? $rawSelection : json_decode((string)$rawSelection, true);
        if (!is_array($selection)) {
            return ['selected' => [], 'other_detail' => ''];
        }

        return inspection_normalize_document_selection(
            $selection['selected'] ?? [],
            (string)($selection['other_detail'] ?? '')
        );
    }
}
