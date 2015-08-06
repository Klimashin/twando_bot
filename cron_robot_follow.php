<?php
/*
 * Custom class to auto-follow users from search result
 */

include('inc/include_top.php');
include('inc/class/class.cron.php');

$cron = new cronFuncs();

//Defines
set_time_limit(0);
$run_cron = true;

//Check crpn key and if running
if ( ($argv[1] != CRON_KEY) and ($_GET['cron_key'] != CRON_KEY) ) {
    echo mainFuncs::push_response(23);
    $run_cron = false;
} else {
    if ($cron->get_cron_state('robot_fw') == 1) {
        echo mainFuncs::push_response(24);
        $run_cron = false;
    }
}

if ($run_cron != true) {
    exit();
}

$db->output_error = 1;
//Set cron status
$cron->set_cron_state('robot_fw', 1);

//Get credentials
$ap_creds = $db->get_ap_creds();

$counter = defined(ROBOT_ACCOUNTS_TO_FOLLOW) ? ROBOT_ACCOUNTS_TO_FOLLOW : 5;

logToFile('robot_fw.log', 'SCRIPT STARTED');

$result = $db->query("
    SELECT user_id, related_user_id, screen_name
      FROM " . DB_PREFIX . "extracted_user_data
     WHERE datetime_robot_follow IS NULL
           AND datetime_updated IS NOT NULL
           LIMIT {$counter}
");

if (!is_connected()) { //internet connection seems broken
    $cron->set_cron_state('robot_fw',0);
    logToFile('robot_fw.log', 'Internet connection error. Exiting..');
    exit();
}

$friendshipCounter = 0;
$protected_accs = 0;

while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    if ($db->is_on_fr_list($row['related_user_id'], $row['user_id'])) {
        logToFile('robot_fw.log', 'Already in friend list ' . $row['screen_name'] . '. Deleting record..');
        $db->query("DELETE FROM " . DB_PREFIX . "extracted_user_data WHERE user_id='" . $row['user_id'] . "'");
        continue;
    }

    $authUserData = $db->get_user_data($row['related_user_id']);

    $connection = new TwitterOAuth(
        $ap_creds['consumer_key'],
        $ap_creds['consumer_secret'],
        $authUserData['oauth_token'],
        $authUserData['oauth_token_secret']
    );

    $response = $connection->post('friendships/create',array('user_id' => $row['user_id']));

    if (!$connection->http_code == 200) {
        logToFile('robot_fw.log', 'Failed to follow user ' . $row['screen_name'] . '. Deleting record..');
        $db->query("DELETE FROM " . DB_PREFIX . "extracted_user_data WHERE user_id='" . $row['user_id'] . "'");
    } else {
        $friendshipCounter++;

        $followDateTime = date('Y-m-d H:i:s');
        $db->query("UPDATE " . DB_PREFIX . "extracted_user_data
                       SET datetime_robot_follow = '{$followDateTime}'
                     WHERE user_id='" . $row['user_id'] . "'");

        if ($response->protected) {
            $protected_accs++;
        }

        if (!empty($response->error)) {
            logToFile('robot_fw.log', 'There was error: ' . $response->error);
        }
    }
}

logToFile('robot_fw.log', 'Succssfuly ' . $friendshipCounter . ' follow requests sent. There were '
        . $protected_accs . ' protected accs.');
$cron->set_cron_state('robot_fw',0);
logToFile('robot_fw.log', 'SCRIPT SUCCESSFULY FINISHED');

function is_connected()
{
    $connected = fopen("http://www.google.com:80/","r");
    if($connected) {
        return true;
    } else {
        return false;
    }
}

function logToFile($filename, $msg)
{
    $fd = fopen($filename, "a");
    $str = "[" . date("Y/m/d h:i:s", mktime()) . "] " . $msg;
    fwrite($fd, $str . "\n");
    fclose($fd);
}