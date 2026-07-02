<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
error_reporting(E_ALL);

// Test feed processing directly
$addon = EMP_GF_Addon::get_instance();
$form = GFAPI::get_form(2); // assuming form ID 2 is the registration form
$entry = GFAPI::get_entry(16); // wait, which entry ID? I'll just use dummy data.
print_r(EMP_GF_Integration::$last_attendee_ids);
