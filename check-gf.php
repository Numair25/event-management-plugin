<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
$forms = GFAPI::get_forms();
foreach ($forms as $form) {
	echo "Form ID: " . $form['id'] . "\n";
	print_r($form['confirmations']);
}
