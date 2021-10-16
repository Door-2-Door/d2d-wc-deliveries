<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Update the D2D Deliveries integration keys
 */
if ( current_user_can('administrator') && $_GET['page'] == 'd2d-deliveries-setup'
 && isset($_POST['account-slug']) && !empty($_POST['account-slug'])
 && isset($_POST['secret-key']) && !empty($_POST['secret-key'])
) {

	if ( D2D_Admin_Page::sanitize_inputs($_POST['account-slug'], $_POST['secret-key']) ) {
		D2D_Admin_Page::update_delivery_integration($_POST['account-slug'], $_POST['secret-key']);
	}

}

class D2D_Admin_Page {

	/**
	 * Sanitize the inputs prior update
	 * 
	 * @param string $slug The merchant slug
	 * @param string $secret The merchant secret key
	 * @return bool Whether the inputs are validated or not
	 */
	public static function sanitize_inputs($slug, $secret) {
		global $d2d_admin_page_error_container;

		if (! preg_match('/[a-z-]*/', $slug) ) {
			$d2d_admin_page_error_container[] = 'The account slug format is incorrect';
		}

		if (! preg_match('/[a-zA-Z0-9]*/', $secret) ) {
			$d2d_admin_page_error_container[] = 'The secret format is incorrect';
		}

		if ( strlen($secret) !== 30 ) {
			$d2d_admin_page_error_container[] = 'The secret lenght must be 30 characters';
		}

		return empty( $d2d_admin_page_error_container );
	}

	/**
	 * Update the delivery integration details
	 * 
	 * @param string $slug The merchant slug
	 * @param string $secret The merchant secret key
	 * @return bool Whether the keys are updated or not
	 */
	public static function update_delivery_integration($slug, $secret) {
		update_option('d2d_account_slug', $slug);
		update_option('d2d_secret_key', $secret);
		return true;
	}
	
	/**
	 * Display the plugin configuration page
	 */
	public static function wrap_ui() {
		global $d2d_admin_page_error_container;

		self::register_setting_error();
		self::enqueue_page_style();

		if (! self::check_inputs_are_valid() ) {
			settings_errors('d2d_admin_data_missing');
		} else {

			if (! self::perform_test_request() ) {
				settings_errors('d2d_admin_connection_error');
			
			} else {
				settings_errors('d2d_admin_data_complete');
			}
		}

	?>
		<div class="wrap">
			<h1><?= esc_html__('Door 2 Door deliveries integration', 'd2d-wc-deliveries') ?></h1>
		
	<?php if (! $d2d_admin_page_error_container): ?>
		
			<p>Door 2 Door will receive your WooCommerce orders details once the order status becomes <b>processing</b>.</p>
			<p>To ensure security, please fill the form below with the keys you get from your Account Manager.</p>

			<form method="POST">
				<div class="d2d-form-group">
					<label for="account-slug">Account slug</label>
					<input type="text" name="account-slug" id="account-slug" value="<?= get_option('d2d_account_slug') ?? '' ?>" maxlength="100" required>
				</div>
				<div class="d2d-form-group">
					<label for="merchant-slug">Secret key</label>
					<input type="password" name="secret-key" id="secret-key" value="<?= get_option('d2d_secret_key') ?? '' ?>" size="40" minlength="30" maxlength="30" required>
				</div>
				<div class="d2d-form-group">
					<input type="submit" value="Save" class="button button-primary">
				</div>
			</form>
		
		

	<?php else: ?>
		<p>Some errors happend:</p>
		<ul>
			<?php foreach ($d2d_admin_page_error_container as $error) { ?>
				
				<li>&bull; <?= $error ?></li>
				
			<?php } ?>
		</ul>
	
	<?php endif; ?>

		</div>

	<?php
	}

	public static function check_inputs_are_valid(): bool {

		if ( !get_option('d2d_account_slug') ) {
			return false;
		}

		$secret_key = get_option('d2d_secret_key');
		if ( !$secret_key || strlen($secret_key) != 30 ) {
			return false;
		}

		return true;
	}

	private static function perform_test_request(): bool {
		return true;
	}

	private static function register_setting_error() {
		
		add_settings_error('d2d_admin_data_missing', 'd2d_admin_data_missing', 'Please fill all the fields below', 'error');
		add_settings_error('d2d_admin_data_complete', 'd2d_admin_data_complete', 'Setup complete - We can\'t wait to receive your orders!', 'success');
		add_settings_error('d2d_admin_connection_error', 'd2d_admin_connection_error', 'There was an error reaching Door 2 Door servers, check your secrets keys ...', 'warning');

	}

	private static function enqueue_page_style() {
	?>
		<style>
			.d2d-form-group {
				margin-bottom: 15px;
			}

			.d2d-form-group > label {
				margin-right: 15px;
			}
		</style>
	<?php
	}
}
