<?php
define('WP_USE_THEMES', false);
require_once 'c:/xampp/htdocs/event-management/wp-load.php';

$form_id = GFAPI::add_form(array(
    'title' => 'Test Form',
    'fields' => array(
        array('type' => 'name', 'id' => 1, 'label' => 'Name'),
        array('type' => 'email', 'id' => 2, 'label' => 'Email')
    )
));

$result = GFAPI::submit_form($form_id, array(
    'input_1_3' => 'John',
    'input_1_6' => 'Doe',
    'input_2' => 'john@standardtouch.com'
));

echo "Result:\n";
var_dump($result);

GFAPI::delete_form($form_id);
