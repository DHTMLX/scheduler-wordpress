<?php

	$exportArray = Array();
	$exportArray_access['scheduler_guest_view'] = 'access_guestView';
	$exportArray_access['scheduler_guest_add'] = 'access_guestAdd';
	$exportArray_access['scheduler_guest_edit'] = 'access_guestEdit';

	$exportArray_access['scheduler_subscriber_view'] = 'access_subscriberView';
	$exportArray_access['scheduler_subscriber_add'] = 'access_subscriberAdd';
	$exportArray_access['scheduler_subscriber_edit'] = 'access_subscriberEdit';

	$exportArray_access['scheduler_contributor_view'] = 'access_contributorView';
	$exportArray_access['scheduler_contributor_add'] = 'access_contributorAdd';
	$exportArray_access['scheduler_contributor_edit'] = 'access_contributorEdit';

	$exportArray_access['scheduler_author_view'] = 'access_authorView';
	$exportArray_access['scheduler_author_add'] = 'access_authorAdd';
	$exportArray_access['scheduler_author_edit'] = 'access_authorEdit';

	$exportArray_access['scheduler_editor_view'] = 'access_editorView';
	$exportArray_access['scheduler_editor_add'] = 'access_editorAdd';
	$exportArray_access['scheduler_editor_edit'] = 'access_editorEdit';

	$exportArray_access['scheduler_admin_view'] = 'access_administratorView';
	$exportArray_access['scheduler_admin_add'] = 'access_administratorAdd';
	$exportArray_access['scheduler_admin_edit'] = 'access_administratorEdit';


	$exportArray_settings['scheduler_width'] = 'settings_width';
	$exportArray_settings['scheduler_height'] = 'settings_height';
	$exportArray_settings['scheduler_sidebar'] = 'settings_eventnumber';
	$exportArray_settings['scheduler_url'] = 'settings_link';
	$exportArray_settings['scheduler_posts'] = 'settings_posts';
	$exportArray_settings['scheduler_repeat'] = 'settings_repeat';
	$exportArray_settings['scheduler_first_day'] = 'settings_firstday';
	$exportArray_settings['scheduler_multiline'] = 'settings_multiday';
	$exportArray_settings['scheduler_on_click'] = 'settings_singleclick';
	$exportArray_settings['scheduler_day'] = 'settings_day';
	$exportArray_settings['scheduler_week'] = 'settings_week';
	$exportArray_settings['scheduler_month'] = 'settings_month';
	$exportArray_settings['scheduler_year'] = 'settings_year';
	$exportArray_settings['scheduler_agenda'] = 'settings_agenda';
	$exportArray_settings['scheduler_default'] = 'settings_defaultmode';
	$exportArray_settings['scheduler_debug'] = 'settings_debug';

	$exportArray['scheduler_version'] = '';

	$xml = '<config><settings>';
	foreach ($exportArray_settings as $k => $v) {
		if (get_option($k) !== false) {
			$value = get_option($k);
			if (($value == 'on')||($value == '1')) {
				$value = 'true';
			}
			if (($value == 'off')||($value == '')||($value == '0')) {
				$value = 'false';
			}
			$xml .= '<'.$v.'><![CDATA['.$value.']]></'.$v.'>';
		}
	}
	$xml .= '</settings><access>';

	foreach ($exportArray_access as $k => $v) {
		if (get_option($k) !== false) {
			$value = get_option($k);
			if (($value == 'on')||($value == '1')) {
				$value = 'true';
			}
			if (($value == 'off')||($value == '')||($value == '0')) {
				$value = 'false';
			}
			$xml .= '<'.$v.'><![CDATA['.$value.']]></'.$v.'>';
		}
	}

	$xml .= '</access><templates><templates_defaultdate>%d %M %Y</templates_defaultdate><templates_monthdate>%F %Y</templates_monthdate><templates_weekdate>%D, %F %d</templates_weekdate><templates_daydate>%d/%m/%Y</templates_daydate><templates_hourdate>%H:%i</templates_hourdate><templates_monthday>%d</templates_monthday><templates_apidate>%Y-%m-%d</templates_apidate><templates_xmldate>%Y-%m-%d %H:%i:%s</templates_xmldate><templates_minmin>5</templates_minmin><templates_hourheight>40</templates_hourheight><templates_starthour>0</templates_starthour><templates_endhour>24</templates_endhour></templates><customfields><customfield name="Text" dsc="text field description" type="textarea" old_name="Text" use_colors="false" height="50" /></customfields></config>';
	update_option('scheduler_xml', $xml);

?>