<?php
/*
 * Custom script to pregenerate tweet msgs
 */

include('inc/include_top.php');
include('inc/class/class.cron.php');

$cron = new cronFuncs();

set_time_limit(0);
$run_cron = true;

//Check crpn key and if running
if ( ($argv[1] != CRON_KEY) and ($_GET['cron_key'] != CRON_KEY) ) {
    echo mainFuncs::push_response(23);
    $run_cron = false;
} else {
    if ($cron->get_cron_state('bot_tweets') == 1) {
        echo mainFuncs::push_response(24);
        $run_cron = false;
    }
}

if ($run_cron != true) {
    exit();
}

if (!is_connected()) { //internet connection seems broken
    exit();
}

$db->output_error = 1;
//Set cron status
$cron->set_cron_state('bot_tweets', 1);
$cron->set_log(1);

$ap_creds = $db->get_ap_creds();

$configs = $db->query("
    SELECT *
      FROM " . DB_PREFIX . "users_config
     WHERE tweet_bot_status=1;
");

while ($userConfig = mysql_fetch_array($configs, MYSQL_ASSOC)) {
    $cron->set_user_id($userConfig['user_id']);
    $tweetRate = $userConfig['tweeting_rate'];

    $authUserData = $db->get_user_data($userConfig['user_id']);

    $connection = new TwitterOAuth(
        $ap_creds['consumer_key'],
        $ap_creds['consumer_secret'],
        $authUserData['oauth_token'],
        $authUserData['oauth_token_secret']
    );

    $tweetsToSend = $db->query("
        SELECT id, tweet_content
          FROM " . DB_PREFIX . "tweets_queue
         WHERE datetime_tweeted IS NULL
               AND user_id='{$userConfig['user_id']}'
         LIMIT {$tweetRate};
    ");

    while ($tweet = mysql_fetch_array($tweetsToSend, MYSQL_ASSOC)) {
        $connection->post('statuses/update', array('status' => $tweet['tweet_content']));

        //Log result - reasons for a non 200 include duplicate tweets, too many tweets
        //posted in a period of time, etc etc.
        if ($connection->http_code == 200) {
            $cron->store_cron_log(7, $cron_txts[18] . $tweet['tweet_content'] . $cron_txts[19] ,'');
        } else {
            $cron->store_cron_log(7, $cron_txts[18] . $tweet['tweet_content'] . $cron_txts[20],'');
        }

        $postedDateTime = date('Y-m-d H:i:s');
        $db->query("
            UPDATE " . DB_PREFIX . "tweets_queue
               SET datetime_tweeted='{$postedDateTime}'
             WHERE id={$tweet['id']};
        ");
    }
}

$cron->set_cron_state('bot_tweets',0);