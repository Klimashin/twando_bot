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

$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "search_queue` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `related_user_id` varchar(48) NOT NULL,
    `search_key` varchar(255) NOT NULL,
    `search_type` TINYINT NOT NULL,
    `last_search_cursor` varchar(48) DEFAULT 0,
    PRIMARY KEY  (`id`)
    );
");
