<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

global $wpdb;
$attendee = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}emp_attendees ORDER BY id DESC LIMIT 1");
if ($attendee) {
    require_once 'c:/xampp/htdocs/event-management/wp-content/plugins/event-management-plugin/services/class-emp-badge-generator.php';
    $generator = new EMP_Badge_Generator();
    $generator->generate_individual($attendee->id);
} else {
    echo "No attendees found";
}
