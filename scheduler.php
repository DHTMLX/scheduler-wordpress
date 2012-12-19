<?php
/**
 * @package Scheduler
 * @author DHTMLX LTD
 * @version 3.0
 */
/*
Plugin Name: Event Calendar / Scheduler
Plugin URI: http://wordpress.org/extend/plugins/event-calendar-scheduler/
Description: Events calendar that provides rich and intuitive scheduling solution
Author: DHTMLX LTD
Version: 3.0
Author URI: http://dhtmlx.com
*/

require (ABSPATH.WPINC.'/pluggable.php');
global $wpdb,
		$config,
		$scheduler_locale,
		$is_mu,
		$scheduler,
		$scheduler_userid,
		$default_xml,
		$current_user;

// getting default xml
$default_xml = file_get_contents(WP_PLUGIN_DIR.'/event-calendar-scheduler/default.xml');
$default_xml = str_replace("\r", "", $default_xml);
$default_xml = str_replace("\n", "", $default_xml);
$default_xml = str_replace("\t", "", $default_xml);

$is_mu = (MULTISITE) ? true : false;
$scheduler_prefix = ($is_mu == true && get_site_option("scheduler_main") == 'on') ? $wpdb->base_prefix : $wpdb->prefix;

add_option('scheduler_version', '3.0');
register_activation_hook(__FILE__, 'scheduler_activate');

require_once(WP_PLUGIN_DIR.'/event-calendar-scheduler/codebase/dhtmlxSchedulerConfigurator.php');
require_once(WP_PLUGIN_DIR.'/event-calendar-scheduler/codebase/dhtmlxSchedulerHelpers.php');
require_once(WP_PLUGIN_DIR.'/event-calendar-scheduler/SchedulerHelper.php');

$scheduler_usertypes = Array(0 => 'subscriber', 1 => 'contributor', 2 => 'author', 3 => 'editor', 4 => 'editor', 5 => 'editor', 6 => 'editor', 7 => 'editor', 8 => 'administrator', 9 => 'administrator', 10 =>'administrator');

$config = new DHXDBConfig();
$config->connection = $wpdb->dbh;
$config->prefix = $scheduler_prefix;
$config->events = 'events_rec';
$config->options = 'options';
$config->options_name = 'option_name';
$config->options_value = 'option_value';
$config->users = 'users';
$config->users_id = 'ID';
$config->users_login = 'user_login';
$config->locale = substr(get_locale(), 0, 2);
$config->default_xml = $default_xml;

$filename = ABSPATH.'wp-content/plugins/event-calendar-scheduler/codebase/locale/common_'.$config->locale.'.php';
if (file_exists($filename)) include($filename);

scheduler_activate();
if (!$is_mu) get_currentuserinfo();

$scheduler_userid = $current_user->id;

$scheduler = new SchedulerConfig('scheduler_config_xml', $config, $scheduler_userid, false);

wp_register_sidebar_widget('upcoming_events', scheduler_locale('Upcoming events'), 'upcoming_widget');
add_action('admin_menu', 'scheduler_add_pages');
add_action('admin_init', 'scheduler_admin_init');
add_action('init', 'scheduler_addbuttons');
add_filter('the_content', 'scheduler_check');

$version = get_option('scheduler_version');
if (($version !== false)&&((int) $version < 2)) {
	include('settings_export.php');
	update_option('scheduler_version', '2.1');
} else {
	add_option('scheduler_version', '2.1');
}

if ($scheduler->get_option('settings_link') == '')
	create_scheduler_link();

function scheduler_admin_init(){
	global $is_mu;
	register_setting('scheduler_options', 'scheduler_xml');
	register_setting('scheduler_options', 'scheduler_xml_version');
}


function scheduler_add_pages() {
	add_submenu_page('plugins.php', scheduler_locale('Scheduler'), scheduler_locale('Scheduler'), 'administrator', __FILE__, 'scheduler_settings');
}


function scheduler_settings() {
	global $current_user, $scheduler, $db;
	include(ABSPATH.'wp-content/plugins/event-calendar-scheduler/admin.php');
}


function scheduler_init() {
	global $scheduler_usertypes, $current_user, $scheduler_userid, $scheduler, $config;
	if (isset($current_user->roles[0]))
		$usertype = $current_user->roles[0];
	else
		$usertype = '-1';
	$usertype = Array($usertype);
	
	$url = WP_PLUGIN_URL.'/event-calendar-scheduler/codebase/';
	$loader_url = WP_PLUGIN_URL.'/event-calendar-scheduler/codebase/dhtmlxSchedulerConfiguratorLoad.php?scheduler_events=true';
	$final = $scheduler->schedulerInit($usertype, $config->locale, $url, $loader_url);
	return $final;
}


function get_events($number = 5) {
	global $wpdb, $config, $scheduler, $scheduler_userid;

	date_default_timezone_set(get_option('timezone_string'));
	$start = date('Y-m-d H:i:s');
	$endd = date('Y-m-d H:i:s', time() + 60*60*24*30*3);
	$dates = new SchedulerHelper($wpdb->dbh, $config->prefix.'events_rec');
	$events = $dates->get_dates($start, $endd);
	date_default_timezone_set('UTC');

	if ($scheduler->get_option("privatemode") == "on") {
		$all_events = $events;
		$events = Array();
		for ($i = 0; $i < count($all_events); $i++) {
			if ($all_events[$i]['user'] == $scheduler_userid)
				$events[] = $all_events[$i];
		}
	}

	$repeat = true;
	while ($repeat == true) {
		$repeat = false;
		for ($i = 0; $i < count($events) - 1; $i++) {
			if ($events[$i]['start_date'] > $events[$i + 1]['start_date']) {
				$ev = $events[$i];
				$events[$i] = $events[$i + 1];
				$events[$i + 1] = $ev;
				$repeat = true;
			}
		}
	}
	if ($number < count($events)) {
		array_splice($events, $number);
	}
	return $events;
}


function scheduler_sidebar() {
	global $wpdb, $config, $usertypes, $current_user, $scheduler_userid, $scheduler;

	get_currentuserinfo();
	if (isset($current_user->roles[0]))
		$usertype = $current_user->roles[0];
	else
		$usertype = '-1';

	if ($scheduler->can('view', $usertype))

	include(WP_PLUGIN_DIR.'/event-calendar-scheduler/sidebar.php');
	$number = $scheduler->get_option('settings_eventnumber');
	if ($number == '') {
		$number = 5;
	}

	$events = get_events($number);

	$final = '<ul>';
	if (count($events) == 0) {
		$final = '';
	}

	$url = $scheduler->get_option('settings_link');

	for ($i = 0; $i < count($events); $i++) {
		$event = $sidebarEvent;
		$url_rand = $url.((strpos($url, "?") !== false) ? "&" : "?")."dhx_rand=".rand(10000, 99000);
		$start_date = str_replace("-", "/", $events[$i]['start_date']);
		$start_date = date_parse($events[$i]['start_date']);
		$start_date = mktime($start_date['hour'], $start_date['minute'], $start_date['second'], $start_date['month'], $start_date['day'], $start_date['year']);
		$start_date = date_i18n(get_option('date_format').' '.get_option('time_format'), $start_date);

		$event = str_replace("{*URL*}", $url_rand, $event);
		$event = str_replace("{*DATE*}", $start_date, $event);
		$event = str_replace("{*DATE_SQL*}", $events[$i]['start_date'], $event);
		$event = str_replace("{*TEXT*}", stripslashes($events[$i]['text']), $event);
		$final .= $event;
	}
	$final .= '</ul>';
	return $final;
}


function scheduler_check($content) {
	$ver = phpversion();
	$ver_main = (int) substr($ver, 0, 1);
	if ( $ver_main < 5) {
		return scheduler_locale('Installation error: Event Calendar / Scheduler plugin requires PHP 5.x');
	}

	if (strpos($content, "[[scheduler_plugin]]") !== FALSE)  {
		$content = preg_replace('/<p>\s*\[\[(.*)\]\]\s*<\/p>/i', "[[$1]]", $content);
		$content = preg_replace('/\[\[scheduler_plugin\]\]/Ui', scheduler_init(), $content, 1);
		$content = str_replace('[[scheduler_plugin]]', '', $content);
	}
	if (strpos($content, "[[scheduler_sidebar]]")) {
		$content = preg_replace('/<p>\s*\[\[(.*)\]\]\s*<\/p>/i', "[[$1]]", $content);
		$content = str_replace('[[scheduler_sidebar]]', scheduler_sidebar(), $content);
	}
	return $content;
}


function scheduler_addbuttons() {
	global $current_user, $scheduler_usertypes, $scheduler;
	get_currentuserinfo();
	if (isset($current_user->roles[0]))
		$usertype = $current_user->roles[0];
	else
		$usertype = '-1';
	$usertype = Array($usertype);
	if ((!current_user_can('edit_posts') && ! current_user_can('edit_pages'))||(!$scheduler->can('add', $usertype))) {
		return;
	}

	if (get_user_option('rich_editing') == 'true') {
		add_filter("mce_external_plugins", "add_scheduler_tinymce_plugin");
		add_filter('mce_buttons', 'register_scheduler_button');
	}
}


function register_scheduler_button($buttons) {
	array_push($buttons, "separator", "scheduler");
	return $buttons;
}


function add_scheduler_tinymce_plugin($plugin_array) {
	$plugin_array['scheduler'] = WP_PLUGIN_URL.'/event-calendar-scheduler/mce_scheduler/editor_plugin.js';
	return $plugin_array;
}

function upcoming_widget($args) {
    extract($args);
	echo $before_widget;
	echo $before_title;
	echo scheduler_locale('Upcoming events');
	echo $after_title;
	echo scheduler_sidebar();
	echo $after_widget;
}


function scheduler_activate() {
	global $wpdb, $scheduler, $config;

	$table_exists = $wpdb->query("SELECT * FROM ".$config->prefix.$config->events);
	if ($wpdb->last_error !== '') {
		create_events_rec();
	}

	$field_exists = $wpdb->query("SELECT `lat` FROM ".$config->prefix.$config->events);
	if ($wpdb->last_error !== '') {
		create_compatible_fields();
		update_config();
	}

	$table_exists = $wpdb->query("SELECT * FROM ".$config->prefix."options");
	if ($wpdb->last_error !== '') {
		create_options();
	}

	$config_exists = $wpdb->query("SELECT * FROM ".$config->prefix."options WHERE option_name='scheduler_php'");
	if ($config_exists == false) {
		set_default_options();
	}

	$stable_config_row = $wpdb->get_row("SELECT * FROM ".$config->prefix."options WHERE option_name='scheduler_stable_config' LIMIT 1");
	if (!$stable_config_row) {
		$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_stable_config', '".$config->default_xml."', 'yes')";
		$wpdb->query($query);
	}
	$version = (int) get_option('scheduler_xml_version');
	update_option('scheduler_xml_version', $version + 1);
}


function create_events_rec() {
	global $wpdb, $config;
	$query = "CREATE TABLE IF NOT EXISTS `".$config->prefix.$config->events."` (
		`event_id` int(11) NOT NULL AUTO_INCREMENT,
		`start_date` datetime NOT NULL,
		`end_date` datetime NOT NULL,
		`text` varchar(255) NOT NULL,
		`rec_type` varchar(64) NOT NULL,
		`event_pid` int(11) NOT NULL,
		`event_length` int(11) NOT NULL,
		`user` int(11) NOT NULL,
		`lat` float(10,6) DEFAULT NULL,
		`lng` float(10,6) DEFAULT NULL,
		PRIMARY KEY (`event_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
	$wpdb->query($query);

	$query = "INSERT INTO ".$config->prefix.$config->events.
		" (`start_date`, `end_date`, `text`, `event_pid`, `event_length`) VALUES ".
		"(NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'The Scheduler Calendar was installed!', 0, 0);";
	$wpdb->query($query);
}

function create_compatible_fields() {
	global $wpdb, $config;

	$query = "ALTER TABLE `".$config->prefix.$config->events."` ADD COLUMN `lat` FLOAT(10,6) NULL";
	$res = $wpdb->query($query);

	$query = "ALTER TABLE `".$config->prefix.$config->events."` ADD COLUMN `lng` FLOAT(10,6) NULL";
	$res = $wpdb->query($query);
}

function update_config() {
	global $wpdb, $config;
	$query = "SELECT option_value FROM ".$config->prefix."options WHERE option_name='scheduler_xml'";
	$row = $wpdb->get_row($query);
	$xml = $row->option_value;
	$xml = str_replace("&ltesc;", "<", $xml);
	$xml = str_replace("&gtesc;", ">", $xml);
	$xml = preg_replace_callback("/<access_([^>]+)View>.*(true|false).*(true|false).*(true|false).*<\/access_([^>]+)Edit>/U", "replace_roles", $xml);
	$xml = addslashes($xml);
	$query = "UPDATE ".$config->prefix."options SET option_value='{$xml}' WHERE option_name='scheduler_xml'";
	$wpdb->query($query);
}

function replace_roles($matches) {
	$group = ($matches[1] === 'guest') ? '-1' : $matches[1];
	$result = "<group id=\"{$group}\">";
	$result .= "<view>{$matches[2]}</view>";
	$result .= "<add>{$matches[3]}</add>";
	$result .= "<edit>{$matches[4]}</edit>";
	$result .= "</group>";
	return $result;
}

function create_options() {
	global $wpdb, $config;
	$query = "CREATE TABLE `".$config->prefix."options` (
		`option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`option_name` varchar(64) NOT NULL DEFAULT '',
		`option_value` longtext NOT NULL,
		`autoload` varchar(20) NOT NULL DEFAULT 'yes',
		PRIMARY KEY (`option_id`),
		UNIQUE KEY `option_name` (`option_name`)
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
	$wpdb->query($query);
}

function set_default_options() {
	global $wpdb, $config;
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_php', '', 'yes')";
	$wpdb->query($query);
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_xml', '".$config->default_xml."', 'yes')";
	$wpdb->query($query);
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_php_version', '0', 'yes')";
	$wpdb->query($query);
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_xml_version', '1', 'yes')";
	$wpdb->query($query);
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_num', '5', 'yes')";
	$wpdb->query($query);
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_url', '', 'yes')";
	$wpdb->query($query);
	$query = "INSERT INTO ".$config->prefix."options VALUES (null, 'scheduler_stable_config', '".$config->default_xml."', 'yes')";
	$wpdb->query($query);
}

function create_scheduler_link() {
	global $wpdb, $scheduler, $config, $is_mu;
	$url_query = "SELECT `guid` FROM `".$config->prefix."posts` WHERE `post_title`='scheduler'";
	$url_exists = $wpdb->get_var($url_query);
	if ($url_exists == false) {
		$page_number = "SELECT MAX(`ID`) FROM `".$config->prefix."posts`";
		$page_number = $wpdb->get_var($page_number);
		if ($is_mu) {
			$scheduler_url = get_option('home').'/?page_id='.($page_number + 1);
		} else {
			$scheduler_url = get_option('siteurl')."/?page_id=".($page_number + 1);
		}
		$insert = "INSERT INTO `".$config->prefix."posts` (`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES
			(".($page_number + 1).", 1, '".date("Y-m-d H:i:s")."', '0000-00-00 00:00:00', '[[scheduler_plugin]]', 'scheduler', '', 'publish', 'closed', 'closed', '', 'scheduler', '', '', '".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."', '', 0, '".$scheduler_url."', 0, 'page', '', 0)";
		$wpdb->query($insert);
	}
	$url_query = "SELECT `guid` FROM `".$wpdb->prefix."posts` WHERE `post_title`='scheduler'";
	$url = $wpdb->get_var($url_query);
	$scheduler->set_option('settings_link', $url);
}


function scheduler_locale($id) {
	global $scheduler_locale;
	if (isset($scheduler_locale[$id])) return $scheduler_locale[$id];
	return $id;
}

?>