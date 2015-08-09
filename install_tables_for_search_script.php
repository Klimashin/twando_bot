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
    `last_search_date` DATETIME DEFAULT NULL,
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
    `used_search_key` INT DEFAULT NULL,
    PRIMARY KEY  (`user_id`),
    INDEX (used_search_key),
    INDEX (screen_name)
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

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('gen_tweets');
");

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('bot_tweets');
");

//create and fill table for user config
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "users_config` (
    `user_id` varchar(48) NOT NULL,
    `follow_bot_status` BOOLEAN DEFAULT FALSE,
    `follow_rate` INT DEFAULT 5,
    `follow_rule` TEXT DEFAULT NULL,
    `tweet_bot_status` BOOLEAN DEFAULT FALSE,
    `tweet_template` TEXT DEFAULT NULL,
    `tweet_query` TEXT DEFAULT NULL,
    `tweeting_rate` INT DEFAULT 5,
    `tweet_generation_rate` INT DEFAULT 5,
    `tweet_generation_offset` INT DEFAULT 0,
    PRIMARY KEY  (user_id)
    );
");

$existingAccounts = $db->query("
    SELECT id
      FROM " . DB_PREFIX . "authed_users;
");

while ($twando_account = mysql_fetch_array($existingAccounts, MYSQL_ASSOC)) {
    $db->query("
        INSERT INTO " . DB_PREFIX . "users_config
        (user_id) VALUES ('{$twando_account['id']}');
    ");

    $db->query("
        ALTER TABLE " . DB_PREFIX . "extracted_user_data
                ADD `datetime_robot_follow_{$twando_account['id']}` DATETIME DEFAULT NULL;
    ");
}

$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "tweets_queue` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` varchar(48) NOT NULL,
    `tweet_content` TEXT DEFAULT NULL,
    `datetime_created` DATETIME DEFAULT NULL,
    `datetime_tweeted` DATETIME DEFAULT NULL,
    PRIMARY KEY  (id),
    INDEX (user_id)
    );
");