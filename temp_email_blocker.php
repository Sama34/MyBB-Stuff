<?php

	define('IN_MYBB', 1);

	require_once 'global.php';

	$inserts = [];

	$emails = fetch_remote_file('https://raw.githubusercontent.com/martenson/disposable-email-domains/master/disposable_email_blacklist.conf');

	foreach (array_filter(array_map($func, explode(PHP_EOL, $emails))) as $e)
		$inserts[] = ['filter' => $db->escape_string(trim($e)), 'type' => 3, 'dateline' => TIME_NOW];

	$db->insert_query_multiple("banfilters", $inserts);

	$cache->update_bannedemails();
