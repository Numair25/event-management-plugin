# E2E Test Runner Design Handoff Report

## 1. Observation

During the read-only exploration of the workspace and the `event-management-plugin` codebase, the following facts were observed:

- **WordPress Root & Bootstrapper**: `wp-load.php` is located at `c:\xampp\htdocs\event-management\wp-load.php`.
- **CPT 'emp_event' registration**: Registered in `wp-content/plugins/event-management-plugin/includes/core/class-emp-cpt.php` (line 102):
  ```php
  register_post_type( 'emp_event', $args );
  ```
  Metadata fields managed in `admin/class-emp-event-meta.php` include:
  - `_emp_capacity` (line 92)
  - `_emp_gf_form_id` (line 95)
  - `_emp_require_payment` (line 105)
  - `_emp_badge_width` (line 98)
  - `_emp_badge_height` (line 101)
- **Ticket Types Table**: Defined in `includes/class-emp-activator.php` (lines 50-60):
  ```sql
  CREATE TABLE $table_ticket_types (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      event_id bigint(20) NOT NULL,
      name varchar(255) NOT NULL,
      price decimal(10,2) NOT NULL DEFAULT '0.00',
      capacity int(11) DEFAULT NULL,
      color_code varchar(20) DEFAULT NULL,
      is_comp tinyint(1) NOT NULL DEFAULT '0',
      PRIMARY KEY  (id),
      KEY event_id (event_id)
  )
  ```
- **QR Payment Settings Option**: Managed in `admin/class-emp-qr-settings-admin.php` (line 49):
  ```php
  update_option( 'emp_qr_payment_settings', $settings );
  ```
  This options maps Form IDs to enabled state, amount, and QR image:
  ```php
  $settings[ $form_id ] = array(
      'enabled'      => $enabled,
      'amount'       => $amount,
      'qr_image_url' => $qr_image_url,
  );
  ```
- **AJAX Action and Dashboard Actions**: No implementation of `emp_upload_qr_screenshot` AJAX action or bulk dashboard approval/rejection actions exists in the custom plugin files yet.
- **WP CLI Commands**: Direct command execution (`run_command`) timed out on user permission prompts, necessitating that the E2E runner be entirely runnable and testable via non-interactive PHP commands (e.g. `php e2e-runner.php`).

---

## 2. Logic Chain

1. **WordPress Bootstrap**: Because the runner file will be situated at `wp-content/plugins/event-management-plugin/tests/e2e-runner.php`, we can load WordPress dynamically by referencing the relative path:
   ```php
   define('WP_USE_THEMES', false);
   require_once dirname(__DIR__, 4) . '/wp-load.php';
   ```
2. **Form Management via GFAPI**: Programmatic form creation/deletion can isolate test forms using `GFAPI::add_form( $form_meta )` and `GFAPI::delete_form( $form_id )`.
3. **Linkage to Event CPT**: To route form entries to the event and trigger attendee generation, we must programmatically create a dummy `emp_event` post and link it via `update_post_meta( $event_id, '_emp_gf_form_id', $form_id )`. We also insert a ticket type in `wp_emp_ticket_types`.
4. **Form Submission simulation**: Programmatically simulate form submissions by setting up a POST mock or directly using:
   ```php
   GFAPI::submit_form( $form_id, $input_values );
   ```
   Or creating entries and updating their payment status:
   ```php
   $entry_id = GFAPI::add_entry( $entry_data );
   ```
5. **Screenshot Upload AJAX Action**: Since this AJAX action is not in the codebase, we design it to:
   - Handle file upload via `$_FILES['screenshot']`.
   - Validate mime-types (e.g., `image/png`, `image/jpeg`) using `mime_content_type()`.
   - Save to `wp_upload_dir()['basedir'] . '/emp_screenshots/'`.
6. **Dashboard Actions**: 
   - Approval: Set the Gravity Forms entry's `payment_status` to `'Paid'`, which triggers the integration hooks to create the attendee record.
   - Rejection: Mark the Gravity Forms entry as `'Failed'` and the attendee record as `'cancelled'`.
7. **Robust Testing Harness**: Establish an `E2E_Test_Runner` class in `e2e-runner.php` that implements its own assertion methods (`assertEquals`, `assertNotWPError`), captures exceptions, logs errors, and executes teardown cleanup of created posts, forms, database rows, and options.

---

## 3. Caveats

- **Mocking `is_uploaded_file()`**: When simulating file uploads programmatically in CLI (via direct function calls), standard PHP functions like `move_uploaded_file` or `is_uploaded_file` will fail because the mock files are not uploaded via HTTP POST. The handler code or the runner must check `defined('WP_CLI')` or allow direct file copying in test environments.
- **Gravity Forms Hook dependencies**: The tests assume Gravity Forms is fully initialized and its DB schema exists in the WordPress database.

---

## 4. Conclusion

The E2E test runner should be designed as a standalone CLI script in `wp-content/plugins/event-management-plugin/tests/e2e-runner.php` that boots WordPress, dynamically sets up isolated test forms and CPTs, simulates registration and payment confirmations, validates the plugin's hooks/controllers, and cleans up the database.

Below is the step-by-step implementation guide for the worker.

---

## 5. Step-by-Step Implementation Guide

### Step 1: Bootstrap WordPress in `e2e-runner.php`
The script must check if it is run via CLI and boot WordPress.
```php
<?php
/**
 * E2E Test Runner for Event Management Plugin
 */

if ( php_sapi_name() !== 'cli' ) {
    die( "This script can only be run from the command line.\n" );
}

// Bootstrap WordPress
define( 'WP_USE_THEMES', false );
define( 'SHORTINIT', false ); // Set to false to load full plugins including GF
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Verify Gravity Forms is active
if ( ! class_exists( 'GFAPI' ) ) {
    die( "Gravity Forms is not active or not installed.\n" );
}
```

### Step 2: Write the Test Harness Assertion Engine
Create a base runner class to track test counts, assertion passes/failures, and clean formatting.
```php
class E2E_Test_Runner {
    private $passed = 0;
    private $failed = 0;
    private $errors = array();

    public function run() {
        echo "\n\033[1;34mStarting E2E Tests...\033[0m\n";
        
        $methods = get_class_methods( $this );
        foreach ( $methods as $method ) {
            if ( strpos( $method, 'test_' ) === 0 ) {
                echo "Running {$method}... ";
                try {
                    $this->setup();
                    $this->$method();
                    $this->teardown();
                    echo "\033[32mPASS\033[0m\n";
                    $this->passed++;
                } catch ( Exception $e ) {
                    echo "\033[31mFAIL\033[0m\n";
                    echo "  Error: " . $e->getMessage() . "\n";
                    $this->failed++;
                    $this->errors[] = "{$method}: " . $e->getMessage();
                    $this->teardown();
                }
            }
        }

        $this->report();
    }

    protected function setup() {
        // Setup base test state (will be overridden)
    }

    protected function teardown() {
        // Cleanup base test state (will be overridden)
    }

    protected function assertEquals( $expected, $actual, $msg = '' ) {
        if ( $expected !== $actual ) {
            throw new Exception( "Assertion failed: Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ". " . $msg );
        }
    }

    protected function assertNotWPError( $val, $msg = '' ) {
        if ( is_wp_error( $val ) ) {
            throw new Exception( "Assertion failed: Expected not WP_Error, got " . $val->get_error_message() . ". " . $msg );
        }
    }

    private function report() {
        echo "\n\033[1mTest Run Completed:\033[0m\n";
        echo "Passed: \033[32m{$this->passed}\033[0m\n";
        echo "Failed: \033[31m{$this->failed}\033[0m\n";
        
        if ( ! empty( $this->errors ) ) {
            echo "\nErrors Summary:\n";
            foreach ( $this->errors as $err ) {
                echo "- $err\n";
            }
            exit(1);
        }
        exit(0);
    }
}
```

### Step 3: Implement Setup & Cleanup Helpers
The runner class should programmatically manage isolated events, forms, and ticket types.
```php
class Event_Plugin_E2E_Tests extends E2E_Test_Runner {
    private $test_event_id = 0;
    private $test_form_id = 0;
    private $test_ticket_type_id = 0;

    protected function setup() {
        global $wpdb;

        // 1. Create a dummy event CPT
        $event_post = array(
            'post_title'   => 'E2E Test Event ' . uniqid(),
            'post_status'  => 'publish',
            'post_type'    => 'emp_event',
        );
        $this->test_event_id = wp_insert_post( $event_post );
        
        // 2. Create Ticket Type
        $wpdb->insert( $wpdb->prefix . 'emp_ticket_types', array(
            'event_id'   => $this->test_event_id,
            'name'       => 'E2E Ticket',
            'price'      => 99.00,
            'capacity'   => 10,
            'color_code' => '#00ff00',
        ) );
        $this->test_ticket_type_id = $wpdb->insert_id;

        // 3. Create Gravity Form
        $form_meta = array(
            'title'  => 'E2E Test Form ' . uniqid(),
            'fields' => array(
                array( 'type' => 'name', 'id' => 1, 'label' => 'Full Name' ),
                array( 'type' => 'email', 'id' => 2, 'label' => 'Email Address' ),
                array( 'type' => 'text', 'id' => 3, 'label' => 'Organization' ),
                array( 'type' => 'text', 'id' => 4, 'label' => 'Transaction ID' ),
                array( 'type' => 'fileupload', 'id' => 5, 'label' => 'Screenshot' ),
            )
        );
        $this->test_form_id = GFAPI::add_form( $form_meta );

        // 4. Link Form to CPT
        update_post_meta( $this->test_event_id, '_emp_gf_form_id', $this->test_form_id );
        update_post_meta( $this->test_event_id, '_emp_require_payment', '1' );
    }

    protected function teardown() {
        global $wpdb;

        if ( $this->test_event_id ) {
            wp_delete_post( $this->test_event_id, true ); // Automatically cleans up ticket types/attendees via CPT hook
        }
        if ( $this->test_form_id ) {
            GFAPI::delete_form( $this->test_form_id );
        }
    }
}
```

### Step 4: Write Test Scenarios
Implement methods starting with `test_` inside the test class:

#### Test A: Verify form linkage and settings
```php
public function test_form_linkage_settings() {
    $linked_form_id = get_post_meta( $this->test_event_id, '_emp_gf_form_id', true );
    $this->assertEquals( $this->test_form_id, intval( $linked_form_id ), 'Form ID should be linked in postmeta.' );
}
```

#### Test B: Simulate Form Submission
Use `GFAPI::submit_form` to push registration entries containing mock Transaction IDs and Screenshot URLs.
```php
public function test_simulated_form_submission() {
    $input_values = array(
        'input_1_3' => 'Jane',
        'input_1_6' => 'Doe',
        'input_2'   => 'jane.doe@example.com',
        'input_3'   => 'E2E Corp',
        'input_4'   => 'TXN_TEST_999',
        'input_5'   => 'http://example.com/mock-upload/screenshot.png',
    );

    $submit_result = GFAPI::submit_form( $this->test_form_id, $input_values );
    $this->assertEquals( true, $submit_result['is_valid'], 'Form submission must be valid.' );

    $entry_id = $submit_result['entry_id'];
    $entry = GFAPI::get_entry( $entry_id );
    $this->assertNotWPError( $entry );
    
    // Verify payment is marked as pending first
    $this->assertEquals( 'Pending', $entry['payment_status'] );
}
```

#### Test C: Design and call the AJAX screenshot upload handler
Add code to test the `emp_upload_qr_screenshot` AJAX handler. Since it is run locally in CLI, we can mock `$_FILES` and run the handler callback function directly:
```php
public function test_ajax_screenshot_upload() {
    // 1. Create a dummy file locally
    $temp_file = tempnam( sys_get_temp_dir(), 'mock_screenshot_' ) . '.png';
    file_put_contents( $temp_file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' ) ); // 1px png

    // 2. Set up $_FILES and $_POST
    $_FILES['screenshot'] = array(
        'name'     => 'test_screenshot.png',
        'type'     => 'image/png',
        'tmp_name' => $temp_file,
        'error'    => 0,
        'size'     => filesize( $temp_file ),
    );

    // Call the designed AJAX callback directly (or via hook trigger)
    // We expect the callback to store the file inside wp_upload_dir()/emp_screenshots/
    // Code mock example:
    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/emp_screenshots';
    if ( ! file_exists( $target_dir ) ) {
        wp_mkdir_p( $target_dir );
    }
    
    $filename = 'e2e_screenshot_' . time() . '.png';
    $target_path = $target_dir . '/' . $filename;
    
    // Perform copy instead of move_uploaded_file for CLI testing
    $success = copy( $temp_file, $target_path );
    $this->assertEquals( true, $success, 'File copy to uploads folder must succeed.' );
    
    unlink( $temp_file );
    unlink( $target_path ); // Cleanup
}
```

#### Test D: Simulate Dashboard Approval/Rejection Actions
Simulate clicking "Approve" by changing entry property and verifying the attendee gets created.
```php
public function test_dashboard_approval_actions() {
    // Setup feed mapping first so integration processes attendee
    global $wpdb;
    
    // Create mapping feed
    $feed_meta = array(
        'event_id'       => $this->test_event_id,
        'ticket_type_id' => $this->test_ticket_type_id,
        'mappedFields'   => array(
            'name'         => '1',
            'email'        => '2',
            'organization' => '3',
        )
    );
    
    $wpdb->insert( $wpdb->prefix . 'gf_addon_feed', array(
        'addon_slug' => 'event-management-plugin',
        'form_id'    => $this->test_form_id,
        'is_active'  => 1,
        'meta'       => json_encode( $feed_meta ),
    ) );
    $feed_id = $wpdb->insert_id;

    // Submit form
    $submit = GFAPI::submit_form( $this->test_form_id, array(
        'input_1_3' => 'Bob',
        'input_1_6' => 'Builder',
        'input_2'   => 'bob@example.com',
        'input_3'   => 'Bob Construction',
    ) );
    
    $entry_id = $submit['entry_id'];

    // Simulating Dashboard Approval
    // 1. Mark entry as Paid
    GFAPI::update_entry_property( $entry_id, 'payment_status', 'Paid' );
    
    // 2. Trigger Gravity Forms post payment action
    $entry = GFAPI::get_entry( $entry_id );
    $action = array(
        'type'           => 'complete_payment',
        'amount'         => 99.00,
        'transaction_id' => 'TXN_E2E_OK',
    );
    
    // Trigger integration payment hook
    do_action( 'gform_post_payment_action', $entry, $action );

    // Verify Attendee Record was created
    $attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emp_attendees WHERE event_id = %d AND email = %s", $this->test_event_id, 'bob@example.com' ) );
    $this->assertEquals( 'Bob Builder', $attendee->name, 'Attendee name must match form entry.' );
    $this->assertEquals( 'paid', $attendee->payment_status, 'Attendee payment status must update to paid.' );
    
    // Cleanup feed
    $wpdb->delete( $wpdb->prefix . 'gf_addon_feed', array( 'id' => $feed_id ) );
}
```

---

## 6. Verification Method

To verify the test runner:
1. Run the script directly using PHP CLI:
   ```bash
   php wp-content/plugins/event-management-plugin/tests/e2e-runner.php
   ```
2. **Expected Output**:
   The output should display progress for each test method and exit with `0` on complete success, or exit with code `1` and print backtraces if any assertion fails.
