<?php
/**
 * Plugin Name: Event Management & Check-In Plugin
 * Plugin URI:  https://standardtouch.com
 * Description: Manages the full lifecycle of an in-person event: registration, payments, photo badges, on-site QR check-in, communications, and reporting. Requires Gravity Forms.
 * Version:     1.1.1
 * Author:      StandardTouch
 * Author URI:  https://standardtouch.com
 * Text Domain: event-management-plugin
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'EMP_VERSION', '1.1.1' );
define( 'EMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Require Composer autoloader
if ( file_exists( EMP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once EMP_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The code that runs during plugin activation.
 */
function activate_event_management_plugin() {
	require_once EMP_PLUGIN_DIR . 'includes/class-emp-activator.php';
	EMP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_event_management_plugin() {
	require_once EMP_PLUGIN_DIR . 'includes/class-emp-deactivator.php';
	EMP_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_event_management_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_event_management_plugin' );

/**
 * Check for Gravity Forms dependency.
 */
add_action( 'admin_notices', function() {
	if ( ! class_exists( 'GFForms' ) ) {
		echo '<div class="notice notice-error"><p>' . __( '<strong>Event Management & Check-In Plugin:</strong> Gravity Forms is required but not active. Please install and activate Gravity Forms for this plugin to work.', 'event-management-plugin' ) . '</p></div>';
	}
});

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require EMP_PLUGIN_DIR . 'includes/class-emp-core.php';

/**
 * Begins execution of the plugin.
 */
function run_event_management_plugin() {
	$plugin = new EMP_Core();
	$plugin->run();
}
run_event_management_plugin();
