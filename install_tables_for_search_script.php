<?php
/*
Config
*/
ob_start();
include('inc/config.php');
ob_end_clean();

/*
Includes
*/
include('inc/class/class.mysql.php');

$db = new mySqlCon();
$db->output_error = 1;

$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "search_queue` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `related_user_id` varchar(48) NOT NULL,
    `search_key` varchar(255) NOT NULL,
    `search_type` TINYINT NOT NULL,
    `last_search_cursor` varchar(48) DEFAULT -1,
    PRIMARY KEY  (`id`)
    );
");

$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "extracted_user_data` (
    `user_id` varchar(48) NOT NULL,
    `screen_name` varchar(255) DEFAULT NULL,
    `related_user_id` varchar(48) NOT NULL,
    `followers_count` INT,
    `following_count` INT,
    `follow_ratio` FLOAT,
    `location` varchar(255) DEFAULT NULL,
    `tw_account_age_days` INT,
    `last_tweet_date` DATETIME DEFAULT NULL,
    `datetime_created` DATETIME DEFAULT NULL,
    `datetime_updated` DATETIME DEFAULT NULL,
    `datetime_robot_follow` DATETIME DEFAULT NULL,
    PRIMARY KEY  (`user_id`)
    );
");

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('search');
");

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('upd_info');
");

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('robot_fw');
");

