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
    if ($cron->get_cron_state('gen_tweets') == 1) {
        echo mainFuncs::push_response(24);
        $run_cron = false;
    }
}

if ($run_cron != true) {
    exit();
}

$db->output_error = 1;
//Set cron status
$cron->set_cron_state('gen_tweets', 1);
$cron->set_log(1);

$configs = $db->query("
    SELECT *
      FROM " . DB_PREFIX . "users_config
     WHERE tweet_bot_status=1;
");

while ($userConfig = mysql_fetch_array($configs, MYSQL_ASSOC)) {
    $cron->set_user_id($userConfig['user_id']);
    $queryLimit = $userConfig['tweet_generation_rate'] ? $userConfig['tweet_generation_rate'] : 0;
    $queryOffset = $userConfig['tweet_generation_offset'] ? $userConfig['tweet_generation_offset'] : 0;
    $tweetsCount = 0;

    if (!empty($userConfig['tweet_template']) && !empty($userConfig['tweet_query'])) {
        $tweetsData = $db->query($userConfig['tweet_query'] . " LIMIT {$queryLimit} OFFSET {$queryOffset};");
        while ($data = mysql_fetch_array($tweetsData, MYSQL_ASSOC)) {
            $tweet_content = bind_to_template($data, $userConfig['tweet_template']);
            addTweetToQueue($tweet_content, $userConfig['user_id']);
            $tweetsCount++;
        }
    }

    $newOffset = $queryOffset + $queryLimit;
    $db->query("UPDATE " . DB_PREFIX . "users_config
                       SET tweet_generation_offset={$newOffset}
                     WHERE user_id='{$userConfig['user_id']}';");
    $cron->store_cron_log(6, "Generated {$tweetsCount} new tweets for user {$userConfig['user_id']}", '');
}

$cron->set_cron_state('gen_tweets',0);

function bind_to_template($replacements, $template)
{
    return preg_replace_callback('/{{(.+?)}}/', function($matches) use ($replacements)
    {
        return $replacements[$matches[1]];
    }, $template);
}

function addTweetToQueue($tweet_content, $user_id)
{
    global $db;

    $createDateTime = date('Y-m-d H:i:s');
    $db->query("
        INSERT INTO " . DB_PREFIX . "tweets_queue
        (user_id, datetime_created, tweet_content)
        VALUES ('{$db->prep($user_id)}', '{$db->prep($createDateTime)}', '{$db->prep($tweet_content)}');
    ");
}
