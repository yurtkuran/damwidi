-- remove tables no longer needed
DROP TABLE IF EXISTS `data_sectors`;
DROP TABLE IF EXISTS `positions`;

-- rename tables
ALTER TABLE `data_spdr`       RENAME `data_performance`;
ALTER TABLE `detail`          RENAME `data_transactions`;
ALTER TABLE `positions_daily` RENAME `data_history`;
ALTER TABLE `value`           RENAME `data_value`;
ALTER TABLE `format`          RENAME `format_above_below`;

-- add new columns
ALTER TABLE `data_performance`  ADD `ytd` decimal(6,3) not null AFTER `1yr`;
ALTER TABLE `data_performance`  ADD `as-of` date not null AFTER `ytd`;
ALTER TABLE `data_performance`  ADD `updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL AFTER `type`;
ALTER TABLE `data_value`        ADD `updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL AFTER `close`;
ALTER TABLE `data_transactions` ADD `updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL AFTER `description`;

-- rename/change columns
ALTER TABLE `data_value` CHANGE `balance` `cash` DECIMAL(8,2) NULL;
ALTER TABLE `data_value` MODIFY `total_shares` DECIMAL(9,4) NULL;
ALTER TABLE `data_value` MODIFY `open`  DECIMAL(8,6) NULL;
ALTER TABLE `data_value` MODIFY `high`  DECIMAL(8,6) NULL;
ALTER TABLE `data_value` MODIFY `low`   DECIMAL(8,6) NULL;
ALTER TABLE `data_value` MODIFY `close` DECIMAL(8,6) NULL;
ALTER TABLE `data_performance` MODIFY `effectiveDate` DATE NOT NULL;
ALTER TABLE `data_performance` MODIFY `fetchedDate`   DATE NOT NULL;

-- remove columns no longer needed
ALTER TABLE `data_transactions` DROP COLUMN `shares_running`;

ALTER TABLE `data_value` DROP COLUMN `positions`;

-- insert new data
INSERT INTO `data_performance` (`sector`, `weight`, `shares`, `basis`, `previous`, `1wk`, `2wk`, `4wk`, `8wk`, `1qtr`, `1yr`, `Description`, `Name`, `sectorDescription`, `effectiveDate`, `fetchedDate`, `type`) VALUES ('DAM', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', 'Damwidi', 'Damwidi', 'Damwidi', '0', '0', 'F');