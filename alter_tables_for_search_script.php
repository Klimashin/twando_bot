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

//Add search key to extracted data
$db->query("
    ALTER TABLE " . DB_PREFIX . "extracted_user_data
            ADD used_search_key INT DEFAULT NULL;
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


//rebuild table search_queue - type column is not nessecary
$existingKeys = $db->query("
    SELECT *
      FROM " . DB_PREFIX . "search_queue;
");

while ($key = mysql_fetch_array($existingKeys, MYSQL_ASSOC)) {
    $SEARCH_TYPE_BY_HANDLE = 1;
    $SEARCH_TYPE_BY_KEYWORD = 2;

    if ($key['search_type'] == $SEARCH_TYPE_BY_HANDLE) {
        $db->query("
            UPDATE " . DB_PREFIX . "search_queue
               SET search_key='@{$key['search_key']}'
             WHERE id={$key['id']};
        ");
    } else {
        $db->query("
            UPDATE " . DB_PREFIX . "search_queue
               SET search_key='#{$key['search_key']}'
             WHERE id={$key['id']};
        ");
    }
}

$db->query("
    ALTER TABLE " . DB_PREFIX . "search_queue
            ADD last_search_date DATETIME DEFAULT NULL,
    DROP COLUMN search_type;
");

//add indexes
$db->query("CREATE INDEX used_search_key ON " . DB_PREFIX . "extracted_user_data(used_search_key);");
$db->query("CREATE INDEX screen_name ON " . DB_PREFIX . "extracted_user_data(screen_name);");
$db->query("OPTIMIZE TABLE " . DB_PREFIX . "extracted_user_data;");

//create table for tweeting bot
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

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('gen_tweets');
");

$db->query("
    INSERT INTO " . DB_PREFIX . "cron_status
        (cron_name) VALUES ('bot_tweets');
");