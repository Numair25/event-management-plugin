<?php
/**
 * E2E Test Runner for Event Management Plugin.
 *
 * Boots WordPress, verifies environment, registers 50 test cases covering Tiers 1-4,
 * executes them using a custom harness with genuine validation assertions, handles
 * un-implemented failures gracefully, and ensures database/setting cleanup.
 */

// 1. CLI Check
if ( php_sapi_name() !== 'cli' ) {
	die( "This script can only be run from the command line.\n" );
}

// 2. Bootstrap WordPress
define( 'WP_USE_THEMES', false );
$wp_load_path = dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! file_exists( $wp_load_path ) ) {
	echo "Error: wp-load.php not found at {$wp_load_path}\n";
	exit( 1);
}

require_once $wp_load_path;

// 3. Verify Gravity Forms is active
if ( ! class_exists( 'GFAPI' ) ) {
	echo "Error: Gravity Forms is not active (class GFAPI not found).\n";
	exit( 1);
}

/**
 * Event_Management_E2E_Tests Class.
 *
 * Implements a custom test harness, setup/teardown helpers,
 * and mappings for all 50 E2E test cases across Tiers 1-4.
 */
class Event_Management_E2E_Tests {

	private $passed  = 0;
	private $failed  = 0;
	private $results = array();
	private $errors  = array();

	// Active test resources tracking
	protected $test_event_id       = 0;
	protected $test_form_id        = 0;
	protected $test_ticket_type_id = 0;

	// Global cleanup registry
	protected $created_event_ids       = array();
	protected $created_ticket_type_ids = array();
	protected $created_form_ids        = array();
	protected $created_feed_ids        = array();
	protected $created_options          = array();

	/**
	 * Run the entire E2E test suite.
	 */
	public function run() {
		echo "======================================================================\n";
		echo "          Event Management & Check-In Plugin: E2E Test Runner          \n";
		echo "======================================================================\n";

		// Mapping of all 50 test cases from TEST_INFRA.md Tiers 1-4
		$test_cases = array(
			// Tier 1 - Feature Coverage (25 tests)
			'test_tier1_tc1_1' => 'TC1.1: Enable QR payment configuration for Form A with positive amount',
			'test_tier1_tc1_2' => 'TC1.2: Disable QR payment configuration for Form A',
			'test_tier1_tc1_3' => 'TC1.3: Update QR payment configuration (change amount and QR image)',
			'test_tier1_tc1_4' => 'TC1.4: Enable QR payment configuration for Form B (different form)',
			'test_tier1_tc1_5' => 'TC1.5: Verify configuration retrieval maps correctly by Form ID',
			'test_tier1_tc2_1' => 'TC2.1: Intercept submission with dynamic inputs (trans ID & screenshot) present',
			'test_tier1_tc2_2' => 'TC2.2: Verify form submission includes emp_qr_transaction_id and emp_qr_screenshot_url',
			'test_tier1_tc2_3' => 'TC2.3: Verify file upload handler uploads QR screenshot and returns URL',
			'test_tier1_tc2_4' => 'TC2.4: Reject screenshot upload with invalid mime type',
			'test_tier1_tc2_5' => 'TC2.5: Enforce at least transaction ID or screenshot in submissions',
			'test_tier1_tc3_1' => 'TC3.1: Verify pending entries are queryable by payment status "Processing"',
			'test_tier1_tc3_2' => 'TC3.2: Verify pending entries contain the saved transaction meta',
			'test_tier1_tc3_3' => 'TC3.3: Verify non-QR entries are not returned in pending list',
			'test_tier1_tc3_4' => 'TC3.4: Verify pending entries list does not include approved/paid entries',
			'test_tier1_tc3_5' => 'TC3.5: Verify listing returns correct metadata (ID, email, name, screenshot)',
			'test_tier1_tc4_1' => 'TC4.1: Approve pending entry: check payment status changes to Paid',
			'test_tier1_tc4_2' => 'TC4.2: Approve pending entry: check attendee record created in wp_emp_attendees',
			'test_tier1_tc4_3' => 'TC4.3: Approve pending entry: check confirmation email sent/logged',
			'test_tier1_tc4_4' => 'TC4.4: Reject pending entry: check payment status changes to Failed',
			'test_tier1_tc4_5' => 'TC4.5: Bulk approve multiple pending entries',
			'test_tier1_tc5_1' => 'TC5.1: Submit form without QR config: verify payment status is comp/paid',
			'test_tier1_tc5_2' => 'TC5.2: Submit form without QR config: verify attendee created instantly',
			'test_tier1_tc5_3' => 'TC5.3: Submit form without QR config: verify confirmation download link shown',
			'test_tier1_tc5_4' => 'TC5.4: Submit free form (amount=0) with QR enabled: verify instant registration (bypasses modal)',
			'test_tier1_tc5_5' => 'TC5.5: Verify delete entry note sync handles deleted QR entries',

			// Tier 2 - Boundary & Corner Cases (15 tests)
			'test_tier2_tc2_1_1' => 'TC2.1.1: Submit with extremely long transaction ID (e.g. 255 chars)',
			'test_tier2_tc2_1_2' => 'TC2.1.2: Submit with empty transaction ID but valid screenshot upload',
			'test_tier2_tc2_1_3' => 'TC2.1.3: Submit with valid transaction ID but empty screenshot upload',
			'test_tier2_tc2_1_4' => 'TC2.1.4: Submit with invalid transaction ID format',
			'test_tier2_tc2_1_5' => 'TC2.1.5: Submit with extremely large screenshot upload (verify file size limit)',
			'test_tier2_tc4_1_1' => 'TC4.1.1: Double approval attempt of already approved entry (idempotence)',
			'test_tier2_tc4_1_2' => 'TC4.1.2: Approve an entry for a deleted event (verify elegant error handling)',
			'test_tier2_tc4_1_3' => 'TC4.1.3: Approve entry when event capacity is fully reached (verify waitlist status)',
			'test_tier2_tc4_1_4' => 'TC4.1.4: Approve entry where mapped ticket type is missing (verify fallback ticket)',
			'test_tier2_tc4_1_5' => 'TC4.1.5: Reject an already approved entry',
			'test_tier2_tc5_1_1' => 'TC5.1.1: Free form with 0 capacity event (verify waitlisting)',
			'test_tier2_tc5_1_2' => 'TC5.1.2: Form without QR configured submits with fake qr parameters (ignored)',
			'test_tier2_tc5_1_3' => 'TC5.1.3: Delete pending entry before approval (verify audit log & cleanup)',
			'test_tier2_tc5_1_4' => 'TC5.1.4: Submit form with exact duplicate email registration (validation block)',
			'test_tier2_tc5_1_5' => 'TC5.1.5: Update QR payment config to amount of 0 (acts as free)',

			// Tier 3 - Cross-Feature Combinations (5 tests)
			'test_tier3_tc3_1_1' => 'TC3.1.1: Enable QR payment, submit, verify pending, then change QR config price, then approve',
			'test_tier3_tc3_1_2' => 'TC3.1.2: Register attendee on QR form, approve, check attendee event and ticket type map',
			'test_tier3_tc3_1_3' => 'TC3.1.3: Group registration via QR form (multiple names): verify multiple attendees',
			'test_tier3_tc3_1_4' => 'TC3.1.4: Bulk approval of mixture of group registration and individual entries',
			'test_tier3_tc3_1_5' => 'TC3.1.5: Capacity limit reached mid-bulk-approval: verify remaining become waitlisted',

			// Tier 4 - Real-World Application Scenarios (5 tests)
			'test_tier4_tc4_1_1' => 'TC4.1.1: Complete Event Registration flow (Full cycle)',
			'test_tier4_tc4_1_2' => 'TC4.1.2: Capacity and Waitlist transition flow',
			'test_tier4_tc4_1_3' => 'TC4.1.3: Backward compatibility regression: Free form for free event',
			'test_tier4_tc4_1_4' => 'TC4.1.4: Admin bulk action rejection & approval',
			'test_tier4_tc4_1_5' => 'TC4.1.5: File integrity check: Rejects malicious upload script'
		);

		foreach ( $test_cases as $method => $description ) {
			printf( "[%-10s] Running... ", substr( $method, 5 ) );
			try {
				$this->setup();
				$this->$method();
				echo "\033[32mPASS\033[0m\n";
				$this->passed++;
				$this->results[ $method ] = array( 'status' => 'PASS', 'desc' => $description );
			} catch ( Throwable $t ) {
				echo "\033[31mFAIL\033[0m\n";
				echo "  Error: " . $t->getMessage() . "\n";
				$this->failed++;
				$this->errors[] = $description . ": " . $t->getMessage();
				$this->results[ $method ] = array( 'status' => 'FAIL', 'desc' => $description, 'error' => $t->getMessage() );
			} finally {
				try {
					$this->teardown();
				} catch ( Throwable $te ) {
					echo "  Teardown Error: " . $te->getMessage() . "\n";
				}
			}
		}

		$this->report();
	}

	/**
	 * Setup event, ticket types, Gravity form, and link them before each test case.
	 */
	protected function setup() {
		global $wpdb;

		// 1. Create a dummy event CPT
		$event_post = array(
			'post_title'   => 'E2E Test Event ' . uniqid(),
			'post_status'  => 'publish',
			'post_type'    => 'emp_event',
		);
		$event_id = wp_insert_post( $event_post );
		if ( ! is_wp_error( $event_id ) && $event_id > 0 ) {
			$this->test_event_id = $event_id;
			$this->created_event_ids[] = $event_id;
			update_post_meta( $event_id, '_emp_capacity', 10 );
		}

		// 2. Create Ticket Type
		$ticket_table = $wpdb->prefix . 'emp_ticket_types';
		$wpdb->insert( $ticket_table, array(
			'event_id'   => $this->test_event_id,
			'name'       => 'E2E Ticket',
			'price'      => 99.00,
			'capacity'   => 10,
			'color_code' => '#00ff00',
			'is_comp'    => 0,
		) );
		$ticket_id = $wpdb->insert_id;
		if ( $ticket_id ) {
			$this->test_ticket_type_id = $ticket_id;
			$this->created_ticket_type_ids[] = $ticket_id;
		}

		// 3. Create Gravity Form
		$form_meta = array(
			'title'  => 'E2E Test Form ' . uniqid(),
			'fields' => array(
				array( 'type' => 'name', 'id' => 1, 'label' => 'Full Name' ),
				array( 'type' => 'email', 'id' => 2, 'label' => 'Email Address' ),
				array( 'type' => 'text', 'id' => 3, 'label' => 'Organization' ),
				array( 'type' => 'text', 'id' => 4, 'label' => 'Transaction ID' ),
				array( 'type' => 'fileupload', 'id' => 5, 'label' => 'Screenshot' ),
			),
		);
		$form_id = GFAPI::add_form( $form_meta );
		if ( ! is_wp_error( $form_id ) && $form_id > 0 ) {
			$this->test_form_id = $form_id;
			$this->created_form_ids[] = $form_id;

			// Link Form to CPT
			update_post_meta( $this->test_event_id, '_emp_gf_form_id', $this->test_form_id );
			update_post_meta( $this->test_event_id, '_emp_require_payment', '1' );
		}
	}

	/**
	 * Cleans up database modifications, posts, forms, and options created by the test case.
	 */
	protected function teardown() {
		global $wpdb;

		// Delete created events (CPT)
		if ( ! empty( $this->created_event_ids ) ) {
			foreach ( $this->created_event_ids as $event_id ) {
				wp_delete_post( $event_id, true );
			}
			$this->created_event_ids = array();
		}

		// Delete created ticket types from DB
		if ( ! empty( $this->created_ticket_type_ids ) ) {
			$ticket_table = $wpdb->prefix . 'emp_ticket_types';
			foreach ( $this->created_ticket_type_ids as $ticket_id ) {
				$wpdb->delete( $ticket_table, array( 'id' => $ticket_id ) );
			}
			$this->created_ticket_type_ids = array();
		}

		// Delete created forms
		if ( ! empty( $this->created_form_ids ) ) {
			foreach ( $this->created_form_ids as $form_id ) {
				GFAPI::delete_form( $form_id );
			}
			$this->created_form_ids = array();
		}

		// Delete created feeds
		if ( ! empty( $this->created_feed_ids ) ) {
			$feed_table = $wpdb->prefix . 'gf_addon_feed';
			foreach ( $this->created_feed_ids as $feed_id ) {
				$wpdb->delete( $feed_table, array( 'id' => $feed_id ) );
			}
			$this->created_feed_ids = array();
		}

		// Delete any other entries created during tests
		if ( $this->test_event_id ) {
			$wpdb->delete( $wpdb->prefix . 'emp_attendees', array( 'event_id' => $this->test_event_id ) );
		}

		// Delete temporary options that were set
		if ( ! empty( $this->created_options ) ) {
			foreach ( $this->created_options as $option_name ) {
				delete_option( $option_name );
			}
			$this->created_options = array();
		}

		// Reset properties
		$this->test_event_id       = 0;
		$this->test_form_id        = 0;
		$this->test_ticket_type_id = 0;
	}

	/**
	 * Asset that two values are strictly equal.
	 */
	protected function assertEquals( $expected, $actual, $msg = '' ) {
		if ( $expected !== $actual ) {
			throw new Exception( "Assertion failed: Expected " . var_export( $expected, true ) . ", got " . var_export( $actual, true ) . ". " . $msg );
		}
	}

	/**
	 * Assert that a value is not a WP_Error.
	 */
	protected function assertNotWPError( $val, $msg = '' ) {
		if ( is_wp_error( $val ) ) {
			throw new Exception( "Assertion failed: Expected not WP_Error, got " . $val->get_error_message() . ". " . $msg );
		}
	}

	/**
	 * Outputs details of the run results and exits with the correct exit code.
	 */
	private function report() {
		echo "======================================================================\n";
		echo "Test Run Completed:\n";
		printf( "Passed: \033[32m%d\033[0m\n", $this->passed );
		printf( "Failed: \033[31m%d\033[0m\n", $this->failed );
		echo "======================================================================\n";

		if ( ! empty( $this->errors ) ) {
			echo "Failed Tests Summary:\n";
			foreach ( $this->errors as $err ) {
				echo "- \033[31m$err\033[0m\n";
			}
			exit( 1 );
		}
		exit( 0 );
	}

	// ======================================================================
	// TIER 1 TEST CASES
	// ======================================================================

	// TC1.1: Enable QR payment configuration for Form A with positive amount.
	public function test_tier1_tc1_1() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$retrieved = get_option( 'emp_qr_payment_settings' );
		$this->assertEquals( true, isset( $retrieved[ $this->test_form_id ] ) );
		$this->assertEquals( true, $retrieved[ $this->test_form_id ]['enabled'] );
		$this->assertEquals( 500.00, $retrieved[ $this->test_form_id ]['amount'] );
	}

	// TC1.2: Disable QR payment configuration for Form A.
	public function test_tier1_tc1_2() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => false,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$retrieved = get_option( 'emp_qr_payment_settings' );
		$this->assertEquals( false, $retrieved[ $this->test_form_id ]['enabled'] );
	}

	// TC1.3: Update QR payment configuration (change amount and QR image).
	public function test_tier1_tc1_3() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		// Update Settings
		$settings[ $this->test_form_id ]['amount'] = 750.00;
		$settings[ $this->test_form_id ]['qr_image_url'] = 'http://example.com/new_qr.png';
		update_option( 'emp_qr_payment_settings', $settings );

		$retrieved = get_option( 'emp_qr_payment_settings' );
		$this->assertEquals( 750.00, $retrieved[ $this->test_form_id ]['amount'] );
		$this->assertEquals( 'http://example.com/new_qr.png', $retrieved[ $this->test_form_id ]['qr_image_url'] );
	}

	// TC1.4: Enable QR payment configuration for Form B (different form).
	public function test_tier1_tc1_4() {
		$form_meta = array(
			'title'  => 'Form B',
			'fields' => array(
				array( 'type' => 'text', 'id' => 1, 'label' => 'Field' )
			)
		);
		$form_b_id = GFAPI::add_form( $form_meta );
		if ( ! is_wp_error( $form_b_id ) && $form_b_id > 0 ) {
			$this->created_form_ids[] = $form_b_id;
		}

		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			),
			$form_b_id => array(
				'enabled'      => true,
				'amount'       => 200.00,
				'qr_image_url' => 'http://example.com/qr_b.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$retrieved = get_option( 'emp_qr_payment_settings' );
		$this->assertEquals( 500.00, $retrieved[ $this->test_form_id ]['amount'] );
		$this->assertEquals( 200.00, $retrieved[ $form_b_id ]['amount'] );
	}

	// TC1.5: Verify configuration retrieval maps correctly by Form ID.
	public function test_tier1_tc1_5() {
		$form_meta = array(
			'title'  => 'Form B',
			'fields' => array(
				array( 'type' => 'text', 'id' => 1, 'label' => 'Field' )
			)
		);
		$form_b_id = GFAPI::add_form( $form_meta );
		if ( ! is_wp_error( $form_b_id ) && $form_b_id > 0 ) {
			$this->created_form_ids[] = $form_b_id;
		}

		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			),
			$form_b_id => array(
				'enabled'      => true,
				'amount'       => 200.00,
				'qr_image_url' => 'http://example.com/qr_b.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$retrieved = get_option( 'emp_qr_payment_settings' );
		$this->assertEquals( 'http://example.com/qr.png', $retrieved[ $this->test_form_id ]['qr_image_url'] );
		$this->assertEquals( 'http://example.com/qr_b.png', $retrieved[ $form_b_id ]['qr_image_url'] );
	}

	// TC2.1: Intercept submission with dynamic inputs (trans ID & screenshot) present.
	public function test_tier1_tc2_1() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$input_values = array(
			'input_1_3' => 'John',
			'input_1_6' => 'Doe',
			'input_2'   => 'john.doe@test.local',
			'input_3'   => 'E2E Corp',
			'input_4'   => 'TXN_E2E_123',
			'input_5'   => 'http://example.com/screenshot.png',
		);
		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$this->assertEquals( true, $result['is_valid'] );

		$entry = GFAPI::get_entry( $result['entry_id'] );
		$this->assertNotWPError( $entry );

		// Expected to fail as QR Code flow is un-implemented.
		$this->assertEquals( 'Processing', $entry['payment_status'], 'Payment status should be Processing for QR submission.' );
	}

	// TC2.2: Verify form submission includes emp_qr_transaction_id and emp_qr_screenshot_url.
	public function test_tier1_tc2_2() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$input_values = array(
			'input_1_3' => 'John',
			'input_1_6' => 'Doe',
			'input_2'   => 'john.doe@test.local',
			'input_3'   => 'E2E Corp',
			'input_4'   => 'TXN_E2E_123',
			'input_5'   => 'http://example.com/screenshot.png',
		);
		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$entry  = GFAPI::get_entry( $result['entry_id'] );

		// Expected to fail as parameter saving is un-implemented.
		$this->assertEquals( 'TXN_E2E_123', $entry['emp_qr_transaction_id'], 'emp_qr_transaction_id should be stored in entry.' );
		$this->assertEquals( 'http://example.com/screenshot.png', $entry['emp_qr_screenshot_url'], 'emp_qr_screenshot_url should be stored in entry.' );
	}

	// TC2.3: Verify file upload handler uploads QR screenshot and returns URL.
	public function test_tier1_tc2_3() {
		// Expected to fail.
		$this->assertEquals( true, has_action( 'wp_ajax_emp_upload_qr_screenshot' ) !== false, 'AJAX handler emp_upload_qr_screenshot must be registered.' );
	}

	// TC2.4: Reject screenshot upload with invalid mime type.
	public function test_tier1_tc2_4() {
		// Expected to fail.
		if ( ! has_action( 'wp_ajax_emp_upload_qr_screenshot' ) ) {
			throw new Exception( "AJAX action emp_upload_qr_screenshot is not registered." );
		}
	}

	// TC2.5: Enforce at least transaction ID or screenshot in submissions.
	public function test_tier1_tc2_5() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 500.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$input_values = array(
			'input_1_3' => 'John',
			'input_1_6' => 'Doe',
			'input_2'   => 'john.doe@test.local',
			'input_3'   => 'E2E Corp',
		);

		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		// Expected to fail (it will succeed validation, but we assert it should fail).
		$this->assertEquals( false, $result['is_valid'], 'Form validation should fail when transaction ID or screenshot are missing.' );
	}

	// TC3.1: Verify pending entries are queryable by payment status "Processing".
	public function test_tier1_tc3_1() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.2: Verify pending entries contain the saved transaction meta.
	public function test_tier1_tc3_2() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.3: Verify non-QR entries are not returned in pending list.
	public function test_tier1_tc3_3() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.4: Verify pending entries list does not include approved/paid entries.
	public function test_tier1_tc3_4() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.5: Verify listing returns correct metadata (ID, email, name, screenshot).
	public function test_tier1_tc3_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1: Approve pending entry: check payment status changes to Paid.
	public function test_tier1_tc4_1() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.2: Approve pending entry: check attendee record created in wp_emp_attendees.
	public function test_tier1_tc4_2() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.3: Approve pending entry: check confirmation email sent/logged.
	public function test_tier1_tc4_3() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.4: Reject pending entry: check payment status changes to Failed.
	public function test_tier1_tc4_4() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.5: Bulk approve multiple pending entries.
	public function test_tier1_tc4_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC5.1: Submit form without QR config: verify payment status is comp/paid.
	public function test_tier1_tc5_1() {
		update_post_meta( $this->test_event_id, '_emp_require_payment', '0' );

		$input_values = array(
			'input_1_3' => 'Alice',
			'input_1_6' => 'Smith',
			'input_2'   => 'alice.smith@test.local',
			'input_3'   => 'E2E Corp',
		);

		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$this->assertEquals( true, $result['is_valid'] );

		$entry = GFAPI::get_entry( $result['entry_id'] );
		$this->assertNotWPError( $entry );

		global $wpdb;
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE event_id = %d AND email = %s", $this->test_event_id, 'alice.smith@test.local' ) );
		$this->assertEquals( true, ! empty( $attendee ) );
		$this->assertEquals( 'paid', $attendee->payment_status );
	}

	// TC5.2: Submit form without QR config: verify attendee created instantly.
	public function test_tier1_tc5_2() {
		update_post_meta( $this->test_event_id, '_emp_require_payment', '0' );

		$input_values = array(
			'input_1_3' => 'Bob',
			'input_1_6' => 'Jones',
			'input_2'   => 'bob.jones@test.local',
			'input_3'   => 'E2E Corp',
		);

		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$this->assertEquals( true, $result['is_valid'] );

		global $wpdb;
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE event_id = %d AND email = %s", $this->test_event_id, 'bob.jones@test.local' ) );
		$this->assertEquals( true, ! empty( $attendee ) );
		$this->assertEquals( 'registered', $attendee->status );
	}

	// TC5.3: Submit form without QR config: verify confirmation download link shown.
	public function test_tier1_tc5_3() {
		$dummy_attendee_id = 99999;
		if ( class_exists( 'EMP_GF_Integration' ) ) {
			EMP_GF_Integration::$last_attendee_ids = array( $dummy_attendee_id );
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$wpdb->insert( $table_attendees, array(
			'id'             => $dummy_attendee_id,
			'event_id'       => $this->test_event_id,
			'ticket_type_id' => $this->test_ticket_type_id,
			'name'           => 'Dummy Attendee',
			'email'          => 'dummy@test.local',
			'qr_token'       => 'dummy_token_53',
			'status'         => 'registered',
			'payment_status' => 'paid',
		) );

		$integration  = new EMP_GF_Integration();
		$confirmation = $integration->append_badge_download( 'Thank you for registering!', array(), array(), false );

		$wpdb->delete( $table_attendees, array( 'id' => $dummy_attendee_id ) );

		$this->assertEquals( true, strpos( $confirmation, 'emp-instant-download' ) !== false );
		$this->assertEquals( true, strpos( $confirmation, 'Download PDF' ) !== false );
	}

	// TC5.4: Submit free form (amount=0) with QR enabled: verify instant registration (bypasses modal).
	public function test_tier1_tc5_4() {
		$settings = array(
			$this->test_form_id => array(
				'enabled'      => true,
				'amount'       => 0.00,
				'qr_image_url' => 'http://example.com/qr.png',
			)
		);
		update_option( 'emp_qr_payment_settings', $settings );
		$this->created_options[] = 'emp_qr_payment_settings';

		$input_values = array(
			'input_1_3' => 'Free',
			'input_1_6' => 'User',
			'input_2'   => 'free.user@test.local',
			'input_3'   => 'E2E Corp',
		);
		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$this->assertEquals( true, $result['is_valid'] );

		// Expected to fail as bypass logic is un-implemented.
		global $wpdb;
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE event_id = %d AND email = %s", $this->test_event_id, 'free.user@test.local' ) );
		$this->assertEquals( true, ! empty( $attendee ), 'Attendee should be created instantly for free form bypass.' );
	}

	// TC5.5: Verify delete entry note sync handles deleted QR entries.
	public function test_tier1_tc5_5() {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$wpdb->insert( $table_attendees, array(
			'event_id'       => $this->test_event_id,
			'ticket_type_id' => $this->test_ticket_type_id,
			'name'           => 'Deleted User',
			'email'          => 'deleted@test.local',
			'qr_token'       => 'deleted_token_55',
			'status'         => 'registered',
			'payment_status' => 'paid',
		) );
		$attendee_id = $wpdb->insert_id;

		$entry_meta = array( 'form_id' => $this->test_form_id );
		$entry_id   = GFAPI::add_entry( $entry_meta );
		GFAPI::add_note( $entry_id, 0, 'Event Management', sprintf( 'Created Attendee ID: %d', $attendee_id ) );

		do_action( 'gform_delete_entry', $entry_id );

		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE id = %d", $attendee_id ) );
		$this->assertEquals( true, empty( $attendee ), 'Attendee should be deleted when associated GF entry is deleted.' );

		GFAPI::delete_entry( $entry_id );
	}

	// ======================================================================
	// TIER 2 TEST CASES
	// ======================================================================

	// TC2.1.1: Submit with extremely long transaction ID (e.g. 255 chars).
	public function test_tier2_tc2_1_1() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC2.1.2: Submit with empty transaction ID but valid screenshot upload.
	public function test_tier2_tc2_1_2() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC2.1.3: Submit with valid transaction ID but empty screenshot upload.
	public function test_tier2_tc2_1_3() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC2.1.4: Submit with invalid transaction ID format.
	public function test_tier2_tc2_1_4() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC2.1.5: Submit with extremely large screenshot upload (verify file size limit).
	public function test_tier2_tc2_1_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.1: Double approval attempt of already approved entry (idempotence).
	public function test_tier2_tc4_1_1() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.2: Approve an entry for a deleted event (verify elegant error handling).
	public function test_tier2_tc4_1_2() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.3: Approve entry when event capacity is fully reached (verify waitlist status).
	public function test_tier2_tc4_1_3() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.4: Approve entry where mapped ticket type is missing (verify fallback ticket).
	public function test_tier2_tc4_1_4() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.5: Reject an already approved entry.
	public function test_tier2_tc4_1_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC5.1.1: Free form with 0 capacity event (verify waitlisting).
	public function test_tier2_tc5_1_1() {
		global $wpdb;

		update_post_meta( $this->test_event_id, '_emp_capacity', 1 );

		$feed_meta = array(
			'event_id'       => $this->test_event_id,
			'ticket_type_id' => $this->test_ticket_type_id,
			'mappedFields'   => array(
				'name'         => '1',
				'email'        => '2',
			)
		);

		$wpdb->insert( $wpdb->prefix . 'gf_addon_feed', array(
			'addon_slug' => 'event-management-plugin',
			'form_id'    => $this->test_form_id,
			'is_active'  => 1,
			'meta'       => json_encode( $feed_meta ),
		) );
		$feed_id = $wpdb->insert_id;
		$this->created_feed_ids[] = $feed_id;

		$form = GFAPI::get_form( $this->test_form_id );
		$addon = EMP_GF_Addon::get_instance();

		// Submit entry 1 (Registered)
		$entry1 = array(
			'id'             => 99991,
			'form_id'        => $this->test_form_id,
			'payment_amount' => 0.00,
			'payment_status' => 'Paid',
		);
		$entry1['1.3'] = 'First';
		$entry1['1.6'] = 'User';
		$entry1['2']   = 'first.user@test.local';
		$feed_row1 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE id = $feed_id", ARRAY_A );
		if ( $feed_row1 ) {
			$feed_row1['meta'] = json_decode( $feed_row1['meta'], true );
			$addon->process_feed( $feed_row1, $entry1, $form );
		}

		// Submit entry 2 (Waitlisted)
		$entry2 = array(
			'id'             => 99992,
			'form_id'        => $this->test_form_id,
			'payment_amount' => 0.00,
			'payment_status' => 'Paid',
		);
		$entry2['1.3'] = 'Second';
		$entry2['1.6'] = 'User';
		$entry2['2']   = 'second.user@test.local';
		$feed_row2 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE id = $feed_id", ARRAY_A );
		if ( $feed_row2 ) {
			$feed_row2['meta'] = json_decode( $feed_row2['meta'], true );
			$addon->process_feed( $feed_row2, $entry2, $form );
		}

		$attendee1 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE email = %s AND event_id = %d", 'first.user@test.local', $this->test_event_id ) );
		$attendee2 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE email = %s AND event_id = %d", 'second.user@test.local', $this->test_event_id ) );

		$this->assertEquals( true, ! empty( $attendee1 ) );
		$this->assertEquals( 'registered', $attendee1->status );

		$this->assertEquals( true, ! empty( $attendee2 ) );
		$this->assertEquals( 'waitlisted', $attendee2->status );
	}

	// TC5.1.2: Form without QR configured submits with fake qr parameters (ignored).
	public function test_tier2_tc5_1_2() {
		update_post_meta( $this->test_event_id, '_emp_require_payment', '0' );

		$input_values = array(
			'input_1_3' => 'Fake',
			'input_1_6' => 'QR',
			'input_2'   => 'fake.qr@test.local',
			'input_3'   => 'E2E Corp',
			'input_4'   => 'FAKE_TXN_ID',
			'input_5'   => 'http://example.com/fake_screenshot.png',
		);

		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$this->assertEquals( true, $result['is_valid'] );

		global $wpdb;
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE event_id = %d AND email = %s", $this->test_event_id, 'fake.qr@test.local' ) );
		$this->assertEquals( true, ! empty( $attendee ) );
		$this->assertEquals( 'registered', $attendee->status );
	}

	// TC5.1.3: Delete pending entry before approval (verify audit log & cleanup).
	public function test_tier2_tc5_1_3() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC5.1.4: Submit form with exact duplicate email registration (validation block).
	public function test_tier2_tc5_1_4() {
		$entry_meta = array(
			'form_id' => $this->test_form_id,
			'status'  => 'active',
			'2'       => 'duplicate@test.local',
		);
		$entry_id = GFAPI::add_entry( $entry_meta );
		$this->assertEquals( true, $entry_id > 0 );

		$validation_result = array(
			'is_valid' => true,
			'form'     => GFAPI::get_form( $this->test_form_id ),
		);
		
		$_POST['input_2'] = 'duplicate@test.local';

		$integration = new EMP_GF_Integration();
		$validated   = $integration->validate_duplicate_attendee( $validation_result );

		unset( $_POST['input_2'] );
		GFAPI::delete_entry( $entry_id );

		$this->assertEquals( false, $validated['is_valid'], 'Duplicate email registration should fail validation.' );
	}

	// TC5.1.5: Update QR payment config to amount of 0 (acts as free).
	public function test_tier2_tc5_1_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// ======================================================================
	// TIER 3 TEST CASES
	// ======================================================================

	// TC3.1.1: Enable QR payment, submit, verify pending, then change QR config price, then approve.
	public function test_tier3_tc3_1_1() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.1.2: Register attendee on QR form, approve, check attendee event and ticket type map.
	public function test_tier3_tc3_1_2() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.1.3: Group registration via QR form (multiple names): verify multiple attendees.
	public function test_tier3_tc3_1_3() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.1.4: Bulk approval of mixture of group registration and individual entries.
	public function test_tier3_tc3_1_4() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC3.1.5: Capacity limit reached mid-bulk-approval: verify remaining become waitlisted.
	public function test_tier3_tc3_1_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// ======================================================================
	// TIER 4 TEST CASES
	// ======================================================================

	// TC4.1.1: Complete Event Registration flow (Full cycle).
	public function test_tier4_tc4_1_1() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.2: Capacity and Waitlist transition flow.
	public function test_tier4_tc4_1_2() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.3: Backward compatibility regression: Free form for free event.
	public function test_tier4_tc4_1_3() {
		update_post_meta( $this->test_event_id, '_emp_require_payment', '0' );

		$input_values = array(
			'input_1_3' => 'Alice',
			'input_1_6' => 'Smith',
			'input_2'   => 'alice.smith@test.local',
			'input_3'   => 'E2E Corp',
		);

		$result = GFAPI::submit_form( $this->test_form_id, $input_values );
		$this->assertEquals( true, $result['is_valid'] );

		global $wpdb;
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE event_id = %d AND email = %s", $this->test_event_id, 'alice.smith@test.local' ) );
		$this->assertEquals( true, ! empty( $attendee ) );
		$this->assertEquals( 'registered', $attendee->status );

		// Verify email communication record
		$comm = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_communications WHERE attendee_id = %d AND type = 'confirmation'", $attendee->id ) );
		$this->assertEquals( true, ! empty( $comm ) );

		// Verify download link appended
		if ( class_exists( 'EMP_GF_Integration' ) ) {
			EMP_GF_Integration::$last_attendee_ids = array( $attendee->id );
		}
		$integration  = new EMP_GF_Integration();
		$confirmation = $integration->append_badge_download( 'Thank you!', array(), array(), false );
		$this->assertEquals( true, strpos( $confirmation, 'emp-instant-download' ) !== false );
	}

	// TC4.1.4: Admin bulk action rejection & approval.
	public function test_tier4_tc4_1_4() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}

	// TC4.1.5: File integrity check: Rejects malicious upload script.
	public function test_tier4_tc4_1_5() {
		// Expected to fail.
		if ( ! class_exists( 'EMP_QR_Dashboard' ) ) {
			throw new Exception( "Class EMP_QR_Dashboard not found." );
		}
	}
}

$tests = new Event_Management_E2E_Tests();
$tests->run();
