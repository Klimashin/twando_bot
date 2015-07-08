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
    if ($cron->get_cron_state('follow') == 1) {
        echo mainFuncs::push_response(24);
        $run_cron = false;
    }
}

if ($run_cron == true) {
    $db->output_error = 1;

    //Set cron status
    $cron->set_cron_state('follow',1);

    //Get credentials
    $ap_creds = $db->get_ap_creds();
}
