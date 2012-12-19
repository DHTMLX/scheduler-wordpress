<?php

class SchedulerConfig {

	private $xml;
	private $settings = Array();
	private $userid;
	private $log_file = 'com_scheduler_log.xml';
	private $tableUsers;
	private $scheduler_include_file = '/../scheduler_include.html';
	private $problems = Array();
	private $joomla;
	private $usermap = null;

	function __construct($hiddenName, $db, $userid = false, $joomla = false) {
		$this->connection = $db->connection;
		$this->prefix = $db->prefix;
		$this->base_prefix = $db->base_prefix;
		$this->tableEventsRec = $db->events;

		$this->table = $db->options;
		$this->fieldName = $db->options_name;
		$this->fieldValue = $db->options_value;

		$this->tableUsers = $db->users;
		$this->userIdField = $db->users_id;
		$this->userLoginField = $db->users_login;

		$this->userid = $userid;
		$this->joomla = $joomla;
		$this->default_xml = $db->default_xml;
		
		$this->parseConfig();
	}


	private function parseConfig() {
		$query = "SELECT `".$this->fieldName."`, `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_xml_version' OR `".$this->fieldName."`='scheduler_php_version' LIMIT 2";
		$res = mysql_query($query, $this->connection);
		$version = mysql_fetch_assoc($res);
		$versions[$version[$this->fieldName]] = $version[$this->fieldValue];
		$version = mysql_fetch_assoc($res);
		$versions[$version[$this->fieldName]] = $version[$this->fieldValue];
		$this->scheduler_xml_version = $versions['scheduler_xml_version'];

		if (($versions['scheduler_php_version'] != $versions['scheduler_xml_version'])||($versions['scheduler_xml_version'] == '')) {
			$this->problems = Array();
			$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_xml'";
			$res = mysql_query($query, $this->connection);
			$xml = str_replace('&ltesc;', '<', mysql_result($res, 0, $this->fieldValue));
			$xml = str_replace('&gtesc;', '>', $xml);
			$xml = str_replace('&#8242;', "'", $xml);

			$this->xml = $xml;
			@$this->xmlObj = simplexml_load_string($this->xml);
			if ($this->xmlObj === false) {
				$invalid_xml = $this->xml;
				$this->addProblem("There was error during configuration saving. Last stable configuration restored.<br>Error report saved to \"".$this->log_file."\"");
				$xml = str_replace('&ltesc;', '<', $this->getLastStableConfig());
				$xml = str_replace('&gtesc;', '>', $xml);
				$xml = str_replace('&#8242;', "'", $xml);
				@$this->xmlObj = simplexml_load_string($xml);
				if ($this->xmlObj === false) {
					@$this->xmlObj = simplexml_load_string($this->default_xml);
					$this->setLastStableConfig($xml);
				}
			} else {
				$this->setLastStableConfig($xml);
				$invalid_xml = false;
			}
			if ((string) $this->xmlObj[0] == 'restore_default') {
				$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($this->default_xml, $this->connection)."' WHERE `".$this->fieldName."` ='scheduler_xml' LIMIT 1 ;";
				$res = mysql_query($query);
				$this->xmlObj = simplexml_load_string($this->default_xml);
				$this->xml = $this->default_xml;
				$this->removeCustomFieldsFromDB();
			}
			$this->settingsParse();
			$this->accessParse();
			$this->templatesParse();
			$this->customfieldsParse();
			$this->skinsParse();
			$this->php = $this->serializeOptions($this->settings);
			$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($this->php, $this->connection)."' WHERE `".$this->fieldName."` ='scheduler_php' LIMIT 1 ;";
			$res = mysql_query($query);
			$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".$versions['scheduler_xml_version']."' WHERE `".$this->fieldName."` ='scheduler_php_version' LIMIT 1 ;";
			$res = mysql_query($query);
			$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".$this->settings['settings_eventnumber']."' WHERE `".$this->fieldName."`='sidebar_num' LIMIT 1";
			$res = mysql_query($query);
			if ($invalid_xml != false) {
				$this->addToLog($invalid_xml, 'invalid_config');
			}
		} else {
			$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_php' LIMIT 1";
			$res = mysql_query($query);
			$this->php = mysql_result($res, 0, $this->fieldValue);
		}
		$this->parseOptions();
	}

	protected function setLastStableConfig($xml) {
		$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($xml, $this->connection)."' WHERE `".$this->fieldName."` = 'scheduler_stable_config' LIMIT 1";
		$res = mysql_query($query);
	}

	protected function getLastStableConfig() {
		$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_stable_config' LIMIT 1";
		$res = mysql_query($query);
		if (mysql_num_rows($res) == 0) {
			return false;
		} else {
			$stable_xml = mysql_result($res, 0, $this->fieldValue);
			return $stable_xml;
		}
	}

	public function get_option($name) {
		if (isset($this->settings[$name])) {
			return $this->settings[$name];
		} else {
			return false;
		}
	}


	public function add_option($group, $name, $value) {
		$this->settings[$name] = $value;
		$this->php = $this->serializeOptions($this->settings);
		$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($this->php, $this->connection)."' WHERE `".$this->fieldName."` ='scheduler_php' LIMIT 1 ;";
		$res = mysql_query($query);
		$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_xml'";
		$res = mysql_query($query, $this->connection);
		$this->xml = mysql_result($res, 0, $this->fieldValue);
		$preg = "/(<".$group.">)(.*)(<\/".$group.">)/";
		$this->xml = preg_replace($preg, "$1<".$name.">".$value."</".$name.">$2$3", $this->xml);
		$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($this->xml, $this->connection)."' WHERE `".$this->fieldName."` ='scheduler_xml' LIMIT 1 ;";
		$res = mysql_query($query);
	}


	public function set_option($name, $value) {
		$this->settings[$name] = $value;
		$this->php = $this->serializeOptions($this->settings);
		$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($this->php, $this->connection)."' WHERE `".$this->fieldName."` ='scheduler_php' LIMIT 1 ;";
		$res = mysql_query($query);
		$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_xml'";
		$res = mysql_query($query, $this->connection);
		$this->xml = mysql_result($res, 0, $this->fieldValue);
		$preg = "/(<".$name.">)(.*)(<\/".$name.">)/";
		$this->xml = preg_replace($preg, "<".$name.">".$value."</".$name.">", $this->xml);
		$query = "UPDATE `".$this->prefix.$this->table."` SET `".$this->fieldValue."` = '".mysql_real_escape_string($this->xml, $this->connection)."' WHERE `".$this->fieldName."` ='scheduler_xml' LIMIT 1 ;";
		$res = mysql_query($query);
	}


	private function settingsParse() {
		$settings = $this->xmlObj->settings->children();
		foreach ($settings as $k=>$v) {
			$this->settings[$k] = (string) $v;
		}
	}

	private function skinsParse() {
		if (!isset($this->xmlObj->skins)) return;
		$skins = $this->xmlObj->skins->children();
		foreach ($skins as $k=>$v) {
			$this->settings[$k] = (string) $v;
		}
	}

	private function accessParse() {
		$access = $this->xmlObj->access->children();
		$this->settings['access'] = array();
		foreach ($access as $k=>$v) {
			if (isset($v->attributes()->id)) {
				$id = (string) $v->attributes()->id;
				$group = array(
					'id' => $id,
					'view' => (string) $v->view,
					'add' => (string) $v->add,
					'edit' => (string) $v->edit
				);
				$this->settings['access'][$id] = $group;
			} else {
				$this->settings[$k] = (string) $v;
			}
		}
	}


	private function templatesParse() {
		$templates = $this->xmlObj->templates->children();
		foreach ($templates as $k=>$v) {
			$value = (string) $v;
			$value = str_replace("\n", "", $value);
			$this->settings[$k] = $value;
		}
	}


	private function customfieldsParse() {
		$customfields = $this->xmlObj->customfields->children();
		$this->settings['customfields'] = Array();
		$this->settings['units'] = array();
		foreach ($customfields as $k=>$v) {
			$cf = Array();
			$cf['name'] = strtolower(str_replace(" ","",(string) $v->attributes()->name));
			$cf['dsc'] = (string) $v->attributes()->dsc;
			$cf['old_name'] = (string) $v->attributes()->old_name;
			$cf['type'] = (string) $v->attributes()->type;
			$cf['use_colors'] = (string) $v->attributes()->use_colors;
			$cf['units'] = (string) $v->attributes()->units;
			$cf['timeline'] = (string) $v->attributes()->timeline;
			if ($cf['type'] == 'select') {
				$cf['secondscale'] = (string) $v->attributes()->timeline_secondscale;
				$options = $v->children();
				$i = 0;
				foreach ($options as $optK=>$optV) {
					$cf['options'][$i]['name'] = (string) $optV;
					$cf['options'][$i]['color'] = (string) $optV->attributes()->color;
					$cf['options'][$i]['hide'] = isset($optV->attributes()->hide) ? (string) $optV->attributes()->hide : false;
					$i++;
				}
			} else {
				$cf['height'] = (string) $v->attributes()->height;
			}
			$this->settings['customfields'][] = $cf;
		}
		$this->customfieldsCreate($this->settings['customfields']);
	}


	private function customfieldsCreate($customfields) {
		$lightbox = array();
		$labels = array();
		$processed = array();
		$dsc = array();
		$css = '';
		$event_class = '';

		$new_fields = Array('event_id', 'start_date', 'end_date', 'rec_type', 'event_pid', 'event_length', 'user', 'lat', 'lng');

		// getting all real existing fields
		$old_fields = array();
		$query = 'SELECT * FROM '.$this->prefix.$this->tableEventsRec.' LIMIT 1';
		$res = mysql_query($query);
		for ($i = 0; $i < mysql_num_fields($res); $i++)
			$old_fields[] = mysql_field_name($res, $i);

		if ($this->settings["settings_posts"] == 'true') {
			$lightbox[] = '{name:"text", height: 150, map_to: "text", type:"textarea", focus:true}';
			$dsc[] = 'Text';
			$labels[] = "scheduler.locale.labels.section_text = 'Text';";
			$processed[] = $new_fields[] = 'text';
		} else {

			for ($i = 0; $i < count($customfields); $i++) {
				$field_string = '';
				$field = $customfields[$i];
				$name = $field['escaped'] = strtolower(preg_replace('/[\/\\\.\| ]/', '', $field['name']));
				if (strlen($name) < 1) {
					$this->addProblem("Incorrect custom field name '".$field['name']."'.");
					continue;
				}
				if (in_array($name, $new_fields)) {
					$this->addProblem("Field '".$name."' is already used. Change name of this field.");
					continue;
				}

				$dsc[] = $field['dsc'];
				$name_old = strtolower(str_replace(' ', '', $field['old_name']));
				if (($field['type'] == 'select') && ((!isset($field['options'])) || (count($field['options']) < 1)) ) {
					$this->addProblem("Field '".$name."' has empty option set. Its type is changed to 'text'");
					$field['type'] = 'textarea';
				}

				$focus = ($name == 'text') ? ', focus: true' : '';
				if ($field['type'] == 'textarea') {
					// addition text custom field
					$height = str_replace("px", "", $field['height']);
					if ($height == '') $height = '100';
					$field_string .= '{name:"'.$name.'", height:'.$height.', map_to:"'.$name.'", type:"textarea"'.$focus.'}';
				} else {
					// addition select custom field
					$options = array();
					$keys = array();
					$event_class = array();
					// parse options set
					for ($j = 0; $j < count($field['options']); $j++) {
						$options[] = "{key:\"{$name}_{$j}\", label:\"{$field['options'][$j]['name']}\"}";
						$keys[] = "{$name}_{$j}:{$field['options'][$j]['name']}";
						if ($field['use_colors'] == 'true') {
							$css .= $this->event_css($name."_".$j, $field['options'][$j]['color']);
							$event_class[] = "case '".$name."_".$j."': return '".$name."_".$j."';";
						}
					}
					$field_string .= '{name:"'.$name.'", height: 21, type: "select"'.$focus.', map_to: "'.$name.'", options:[ ';
					$field_string .= implode(',', $options);
					$field_string .= ']}';

					// preparing options hash for loading into grid
					$this->settings['gridkeys_'.$name] = implode(',', $keys);

					// applying event colors
					if ($field['use_colors'] == 'true') {
						$event_class = "scheduler.templates.event_class = function(start_date, end_date, event) { switch(event.{$name}) {".implode('', $event_class);
						$event_class .= "default: return '".$name."_0'} };";
					}

					// units creating
					$unit = $this->unit($field);
					if ($unit) $this->settings['units_'.$field['name']] = $unit;

					// timeline creating
					$timeline = $this->timeline($field);
					if ($timeline) $this->settings['timeline_'.$field['name'].'timeline'] = $timeline;

				}
				$lightbox[] = $field_string;
				$new_fields[] = $name;
				$labels[] = "scheduler.locale.labels.section_".$name." = '".$field['dsc']."';";
				$processed[] = $name;

				// creates field in database table
				if (!in_array($name, $old_fields)) {
					if (($name !== $name_old) && (!in_array($name_old, $new_fields)) && $name_old !== '')
						$query = "ALTER TABLE `".$this->prefix.$this->tableEventsRec."` CHANGE `".$name_old."` `".$name."` TEXT NOT NULL ";
					else
						$query = "ALTER TABLE `".$this->prefix.$this->tableEventsRec."` ADD `".$name."` TEXT NOT NULL ";
					$res = mysql_query($query, $this->connection);
				}
			}
		}
		if ($this->settings['settings_repeat'] == 'true')
			$lightbox[] = '{name:"recurring", height:115, type:"recurring", map_to:"rec_type", button:"recurring"}';
		$time_type = ($this->settings['settings_minical'] == 'true') ? 'calendar_time' : 'time';
		$lightbox[] = '{name:"time", height:72, type:"'.$time_type.'", map_to:"auto"}';

		// removing non-using custom fields
		for ($i = 0; $i < count($old_fields); $i++) {
			if (!in_array($old_fields[$i], $new_fields)) {
				$query = "ALTER TABLE `".$this->prefix.$this->tableEventsRec."` DROP `".$old_fields[$i]."`";
				$res = mysql_query($query, $this->connection);
			}
		}

		$this->settings['customfields'] = 'scheduler.config.lightbox.sections=['.implode(',', $lightbox).'];';
		$this->settings['customfieldsList'] = implode(',', $processed);
		$this->settings['customfieldsLabels'] = implode(',', $dsc);
		$this->settings['customfieldsNames'] = implode('', $labels);
		$this->settings['customfieldsCSS'] = $css;
		$this->settings['customfieldsTemplate'] = $event_class;
		return true;
	}


	/*! generates unit-view code
	 */
	private function unit($field) {
		if (($field['units'] == 'true') && (isset($field['options'])) && (count($field['options']) > 0) ) {
			$options = array();
			for ($j = 0; $j < count($field['options']); $j++) {
				if ($field['options'][$j]['hide'] == '0') {
					$options[] = "{key:\"{$field['escaped']}_{$j}\", label:\"{$field['options'][$j]['name']}\"}";
				}
			}

			$unit = "scheduler.locale.labels.{$field['name']}_tab = \"{$field['dsc']}\";";
			$unit .= "scheduler.createUnitsView({ name:\"{$field['name']}\", property:\"{$field['name']}\", list: [ ".implode(',', $options)." ]});";
		} else {
			$unit = false;
		}
		return $unit;
	}


	/*! implements events coloring
	 */
	private function event_css($name, $color) {
		$css = ".dhx_cal_event.{$name} div { background-color: {$color} !important; } ";
		$css .= ".dhx_cal_event_line.{$name} { background-color: {$color} !important; background-image: none !important; } ";
		$css .= ".dhx_cal_event_clear.{$name} { background-color: {$color} !important; background-image: none !important; } ";
		if ($this->settings['settings_year'] == 'true')
			$css .= ".dhx_month_head.dhx_year_event.{$name} { background-color: {$color} !important; background-image: none !important; } ";
		return $css;
	}


	/*! generates timeline-view code
	 */
	private function timeline($field) {
		$incorrect = array("", "undefined", "null", "off");
		if ((in_array($field['timeline'], $incorrect) == false) && ((isset($field['options'])) && (count($field['options']) > 0)) ) {
			// preparing options list
			$options = array();
			for ($j = 0; $j < count($field['options']); $j++)
				if ($field['options'][$j]['hide'] == '0')
					$options[] = "{key:\"{$field['escaped']}_{$j}\", label:\"{$field['options'][$j]['name']}\"}";

			switch ($field['timeline']) {
				case 'day':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H:%i',
							'x_step' => '2',
							'x_size' => '12',
							'x_start' => '0',
							'x_length' => '12',
							'render' => 'bar',
							'after' => '',
							'second_scale' => '{ x_unit: "day", x_date: "%F %d" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H:%i',
							'x_step' => '2',
							'x_size' => '12',
							'x_start' => '0',
							'x_length' => '12',
							'render' => 'bar',
							'after' => '',
							'second_scale' => 'false'
						);
					break;
				case 'working_day':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H:%i',
							'x_step' => '2',
							'x_size' => '6',
							'x_start' => '4',
							'x_length' => '12',
							'render' => 'bar',
							'after' => '',
							'second_scale' => '{ x_unit: "day", x_date: "%F %d" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H:%i',
							'x_step' => '2',
							'x_size' => '6',
							'x_start' => '4',
							'x_length' => '12',
							'render' => 'bar',
							'after' => '',
							'second_scale' => 'false'
						);
					break;
				case 'threedays':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H:%i',
							'x_step' => '6',
							'x_size' => '12',
							'x_start' => '0',
							'x_length' => '12',
							'render' => 'bar',
							'after' => '',
							'second_scale' => '{ x_unit: "day", x_date: "%F %d" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'day',
							'x_date' => '%F %d',
							'x_step' => '1',
							'x_size' => '3',
							'x_start' => '0',
							'x_length' => '3',
							'render' => 'bar',
							'after' => '',
							'second_scale' => 'false'
						);
					break;
				case 'week':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H:%i',
							'x_step' => '12',
							'x_size' => '14',
							'x_start' => '0',
							'x_length' => '14',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { return scheduler.date.week_start(date); };',
							'second_scale' => '{ x_unit: "day", x_date: "%M %d, %D" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'day',
							'x_date' => '%M %d, %D',
							'x_step' => '1',
							'x_size' => '7',
							'x_start' => '0',
							'x_length' => '7',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { return scheduler.date.week_start(date); };',
							'second_scale' => 'false'
						);
					break;
				case 'working_week':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'hour',
							'x_date' => '%H %i',
							'x_step' => '12',
							'x_size' => '10',
							'x_start' => '0',
							'x_length' => '14',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { return scheduler.date.week_start(date); };',
							'second_scale' => '{ x_unit: "day", x_date: "%M %d, %D" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'day',
							'x_date' => '%M %d, %D',
							'x_step' => '1',
							'x_size' => '5',
							'x_start' => '0',
							'x_length' => '7',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { return scheduler.date.week_start(date); };',
							'second_scale' => 'false'
						);
					break;
				case 'month':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'day',
							'x_date' => '%d',
							'x_step' => '1',
							'x_size' => '31',
							'x_start' => '0',
							'x_length' => '31',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { console.log("test: ", scheduler.date.month_start(date)); return scheduler.date.month_start(date); };',
							'second_scale' => '{ x_unit: "month", x_date: "%F" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'month',
							'x_date' => '%F',
							'x_step' => '1',
							'x_size' => '1',
							'x_start' => '0',
							'x_length' => '1',
							'render' => 'bar',
							'after' => '',
							'second_scale' => 'false'
						);
					break;
				case 'year':
					if ($field['secondscale'] == 'true')
						$timeline = Array(
							'x_unit' => 'month',
							'x_date' => '%M',
							'x_step' => '1',
							'x_size' => '12',
							'x_start' => '0',
							'x_length' => '12',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { return scheduler.date.year_start(date); };',
							'second_scale' => '{ x_unit: "year", x_date: "%Y" }'
						);
					else
						$timeline = Array(
							'x_unit' => 'year',
							'x_date' => '%Y',
							'x_step' => '1',
							'x_size' => '1',
							'x_start' => '0',
							'x_length' => '1',
							'render' => 'bar',
							'after' => 'scheduler.date.'.$field['name'].'timeline_start = function(date) { return scheduler.date.year_start(date); };',
							'second_scale' => 'false'
						);
					break;
			}
			
			$timeline = "scheduler.locale.labels.{$field['name']}timeline_tab = '{$field['dsc']}';
scheduler.createTimelineView({
	name: '{$field['name']}timeline',
	x_unit: '{$timeline['x_unit']}',
	x_date: '{$timeline['x_date']}',
	x_step: {$timeline['x_step']},
	x_size: {$timeline['x_size']},
	x_start: {$timeline['x_start']},
	x_length: {$timeline['x_length']},
	y_property:'{$field['name']}',
	render: '{$timeline['render']}',
	y_unit: [".implode(',', $options)."],
	second_scale: {$timeline['second_scale']}
});
{$timeline['after']}\n";
			$timeline = str_replace("\n", "", $timeline);
		} else {
			$timeline = false;
		}
		return $timeline;
	}


	private function removeCustomFieldsFromDB() {
		$query = 'SELECT * FROM '.$this->prefix.$this->tableEventsRec.' LIMIT 1';
		$res = mysql_query($query);
		$i = 0;
		$fields = array();
		for ($i = 0; $i < mysql_num_fields($res); $i++) {
			$field = mysql_field_name($res, $i);
			$fields[] = $field;
		}
		$fieldsFinal = Array('event_id', 'start_date', 'end_date', 'rec_type', 'event_pid', 'event_length', 'text', 'user', 'lat', 'lng');
		for ($i = 0; $i < count($fields); $i++) {
			if (!in_array($fields[$i], $fieldsFinal)) {
				$query = "ALTER TABLE `".$this->prefix.$this->tableEventsRec."` DROP `".$fields[$i]."`";
				$res = mysql_query($query, $this->connection);
			}
		}
	}


	private function parseOptions() {
		$this->settings['units'] = array();
		$this->settings['timelines'] = array();
		$this->settings['access'] = array();
		$options = explode("\n", $this->php);
		for ($i = 0; $i < count($options) - 1; $i++) {
			$opt = trim($options[$i]);
			$opt = explode('{*:*}', $opt);
			$subopt = explode("_", $opt[0]);
			switch ($subopt[0]) {
				case 'units':
					$name = substr(trim($opt[0]), 6);
					$this->settings['units'][$name] = trim($opt[1]);
					break;
				case 'timeline':
					$name = substr(trim($opt[0]), 9);
					$this->settings['timelines'][$name] = trim($opt[1]);
					break;
				case 'problem':
//					$this->problems[] = trim($opt[1]);
					break;
				case 'access':
					$id = $subopt[1];
					$action = $subopt[2];
					if (!isset($this->settings['access'][$id]))
						$this->settings['access'][$id] = array('id' => $id);
					$this->settings['access'][$id][$action] = trim($opt[1]);
					break;
				default:
					$this->settings[trim($opt[0])] = trim($opt[1]);
					break;
			}
		}
		if ((!isset($this->settings['templates_agendatime']))||($this->settings['templates_agendatime'] == '')) {
			$this->add_option('templates', 'templates_agendatime', '30');
			$this->settings['templates_agendatime'] = '30';
		}
	}


	private function serializeOptions() {
		$php = '';
		$delim = '{*:*}';
		foreach ($this->settings as $k=>$v) {
			if (!is_array($v)) {
				$php .= $k.$delim.$v."\n";
			}
		}
		foreach ($this->problems as $k=>$v) {
			$php .= "problem_".$k.$delim.$v."\n";
		}
		foreach ($this->settings['access'] as $id => $v) {
			$php .= "access_".$id."_view".$delim.$v['view']."\n";
			$php .= "access_".$id."_add".$delim.$v['add']."\n";
			$php .= "access_".$id."_edit".$delim.$v['edit']."\n";
		}
		return $php;
	}


	public function schedulerInit($usergroups, $locale, $url, $loader_url) {
		$url = $this->replaceHostInURL($url);
		$loader_url = $this->replaceHostInURL($loader_url);
		$settings = $this->settings;
		
		$settings = $this->compatible($settings);

		if ($this->settings['settings_debug'] == 'true') {
			$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_xml'";
			$res = mysql_query($query, $this->connection);
			$xml = mysql_result($res, 0, $this->fieldValue);
			$this->addToLog($xml, $usergroups);
		}

		if (!$this->can('view', $usergroups)) {
			return '';
		}
		$scheduler = "<script src=\"".$url."dhtmlxscheduler.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		$scheduler .= "<link rel=\"stylesheet\" href=\"".$url."dhtmlxscheduler_wp.css\" type=\"text/css\" charset=\"utf-8\">";
		$scheduler .= "<link rel=\"stylesheet\" href=\"".$url."dhtmlxscheduler.css\" type=\"text/css\" charset=\"utf-8\">";

		if ($settings['use'] == 'true')
			$scheduler .= "<link rel=\"stylesheet\" href=\"".$url."skin_builder/custom/dhtmlxscheduler_custom.css\" type=\"text/css\" charset=\"utf-8\">";

		$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_url.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";

		if (count($settings['units']) > 0) {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_units.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}
		if (count($settings['timelines']) > 0) {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_timeline.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_readonly.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";

		if ($settings['settings_repeat'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_recurring.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if ($settings['settings_year'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_year_view.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if ($settings['settings_agenda'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_agenda_view.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}
		
		if ($settings['settings_week_agenda'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_week_agenda.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}
		if ($settings['settings_map'] == 'true') {
			$scheduler .= "<script src=\"http://maps.google.com/maps/api/js?sensor=false\" type=\"text/javascript\"></script>";
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_map_view.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if ($settings['settings_expand'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_expand.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if ($settings['settings_collision'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_collision.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if ($settings['settings_pdf'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_pdf.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}
		if ($settings['settings_ical'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_serialize.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if ($settings['settings_minical'] == 'true') {
			$scheduler .= "<script src=\"".$url."ext/dhtmlxscheduler_minical.js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
		}

		if (strlen($locale) > 0) {
			$scheduler .= "<script src=\"".$url."locale/locale_".$locale.".js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
			if ($settings['settings_repeat'] == 'true' && file_exists(dirname(__FILE__).'/locale/recurring/locale_recurring_'.$locale.'.js')) {
				$scheduler .= "<script src=\"".$url."locale/recurring/locale_recurring_".$locale.".js\" type=\"text/javascript\" charset=\"utf-8\"></script>";
			}
		}

		$scheduler .= "<style>".$settings['customfieldsCSS']."</style>";

		$scheduler .= "<script type=\"text/javascript\" charset=\"utf-8\">";

		$scheduler .= "scheduler.config.details_on_create=true;";
		$scheduler .= "scheduler.config.details_on_dblclick=true;";
		$scheduler .= "scheduler.config.default_date = \"".$settings['templates_defaultdate']."\";";
		$scheduler .= "scheduler.config.month_date = \"".$settings['templates_monthdate']."\";";
		$scheduler .= "scheduler.config.week_date = \"".$settings['templates_weekdate']."\";";
		$scheduler .= "scheduler.config.day_date = \"".$settings['templates_daydate']."\";";
		$scheduler .= "scheduler.config.hour_date = \"".$settings['templates_hourdate']."\";";
		$scheduler .= "scheduler.config.month_day = \"".$settings['templates_monthday']."\";";
		$scheduler .= "scheduler.config.api_date = \"%Y-%m-%d %H:%i:%s\";";
		$scheduler .= "scheduler.config.xml_date = \"%Y-%m-%d %H:%i:%s\";";
		$scheduler .= "scheduler.config.time_step = ".$settings['templates_minmin'].";";
		$scheduler .= "scheduler.config.hour_size_px = ".$settings['templates_hourheight'].";";
		$scheduler .= "scheduler.config.first_hour = ".$settings['templates_starthour'].";";
		$scheduler .= "scheduler.config.last_hour = ".$settings['templates_endhour'].";";
		$scheduler .= "scheduler.config.agenda_start = new Date();";
		$scheduler .= "scheduler.config.agenda_end = scheduler.date.add(new Date(), ".$settings['templates_agendatime'].", \"day\");";

		$scheduler .= "scheduler.templates.event_text=function(start,end,event){ ".htmlspecialchars_decode($settings['templates_eventtext'])." };";
		$scheduler .= "scheduler.templates.event_header=function(start,end,event){ ".htmlspecialchars_decode($settings['templates_eventheader'])." };";
		$scheduler .= "scheduler.templates.event_bar_text=function(start,end,event){ ".htmlspecialchars_decode($settings['templates_eventbartext'])." };";
		$scheduler .= "scheduler.locale.labels.week_agenda_tab = 'Week A.';";
		
		$scheduler .= $this->getExpandFix();

		if ($settings['settings_map'] == 'true') {
			$scheduler .= "scheduler.locale.labels.map_tab = \"Map\";";
			$scheduler .= "scheduler.xy.map_date_width = 140;";
			$scheduler .= "scheduler.xy.map_description_width = 150;";
			$scheduler .= "scheduler.config.map_inital_zoom = 8;";
			$scheduler .= "scheduler.config.map_end = scheduler.date.add(new Date(), ".$settings['templates_agendatime'].", \"day\");";
		}
			if ($settings['settings_fullday'] == 'true') {
				$scheduler .= "scheduler.config.full_day = true;";
			}
			if ($settings['settings_marknow'] == 'true') {
				$scheduler .= "scheduler.config.mark_now = true;";
			}
			if ($settings['settings_firstday'] == 'true') {
				$scheduler .= "scheduler.config.start_on_monday = false;";
			} else {
				$scheduler .= "scheduler.config.start_on_monday = true;";
			}

			if ($settings['settings_multiday'] == 'true') {
				$scheduler .= "scheduler.config.multi_day = true;";
			} else {
				$scheduler .= "scheduler.config.multi_day = false;";
			}

			if ($settings['settings_singleclick'] == 'true') {
				$scheduler .= "(function(){
					var old = scheduler._click.dhx_cal_data;
					scheduler._click.dhx_cal_data=function(e){
						var trg = e?e.target:event.srcElement;
						var id = scheduler._locate_event(trg);
						 if (!id && !scheduler._lightbox_id) {
							scheduler._on_dbl_click(e||event);
						} else {
							old.call(scheduler, e)
						}
					}
				})();\n";
			}

			if ($settings["settings_posts"] == 'true'){
				$scheduler .= "scheduler.config.dblclick_create = false;
					scheduler.config.drag_create= false;
					scheduler.config.readonly_form = true;
					scheduler.locale.labels.confirm_recurring = '';
					scheduler.attachEvent('onClick',function(id){ scheduler.showLightbox(id); return false; });
					scheduler.attachEvent('onBeforeDrag',function(){return false;});";
			} else {
				if (!$this->can('add', $usergroups)) {
					$scheduler .= "scheduler.config.dblclick_create = false;
						scheduler.config.drag_create= false;";
				}
				if (!$this->can('edit', $usergroups) && $this->can('add', $usergroups)) {
					// on/off readonly mode for lightbox
					$check_event = "function(id) {
						if (!id) { scheduler.config.readonly_form = false; return true; }
						scheduler.config.readonly_form = true; scheduler.showLightbox(id); return false; }";

					$scheduler .= "scheduler.attachEvent('onBeforeDrag',{$check_event});
						scheduler.attachEvent('onClick',{$check_event});
						scheduler.attachEvent('onDblClick',{$check_event});";
				}
				if (!$this->can('add', $usergroups) && !$this->can('edit', $usergroups)) {
					$scheduler .= "scheduler.config.readonly_form = true;
						scheduler.locale.labels.confirm_recurring = '';
						scheduler.config.drag_create = false;
						scheduler.config.dblclick_create = false;
						scheduler.attachEvent('onClick',function(id){ scheduler.showLightbox(id); return false; });
						scheduler.attachEvent('onBeforeDrag',function(){return false;});";
				}
			}


			$cfs = Array();
			foreach ($settings['units'] as $k => $v) {
				$scheduler .= $v;
				$kl = strtolower($k);
				$settings['settings_'.$kl] = 'true';
				$cfs[] = $kl;
			}
			foreach ($settings['timelines'] as $k => $v) {
				$scheduler .= $v;
				$kl = strtolower($k);
				$settings['settings_'.$kl] = 'true';
				$cfs[] = $kl;
			}

			$defaultmode = $settings['settings_defaultmode'];
			if ($settings['settings_'.$defaultmode] == 'false') {
				$defaultmode = 'month';
				$modes = Array('day', 'week', 'month', 'agenda', 'week_agenda', 'year', 'map');
				foreach ($cfs as $v)
					$modes[] = $v;
				for ($i = 0; $i < count($modes); $i++) {
					if ($settings['settings_'.$modes[$i]] == 'true') {
						$defaultmode = $modes[$i];
						break;
					}
				}
			}

			$scheduler .= "var default_mode = '{$defaultmode}';\n";
			@$include_content = file_get_contents(dirname(__FILE__).$this->scheduler_include_file);
			if ($include_content) {
				$scheduler .= "</script>";
				$scheduler .= $include_content;
				$scheduler .= "<script type=\"text/javascript\" charset=\"utf-8\">";
			}

			$scheduler .= "window.onload = function init() {";
			$scheduler .= "scheduler.config.export_ = {};";
			if ($settings['settings_pdf'] == 'true') {
				$scheduler .= "scheduler.config.export_.pdf_url = \"http://dhtmlxscheduler.appspot.com/export/pdf\";";
				$scheduler .= "scheduler.config.export_.pdf_mode = \"color\";";
				$scheduler .= "dhtmlxEvent(document.getElementById('export_pdf'), 'click', function() {
					scheduler.toPDF(scheduler.config.export_.pdf_url, scheduler.config.export_.pdf_mode);
				});";
			}
			if ($settings['settings_ical'] == 'true') {
				$scheduler .= "dhtmlxEvent(document.getElementById('export_ical'), 'click', function() {
					var form = document.getElementById('ical_form');
					form.elements.data.value = scheduler.toICal();
					form.submit();
				});";
			}
			$scheduler .= "\n".$settings['customfieldsNames']."\n";
			$scheduler .= $settings['customfields']."\n";

			$scheduler .= (isset($settings['customfieldsTemplate']) ? $settings['customfieldsTemplate'] : '')."\n";
			$scheduler .= "
				scheduler.init(\"scheduler_here\",null,default_mode);
				scheduler.load(\"".$loader_url."\"+scheduler.uid());
				var dp = new dataProcessor(\"".$loader_url."\"+scheduler.uid());
				dp.init(scheduler);";

			if ($settings["privatemode"] == "ext") {
				$scheduler .= "scheduler.attachEvent('onEventLoading', check_user);";
				$scheduler .= "scheduler.attachEvent('onBeforeDrag', check_user_before_drag);";
				$scheduler .= "function check_user_before_drag(event_id, native_event_object){
						if (event_id == null) {
							return true;
						}
						var event = scheduler.getEvent(event_id);
						if (event.user == '".$this->userid."') {
							return true;
						} else {
							return false;
						}
					}";
				$scheduler .= "function check_user(event){
						if (event.user == '".$this->userid."') {
							event.readonly = false;
						} else {
							event.readonly = true;
						}
						return event;
					}";
			}
			$scheduler .= "dp.attachEvent('onAfterUpdate', after_update);";
			$scheduler .= "function after_update(sid, action, tid, xml_node) {
					var userid = xml_node.getAttribute('user');
					if (action != 'deleted') {
						var event = scheduler.getEvent(sid);
						event.user = userid;
					}
				}";

			if ($settings['settings_debug'] == 'true') {
				$scheduler .= "dhtmlxError.catchError(\"LoadXML\",function(a,b,c){
					var html = \"The text below, contains details about of server side problem.<hr><pre style=\\\"font-size: 8pt;\\\">\"+ c[0].responseText + \"</pre>\";
					document.body.innerHTML = html;
					})";
			}

			$scheduler .= "};";

			if ($settings['settings_minical'] == 'true') {
				$scheduler .= "function show_minical(){
					if (scheduler.isCalendarVisible())
						scheduler.destroyCalendar();
					else
						scheduler.renderCalendar({
							position:\"dhx_minical_icon\",
							date:scheduler._date,
							navigation:true,
							handler:function(date,calendar){
								scheduler.setCurrentView(date);
								scheduler.destroyCalendar()
							}
						});
				}";
			}


		$scheduler .= "</script>
			<div id=\"scheduler_here\" class=\"dhx_cal_container\" style='width:".$settings['settings_width']."; height:".$settings['settings_height'].";'>
				<div class=\"dhx_cal_navline\">";
		if ($settings['settings_pdf'] == 'true')
			$scheduler .= "<div class=\"dhx_cal_export pdf\" id=\"export_pdf\" style=\"top: 2px; left: 2px;\" title=\"Export to PDF\">&nbsp;</div>";
		if ($settings['settings_ical'] == 'true')
			$scheduler .= "<div class=\"dhx_cal_export ical\" id=\"export_ical\" style=\"top: 2px; left: 24px;\" title=\"Export to iCal\">&nbsp;</div>";
		$scheduler .= "
					<div class=\"dhx_cal_prev_button\">&nbsp;</div>
					<div class=\"dhx_cal_next_button\">&nbsp;</div>
					<div class=\"dhx_cal_today_button\"></div>
					<div class=\"dhx_cal_date\"></div>";
			if ($settings['settings_minical'] == 'true') {
				$scheduler .= "<div class=\"dhx_minical_icon\" id=\"dhx_minical_icon\" onclick=\"show_minical()\">&nbsp;</div>";
			}
			$modes = array('settings_day', 'settings_week', 'settings_month', 'settings_year', 'settings_agenda', 'settings_week_agenda', 'settings_map');
			foreach ($settings['units'] as $k => $v) {
				$modes[] = 'settings_'.$k;
				$settings['settings_'.$k] = 'true';
			}
			foreach ($settings['timelines'] as $k => $v) {
				$modes[] = 'settings_'.$k;
				$settings['settings_'.$k] = 'true';
			}
			$modesNumber = 0;
			for ($i = 0; $i < count($modes); $i++) {
				if ($settings[$modes[$i]] == 'true') {
					$modesNumber++;
				}
			}
			for ($i = 0; $i < count($modes); $i++) {
				if ($settings[$modes[$i]] == 'true') {
					$modesNumber--;
					$name = substr($modes[$i], 9);
					$scheduler .= "<div class=\"dhx_cal_tab\" name=\"".$name."_tab\" style=\"right:".(20 + 64*$modesNumber)."px;\"></div>";
				}
			}

			$scheduler .= "
				</div>
			<div class=\"dhx_cal_header\">
			</div>
			<div class=\"dhx_cal_data\">
			</div>
			<div style='position:absolute; bottom:5px; right:20px; font: Tahoma 8pt; color:black;'>
				Powered by <a href='http://dhtmlx.com' target='_blank' style='color:#444444;'>dhtmlxScheduler</a>
			</div>
		</div>";
		if ($settings['settings_ical'] == 'true') {
			$ical_url = str_replace("loadxml", "ical", $loader_url);
			$ical_url = str_replace("dhtmlxSchedulerConfiguratorLoad.php", "dhtmlxSchedulerIcal.php", $ical_url);
			// dhtmlxSchedulerIcal.php
			$scheduler .= "<form id='ical_form' action='{$ical_url}' method='post' target='hidden_frame' accept-charset='utf-8'>
				<input type='hidden' name='data' value='' id='data'></form>
			<iframe src='about:blank' frameborder='0' style='width:0px; height:0px;' id='hidden_frame' name='hidden_frame'></iframe>";
		}
		return $scheduler;
	}


	protected function addToLog($xml, $usergroups = array()) {
		$xml = str_replace('&ltesc;', '<', $xml);
		$xml = str_replace('&gtesc;', '>', $xml);
		$log_file_path = dirname(__FILE__).'/'.$this->log_file;
		if (file_exists($log_file_path)) {
			$log = simplexml_load_file($log_file_path);
		} else {
			$log = simplexml_load_string('<logs></logs>');
		}
		$elem = $log->addChild('log', $xml);
		$elem->addAttribute('time', date("Y-m-d H:i:s"));
		$elem->addAttribute('usertype', implode(",", $usergroups));
		$log->asXML($log_file_path);
		return true;
	}


	public function getXML() {
		$query = "SELECT `".$this->fieldValue."` FROM ".$this->prefix.$this->table." WHERE `".$this->fieldName."`='scheduler_xml' LIMIT 1";
		$res = mysql_query($query, $this->connection);
		$xml = mysql_result($res, 0, $this->fieldValue);
		$xml = str_replace("&ltesc;", "<", $xml);
		$xml = str_replace("&gtesc;", ">", $xml);
		$xml = str_replace("&#8242;", "'", $xml);
		@$this->xmlObj = simplexml_load_string($xml);
		if ($this->xmlObj === false) {
			$xml = $this->getLastStableConfig();
			@$this->xmlObj = simplexml_load_string($xml);
			if ($this->xmlObj === false) {
				$xml = $this->default_xml;
			}
		}
		if ((string) $this->xmlObj[0] == 'restore_default') {
			$xml = $this->default_xml;
		}
		return $xml;
	}


	public function getXmlVersion() {
		return ($this->scheduler_xml_version + 1);
	}


	public function getEventsRec($usergroups) {
		require("connector/scheduler_connector.php");
		$this->scheduler = new schedulerConnector($this->connection);
		if ($this->settings['settings_debug'] == 'true') {
			if ($this->joomla == true) {
				$log_file_path = JPATH_SITE.DS.'components'.DS.'com_scheduler'.DS.'scheduler_log.txt';
			} else {
				$log_file_path = WP_PLUGIN_DIR.'/event-calendar-scheduler/scheduler_log.txt';
			}
			$this->scheduler->enable_log($log_file_path, true);
		}

		if ($this->settings['settings_posts'] == 'true') {
			$this->scheduler->access->deny("insert");
			$this->scheduler->access->deny("update");
			$this->scheduler->access->deny("delete");

			$this->scheduler->event->attach("beforeRender", Array($this, "posts_table_builder"));
			$this->scheduler->render_sql("SELECT `ID`,`post_date`,`post_date_gmt`,`post_title`,`guid` FROM `".$this->prefix."posts` WHERE `post_type`='post' AND ((`post_status`='publish') OR (`post_status`='private' AND `post_author`='".$this->userid."'))", "ID", "post_date,post_date_gmt,post_title,guid");
		} else {
			if (!$this->can('add', $usergroups))
				$this->scheduler->access->deny("insert");
			if (!$this->can('edit', $usergroups)) {
				$this->scheduler->access->deny("update");
				$this->scheduler->access->deny("delete");
			}
			if ($this->settings['settings_repeat'] == 'true') {
				$this->scheduler->event->attach("beforeProcessing", Array($this, "delete_related"));
				$this->scheduler->event->attach("afterProcessing", Array($this, "insert_related"));
			}
			$this->scheduler->event->attach("beforeProcessing", Array($this, "set_event_user"));
			$this->scheduler->event->attach("afterProcessing", Array($this, "after_set_event_user"));
			$fields = 'start_date,end_date';
			if ($this->settings['customfieldsList']) {
				$fields .= ",".$this->settings['customfieldsList'];
			}
			$fields .= ',rec_type,event_pid,event_length,user';
			if ($this->settings['settings_map'] == 'true') $fields .= ',lat,lng';
			$this->scheduler->event->attach("beforeRender", Array($this, "render_username"));
			if ($this->settings['templates_username'] == 'true') {
				$username_query = ',1 AS username';
				$username_field = ",1(username)";
			} else
				$username_query = $username_field = "";
			if ($this->settings['privatemode'] == 'on') {
				$this->scheduler->event->attach("beforeRender", Array($this, "private_remove_updated"));
				$query = "SELECT event_id,".$fields.$username_query." FROM `".$this->prefix.$this->tableEventsRec."` WHERE `user`='".($this->userid)."' OR `event_pid`!=0";
				$fields .= $username_field;
				$this->scheduler->render_sql($query,"event_id", $fields);
			} else {
				$query = $query = "SELECT event_id,".$fields.$username_query." FROM `".$this->prefix.$this->tableEventsRec."`";
				$fields .= $username_field;
				$this->scheduler->render_sql($query,"event_id",$fields);
			}
		}
	}


	public function private_remove_updated($row) {
		$rec_type = $row->get_value('rec_type');
		$userid = $row->get_value('user');
		if (($rec_type != 'none')&&($userid != $this->userid)) {
			$row->set_value('rec_type', 'none');
		}
		return $row;
	}


	public function set_event_user($action) {
		if ($this->settings['templates_username'] == 'true') $action->remove_field('1');
		$status = $action->get_status();
		if ($status == "inserted") {
			$action->set_value("user", $this->userid);
		} else {
			if ($this->settings["privatemode"] == "ext") {
				$id = mysql_real_escape_string($action->get_id(), $this->connection);
				$res = $this->scheduler->sql->query("SELECT user FROM {$this->prefix}events_rec WHERE event_id='{$id}'");
				$tmp = $this->scheduler->sql->get_next($res);
				$user = $tmp ? $tmp['user'] : -1;
				if ($user != $this->userid) {
					$action->error();
				}
			}
		}
		if ($action->get_value('event_pid') == '') {
			$action->set_value('event_pid', 0);
		}
		if ($action->get_value('event_length') == '') {
			$action->set_value('event_length', 0);
		}
	}

	public function after_set_event_user($action) {
		$action->set_response_attribute("user", $this->userid);
	}

	public function render_username($event) {
		$username = $this->userMap($event->get_value('user'));
		$event->set_value('username', $username);
	}

	public function userMap($userid) {
		if ($this->usermap === null)
			$this->usermap = $this->getUserNames();
		if (isset($this->usermap[$userid]))
			return $this->usermap[$userid];
		return 'Guest';
	}

	public function getUserNames() {
		$query = "SELECT {$this->userIdField} AS id, {$this->userLoginField} AS username FROM {$this->base_prefix}{$this->tableUsers}";
		$res = mysql_query($query, $this->connection);
		$users = Array();
		while ($user = mysql_fetch_assoc($res))
			$users[$user['id']] = $user['username'];
		return $users;
	}

	public function getEventsRecGrid() {
		require("connector/grid_connector.php");
		$grid = new GridConnector($this->connection);
		if ($this->settings['settings_debug'] == 'true') {
			if ($this->joomla == true) {
				$log_file_path = JPATH_SITE.DS.'components'.DS.'com_scheduler'.DS.'scheduler_log.txt';
			} else {
				$log_file_path = WP_PLUGIN_DIR.'/event-calendar-scheduler/scheduler_log.txt';
			}
			$grid->enable_log($log_file_path, true);
		}

		$fields = '';
		$fieldsNames = '';
		$fieldsLabels = '';
		$types = '';
		$aligns = '';
		$widths = '';
		$sort = '';
		$fieldsNum = 0;
		$fillFields = '';
		$dhx_colls = false;
		if ($this->settings['customfieldsList']) {
			$fieldsList = explode(",", $this->settings['customfieldsList']);
			$fieldsLabelsArray = explode(",", $this->settings['customfieldsLabels']);
			for ($i = 0; $i < count($fieldsList); $i++) {
				if (isset($this->settings['gridkeys_'.$fieldsList[$i]])) {
					$types .= "coro,";
					$fillFields .= $fieldsList[$i].",";
					$opts = explode(',', $this->settings['gridkeys_'.$fieldsList[$i]]);
					$optionsList = array();
					for ($j = 0; $j < count($opts); $j++) {
						$opt = explode(':', $opts[$j]);
						$optionsList[$opt[0]] = $opt[1];
					}
					$dhx_colls .= ($i + $fieldsNum).",";
					$grid->set_options(strtolower($fieldsList[$i]), $optionsList);
				} else {
					$types .= "ed,";
				}
				$fields .= $fieldsList[$i].",";
				$fieldsNames .= $fieldsList[$i].",";
				$fieldsLabels .= $fieldsLabelsArray[$i].",";
				$aligns .= ",left";
				$widths .= ($fieldsList[$i] == 'text') ? "*," : "15,";
				$sort .= ",str";
				if ($fieldsList[$i] == 'text') {
					$fields .= 'start_date,end_date,';
					$fieldsNames .= 'Start date,End Date,';
					$fieldsLabels .= 'Start date,End Date,';
					$types .= 'ed,ed,';
					$aligns .= 'center,center,';
					$widths .= '15,15,';
					$sort .= 'str,str,';
					$fieldsNum += 2;

					if (($this->settings['privatemode'] == 'on')||($this->settings['privatemode'] == 'ext')) {
						$fields .= 'user,';
						$fieldsNames .= 'user,';
						$fieldsLabels .= 'User,';
						$types .= 'coro['.$this->userid.'],';
						$aligns .= 'left,';
						$widths .= '15,';
						$sort .= 'str,';

						$query = "SELECT `".$this->userIdField."`, `".$this->userLoginField."` FROM `".$this->base_prefix.$this->tableUsers."`";
						$res = $grid->sql->query($query);
						$users_array = Array('0'=>'Guest');
						while ($user = mysql_fetch_assoc($res)) {
							$users_array[$user[$this->userIdField]] = $user[$this->userLoginField];
						}
						$grid->set_options('user', $users_array);
						$dhx_colls .= "3,";
						$fieldsNum++;
					}
				}
			}
		}
		if ($dhx_colls) {
			$this->dhx_colls = substr($dhx_colls, 0, strlen($dhx_colls) - 1);
			$grid->event->attach("beforeExtraOutput", Array($this, "extra_output_callback"));
		}

		$fields = substr($fields, 0, strlen($fields) - 1);
		$fieldsNames = substr($fieldsNames, 0, strlen($fieldsNames) - 1);
		$fieldsLabels = substr($fieldsLabels, 0, strlen($fieldsLabels) - 1);
		$config = new GridConfiguration($fieldsLabels);
		$config->setColIds($fieldsNames);
		$config->setColTypes($types);
		$config->setColAlign($aligns);
		$config->setInitWidthsP($widths);
		$config->setColSorting($sort);
		$grid->set_config($config);

		$grid->render_table($this->prefix.$this->tableEventsRec, "event_id", $fields);
	}


	public function extra_output_callback($grid) {
		$grid->fill_collections($this->dhx_colls);
	}


	public function insert_related($action) {
		$status = $action->get_status();
		$type =$action->get_value("rec_type");

		if ($status == "inserted" && $type=="none")
			$action->set_status("deleted");
	}


	public function delete_related($action){
		$status = $action->get_status();
		$type =$action->get_value("rec_type");
		$pid =$action->get_value("event_pid");
		if (($status == "deleted" || $status == "updated") && $type!=""){
			$this->scheduler->sql->query("DELETE FROM `".$this->prefix.$this->tableEventsRec."` WHERE event_pid='".$this->scheduler->sql->escape($action->get_id())."'");
		}
		if ($status == "deleted" && $pid !=0){
			$this->scheduler->sql->query("UPDATE `".$this->prefix.$this->tableEventsRec."` SET rec_type='none' WHERE event_id='".$this->scheduler->sql->escape($action->get_id())."'");
			$action->success();
		}
	}


	private function posts_table_builder($row) {
		$start = substr($row->get_value("post_date"), 0, 10)." 00:00:00";
		$row->set_value("post_date", $start);
		$start = date_parse($start);
		$endd = mktime($start['hour'], $start['minute'], $start['second'], $start['month'], $start['day'] + 1, $start['year']);
		$endd = date("Y-m-d", $endd)." 00:00:00";
		$row->set_value("post_date_gmt", $endd);
		$text = $row->get_value("post_title");
		$text = "<a href=\"".$row->get_value("guid")."\">".$text."</a>";
		$row->set_value("post_title", $text);
	}


	public function getProblems() {
		if (count($this->problems) == 0) {
			return "";
		}
		$problems = "<ul class='scheduler_problems'>";
		for ($i = 0; $i < count($this->problems); $i++) {
			$problems .= "<li>".$this->problems[$i]."</li>";
		}
		$problems .= "</ul>";
		return $problems;
	}


	protected function addProblem($problem) {
		if (!in_array($problem, $this->problems)) {
			$this->problems[] = $problem;
			return true;
		} else {
			return false;
		}
	}


	protected function replaceHostInURL($url) {
		$url_parsed = parse_url($url);
		$host = $_SERVER['SERVER_NAME'];
		$url = preg_replace("/".preg_quote($url_parsed['host'])."/", $host, $url, 1);
		return $url;
	}

	public function can($action, $usergroups) {
		$access = $this->settings['access'];
		// default value should be used
		if (count($usergroups) === 0)
			return $access['-1'][$action] === 'true' ? true : false;
		$result = false;
		for ($i = 0; $i < count($usergroups); $i++) {
			if (isset($access[$usergroups[$i]]))
				$value = $access[$usergroups[$i]][$action] === 'true' ? true : false;
			else
				$value = $access['-1'][$action] === 'true' ? true : false;
			$result = $result || $value;
		}
		return $result;
	}

	public function gcalImport($email, $pass, $cal) {
		return $this->gcal_export($email, $pass, $cal, "import");
	}

	public function gcalExport($email, $pass, $cal) {
		return $this->gcal_export($email, $pass, $cal, "export");
	}

	private function gcal_export($email, $pass, $cal, $method) {
		if (!function_exists("curl_init")) {
			$status = "error_curl";
			$count = 0;
		} else {
			include('google_proxy.php');
			$calendar = new GoogleCalendarProxy($email, $pass, $cal);
			if ($calendar->isIncorrect()) {
				$status = "error_auth";
				$count = 0;
			} else {
				// event location mapping
				if ($this->settings['settings_map'] == 'true') $calendar->map('location', 'event_location');

				$count = $calendar->$method($this->connection, $this->prefix.$this->tableEventsRec);
				if ($count === false) {
					$status = "error_cal";
					$count = 0;
				} else
					$status = "success";
			}
		}

		return "{status:\"{$status}\",event:{$count}}";
	}

	private function getExpandFix() {
		$js = 'scheduler.collapse_original = scheduler.collapse;';
		$js .= 'scheduler.collapse = function() {';
		$js .= 'var bar = document.getElementById("wpadminbar");';
		$js .= 'if (bar) bar.style.display = "block";';
		$js .= 'scheduler.collapse_original(); };';
		
		$js = 'scheduler.expand_original = scheduler.expand;';
		$js .= 'scheduler.expand = function() {';
		$js .= 'var bar = document.getElementById("wpadminbar");';
		$js .= 'if (bar) bar.style.display = "none";';
		$js .= 'scheduler.expand_original(); };';
		return $js;
	}
	
	private function compatible($settings) {
		if (!isset($settings['use'])) $settings['use'] = 'false';
		if (!isset($settings['settings_week_agenda'])) $settings['settings_week_agenda'] = 'false';
		if (!isset($settings['settings_map'])) $settings['settings_map'] = 'false';
		if (!isset($settings['settings_pdf'])) $settings['settings_pdf'] = 'false';
		if (!isset($settings['settings_ical'])) $settings['settings_ical'] = 'false';
		if (!isset($settings['settings_fullday'])) $settings['settings_fullday'] = 'false';
		if (!isset($settings['settings_marknow'])) $settings['settings_marknow'] = 'false';
		return $settings;
	}

}

?>