<?php

class DHXDBConfig {
	public $connection = null;
	public $prefix = null;
	public $events = 'events_rec';

	// options table configs
	public $options = null;
	public $options_name = null;
	public $options_value = null;

	// user table configs
	public $users = null;
	public $users_id = null;
	public $users_login = null;
	
	public $locale = 'en';
	public $default_xml = '';
}

?>