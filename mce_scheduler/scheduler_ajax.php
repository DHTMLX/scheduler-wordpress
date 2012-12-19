<?php
if (!defined('E_DEPRECATED'))
	define('E_DEPRECATED', 8192);

$err = error_reporting();
if ($err & E_DEPRECATED) {
	$err = $err ^ E_DEPRECATED;
	error_reporting($err);
}

require('../../../../wp-config.php');
define('WP_USE_THEMES', true);

if (!current_user_can('edit_posts') && !current_user_can('edit_pages'))
	die('access is denied');

$title = $_GET['title'];
//$dsc = ($_GET['dsc'];
$start = $_GET['start'];
$end = $_GET['end'];
$userId = $_GET['userId'];

if (get_magic_quotes_gpc()) {
	$title = stripslashes($title);
	$start = stripslashes($start);
	$end = stripslashes($end);
}

if (!preg_match("/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/", $start)) {
	$start = date("Y/m/d H:i:s", time());
}

if (!preg_match("/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/", $end)) {
	$end = date("Y/m/d H:i:s", time() + 60*5);
}
$table_name = 'events_rec';
if (isset($wpdb->base_prefix)) {
	$query = "SELECT * FROM ".$wpdb->base_prefix."blogs LIMIT 1";
	$table_exists = $wpdb->query($query);
	if ($table_exists == false) {
		$mu_version = false;
	} else {
		$mu_version = true;
	}
} else {
	$mu_version = false;
}

if (($mu_version == true)&&(get_site_option("scheduler_main") == 'on')) {
	$prefix = $wpdb->base_prefix;
} else {
	$prefix = $wpdb->prefix;
}
$insert = "INSERT INTO `". mysql_real_escape_string($prefix.$table_name, $wpdb->dbh).
		"` (`start_date`, `end_date`, `text`, `event_pid`, `event_length`, `user`) VALUES " .
		"('".mysql_real_escape_string($start, $wpdb->dbh)."', '".mysql_real_escape_string($end, $wpdb->dbh)."', '".mysql_real_escape_string($title, $wpdb->dbh)."', 0, 0, '".mysql_real_escape_string($userId, $wpdb->dbh)."');";
$result = $wpdb->query($insert);

echo $result;


?>