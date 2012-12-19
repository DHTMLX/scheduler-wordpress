<?php


class SchedulerDate {
	public $ev_id;
	public $start_date;
	public $end_date;
	public $text;
	public $ev_pid;
	public $rec_type;
	public $event_length;
	public $type;
	public $coun;
	public $coun2;
	public $day;
	public $days;
	public $result;
	public $updates;
	public $updates_time;
	public $date_start_general;

	
	function __construct($data, $updates, $date_start_general) {
		$start_date = date_parse($data['start_date']);
		$this->start_date = mktime($start_date['hour'], $start_date['minute'], $start_date['second'], $start_date['month'], $start_date['day'], $start_date['year']);
		$end_date = date_parse($data['end_date']);
		$this->end_date = mktime($end_date['hour'], $end_date['minute'], $end_date['second'], $end_date['month'], $end_date['day'], $end_date['year']);
		$this->date_start_general = $date_start_general;
		if ($this->end_date == false) {
			$this->end_date = mktime(0, 0, 0, 1, 1, 2038);
		}
		$this->text = $data['text'];
		$this->rec_type = $data['rec_type'];
		$this->event_length = $data['event_length'];
		$this->ev_id = $data['event_id'];
		$this->ev_pid = $data['event_pid'];
		$this->orig_event = $data;
		if ($this->rec_type != '') {
			list($this->type, $this->coun, $this->coun2, $this->day, $this->days, $this->extra) = preg_split("/(_|#)/", $this->rec_type);
		}
		$this->days = preg_split("/,/",$this->days);

		if ($this->days[0] == '') {
			$this->days = array();
		}

		$this->updates = $updates;
	}
	
	
	function get_day_of_week($time_stamp) {
		$week_day = getdate($time_stamp);
		$week_day = $week_day['wday'];
		return $week_day;
	}


	function get_updates($cur_date) {
		if (isset($this->updates[$cur_date])) {
			if ($this->updates[$cur_date]['event_pid'] == $this->ev_id) {
				if ($this->updates[$cur_date]['rec_type'] == 'none') {
					return 1;
				}
				if ($this->updates[$cur_date]['rec_type'] == '') {
					return array('event_id' => $this->updates[$cur_date]['event_id'], 'start_date' => $this->updates[$cur_date]['start_date'], 'end_date' => $this->updates[$cur_date]['end_date'], 'text' => $this->updates[$cur_date]['text'], 'rec_type' => $this->rec_type,  'event_pid' => $this->updates[$cur_date]['event_pid'], 'event_length' => $this->event_length );
				}
				return 0;
			}
		} else {
			return 0;
		}
	}
	
	
	function get_days($cur_date, $date_start, $date_end) {
		$day = 60*60*24;
		for ($j = 0; $j<7; $j++) {
			$week_day = $this->get_day_of_week($cur_date);
			if (in_array($week_day, $this->days)) {
				if (($cur_date > $date_start)&&($cur_date < $date_end)) {
					$changes = $this->get_updates($cur_date);
					if ($changes == 0) {
						if ($cur_date > $this->start_date) {
							$this->result[] = array('event_id' => $this->ev_id, 'start_date' => date("Y-m-d H:i:s", $cur_date), 'end_date' => date("Y-m-d H:i:s", $cur_date + $this->event_length), 'text' => $this->text, 'rec_type' => $this->rec_type,  'event_pid' => $this->ev_pid, 'event_length' => $this->event_length );
						}
					} elseif ($changes != 1) {
						$this->result[] = $changes;
					}
				}
			}
			$cur_date += $day;
		}
		return $cur_date;
	}

	function get_day($cur_date, $date_start, $date_end) {
		$cur_date = mktime(date("H", $cur_date), date("i", $cur_date), date("s", $cur_date), date("m", $cur_date), 1, date("Y", $cur_date));
		$coun = ($this->day - 1)*7;
		$cday = $this->get_day_of_week($cur_date);
		$nday = $this->coun2*1 + $coun - $cday + 1;
		if ($nday <= $coun) {
			$cur_date = mktime(date("H", $cur_date), date("i", $cur_date), date("s", $cur_date), date("m", $cur_date), $nday+7, date("Y", $cur_date));
		} else {
			$cur_date = mktime(date("H", $cur_date), date("i", $cur_date), date("s", $cur_date), date("m", $cur_date), $nday, date("Y", $cur_date));
		}
		if (($cur_date > $date_start)&&($cur_date < $date_end)) {
			$changes = $this->get_updates($cur_date);
			if ($changes == 0) {
				if (date("Y-m-d H:i:s", $cur_date) > $this->date_start_general) {
					$this->result[] = array('event_id' => $this->ev_id, 'start_date' => date("Y-m-d H:i:s", $cur_date), 'end_date' => date("Y-m-d H:i:s", $cur_date + $this->event_length), 'text' => $this->text, 'rec_type' => $this->rec_type,  'event_pid' => $this->ev_pid, 'event_length' => $this->event_length );
				}
			} elseif ($changes != 1) {
				$this->result[] = $changes;
			}
		}
		return $cur_date;
	}
	
	function transpositor($date_start) {
		$step = 1;
		$day = 60*60*24;
		
		if (!$this->coun) {
			return;
		}
		
		if (($this->type == 'day')||($this->type == 'week')) {
			if ($this->type == 'week') {
				$step = 7;
			}
			$diff = $date_start - $this->start_date;
			$diff = floor($diff/$day);
			$delta = floor($diff/($this->coun*$step));
			if ($delta>=0) {
				$this->start_date = mktime(date("H", $this->start_date), date("i", $this->start_date), date("s", $this->start_date), date("m", $this->start_date), date("d", $this->start_date) + $delta*$step*$this->coun, date("Y", $this->start_date));
			}
		} else {
			if ($this->type == 'year')
				$step = 12;
			$delta = ceil(((date("Y", $date_start)*12 + date("m", $date_start)*1) - (date("Y", $this->start_date)*12 + date("m", $this->start_date)*1))/($step*$this->coun));
			if ($delta>=0) {
				$this->start_date = mktime(date("H", $this->start_date), date("i", $this->start_date), date("s", $this->start_date), date("m", $this->start_date)+$delta*$step*$this->coun, date("d", $this->start_date), date("Y", $this->start_date));
			}
		}
	}


	function date_generator($date_start, $date_end) {
		$final = array();
		$day = 60*60*24;
		$cur_date = $this->start_date;
		$this->result = array();
		$i = 0;
		while (($date_end > $cur_date)&&($this->end_date > $cur_date)) {
			$cur_date = $this->start_date;
			if (($this->type == 'day')||($this->type == 'week')) {
				$st = 1;
				if ($this->type == 'week') {
					$st = 7;
				}
				$step = $st*$i*$this->coun;
				$cur_date += $step*$day;
			} elseif (($this->type == 'month')||($this->type == 'year')) {
				$st = 1;
				if ($this->type == 'year') {
					$st = 12;
				}
				$step = $st*$i*$this->coun;
				$cur_date = mktime(date("H", $cur_date), date("i", $cur_date), date("s", $cur_date),date("m", $cur_date) + $step, date("d", $cur_date), date("Y", $cur_date));
			}
			$this->get_correct_date($cur_date, $date_start, $date_end);
			$i++;
			if ($this->type == '')
				break;
		}
		return $this->result;
	}


	function get_correct_date($cur_date, $date_start, $date_end) {
		$final = array();
		$day = 60*60*24;

		if (count($this->days)) {
			$week_day = $this->get_day_of_week($cur_date);
			$cur_date -= ((--$week_day)*$day);
			$cur_date = $this->get_days($cur_date, $date_start, $date_end);
		} elseif (($this->coun2 != '')&&($this->day != '')) {
				$cur_date = $this->get_day($cur_date, $date_start, $date_end);
			} else {
				if (($cur_date > $date_start)&&($cur_date < $date_end)) {
					$changes = $this->get_updates($cur_date);
					if ($changes == 0) {
						if (date("Y-m-d H:i:s", $cur_date) > $this->date_start_general) {
							$ev = $this->orig_event;
							$ev['start_date'] = date("Y-m-d H:i:s", $cur_date);
							$ev['end_date'] = date("Y-m-d H:i:s", $cur_date + $this->event_length);
							$this->result[] = $ev;
//							$this->result[] = array('event_id' => $this->ev_id, 'start_date' => date("Y-m-d H:i:s", $cur_date), 'end_date' => date("Y-m-d H:i:s", $cur_date + $this->event_length), 'text' => $this->text, 'rec_type' => $this->rec_type,  'event_pid' => $this->ev_pid, 'event_length' => $this->event_length );
						}
					} elseif ($changes != 1) {
						$this->result[] = $changes;
					}
				}
			}
	}
}



class SchedulerHelper
{
	public $date_start;
	public $date_end;
	public $date_start_ts;
	public $date_end_ts;
	public $connect;
	public $table_name;
	public $field_start;
	public $field_end;

	function __construct($connect, $table_name, $fields="start_date,end_date") {
		$this->connect = $connect;
		$this->table_name = $table_name;
		list($this->field_start, $this->field_end) = explode(",", $fields);
		$this->field_start = trim($this->field_start);
		$this->field_end = trim($this->field_end);
	}


	function get_dates($date_start, $date_end) {
		$this->date_start = $date_start;
		$this->date_end = $date_end;
		$date_start = date_parse($date_start);
		$this->date_start_ts = mktime($date_start['hour'], $date_start['minute'], $date_start['second'], $date_start['month'], $date_start['day'], $date_start['year']);
		$date_end = date_parse($date_end);
		$this->date_end_ts = mktime($date_end['hour'], $date_end['minute'], $date_end['second'], $date_end['month'], $date_end['day'], $date_end['year']);

		$final = array();
		$updates = Array();
		$query = "SELECT * FROM ".$this->table_name." WHERE `rec_type`='none' OR (`rec_type`='' AND `event_length`!='0')";
		$res = mysql_query($query, $this->connect);
		for ($i = 0; $i < mysql_num_rows($res); $i++) {
			$event = mysql_fetch_assoc($res);
			$updates[mysql_result($res, $i, 'event_length')] = $event;
		}

		$query = "SELECT * FROM ".$this->table_name." WHERE (`start_date`<='".($this->date_end)."' AND `end_date`>='".($this->date_start)."' AND ((`event_pid`='0') OR (`event_pid`!='0' AND `event_length`<'".$this->date_start_ts."')))";
		$res = mysql_query($query, $this->connect);

		while ($data = mysql_fetch_assoc($res)) {
			$event_cur = new SchedulerDate($data, $updates, $this->date_start);
			$event_cur->transpositor($this->date_start_ts);
			$final_temp = $event_cur->date_generator($this->date_start_ts, $this->date_end_ts);
			foreach ($final_temp as $v) {
				$final[] = $v;
			}
		}
		return $final;
	}
}




?>