<?php

require('../../../wp-config.php');
define('WP_USE_THEMES', true);
require_once(ABSPATH.'wp-admin/admin.php');

if( is_site_admin() == false ) {
	wp_die(__('You do not have permission to access this page.'));
}

if( isset($_GET['id']) ) { 
	$id = intval($_GET['id']); 
} elseif(isset($_POST['id'])) { 
	$id = intval($_POST['id']); 
}

if( isset($_POST['ref']) == false && !empty($_SERVER['HTTP_REFERER']) ) {
	$_POST['ref'] = $_SERVER['HTTP_REFERER'];
}

switch($_POST['action']) {
	case "update":
		if (!isset($_POST['page_options'])) {
			wp_die();
		}
		$page_options = explode(",", $_POST['page_options']);
		for ($i = 0; $i < count($page_options); $i++) {
			if (isset($_POST[$page_options[$i]])) {
				$name = $page_options[$i];
				$value = $_POST[$page_options[$i]];
				$query = "UPDATE ".$wpdb->base_prefix."options SET `option_value`='".$value."' WHERE `option_name`='".$name."'";
				$wpdb->query($query);
			}
		}
		break;
}

wp_redirect(add_query_arg("updated", "true", $_POST['ref']));


?>