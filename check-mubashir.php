<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'emp_attendees';
$attendees = $wpdb->get_results("SELECT id, name, email, event_id, ticket_type_id FROM $table WHERE name LIKE '%Mubashir%'");
print_r($attendees);
