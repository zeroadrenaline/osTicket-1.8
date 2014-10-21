/**
 * @version v1.9.4 RC5
 * @signature Unknown
 * @file name Unknown.patch.sql
 *
 *  - Adds required fields to ticket table
 *  - Adds required fields to ticket_thread table
 *  - Inserts Time Type into Custom Lists
 *	- Inserts standard Time Types into Custom List Items
 *	- Inserts osTicket configuration options
 */
 
SET SQL_SAFE_UPDATES=0$

-- Add information to existing tables
-- ===================================

-- Add Time Types to Custom List
INSERT INTO `%TABLE_PREFIX%list` (`name`, `name_plural`, `sort_mode`, `masks`, `type`, `notes`, `created`, `updated`)
VALUES ('Time Type', 'Time Types', 'SortCol', '13', 'time-type', 'Time Spent plugin list, do not modify', NOW(), NOW())$ 

-- Add Times of time to Custom List Items
BEGIN
    SET @listitem_list_id = (SELECT id FROM `%TABLE_PREFIX%list` WHERE `name`='Time Type')$

	INSERT INTO `%TABLE_PREFIX%time_type` (`list_id`, `status`, `value`, `sort`) VALUES
	(@listitem_list_id, 1, 'Telephone', 1),
	(@listitem_list_id, 1, 'Email', 2),
	(@listitem_list_id, 1, 'Remote', 3),
	(@listitem_list_id, 1, 'Workshop', 4),
	(@listitem_list_id, 1, 'Onsite', 5)$
END$

-- Add osTicket Time configurations to config table
INSERT INTO `%TABLE_PREFIX%config` (`namespace`, `key`, `value`, `updated`) VALUES
('core', 'isclienttime', 0, now()),
('core', 'istickettime', 0, now()),
('core', 'isthreadtime', 0, now())$



-- Modify existing tables
-- ======================

-- Adds time_spent field to ticket table
ALTER TABLE `%TABLE_PREFIX%ticket` ADD COLUMN `time_spent` FLOAT(4,2)  NOT NULL DEFAULT '0.00' AFTER `closed`$

-- Adds time_spent & time_type field to ticket_thread table
ALTER TABLE `%TABLE_PREFIX%ticket_thread` ADD COLUMN `time_spent` FLOAT(4,2) NOT NULL DEFAULT '0.00' AFTER `thread_type`$
ALTER TABLE `%TABLE_PREFIX%ticket_thread` ADD COLUMN `time_type` INT( 11 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `time_spent`$


SET SQL_SAFE_UPDATES=1$	