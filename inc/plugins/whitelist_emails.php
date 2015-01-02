<?php
	defined('IN_MYBB') OR die('No');

	function whitelist_emails_info() {
		return array(
			  'name'          => 'Whitelisted Emails',
			  'description'   => 'Ability to Whitelist email carriers rather than blacklisting them',
			  'author'        => 'Cake',
			  'website'       => 'http://keybase.io/to',
			  'version'       => '1.0',
			  'compatibility' => '18*'
		);
	}

	function whitelist_emails_activate() {
		global $db;

		$default = ['gmail.*', 'googlemail.*', 'hotmail.*', 'hotmail.co.*', 'yahoo.*', 'ymail.*'];

		$gid = $db->insert_query("settinggroups", ['name' => 'whitelist_emails', 'title' => 'Whitelisted Emails']);

		$setting = array(
			  array('name'        => 'whitelist_emails_whitelist',
			        'gid'         => $gid,
			        'title'       => 'Domains to whitelist',
			        'description' => 'One per line; * covers alphanumbric input',
			        'optionscode' => 'textarea',
			        'value'       => $db->escape_string(implode(PHP_EOL, $default))),
			  array('name'        => 'whitelist_emails_error',
			        'gid'         => $gid,
			        'title'       => 'The error',
			        'description' => 'The error that will be returned',
			        'optionscode' => 'text',
			        'value'       => $db->escape_string('We only accept the following email carriers: %s')));

		$db->insert_query_multiple('settings', $setting);

		rebuild_settings();
	}

	function whitelist_emails_deactivate() {
		global $db;

		$db->delete_query('settings', "name like 'whitelist_emails_%'");
		$db->delete_query('settinggroups', "name = 'whitelist_emails'");

		rebuild_settings();
	}

	/** @var $handler UserDataHandler */
	function whitelist_emails_check(&$handler) {
		global $user, $settings;

		if (empty(trim($settings['whitelist_emails_whitelist'])))
			return FALSE;

		if ($handler->method == "insert" || array_key_exists('email', $user)) {
			$email = explode('@', $user['email'], 2);

			if (count($email) != 2)
				return FALSE;

			$whitelist = array_filter(array_map('trim', explode(PHP_EOL, $settings['whitelist_emails_whitelist'])));

			$func = function ($wl) {
				return str_replace('\*', '[A-Za-z0-9]+', preg_quote($wl, '#'));
			};

			if (!preg_match('#^(' . implode('|', array_map($func, $whitelist)) . ')$#i', $email[1]))
				return FALSE | $handler->set_error(sprintf($settings['whitelist_emails_error'], implode(', ', $whitelist)));
		}
	}

	$plugins->add_hook('datahandler_user_validate', 'whitelist_emails_check');
