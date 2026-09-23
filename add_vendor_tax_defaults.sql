-- Vendor tax defaults schema contract.
-- Preserve legacy project VAT meaning from stored monetary data:
-- NULL means no VAT, 0 means VAT inclusive, and 1 means VAT exclusive.
-- Run this read-only audit before applying the conversion to inspect every
-- VAT-bearing legacy project and its computed target classification:
-- SELECT id AS project_id,
--        contract_value,
--        total_vat_amount,
--        has_vat AS legacy_has_vat,
--        CASE
--            WHEN COALESCE(total_vat_amount, 0) = 0 THEN NULL
--            WHEN has_vat = '' THEN '0'
--            WHEN ABS(total_vat_amount - (contract_value * 0.07)) <= 0.02 THEN '1'
--            ELSE '0'
--        END AS computed_has_vat
-- FROM projects
-- WHERE COALESCE(total_vat_amount, 0) <> 0
--   AND has_vat IS NOT NULL AND has_vat NOT IN ('0', '1');
-- Only noncanonical legacy strings are classified. Existing NULL/0/1 modes
-- are authoritative, even when stored amounts are zero or inconsistent.

ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS entity_type ENUM('juristic','individual') NOT NULL DEFAULT 'juristic' AFTER customer_name,
    ADD COLUMN IF NOT EXISTS default_vat_mode ENUM('none','exclusive','inclusive') NOT NULL DEFAULT 'none' AFTER entity_type,
    ADD COLUMN IF NOT EXISTS default_wht_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER default_vat_mode,
    ADD COLUMN IF NOT EXISTS default_wht_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00 AFTER default_wht_enabled;

ALTER TABLE projects MODIFY has_vat VARCHAR(10) NULL;

UPDATE projects
SET has_vat = CASE
    WHEN COALESCE(total_vat_amount, 0) = 0 THEN NULL
    WHEN has_vat = '' THEN '0'
    WHEN ABS(total_vat_amount - (contract_value * 0.07)) <= 0.02 THEN '1'
    ELSE '0'
END
WHERE has_vat IS NOT NULL AND has_vat NOT IN ('0', '1');

ALTER TABLE projects MODIFY has_vat TINYINT(1) NULL DEFAULT NULL;
