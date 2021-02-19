-- BDATE current type VARCHAR cannot be converted to DATE as is
-- rename current BDATE field to BDATE_OLD to store old data
ALTER TABLE `bios` RENAME COLUMN `BDATE` TO `BDATE_OLD`;
-- create new field BDATE w/ right date type
ALTER TABLE `bios` ADD `BDATE` DATE;
-- str_to_date does not handle empty value, replace them by NULL
UPDATE `bios` SET `BDATE_OLD` = NULL WHERE `BDATE_OLD` = '';
-- convert current BDATE VARCHAR to new BDATE DATE using str_to_date with right format
UPDATE `bios` SET `BDATE` = STR_TO_DATE(`BDATE_OLD`, '%d/%m/%Y');

-- then keep/drop old column if needed
