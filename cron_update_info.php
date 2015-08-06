<?php
/*
 * Custom class to update user info from twitter api
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
    if ($cron->get_cron_state('upd_info') == 1 || $cron->get_cron_state('follow') == 1) {
        echo mainFuncs::push_response(24);
        $run_cron = false;
    }
}

if ($run_cron != true) {
    exit();
}

$db->output_error = 1;
//Set cron status
$cron->set_cron_state('upd_info', 1);

//Get credentials
$ap_creds = $db->get_ap_creds();

$authUserData = $db->get_user_data(CRON_SEARCH_AUTH_ID);

$connection = new TwitterOAuth(
    $ap_creds['consumer_key'],
    $ap_creds['consumer_secret'],
    $authUserData['oauth_token'],
    $authUserData['oauth_token_secret']
);

$rate_con = $cron->get_remaining_hits();
$usersRequestsRemaining = intval($rate_con['ul_remaining']);
echo $usersRequestsRemaining;
logToFile('upd_info.log', 'SCRIPT STARTED');

while ($usersRequestsRemaining > 10) {
    $result = getIds($db);
    $user_ids = array();
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $user_ids[] =  $row['user_id'];
    }

    if (empty($user_ids)) {
        $cron->set_cron_state('upd_info',0);
        logToFile('upd_info.log', 'Nothing to update. Exiting..');
        exit();
    }

    if (!is_connected()) { //internet connection seems broken
        $cron->set_cron_state('upd_info',0);
        logToFile('upd_info.log', 'Internet connection error. Exiting..');
        exit();
    }

    $users_info = $connection->post('users/lookup', array('user_id' => implode(',', $user_ids)));
    $usersRequestsRemaining--;

    foreach ($users_info as $user_info) {
        var_dump($user_info->screen_name);
        updateUserInfo($user_info);
        unset($user_ids[array_search($user_info->id_str, $user_ids)]);
    }

    var_dump($user_ids);
    //delete users that was not found
    foreach ($user_ids as $id) {
        $db->query("DELETE FROM " . DB_PREFIX . "extracted_user_data WHERE user_id='" . $db->prep($id) . "'");
    }

    logToFile('upd_info.log', 'Successfuly updated ' . count((array)$users_info) . ' records');
}

$cron->set_cron_state('upd_info', 0);
logToFile('upd_info.log', 'SCRIPT SUCCESSFULY FINISHED');

function getIds()
{
    global $db;

    return $db->query("
        SELECT user_id
          FROM " . DB_PREFIX . "extracted_user_data
         WHERE datetime_updated IS NULL
      ORDER BY datetime_created ASC LIMIT " . 100 . "
    ");
}

function updateUserInfo($userInfo)
{
    global $db;

    $updateDateTime = date('Y-m-d H:i:s');
    $followers_count = intval($userInfo->followers_count);
    $following_count = intval($userInfo->friends_count);

    $follow_ratio = !empty($following_count) ?
            round( floatval($followers_count / $following_count), 4) : 0;

    $account_age_days = intval((time() - strtotime($userInfo->created_at)) / (3600*24));
    $last_tweet_date = !empty($userInfo->status) ?
            date('Y-m-d H:i:s', strtotime($userInfo->status->created_at)) : NULL;


    $db->query("
        UPDATE " . DB_PREFIX . "extracted_user_data
         SET datetime_updated='{$updateDateTime}',
             screen_name='{$db->prep($userInfo->screen_name)}',
             followers_count={$followers_count},
             following_count={$following_count},
             follow_ratio={$follow_ratio},
             location='{$db->prep($userInfo->location)}',
             tw_account_age_days={$account_age_days},
             last_tweet_date='{$last_tweet_date}'
        WHERE user_id='{$db->prep($userInfo->id_str)}'
    ");
}

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