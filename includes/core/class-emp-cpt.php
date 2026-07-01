<?php
/**
 * Register Custom Post Types for the plugin.
 */
class EMP_CPT {

	public function register() {
		$this->register_event_cpt();
	}

	private function register_event_cpt() {
		$labels = array(
			'name'                  => _x( 'Events', 'Post Type General Name', 'event-management-plugin' ),
			'singular_name'         => _x( 'Event', 'Post Type Singular Name', 'event-management-plugin' ),
			'menu_name'             => __( 'Events', 'event-management-plugin' ),
			'name_admin_bar'        => __( 'Event', 'event-management-plugin' ),
			'archives'              => __( 'Event Archives', 'event-management-plugin' ),
			'attributes'            => __( 'Event Attributes', 'event-management-plugin' ),
			'parent_item_colon'     => __( 'Parent Event:', 'event-management-plugin' ),
			'all_items'             => __( 'All Events', 'event-management-plugin' ),
			'add_new_item'          => __( 'Add New Event', 'event-management-plugin' ),
			'add_new'               => __( 'Add New', 'event-management-plugin' ),
			'new_item'              => __( 'New Event', 'event-management-plugin' ),
			'edit_item'             => __( 'Edit Event', 'event-management-plugin' ),
			'update_item'           => __( 'Update Event', 'event-management-plugin' ),
			'view_item'             => __( 'View Event', 'event-management-plugin' ),
			'view_items'            => __( 'View Events', 'event-management-plugin' ),
			'search_items'          => __( 'Search Event', 'event-management-plugin' ),
			'not_found'             => __( 'Not found', 'event-management-plugin' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'event-management-plugin' ),
			'featured_image'        => __( 'Event Logo/Image', 'event-management-plugin' ),
			'set_featured_image'    => __( 'Set event image', 'event-management-plugin' ),
			'remove_featured_image' => __( 'Remove event image', 'event-management-plugin' ),
			'use_featured_image'    => __( 'Use as event image', 'event-management-plugin' ),
			'insert_into_item'      => __( 'Insert into event', 'event-management-plugin' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'event-management-plugin' ),
			'items_list'            => __( 'Events list', 'event-management-plugin' ),
			'items_list_navigation' => __( 'Events list navigation', 'event-management-plugin' ),
			'filter_items_list'     => __( 'Filter events list', 'event-management-plugin' ),
		);
		$args = array(
			'label'                 => __( 'Event', 'event-management-plugin' ),
			'description'           => __( 'In-person events managed by the plugin', 'event-management-plugin' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail' ),
			'hierarchical'          => false,
			'public'                => false, // Internal management primarily
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-tickets-alt',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'capabilities' => array(
				'edit_post'          => 'manage_event_settings',
				'read_post'          => 'manage_event_settings',
				'delete_post'        => 'manage_event_settings',
				'edit_posts'         => 'manage_event_settings',
				'edit_others_posts'  => 'manage_event_settings',
				'publish_posts'      => 'manage_event_settings',
				'read_private_posts' => 'manage_event_settings',
			),
			'show_in_rest'          => true,
		);
		register_post_type( 'emp_event', $args );
	}
}
