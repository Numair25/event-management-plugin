# Milestone 1 Handoff Report: QR Configuration Admin

This report outlines the codebase investigation and design specifications for registering the QR configuration page, loading Gravity Forms, managing the settings option, and integrating WordPress Media Uploader.

---

## 1. Observation
From the investigation of the plugin codebase, the following files and code snippets were observed:
* **Admin Registration & Menu Hooks**: In `includes/class-emp-core.php` (lines 96-99), existing settings are registered under the `admin_menu` action hook using the plugin's `EMP_Loader` system:
  ```php
  require_once EMP_PLUGIN_DIR . 'admin/class-emp-settings-admin.php';
  $plugin_settings_admin = new EMP_Settings_Admin();
  $this->loader->add_action( 'admin_menu', $plugin_settings_admin, 'register_menu' );
  ```
* **Setting Registration Capabilities**: In `admin/class-emp-settings-admin.php` (lines 7-16), submenu registration is defined as:
  ```php
  public function register_menu() {
      add_submenu_page(
          'edit.php?post_type=emp_event',
          __( 'Settings', 'event-management-plugin' ),
          __( 'Settings', 'event-management-plugin' ),
          'manage_event_settings',
          'emp-settings',
          array( $this, 'render_page' )
      );
  }
  ```
  The user capability `'manage_event_settings'` is assigned to the Organizer and Administrator roles (as seen in `includes/class-emp-activator.php` lines 172 and 198).
* **Gravity Forms Retrieval**: In `admin/class-emp-badges-admin.php` (lines 227-237), forms are retrieved using `GFFormsModel::get_forms()`, while `services/class-emp-gf-integration.php` confirms usage of `GFAPI` when `class_exists( 'GFAPI' )` is true.
* **WordPress Media Library Integration**: In `admin/class-emp-badges-admin.php` (lines 129 and 351-362), the media library is loaded and handled using:
  ```php
  wp_enqueue_media();
  ```
  And standard `wp.media` uploader javascript:
  ```javascript
  image_frame = wp.media({ title: 'Select Background', multiple : false, library : { type : 'image' } });
  image_frame.on('close',function() {
      var selection = image_frame.state().get('selection').first().toJSON();
      $('#bg_image').val(selection.url).trigger('change');
  });
  ```

---

## 2. Logic Chain
Based on the observations:
1. **Integrating the Admin Hook**: To introduce the new page, we must follow the existing pattern in `includes/class-emp-core.php` by requiring `admin/class-emp-qr-settings-admin.php` and adding its `register_menu` method to the `admin_menu` action hook via `$this->loader->add_action()`.
2. **Assigning Page Route & Slug**: Registering the settings under `edit.php?post_type=emp_event` as a submenu page with capability `'manage_event_settings'` ensures consistency with other settings and matches role authorization patterns (Administrators and Event Organizers).
3. **Gravity Forms API Access**: Utilizing `GFAPI::get_forms()` is the standard Gravity Forms API method to fetch all forms. We will check `class_exists('GFAPI')` to avoid fatal errors if Gravity Forms is deactivated or not installed.
4. **Options Database Structure**: The saved settings must be saved under the option `emp_qr_payment_settings` with the structure:
   ```json
   {
     "form_id": {
       "enabled": true,
       "amount": 1500.00,
       "qr_image_url": "http://..."
     }
   }
   ```
   We will collect these inputs in a multi-dimensional array and call `update_option()`. WordPress will automatically serialize this array, which maps directly to the requested structure when parsed.
5. **Media Library Support**: To support multiple forms in a single settings table, we will use a common `wp.media` instance. Clicking a form's "Upload / Select" button sets a `data-target` attribute mapping to the specific form's QR Image URL text input, allowing a single JS handler to update the correct field.

---

## 3. Caveats
* **Gravity Forms Dependency**: If Gravity Forms is disabled, the page will display an admin notice informing the administrator, rather than attempting to call `GFAPI::get_forms()`.
* **Float Amount Validation**: The amount field is sanitized using `floatval()` and rounded. It defaults to `0.00` if not set. Negative numbers are blocked using the HTML `min="0"` attribute.

---

## 4. Conclusion & Design Plan

### Proposed File Structure
* `admin/class-emp-qr-settings-admin.php` (New File)
* `includes/class-emp-core.php` (Modified)

### Proposed Code Design

#### 1. New File: `admin/class-emp-qr-settings-admin.php`
```php
<?php
/**
 * QR Configuration Settings Admin Page
 */
class EMP_QR_Settings_Admin {

	/**
	 * Register the submenu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'QR Settings', 'event-management-plugin' ),
			__( 'QR Settings', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-qr-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the settings page and handle saving options.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_event_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'event-management-plugin' ) );
		}

		// Enqueue WP Media Library scripts
		wp_enqueue_media();

		// Handle saving option
		if ( isset( $_POST['emp_save_qr_settings'] ) && check_admin_referer( 'emp_save_qr_settings_action', 'emp_save_qr_settings_nonce' ) ) {
			$settings = array();
			if ( isset( $_POST['qr_settings'] ) && is_array( $_POST['qr_settings'] ) ) {
				foreach ( $_POST['qr_settings'] as $form_id => $form_data ) {
					$form_id = intval( $form_id );
					$enabled = isset( $form_data['enabled'] ) ? true : false;
					$amount = isset( $form_data['amount'] ) ? round( floatval( $form_data['amount'] ), 2 ) : 0.00;
					$qr_image_url = isset( $form_data['qr_image_url'] ) ? esc_url_raw( $form_data['qr_image_url'] ) : '';

					$settings[ $form_id ] = array(
						'enabled'      => $enabled,
						'amount'       => $amount,
						'qr_image_url' => $qr_image_url,
					);
				}
			}
			update_option( 'emp_qr_payment_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'QR Payment Settings saved successfully.', 'event-management-plugin' ) . '</p></div>';
		}

		// Fetch current option values
		$saved_settings = get_option( 'emp_qr_payment_settings', array() );

		// Load Gravity Forms
		$forms = array();
		$gf_active = class_exists( 'GFAPI' );
		if ( $gf_active ) {
			$forms = GFAPI::get_forms();
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'QR Payment Settings', 'event-management-plugin' ); ?></h1>
			<p class="description"><?php _e( 'Configure payment amounts and upload QR images for individual Gravity Forms.', 'event-management-plugin' ); ?></p>
			
			<?php if ( ! $gf_active ) : ?>
				<div class="notice notice-warning"><p><?php _e( 'Gravity Forms is not active. Please install and activate Gravity Forms to configure QR payments.', 'event-management-plugin' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'emp_save_qr_settings_action', 'emp_save_qr_settings_nonce' ); ?>
					<input type="hidden" name="emp_save_qr_settings" value="1" />
					
					<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
						<thead>
							<tr>
								<th style="width: 80px; text-align: center;"><?php _e( 'Enabled', 'event-management-plugin' ); ?></th>
								<th style="width: 80px;"><?php _e( 'Form ID', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'Form Title', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'Amount (₹)', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'QR Image URL / Uploader', 'event-management-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $forms ) ) : ?>
								<tr>
									<td colspan="5"><?php _e( 'No Gravity Forms found.', 'event-management-plugin' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $forms as $form ) : 
									$form_id = intval( $form['id'] );
									$form_settings = isset( $saved_settings[ $form_id ] ) ? $saved_settings[ $form_id ] : array();
									$enabled = isset( $form_settings['enabled'] ) ? (bool) $form_settings['enabled'] : false;
									$amount = isset( $form_settings['amount'] ) ? floatval( $form_settings['amount'] ) : 0.00;
									$qr_image_url = isset( $form_settings['qr_image_url'] ) ? $form_settings['qr_image_url'] : '';
								?>
									<tr>
										<td style="text-align: center;">
											<input type="checkbox" name="qr_settings[<?php echo $form_id; ?>][enabled]" value="1" <?php checked( $enabled, true ); ?> />
										</td>
										<td><code><?php echo esc_html( $form_id ); ?></code></td>
										<td><strong><?php echo esc_html( $form['title'] ); ?></strong></td>
										<td>
											<input type="number" name="qr_settings[<?php echo $form_id; ?>][amount]" value="<?php echo esc_attr( $amount ); ?>" step="0.01" min="0" class="small-text" style="width: 100px;" />
										</td>
										<td>
											<input type="text" id="qr_image_url_<?php echo $form_id; ?>" name="qr_settings[<?php echo $form_id; ?>][qr_image_url]" value="<?php echo esc_url( $qr_image_url ); ?>" class="regular-text" style="max-width: 300px;" />
											<button type="button" class="button emp-upload-qr-btn" data-target="#qr_image_url_<?php echo $form_id; ?>"><?php _e( 'Upload / Select Image', 'event-management-plugin' ); ?></button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<?php submit_button( __( 'Save QR Settings', 'event-management-plugin' ) ); ?>
				</form>
				
				<script>
				jQuery(document).ready(function($){
					var mediaUploader;
					var activeInputTarget = null;
					
					$('.emp-upload-qr-btn').on('click', function(e) {
						e.preventDefault();
						activeInputTarget = $(this).data('target');
						
						if (mediaUploader) {
							mediaUploader.open();
							return;
						}
						
						mediaUploader = wp.media({
							title: '<?php esc_attr_e( 'Select or Upload QR Code Image', 'event-management-plugin' ); ?>',
							button: {
								text: '<?php esc_attr_e( 'Use this image', 'event-management-plugin' ); ?>'
							},
							multiple: false
						});
						
						mediaUploader.on('select', function() {
							var attachment = mediaUploader.state().get('selection').first().toJSON();
							if (activeInputTarget && attachment.url) {
								$(activeInputTarget).val(attachment.url);
							}
						});
						
						mediaUploader.open();
					});
				});
				</script>
			<?php endif; ?>
		</div>
		<?php
	}
}
```

#### 2. Modified File Integration: `includes/class-emp-core.php`
```php
<<<<
		require_once EMP_PLUGIN_DIR . 'admin/class-emp-settings-admin.php';
		$plugin_settings_admin = new EMP_Settings_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_settings_admin, 'register_menu' );
		$this->loader->add_action( 'wp_ajax_emp_global_search', $plugin_settings_admin, 'ajax_global_search' );

		require_once EMP_PLUGIN_DIR . 'services/class-emp-gf-integration.php';
====
		require_once EMP_PLUGIN_DIR . 'admin/class-emp-settings-admin.php';
		$plugin_settings_admin = new EMP_Settings_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_settings_admin, 'register_menu' );
		$this->loader->add_action( 'wp_ajax_emp_global_search', $plugin_settings_admin, 'ajax_global_search' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-qr-settings-admin.php';
		$plugin_qr_settings_admin = new EMP_QR_Settings_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_qr_settings_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'services/class-emp-gf-integration.php';
>>>>
```

---

## 5. Verification Method
1. **PHP Syntax Check**: Run `php -l admin/class-emp-qr-settings-admin.php` and `php -l includes/class-emp-core.php` to ensure there are no compilation/syntax errors.
2. **PHPCS Linting**: Execute `phpcs --standard=WordPress admin/class-emp-qr-settings-admin.php` to verify standard WordPress styling and lint compliance.
3. **WordPress Admin Verification**:
   * Navigate to the WordPress dashboard.
   * Go to "Events" -> "QR Settings".
   * Verify that the page loads correctly and displays all Gravity Forms.
   * Check "Enabled", insert an "Amount", click "Upload / Select Image", select a file from the Media Uploader, and click "Save QR Settings".
   * Validate that the `emp_qr_payment_settings` option is successfully updated in the `wp_options` table in the database with the correct serialized structure.
