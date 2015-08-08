<?php
/*
 * Custom class to search user id's by twitter user_screen or hashtag
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
    if ($cron->get_cron_state('search') == 1 || $cron->get_cron_state('follow') == 1) {
        echo mainFuncs::push_response(24);
        $run_cron = false;
    }
}

if ($run_cron != true) {
    exit();
}

$db->output_error = 1;
//Set cron status
$cron->set_cron_state('search', 1);

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

$followersReqLimit = intval($rate_con['fw_limit']);
$followersRequestsRemaining = intval($rate_con['fw_remaining']);
$tweetsRequestsRemaining = intval($rate_con['tw_remaining']);

logToFile('search.log', 'SCRIPT STARTED');

while ($followersRequestsRemaining > 3 && $tweetsRequestsRemaining > 3) {
    $search_queue_record = getSearchQueueRecord();

    if (empty($search_queue_record)) {
        $cron->set_cron_state('search',0);
        logToFile('search.log', 'Nothing to search. Exiting..');
        exit();
    }

    $this_cursor = $search_queue_record['last_search_cursor'];
    $related_user = $db->get_user_data($search_queue_record['related_user_id']);

    if (substr($search_queue_record['search_key'], 0 ,1) == '#') {
        $search_type = 'search_by_keyword';
    } elseif (substr($search_queue_record['search_key'], 0, 1) == '@') {
        $search_type = 'search_by_user';
    } else {
        //
    }

    $search_queue_record['search_key'] = substr($search_queue_record['search_key'], 1);

    if ($search_type == 'search_by_user') { //search by user
        $followersRequestsRemaining--;

        $followersList = $connection->get(
            'followers/ids',
            array(
                'screen_name' => $search_queue_record['search_key'],
                'cursor' => $this_cursor,
                'stringify_ids' => 'true',
                'count' => TWITTER_API_LIST_FW
            )
        );

        if ( (!is_object($followersList)) or ($connection->http_code != 200) ) {
            if (!is_connected()) { //internet connection seems broken
                $cron->set_cron_state('search',0);
                logToFile('search.log', 'Internet connection error. Exiting..');
                exit();
            }

            if (in_array($connection->http_code, array(500, 502, 503, 504))) {
                $cron->set_cron_state('search',0);
                logToFile('search.log', 'Twitter server error occured. Exiting..');
                exit();
            }

            //if Internet connection is up and Twitter servers are ok - then it probably
            // something wrong with our data. Let's skip it.
            searchRecordUpdateCursor($search_queue_record['id'], '0');
            logToFile('search.log', 'Error occured while processing ' . $search_queue_record['search_key']
                    . ' (http return code: ' . $connection->http_code . ') skipping..');
            continue;
        }

        foreach ($followersList->ids as $id) {
            createUserDataRecord(
                $id,
                $search_queue_record['related_user_id'],
                $search_queue_record['search_key']
            );
        }

        logToFile('search.log', 'Successfuly extracted ' . count((array)$followersList->ids)
                . ' records for search_key ' . $search_queue_record['search_key']);

        searchRecordUpdateCursor($search_queue_record['id'], $followersList->next_cursor_str);
    } elseif ($search_type == 'search_by_keyword') { //search by keyword
        $tweetsRequestsRemaining--;

        if ($this_cursor > 0) {
            $content = $connection->get(
                'search/tweets',
                array(
                    'q' => $search_queue_record['search_key'],
                    'count' => TWITTER_TWEET_SEARCH_PP,
                    'include_entities' => false,
                    'max_id' => $this_cursor
                )
            );
        } else {
            $content = $connection->get(
                'search/tweets',
                array(
                    'q' => $search_queue_record['search_key'],
                    'count' => TWITTER_TWEET_SEARCH_PP,
                    'include_entities' => false
                )
            );
        }

        if ( (!is_object($content)) or ($connection->http_code != 200) ) {
            if (!is_connected()) { //internet connection seems broken
                $cron->set_cron_state('search',0);
                logToFile('search.log', 'Internet connection error. Exiting..');
                exit();
            }

            if (in_array($connection->http_code, array(500, 502, 503, 504))) {
                $cron->set_cron_state('search',0);
                logToFile('search.log', 'Twitter server error occured. Exiting..');
                exit();
            }

            //if Internet connection is up and Twitter servers are ok - then it probably
            // something wrong with our data. Let's skip it.
            searchRecordUpdateCursor($search_queue_record['id'], '0');
            logToFile('search.log', 'Error occured while processing ' . $search_queue_record['search_key']
                    . ' (http return code: ' . $connection->http_code . ') skipping..');
            continue;
        }

        foreach ($content->statuses as $tweet) {
            createUserDataRecord(
                $tweet->user->id_str,
                $search_queue_record['related_user_id'],
                $search_queue_record['search_key']
            );
        }

        logToFile('search.log', 'Successfuly extracted ' . count((array)$content->statuses)
                . ' records for search_key ' . $search_queue_record['search_key']);

        if (count((array)$content->statuses) < TWITTER_TWEET_SEARCH_PP) { //extracted last availaible tweets
            $next_max_id = 0;
        } else {
            $next_max_id = $content->statuses[99]->id_str - 1;
        }

        searchRecordUpdateCursor($search_queue_record['id'], $next_max_id);
    }
}

$cron->set_cron_state('search',0);
logToFile('search.log', 'SCRIPT SUCCESSFULY FINISHED');

//FUNCTION DEFINITION AREA
function getSearchQueueRecord()
{
    global $db;

    return $db->fetch_array($db->query("
        SELECT id, search_key, last_search_cursor, related_user_id
          FROM " . DB_PREFIX . "search_queue
         WHERE last_search_cursor != '0' ORDER BY id ASC LIMIT 1
    "));
}

function createUserDataRecord($user_id, $related_user_id, $usedKey)
{
    global $db;

    $createDateTime = date('Y-m-d H:i:s');
    $db->query("
        INSERT INTO " . DB_PREFIX . "extracted_user_data
        (user_id, datetime_created, related_user_id, used_search_key)
        VALUES ('{$db->prep($user_id)}', '{$db->prep($createDateTime)}', '{$db->prep($related_user_id)}',"
        . "'{$db->prep($usedKey)}')
        ON DUPLICATE KEY UPDATE user_id = user_id
    ");
}

function searchRecordUpdateCursor($id, $cursor)
{
    global $db;

    $currentDatetime = date('Y-m-d H:i:s');

    $db->query("
        UPDATE " . DB_PREFIX . "search_queue
           SET last_search_cursor = '{$cursor}',
               last_search_date='{$db->prep($currentDatetime)}'
         WHERE id='{$id}'
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
//ENDOF FUNCTION DEFINITION AREA