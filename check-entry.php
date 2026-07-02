<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
global $wpdb;
$attendees = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}emp_attendees ORDER BY id DESC LIMIT 5");
print_r($attendees);
