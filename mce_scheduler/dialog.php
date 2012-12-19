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

require('../../../../wp-config.php');
define('WP_USE_THEMES', true);

require_once(WP_PLUGIN_DIR.'/event-calendar-scheduler/codebase/dhtmlxSchedulerConfigurator.php');
require_once(WP_PLUGIN_DIR.'/event-calendar-scheduler/codebase/dhtmlxSchedulerHelpers.php');

$db = new DHXDBConfig();
$db->connection = $wpdb->dbh;
$db->prefix = $wpdb->prefix;
$db->events = 'events_rec';
$db->options = 'options';
$db->options_name = 'option_name';
$db->options_value = 'option_value';
$db->users = 'users';
$db->users_id = 'ID';
$db->users_login = 'user_login';
$cfg = new SchedulerConfig('scheduler_config_xml', $db, $scheduler_userid, false);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<script type="text/javascript" src="../../../../wp-includes/js/tinymce/tiny_mce.js?ver=3241-1141"></script>
<script type="text/javascript" src="../../../../wp-includes/js/tinymce/tiny_mce_popup.js"></script>

<script type="text/javascript" src="../codebase/dhtmlx.js"></script>
<link rel="stylesheet" type="text/css" href="../codebase/dhtmlx.css">
<title><?php _e('Add event in the Scheduler calendar', 'scheduler'); ?></title>

<base target="_self" />
<style>
	input.button {
		width: 60px;
	}

	div.label {
		float: left;
		height: 15px;
		width: 100px;
		background-color: #DFDFDF;
		padding-top: 3px;
		padding-right: 3px;
		text-align: right;
	}
	
	div.label_date {
		float: left;
		height: 15px;
		width: 170px;
		background-color: #DFDFDF;
		padding-top: 3px;
		padding-right: 3px;
		text-align: center;
	}
	
	input.input_text {
		height: 12px;
		width: 256px;
		padding-top: 2px;
		padding-left: 3px;
	}
	input.input_date {
		height: 12px;
		width: 74px;
		padding-top: 2px;
		padding-left: 3px;
		text-align: center;
	}
	
	select.input_time {
		height: 18px;
		width: 40px;
		padding-top: 0px;
		padding-left: 0px;
		text-align: center;
		position: relative;
		top: 0px;
	}
	
	table.scheduler_add_form {
		width: 100%;
	}

	table.scheduler_add_form td {
		vertical-align: bottom;
	}
	
	td.input_line1 {
		height: 30px;
	}
	
	td.input_line2 {
		height: 46px;
	}
	
	td.buttons_field {
		height: 30px;
	}
	
	div#center_div {
		margin: 0px auto;
		width: 370px;
	}
	
	div.div_date {
		width: 76px;
		float: left;
		text-align: left;
	}
	div.div_time {
		width: 90px;
		float: right;
		text-align: right;
	}
	
	div#divStart {
		position: absolute;
		left: 16px;
		top: 4px;
	}
	
	div#divEnd {
		position: absolute;
		left: 206px;
		top: 4px;
	}

</style>
</head>
<script type="text/javascript"  charset="utf-8">
function loader() {
	startCal = new dhtmlxCalendarObject("divStart");
	startCal.setDateFormat("%Y/%m/%d");
	startCal.hideTime();
	startCal.attachEvent('onClick', function(date) {
		startCal.hide();
		date = startCal.getFormatedDate("%Y/%m/%d");
		document.getElementById("event_start_date").value = date;
	});
	startCal.draw();
	startCal.hide();

	endCal = new dhtmlxCalendarObject("divEnd");
	endCal.setDateFormat("%Y/%m/%d");
	endCal.hideTime();
	endCal.attachEvent('onClick', function(date) {
		endCal.hide();
		date = endCal.getFormatedDate("%Y/%m/%d");
		document.getElementById("event_end_date").value = date;
		});
	endCal.draw();
	endCal.hide();



	var title = tinyMCEPopup.getWindowArg('postTitle', 'some title');
	document.getElementById('event_name').value = title;
	document.getElementById('event_link').value = title;

	var hourValue = <?php echo date("H"); ?>;
	var minValue = <?php echo date("i"); ?>;
	
	var dev = Math.round(minValue/5);
	minValue = dev*5;
	
	if (minValue >= 60) {
		minValue = 0;
		hourValue++;
	}
	
	var hourValueEnd = hourValue;
	var minValueEnd = minValue + 5;
	
	if (minValueEnd >= 60) {
		minValueEnd = 0;
		hourValueEnd++;
		if (hourValueEnd > 23) {
			hourValueEnd = 0;
			var Y = endCal.getFormatedDate("%Y");
			var m = endCal.getFormatedDate("%m");
			var d = endCal.getFormatedDate("%d");
			d = parseInt(d);
			d++;
			d = d.toString();
			if (d.length < 2) {
				d = '0' + d;
			}
			endCal.setDate(Y + '/' + m + '/' + d);
			document.getElementById("event_end_date").value = endCal.getFormatedDate("%Y/%m/%d");
		}
	}
	
	hourValue = hourValue.toString();
	minValue = minValue.toString();
	hourValueEnd = hourValueEnd.toString();
	minValueEnd = minValueEnd.toString();

	hourValue = (hourValue.length < 2) ? ('0' + hourValue) : hourValue;
	minValue = (minValue.length < 2) ? ('0' + minValue) : minValue;
	hourValueEnd = (hourValueEnd.length < 2) ? ('0' + hourValueEnd) : hourValueEnd;
	minValueEnd = (minValueEnd.length < 2) ? ('0' + minValueEnd) : minValueEnd;

	document.getElementById("event_start_time_h").value = hourValue;
	document.getElementById("event_start_time_m").value = minValue;
	document.getElementById("event_end_time_h").value = hourValueEnd;
	document.getElementById("event_end_time_m").value = minValueEnd;
	}
	
function makeDate(date, h, m) {
	var date = date.split('/');
	var year = date[0];
	var month = date[1];
	var day = date[2];
	var dateObj = new Date(year, month, day, h, m, '00')
	var month = dateObj.getMonth();
	month--;
	dateObj.setMonth(month);
	return dateObj;
}

function dateFormat(date) {
	var result = '';
	result += date.getFullYear() + "-";

	var tmp = (date.getMonth() + 1).toString();
	tmp = (tmp.length < 2) ? ('0' + tmp) : tmp;
	result += tmp + "-";

	tmp = date.getDate().toString();
	tmp = (tmp.length < 2) ? ('0' + tmp) : tmp;
	result += tmp + " ";

	tmp = date.getHours().toString();
	tmp = (tmp.length < 2) ? ('0' + tmp) : tmp;
	result += tmp + ":";

	tmp = date.getMinutes().toString();
	tmp = (tmp.length < 2) ? ('0' + tmp) : tmp;
	result += tmp + ":";

	tmp = date.getSeconds().toString();
	tmp = (tmp.length < 2) ? ('0' + tmp) : tmp;
	result += tmp;
	return result;
}

function okClicked() {
	var title = document.getElementById('event_name').value;
	var link = document.getElementById('event_link').value;

	var start = startCal.getFormatedDate("%Y/%m/%d");
	var end = endCal.getFormatedDate("%Y/%m/%d");

	var start = makeDate(start, document.getElementById('event_start_time_h').value, document.getElementById('event_start_time_m').value);
	var end = makeDate(end, document.getElementById('event_end_time_h').value, document.getElementById('event_end_time_m').value);

	if (start >= end) {
		alert("<?php _e('Incorrect time period!'); ?>");
		return false;
	}
	start = dateFormat(start);
	end = dateFormat(end);
	
	userId = "<?php get_currentuserinfo(); echo $current_user->id; ?>";

	var url = "<?php echo get_option('siteurl'); ?>/wp-content/plugins/event-calendar-scheduler/mce_scheduler/scheduler_ajax.php";
	url += "?title=" + encodeURIComponent(title) + "&start=" + encodeURIComponent(start) + "&end=" + encodeURIComponent(end) + "&userId=" + encodeURIComponent(userId);
	dhtmlxAjax.get(url, function(loader) {
				if (loader.xmlDoc.responseText != '1') {
					alert("Error of addition event in Scheduler!");
					tinyMCEPopup.close();
				} else {
					var reg = /\//g;
					start = start.replace(reg, '-');
					bookmark = '<a href="<?php echo $cfg->get_option('settings_link'); ?>#date=' + start + ',mode=month" target="_blank">' + link + '</a>';
					tinyMCE.execCommand('mceInsertContent', false, bookmark);
					tinyMCEPopup.close();
					}
				});
	
	
	
	return true;
	}
	
function cancelClicked() {
	tinyMCEPopup.close();
	return true;
	}

</script>
<body onload="loader();">
	<div id="center_div">
	<div id="divStart"></div>
	<div id="divEnd"></div>
	<table border="0" class="scheduler_add_form">
		<tr>
			<td colspan="3" class="input_line1">
				<div class="label"><label for="event_name"><strong><?php _e('Event name', 'scheduler'); ?></strong></label></div>
				<input type="text" id="event_name" name="event_name" class="input_text" value=""  size="30"/>
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="input_line1">
				<div class="label"><label for="event_link"><strong><?php _e('Link text', 'scheduler'); ?></strong></label></div>
				<input type="text" id="event_link" name="event_link" class="input_text" value=""  size="30"/>
			</td>
		</tr>
		<tr>
			<td class="input_line2">
				<div class="label_date"><label for="event_start_date"><strong><?php _e('Event start date', 'scheduler'); ?>:</strong></label></div>
				<div class="div_date">
					<input type="text" id="event_start_date" name="event_start_date" class="input_date" value="<?php echo date("Y/m/d"); ?>"  size="25" maxlength="20"/ onClick="startCal.show()">
				</div>
				<div class="div_time">
					<select class="input_time" id="event_start_time_h" name="event_start_time_h">
						<option value="00">00</option>
						<option value="01">01</option>
						<option value="02">02</option>
						<option value="03">03</option>
						<option value="04">04</option>
						<option value="05">05</option>
						<option value="06">06</option>
						<option value="07">07</option>
						<option value="08">08</option>
						<option value="09">09</option>
						<option value="10">10</option>
						<option value="11">11</option>
						<option value="12">12</option>
						<option value="13">13</option>
						<option value="14">14</option>
						<option value="15">15</option>
						<option value="16">16</option>
						<option value="17">17</option>
						<option value="18">18</option>
						<option value="19">19</option>
						<option value="20">20</option>
						<option value="21">21</option>
						<option value="22">22</option>
						<option value="23">23</option>
					</select>:
					<select class="input_time" id="event_start_time_m" name="event_start_time_m">
						<option value="00">00</option>
						<option value="05">05</option>
						<option value="10">10</option>
						<option value="15">15</option>
						<option value="20">20</option>
						<option value="25">25</option>
						<option value="30">30</option>
						<option value="35">35</option>
						<option value="40">40</option>
						<option value="45">45</option>
						<option value="50">50</option>
						<option value="55">55</option>
					</select>
				</div>
			</td>
			<td align="center"><strong> &#151 </strong>
			</td>
			<td class="input_line2" align="right">
				<div class="label_date" style="float: right;"><label for="event_end_date"><strong><?php _e('Event end date', 'scheduler'); ?>:</strong></label></div>
				<div class="div_date">
					<input type="text" id="event_end_date" name="event_end_date" class="input_date" value="<?php echo date("Y/m/d", time() + 60*5); ?>"  size="25" maxlength="20" onClick="endCal.show()"/>
				</div>
				<div class="div_time">
					<select class="input_time" id="event_end_time_h" name="event_end_time_h">
						<option value="00">00</option>
						<option value="01">01</option>
						<option value="02">02</option>
						<option value="03">03</option>
						<option value="04">04</option>
						<option value="05">05</option>
						<option value="06">06</option>
						<option value="07">07</option>
						<option value="08">08</option>
						<option value="09">09</option>
						<option value="10">10</option>
						<option value="11">11</option>
						<option value="12">12</option>
						<option value="13">13</option>
						<option value="14">14</option>
						<option value="15">15</option>
						<option value="16">16</option>
						<option value="17">17</option>
						<option value="18">18</option>
						<option value="19">19</option>
						<option value="20">20</option>
						<option value="21">21</option>
						<option value="22">22</option>
						<option value="23">23</option>
					</select>:
					<select class="input_time" id="event_end_time_m" name="event_end_time_m">
						<option value="00">00</option>
						<option value="05">05</option>
						<option value="10">10</option>
						<option value="15">15</option>
						<option value="20">20</option>
						<option value="25">25</option>
						<option value="30">30</option>
						<option value="35">35</option>
						<option value="40">40</option>
						<option value="45">45</option>
						<option value="50">50</option>
						<option value="55">55</option>
					</select>
				</div>
			</td>
		</tr>

		<tr>
			<td colspan="3" class="buttons_field" align="right">
				<input type="button" name="ok" value="OK" class="button" onClick="okClicked();">
				<input type="button" name="cancel" value="Cancel" class="button" onClick="cancelClicked();">
			</td>
		</tr>
	</table>
	</div>
</body>
</html>
