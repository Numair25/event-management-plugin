<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'emp_attendees';
$attendees = $wpdb->get_results("SELECT id, name, email, event_id, ticket_type_id, printed_status, source FROM $table ORDER BY id DESC LIMIT 10");
print_r($attendees);
