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

if (!is_connected()) { //internet connection seems broken
    $cron->set_cron_state('robot_fw',0);
    exit();
}

$db->output_error = 1;
//Set cron status
$cron->set_cron_state('robot_fw', 1);
$cron->set_log(1);

//Get credentials
$ap_creds = $db->get_ap_creds();

$configs = $db->query("
    SELECT *
      FROM " . DB_PREFIX . "users_config
     WHERE follow_bot_status=1;
");

while ($userConfig = mysql_fetch_array($configs, MYSQL_ASSOC)) {
    $counter = $userConfig['follow_rate'];
    $cron->set_user_id($userConfig['user_id']);

    $result = $db->query("
        SELECT user_id, related_user_id, screen_name
          FROM " . DB_PREFIX . "extracted_user_data
         WHERE datetime_robot_follow_{$userConfig['user_id']} IS NULL
               AND datetime_updated IS NOT NULL
               {$userConfig['follow_rule']}
               LIMIT {$counter}
    ");

    $friendshipCounter = [];
    $protected_accs = 0;

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        if ($db->is_on_fr_list($userConfig['user_id'], $row['user_id'])) {
            $cron->store_cron_log(3, 'Already in friend list ' . $row['screen_name'] . '.', '');
            $followDateTime = date('Y-m-d H:i:s');
            $db->query("UPDATE " . DB_PREFIX . "extracted_user_data
                           SET datetime_robot_follow_{$userConfig['user_id']} = '{$followDateTime}'
                         WHERE user_id='" . $row['user_id'] . "'");
            continue;
        }

        $authUserData = $db->get_user_data($userConfig['user_id']);

        $connection = new TwitterOAuth(
            $ap_creds['consumer_key'],
            $ap_creds['consumer_secret'],
            $authUserData['oauth_token'],
            $authUserData['oauth_token_secret']
        );

        $response = $connection->post('friendships/create',array('user_id' => $row['user_id']));

        if (!$connection->http_code == 200) {
            $db->query("DELETE FROM " . DB_PREFIX . "extracted_user_data WHERE user_id='" . $row['user_id'] . "'");
        } else {
            $followDateTime = date('Y-m-d H:i:s');
            $db->query("UPDATE " . DB_PREFIX . "extracted_user_data
                           SET datetime_robot_follow_{$userConfig['user_id']} = '{$followDateTime}'
                         WHERE user_id='" . $row['user_id'] . "'");

            if ($response->protected) {
                $protected_accs++;
            } else {
                $friendshipCounter[] = $row['user_id'];
            }

            if (!empty($response->error)) {
                logToFile('robot_fw.log', 'There was error: ' . $response->error);
            }
        }
    }

    $cron->store_cron_log(3, count($friendshipCounter) . ' users have been successfuly followed', $friendshipCounter);
}

$cron->set_cron_state('robot_fw',0);
