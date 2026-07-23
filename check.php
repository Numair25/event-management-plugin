<?php
require_once( dirname(__DIR__, 3) . '/wp-load.php' );
global $wpdb;
$table_attendees = $wpdb->prefix . 'emp_attendees';
$count = $wpdb->get_var("SELECT COUNT(id) FROM $table_attendees WHERE phone IS NOT NULL AND phone != ''");
file_put_contents('count.txt', $count);
