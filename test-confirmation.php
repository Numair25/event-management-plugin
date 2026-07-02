<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
error_reporting(E_ALL);

$entries = GFAPI::get_entries(2);
if ($entries) {
    $entry = $entries[0]; // get latest
    $form = GFAPI::get_form($entry['form_id']);
    $emp = new EMP_GF_Integration();
    
    // Simulate what append_badge_download does using entry notes (since last_attendee_ids will be empty)
    EMP_GF_Integration::$last_attendee_ids = array();
    $html = $emp->append_badge_download('CONFIRMATION TEXT', $form, $entry, false);
    
    echo $html;
}
