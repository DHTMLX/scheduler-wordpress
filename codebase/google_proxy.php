<?php

ini_set('max_execution_time', '300');

class GoogleCalendarProxy {

	private $email;
	private $password;
	private $auth = false;
	private $cal_name;
	private $cals_hash = Array();
	private $map = Array(
		"startTime" => "start_date",
		"endTime" => "end_date",
		"title" => "text"
	);
	private $back_map;

	private $export_map = Array(
		"startTime" => "start_date",
		"endTime" => "end_date",
		"title" => "text"
	);
	private $export_back_map;

	private $timezones = Array();
	private $log_mode = false;
	private $seconds = ':00';
	private $log_name = 'google_proxy.log';
	public $delim = "\n==========================================================\n";

	/*! class constructor
	 *	@param email
	 *		gCal profile email
	 *	@param pass
	 *		gCal profile password
	 *	@param cal_name
	 *		gCal calendar name
	 */
	public function __construct($email, $pass, $cal_name) {
		$this->email = $email;
		$this->password = $pass;
		$this->cal_name = $cal_name;
		$url = "https://www.google.com/accounts/ClientLogin";
		$post_data = "Email=".$this->email."&Passwd=".$this->password."&source=exampleCo-exampleApp-1&service=cl";
		$content = $this->httpRequest($url, Array(
			CURLOPT_POST => true,
			CURLOPT_HEADER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_POSTFIELDS => $post_data,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true
		));

		$res = explode("\n", $content);
		for ($i = 0; $i < count($res); $i++) {
			$res[$i] = explode("=", $res[$i]);
			if (count($res[$i]) == 2)
				if ($res[$i][0] == "Auth")
					$this->auth = $res[$i][1];
		}
		foreach ($this->map as $k => $v)
				$this->back_map[$v] = $k;
		foreach ($this->export_map as $k => $v)
				$this->export_back_map[$v] = $k;
	}

	/*! if user login/password is correct
	 */
	public function isIncorrect() {
		return ($this->auth === false) ? true : false;
	}

	/*! send http request
	 *	@param url
	 *		request url
	 *	@param options
	 *		options associative array
	 *	@return
	 *		content
	 */
	private function httpRequest($url, $options) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		foreach ($options as $name => $value)
			curl_setopt($ch, $name, $value);
		$content = curl_exec($ch);
		$this->log($content);
		curl_close($ch);
		return $content;
	}


	/*! takes information about all user calendars
	 *	@return
	 *		calendars hash
	 */
	private function retrieveAllCalendars() {
		$url = "http://www.google.com/calendar/feeds/default/owncalendars/full?alt=jsonc";
		$this->cache_calendars = $this->httpRequest($url, Array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => array('Authorization: GoogleLogin auth='.$this->auth, 'GData-Version: 2')
		));
		$cals = $this->extractCalendarsFromJSON($this->cache_calendars);
		return $cals;
	}

	/*! takes all calendar events
	 *	@param cal_id
	 *		google calendar id
	 *	@return
	 *		events hash
	 */
	private function retrieveAllEvents($cal_id = false) {
		if ($cal_id === false) $cal_id = $this->email;
		$url = "http://www.google.com/calendar/feeds/".$cal_id."/private/full?alt=jsonc";
		$this->cache_events = $this->httpRequest($url, Array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => array('Authorization: GoogleLogin auth='.$this->auth, 'GData-Version: 2')
		));
		$events = $this->extractEventsFromJSON($this->cache_events);
		return $events;
	}

	/*! take calendar event by id
	 *	@param cal_id
	 *		google calendar id
	 *	@param event_id
	 *		google event id
	 *	@render
	 *		method returns given string or parse it into object
	 *	@return
	 *		event by id
	 */
	private function retrieveEvent($cal_id, $event_id, $render = false) {
		if ($cal_id === false) $cal_id = $this->email;
		$this->log("\nRetriving event: ".$cal_id." => ".$event_id);
		$url = "http://www.google.com/calendar/feeds/".$cal_id."/private/full/".$event_id."?alt=jsonc";
		$event = $this->httpRequest($url, Array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_HTTPHEADER => array('Authorization: GoogleLogin auth='.$this->auth, 'GData-Version: 2')
		));
		if ($render === false)
			return $event;
		$events = $this->extractEventsFromJSON($event);
		return $events[0];
	}


	/*! parse calendars json into calendars list
	 *	@param json
	 *		calendars json
	 *	@return
	 *		calendars array
	 */
	private function extractCalendarsFromJSON($json) {
		$json = json_decode($json);
		$list = $json->data->items;
		
		$cals = Array();
		for ($i = 0; $i < count($list); $i++) {
			$cal = $list[$i];
			$id = $cal->id;
			$id = substr($id, strrpos($id, "/") + 1);
			$title = $cal->title;
			// sets timezone for calendar
			// it's important to process events (update, insert)
			$timezone = $cal->timeZone;
			$timezone = new DateTimeZone($timezone);
			$time = new dateTime("", $timezone);
			$timezone = $time->format('P');
			$this->cals_hash[$id] = $id;
			$this->cals_hash[$title] = $id;
			$this->timezones[$id] = $timezone;
			$cals[] = array("id" => $id, "title" => $title, "timezone" => $timezone);
		}
		return $cals;
	}


	/*! parse events json
	 *	@param
	 *		events json
	 *	@return
	 *		events array
	 */
	private function extractEventsFromJSON($json) {
		$json = json_decode($json);
		$events = Array();
		if (isset($json->data->items)) {
			for ($i = 0; $i < count($json->data->items); $i++) {
				$event = $json->data->items[$i];
				$event->startTime = $this->parse_gDate($event->when[0]->start);
				$event->endTime = $this->parse_gDate($event->when[0]->end);
				$events[] = $event;
			}
		}
		return $events;
	}


	/*! convert gCal date format into scheduler format
	 *	@param input_date
	 *		date as String in gCal format
	 *	@return
	 *		date as String in scheduler format
	 */
	private function parse_gDate($input_date) {
		$date = explode("T", $input_date);
		$time = isset($date[1]) ? $date[1] : '00:00:00';
		$date = $date[0];
		$time = explode("+", $time);
		$time = $time[0];
		$time = explode('.', $time);
		$time = $time[0];
		return $date.' '.$time;
	}

	/*! convert scheduler date format into gCal format
	 *	@param input_date
	 *		date as String in scheduler format
	 *	@return
	 *		date as String in gCal format
	 */
	private function to_gDate($input_date, $timezone = '+00:00') {
		$input_date = str_replace(" ", "T", $input_date);
		$input_date .= $this->seconds.".000".$timezone;
		return $input_date;
	}


	/*! method for mapping scheduler custom fields with gCal fields
	 *	@param googleField
	 *		google calendar field name
	 *	@param dhtmlxField
	 *		scheduler custom field name
	 */
	public function map($googleField, $dhtmlxField) {
		$this->map[$googleField] = $dhtmlxField;
		$this->back_map[$dhtmlxField] = $googleField;
		$this->export_map[$googleField] = $dhtmlxField;
		$this->export_back_map[$dhtmlxField] = $googleField;
	}


	/*! call request processing
	 */
	public function connect() {
		$this->log($this->delim.date("Y-m-d H:i:s").$this->delim."Retrieving calendars");

		$this->cals = $this->getCalendars();
		if (isset($_GET['!nativeeditor_status'])) {
			$xml = $this->dataProcessor();
		} else {
			$xml = $this->render();
		}

		$this->log($this->delim."\n\n");

		header('Content-type: text/xml');
		echo $xml;
	}


	/*! render events list
	 *	@return
	 *		events list as XML
	 */
	private function render() {
		if (isset($this->cals_hash[$this->cal_name])) {
			$this->log("\nRetrieving events");
			$events = $this->retrieveAllEvents($this->cals_hash[$this->cal_name]);
			$xml = '<data>';
			foreach ($events as $event_id => $ev) {
				$xml .= '<event id="'.$ev->id.'">';
				foreach ($this->map as $googleField => $dhtmlxField) {
					if (!isset($ev->$googleField)) continue;
					$xml .= "<{$dhtmlxField}><![CDATA[".$ev->$googleField."]]></{$dhtmlxField}>";
				}
				$xml .= '</event>';
				$event_id++;
			}
			$xml .= '</data>';
		} else {
			throw new Exception("Unknown gCal calendar");
		}
		return $xml;
	}


	/*! calls dataprocessor to save event changes
	 *	@return
	 *		event saving status in XML-format
	 */
	private function dataProcessor() {
		$status = $_GET['!nativeeditor_status'];
		$this->log("\nDataProcessor status: ".$status);
		switch ($status) {
			case 'updated':
				$xml = $this->update();
				break;
			case 'inserted':
				$xml = $this->insert();
				break;
			case 'deleted':
				$xml = $this->delete();
				break;
		}

		return $xml;
	}


	/*! take event, maps custom fields to gCal event and try to save it
	 *	@return
	 *		update status in XML format
	 */
	private function update() {
		$id = $_GET['id'];
		// takes event from google server
		$ev_json = $this->retrieveEvent($this->cals_hash[$this->cal_name], $id);
		$ev = $this->mapEvent($ev_json);

		// call saving event
		$xml = $this->updateEvent($ev, $ev_json);
		return $xml;
	}


	/*! maps custom fields to gCal event
	 *	@ev_json
	 *		
	 */
	private function mapEvent(&$ev_json, $map = false, $source = false) {
		$ev = json_decode($ev_json);

		if (!is_array($source))
			$source = $_GET;
		if ($map === false)
			$map = $this->back_map;

		// maps custom fields from scheduler to gCal event
		foreach ($source as $k => $v)
			if (isset($map[$k])) {
				$googleField = $map[$k];
				$ev->data->$googleField = $v;
			}
		// maps start and end time
		$timezone = $this->timezones[$this->cals_hash[$this->cal_name]];
		$seconds = $this->seconds;
		$ev->data->when[0]->start = $this->to_gDate($ev->data->startTime, $timezone, $seconds);
		$ev->data->when[0]->end = $this->to_gDate($ev->data->endTime, $timezone, $seconds);

		$ev_json = json_encode($ev);
		$ev_json = str_replace('\/', '/', $ev_json);

		return $ev;
	}


	/*! send request to update event and process response
	 *	@param ev
	 *		event as object
	 *	@param ev_json
	 *		event encoded in JSON
	 *	@url
	 *		request url
	 *	@return
	 *		updating result as XML
	 */
	private function updateEvent($ev, $ev_json, $url = false) {
		if ($url === false)
			$url = $ev->data->selfLink.'?alt=jsonc';

		// send request
		$this->log("\nSend updating request");
		$content = $this->httpRequest($url, Array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $ev_json,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_HTTPHEADER => Array(
				'Authorization: GoogleLogin auth='.$this->auth,
				'GData-Version: 2',
				'Content-type: application/json',
				'If-Match: '.$ev->data->etag,
				'Expect:'
			)
		));

		// process response
		if (strpos($content, "200 OK") !== false)
			// event is updated successful
			return "<?xml version='1.0' ?><data><action type='updated' sid='{$ev->data->id}' tid='{$ev->data->id}'></action></data>";
		else {
			if (strpos($content, "302 Moved Temporarily") !== false) {
				// it's 302 response. gCal doc tell us to send one more request but with gsessionid parameter
				preg_match("/Location:(.*)/", $content, $matches);
				$url = trim($matches[1]);
				return $this->updateEvent($ev, $ev_json, $url);
			} else {
				// some unknown response
				return "<?xml version='1.0' ?><data><action type='error' sid='{$ev->data->id}' tid='{$ev->data->id}'></action></data>";
			}
		}
	}


	/*! take default event, maps custom fields to gCal event and try to save it
	 *	@return
	 *		insert status in XML format
	 */
	private function insert() {
		$id = $_GET['id'];
		// take default gCal event
		$ev_json = $this->getInsertJSON();
		$ev = $this->mapEvent($ev_json);
		$ev->data->id = $id;
		$xml = $this->insertEvent($ev, $ev_json);
		return $xml;
	}


	/*! get default gCal event JSON
	 *	@return
	 *		default gCal event JSON
	 */
	private function getInsertJSON() {
		$json = '{
	"data": {
		"title": "New event",
		"details": "",
		"transparency": "opaque",
		"status": "confirmed",
		"location": "",
		"when": [
			{
				"start": "2010-04-17T15:00:00.000Z",
				"end": "2010-04-17T17:00:00.000Z"
			}
		]
	}
}';
		return $json;
	}


	/*! sends insert request and process response
	 *	@param ev
	 *		event as object
	 *	@param ev_json
	 *		event encoded in JSON
	 *	@url
	 *		request url
	 *	@return
	 *		inserting result as XML
	 */
	private function insertEvent($ev, $ev_json, $url = false) {
		if ($url === false)
			$url = "https://www.google.com/calendar/feeds/".$this->cals_hash[$this->cal_name]."/private/full?alt=jsonc";

		// sends request
		$this->log("\nSend inserting request");
		$content = $this->httpRequest($url, Array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => array(
				'Authorization: GoogleLogin auth='.$this->auth,
				'GData-Version: 2',
				'Content-type: application/json'
			),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $ev_json
		));

		// process result
		if (strpos($content, "201 Created") !== false) {
			// inserting is finished successful
			// we have to take new event id and insert it in result xml
			$content = explode("\r\n\r\n", $content);
			$json = $content[1];
			$json = json_decode($json);
			return "<?xml version='1.0' ?><data><action type='inserted' sid='{$ev->data->id}' tid='{$json->data->id}'></action></data>";
		} else {
			// it's 302 response. gCal doc tell us to send one more request but with gsessionid parameter
			if (strpos($content, "302 Moved Temporarily") !== false) {
				preg_match("/Location:(.*)/", $content, $matches);
				$url = trim($matches[1]);
				return $this->insertEvent($ev, $ev_json, $url);
			} else {
				// some error occurs during creating new event
				return "<?xml version='1.0' ?><data><action type='error' sid='{$ev->data->id}' tid='{$ev->data->id}'></action></data>";
			}
		}
	}


	/*! take event by id and delete it
	 *	@return
	 *		delete status as XML
	 */
	private function delete() {
		$id = $_GET['id'];
		$ev_json = $this->retrieveEvent($this->cals_hash[$this->cal_name], $id);
		$ev = json_decode($ev_json);
		$xml = $this->deleteEvent($ev);
		return $xml;
	}


	/*! sends delete event request and process response
	 *	@param ev
	 *		event as object
	 *	@url
	 *		request url
	 *	@return
	 *		deleting result as XML
	 */
	private function deleteEvent($ev, $url = false) {
		if ($url === false)
			$url = $ev->data->selfLink.'?alt=jsonc';

		// sends request
		$this->log("\nSend deleting request");
		$content = $this->httpRequest($url, Array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_CUSTOMREQUEST => 'DELETE',
			CURLOPT_HTTPHEADER => array(
				'Authorization: GoogleLogin auth='.$this->auth,
				'GData-Version: 2',
				'If-Match: '.$ev->data->etag
			)
		));

		if (strpos($content, "200 OK") !== false)
			// everything is ok. event was deleted
			return "<?xml version='1.0' ?><data><action type='deleted' sid='{$ev->data->id}' tid='{$ev->data->id}'></action></data>";
		else {
			if (strpos($content, "302 Moved Temporarily") !== false) {
				// it's 302 response. gCal doc tell us to send one more request but with gsessionid parameter
				preg_match("/Location:(.*)/", $content, $matches);
				$url = trim($matches[1]);
				return $this->deleteEvent($ev, $url);
			} else {
				// some error occurs during event deleting
				return "<?xml version='1.0' ?><data><action type='error' sid='{$ev->data->id}' tid='{$ev->data->id}'></action></data>";
			}
		}
	}


	/*! enables logging
	 *	@param log_mode
	 *		true to turn on, false to turn off
	 *	@param log_name
	 *		log file name
	 */
	public function enable_log($log_mode, $log_name = 'google_proxy_log.log') {
		$this->log_mode = $log_mode;
		$this->log_name = $log_name;
	}


	/*! logs given data
	 *	@msg
	 *		message to send into log file
	 */
	private function log($msg) {
		// if log_mode is false we don't have any work here
		if ($this->log_mode === false) return;

		// if given message is object or array we present it as string
		if (is_object($msg))
			$msg = print_r($msg, true);
		if (is_array($msg))
			$msg = print_r($msg, true);

		// adds date and delimiters into message
//		$msg = $delim.date("Y-m-d H:i:s").$delim.$msg.$delim;
		
		// sends message into log-file
		error_log($msg."\n", 3, $this->log_name);
	}


	/*! export data from database to google calendar
	 */
	public function import($connection, $table) {
		$this->log($this->delim.date("Y-m-d H:i:s").$this->delim."Retrieving calendars");

		$counter = 0;
		$this->cals = $this->getCalendars();
		if (isset($this->cals_hash[$this->cal_name])) {
			$this->log("\nRetrieving events");
			$events = $this->retrieveAllEvents($this->cals_hash[$this->cal_name]);
			for ($i = 0; $i < count($events); $i++) {
				$ev = $events[$i];
				$fields = Array();
				$values = Array();
				foreach ($this->export_map as $googleField => $dhtmlxField) {
					if (!isset($ev->$googleField)) continue;
					$fields[] = $dhtmlxField;
					$values[] = "'".$ev->$googleField."'";
				}
				$query = "INSERT INTO ".$table." (".implode(", ", $fields).") VALUES (".implode(", ", $values).")";
				mysql_query($query, $connection);
				if (mysql_errno($connection) == 0) $counter++;
			}
			return $counter;
		} else {
			return false;
			throw new Exception("Unknown gCal calendar");
		}
	}


	/*! from database to google calendar
	 *  @param connection
	 *		database connection
	 *	@table
	 *		events table name
	 *	@return
	 *		number of imported events
	 */
	public function export($connection, $table) {
		$this->log($this->delim.date("Y-m-d H:i:s").$this->delim."Retrieving calendars");

		$counter = 0;
		$this->cals = $this->getCalendars();
		if (isset($this->cals_hash[$this->cal_name])) {
			$this->seconds = '';

			$fields = Array();
			foreach ($this->export_back_map as $dhtmlxField => $googleField)
				$fields[] = $dhtmlxField;
			$fields = implode(", ", $fields);
			$query = "SELECT {$fields} FROM ".$table;
			$res = mysql_query($query, $connection);
			$count = 0;
			while ($event = mysql_fetch_assoc($res)) {
				$ev_json = $this->getInsertJSON();
				$ev = json_decode($ev_json);
				$ev = $this->mapEvent($ev_json, $this->export_back_map, $event);
				$ev->data->id = 0;
				$xml = $this->insertEvent($ev, $ev_json);
				$count++;
			}
			return $count;
		} else {
			return false;
			throw new Exception("Unknown gCal calendar");
		}
	}

	private function getCalendars() {
//		session_start();
		$cals = $this->retrieveAllCalendars();
//		if (isset($_SESSION[$this->email][$this->cal_name])) {
//			$cals = $this->getCalFromSession();
//		} else {
//			$cals = $this->retrieveAllCalendars();
//			$this->pushCalToSession();
//		}
		return $cals;
	}
	
	public function pushCalToSession() {
		$cal_id = $this->cals_hash[$this->cal_name];
		$cal_timezone = $this->timezones[$cal_id];
		if (!isset($_SESSION[$this->email]))
			$_SESSION[$this->email] = Array();
		if (!isset($_SESSION[$this->email][$this->cal_name]))
			$_SESSION[$this->email][$this->cal_name] = Array();
		$_SESSION[$this->email][$this->cal_name]['cal_id'] = $cal_id;
		$_SESSION[$this->email][$this->cal_name]['cal_timezone'] = $cal_timezone;
	}
	
	public function getCalFromSession() {
		if (isset($_SESSION[$this->email][$this->cal_name])) {
			$cal_id = $_SESSION[$this->email][$this->cal_name]['cal_id'];
			$cal_timezone = $_SESSION[$this->email][$this->cal_name]['cal_timezone'];
			$this->cals_hash[$this->cal_name] = $cal_id;
			$this->timezones[$cal_id] = $cal_timezone;
			return Array('id' => $cal_id, 'title' => $this->cal_name, 'timezone' => $cal_timezone);
		}
		return Array();
	}
}

?>