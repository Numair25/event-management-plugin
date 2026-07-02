<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
$addon = EMP_GF_Addon::get_instance();
$feeds = $addon->get_active_feeds(2); // assuming form 2
foreach ($feeds as $feed) {
	print_r($feed['meta']);
}
