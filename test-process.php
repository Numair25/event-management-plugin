<?php
require 'c:/xampp/htdocs/event-management/wp-load.php';
$entry = GFAPI::get_entry(23); // Use a valid entry ID if 23 exists, or just query latest
global $wpdb;
$entry_id = $wpdb->get_var("SELECT id FROM wp_gf_entry ORDER BY id DESC LIMIT 1");
$entry = GFAPI::get_entry($entry_id);
$form = GFAPI::get_form($entry['form_id']);
$addon = EMP_GF_Addon::get_instance();
$feeds = $addon->get_active_feeds($form['id']);
foreach ($feeds as $feed) {
    if ($addon->is_feed_condition_met($feed, $form, $entry)) {
        $field_map = $addon->get_field_map_fields( $feed, 'mappedFields' );
        $name_val = $addon->get_field_value( $form, $entry, rgar( $field_map, 'name' ) );
        echo "Raw Name Val:\n";
        print_r($name_val);
        $names = is_serialized( $name_val ) ? maybe_unserialize( $name_val ) : ( is_array( $name_val ) ? $name_val : array( $name_val ) );
        echo "Names array:\n";
        print_r($names);
    }
}
