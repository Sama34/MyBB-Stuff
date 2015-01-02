<?php
	defined('IN_MYBB') OR die('No');

	function blacklist_avatars_info() {
		return array(
			  'name'          => 'BlackList Avatars URLS',
			  'description'   => 'Ability to blacklist avatar urls?',
			  'author'        => 'Cake',
			  'website'       => 'http://keybase.io/to',
			  'version'       => '1.0',
			  'compatibility' => '18*'
		);
	}

	function blacklist_avatars_activate() {
		global $db;

		$setting_group = array('name' => 'blacklist_avatars', 'title' => 'blacklist Avatars');

		$gid = $db->insert_query("settinggroups", $setting_group);

		$setting = array('name'        => 'blacklist_domains',
		                 'gid'         => $gid,
		                 'title'       => 'Domains to blacklist',
		                 'description' => 'Enter domains to blacklist line by line',
		                 'optionscode' => 'textarea',
		                 'disporder'   => 1);

		$db->insert_query('settings', $setting);

		rebuild_settings();
	}

	function blacklist_avatars_deactivate() {
		global $db;

		$db->delete_query('settings', "name = 'blacklist_domains'");
		$db->delete_query('settinggroups', "name = 'blacklist_avatars'");

		rebuild_settings();
	}

	function blacklist_avatars_check() {
		global $mybb, $tmp_url, $settings;

		$mybb->input['avatarurl'] = trim($mybb->get_input('avatarurl'));

		if (empty($mybb->input['remove']) && !empty($mybb->input['avatarurl']) && !empty($settings['blacklist_domains'])) {
			if (validate_email_format($mybb->input['avatarurl']) == FALSE) {
				$parsed = parse_url($mybb->input['avatarurl'], PHP_URL_HOST);
				$test_regex = implode('|', array_map('preg_quote', array_map('trim', explode(PHP_EOL, $settings['blacklist_domains']))));

				if (preg_match('#^' . $test_regex . '$#i', $parsed)) {
					$tmp_url = $mybb->input['avatarurl'];
					unset($mybb->input['avatarurl']);
				}
			}
		}
	}

  // You know, hooks would be nice and all..
	function blacklist_avatars_error() {
		global $mybb, $avatar_error, $tmp_url;

		if (!empty($tmp_url))
			$avatar_error = inline_error('Specified avatar url is blacklisted.');
	}

	$plugins->add_hook('usercp_do_avatar_start', 'blacklist_avatars_check');
	$plugins->add_hook('usercp_avatar_start', 'blacklist_avatars_error');
