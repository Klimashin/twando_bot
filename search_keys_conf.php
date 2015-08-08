<?php
include('inc/include_top.php');

//Set return page
$return_url = "search_keys_conf.php";

//Check if logged in
if (mainFuncs::is_logged_in() != true) {
    $page_select = "not_logged_in";
} else {
    $page_select = "search_keys_conf";

    if (!empty($_POST['search_str'])) {
        Header("Location: " . $return_url);
        // local canstants
        $SEARCH_TYPE_BY_HANDLE = 1;
        $SEARCH_TYPE_BY_KEYWORD = 2;
        $DELIMETER = ',';

        $search_str = $_REQUEST['search_str'];
        $related_user_id = $_REQUEST['related_user'];

        $search_keys = explode($DELIMETER, $search_str);
        foreach ($search_keys as $key) {
            try {
                if (empty($key)) { continue; }
                $key = trim($key);

                // determine search key type
                if (!(substr($key, 0 ,1) == '#' || substr($key, 0, 1) == '@')) {
                    throw new Exception("Invalid key {$key}");
                }

                $db->query("
                    INSERT INTO " . DB_PREFIX . "search_queue
                    (related_user_id, search_key)
                    VALUES ('"
                        . $db->prep($related_user_id) .
                        "', '{$db->prep($key)}');
                ");
            } catch (Exception $e) {
                echo '<span class="error">' . print_r($e->getMessage()) . '</span>';
            }
        }
    } elseif (!empty($_GET['id'])) {
        Header("Location: " . $return_url);
        $keyId = intval($_GET['id']);

        try {
            $db->query("
                DELETE FROM " . DB_PREFIX . "search_queue
                 WHERE id={$keyId};
            ");
        } catch (Exception $e) {
            echo '<span class="error">' . print_r($e->getMessage()) . '</span>';
        }
    }
}

mainFuncs::print_html($page_select);

include('inc/include_bottom.php');
