-- Flex Mounting: payment numbers (FMP/26-27/0001) so one bulk payment over several entries shares one number.
-- Safe to re-run; additive only.
ALTER TABLE `mounting_payments`
  ADD COLUMN IF NOT EXISTS `payment_number` VARCHAR(50) DEFAULT NULL AFTER `id`;
ALTER TABLE `mounting_payments`
  ADD INDEX IF NOT EXISTS `idx_payment_number` (`payment_number`);

INSERT INTO `document_sequences` (`module_key`, `financial_year`, `prefix`, `next_value`, `padding`)
SELECT 'flex_mounting_payment', '26-27', 'FMP/{FY}/', 1, 4
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `document_sequences` WHERE `module_key` = 'flex_mounting_payment');
