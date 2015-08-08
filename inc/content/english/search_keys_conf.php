<?php

if (!$content_id) {
    exit;
}

global $db;

$existingKeys = $db->query("
    SELECT sq.*, au.screen_name
      FROM " . DB_PREFIX . "search_queue sq
      LEFT JOIN " . DB_PREFIX . "authed_users au
           ON sq.related_user_id=au.id;
");

$existingAccounts = $db->query("
    SELECT id, screen_name
      FROM " . DB_PREFIX . "authed_users;
");
?>
<h2>Search to Follow</h2>
<p>Enter search keys using the input below. User handles should started with @. Keywords should started with #.
You can mix different values in a single input like this: #apple,@Ford,#claus,... Enteries without symbol # or @ would be
dropped. All search keys stored in table DBPREFIX + search_queue.</p>
<form method="post" action="" name="search_queue_form" id="search_queue_form">
    <div class="cron_row">
        <div class="cron_left">Search term:</div>
        <div class="cron_right"><input type="text" name="search_str" id="search_str" class="input_box_style" value="" /></div>
    </div>
    <div class="cron_row">
        <div class="cron_left">Related account:</div>
        <div class="cron_right">
            <select name="related_user">
                <option value="<?= $twando_account['id']; ?>"><?= $twando_account['screen_name']; ?></option>
            <?php while ($twando_account = mysql_fetch_array($existingAccounts, MYSQL_ASSOC)) { ?>
                <option value="<?= $twando_account['id']; ?>"><?= $twando_account['screen_name']; ?></option>
            <?php } ?>
            </select>
        </div>
    </div>
    <input type="submit" value="Search" class="submit_button_style"/>
</form>
<table style="width:100%">
    <tr>
        <th>Keys</th>
        <th>Related Account</th>
        <th>Last Search Cursor</th>
        <th>Last Search Date</th>
        <th>Action</th>
    </tr>
<?php while ($key = mysql_fetch_array($existingKeys, MYSQL_ASSOC)) { ?>
    <tr>
        <th><?= $key['search_key']; ?></th>
        <th><?= $key['screen_name']; ?></th>
        <th><?= $key['last_search_cursor']; ?></th>
        <th><?= $key['last_search_date']; ?></th>
        <th><a href="search_keys_conf.php?id=<?= $key['id']; ?>">delete</a></th>
    </tr>
<?php } ?>
</table>
<br style="clear: both;" />
<br />
<a href="<?=BASE_LINK_URL?>">Return to main admin screen</a>