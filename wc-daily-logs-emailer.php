<?php
/**
 * Error Logs Emailer for WooCommerce
 *
 * @package   error-logs-emailer-for-woocommerce
 * @link      https://github.com/rootscopeltd/wc-error-logs-emailer
 * @author    WP Maintenance PRO <support@wp-maintenance.pro>
 * @copyright Michal Slepko
 * @license   GPLv3 or later
 *
 * @wordpress-plugin
 * Plugin Name: Error Logs Emailer for WooCommerce
 * Description: Sends the previous day's WooCommerce fatal error log to specified email(s) using Action Scheduler.
 * Version: 1.2.3
 * Author: WP Maintenance PRO
 * Plugin URI: https://github.com/rootscopeltd/wc-error-logs-emailer
 * Author URI: https://wp-maintenance.pro
 * Requires Plugins: woocommerce
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Activation hook.
register_activation_hook( __FILE__, 'rs_elew_wc_schedule_daily_error_log_email' );

// Deactivation hook.
register_deactivation_hook( __FILE__, 'rs_elew_wc_daily_error_log_emailer_deactivate_action' );


// Register admin menu and settings.
add_action( 'admin_menu', 'rs_elew_wc_daily_logs_emailer_add_admin_menu' );
add_action( 'admin_init', 'rs_elew_wc_daily_logs_emailer_settings_init' );

/**
 * Add admin menu for Error Logs Emailer for WooCommerce.
 */
function rs_elew_wc_daily_logs_emailer_add_admin_menu() {
	add_options_page(
		'Error Logs Emailer for WooCommerce',
		'Error Logs Emailer',
		'manage_options',
		'error-logs-emailer-for-woocommerce',
		'rs_elew_wc_daily_logs_emailer_settings_page'
	);
}

/**
 * Initialize the settings for Error Logs Emailer for WooCommerce.
 */
function rs_elew_wc_daily_logs_emailer_settings_init() {
	register_setting( 'rs_elew_wc_daily_logs_emailer', 'rs_elew_wc_log_email_settings' );

	add_settings_section(
		'rs_elew_wc_daily_logs_emailer_section',
		__( 'Configure your daily error log email settings.', 'error-logs-emailer-for-woocommerce' ),
		'rs_elew_wc_daily_logs_emailer_settings_section_callback',
		'rs_elew_wc_daily_logs_emailer',
		array(
			'after_section' => rs_elew_wc_daily_logs_emailer_settings_description(),
		)
	);

	add_settings_field(
		'wc_log_email',
		__( 'Log Email Address', 'error-logs-emailer-for-woocommerce' ),
		'rs_elew_wc_log_email_render',
		'rs_elew_wc_daily_logs_emailer',
		'rs_elew_wc_daily_logs_emailer_section'
	);
}

/**
 * Renders the input field for the log email address setting.
 */
function rs_elew_wc_log_email_render() {
	$options     = get_option( 'rs_elew_wc_log_email_settings' );
	$admin_email = get_option( 'admin_email' );
	// Check for RECOVERY_MODE_EMAIL.
	$recovery_mode_email = defined( 'RECOVERY_MODE_EMAIL' ) ? RECOVERY_MODE_EMAIL : false;
	$to_email            = $recovery_mode_email ? $recovery_mode_email : $admin_email;
	?>
	<input type='email' name='rs_elew_wc_log_email_settings[rs_elew_wc_log_email]' value='<?php echo esc_attr( $options['rs_elew_wc_log_email'] ?? '' ); ?>' class="regular-text">
	<p class="description">Enter the email address to receive the logs. Separate multiple emails with commas. </p>
	<p>Leave blank to use <strong><?php echo esc_html( $to_email ); ?></strong></p>
	<?php
}

/**
 * Callback function for the settings section in Error Logs Emailer for WooCommerce.
 */
function rs_elew_wc_daily_logs_emailer_settings_section_callback() {
	echo '<p>Adjust the settings for how and where you receive WooCommerce error logs.</p>';
}

/**
 * Displays additional information about the email recipient settings.
 */
function rs_elew_wc_daily_logs_emailer_settings_description() {
	$admin_email = get_option( 'admin_email' );
	// Check for RECOVERY_MODE_EMAIL.
	$recovery_mode_email = defined( 'RECOVERY_MODE_EMAIL' ) ? RECOVERY_MODE_EMAIL : false;
	$to_email            = $recovery_mode_email ? $recovery_mode_email : $admin_email;

	return 'Email recipient settings priority
	<ol>
		<li>Email(s) set above.</li>
		<li>RECOVERY_MODE_EMAIL setting from wp-config.php - ' . esc_html( $recovery_mode_email ? $recovery_mode_email : 'none set' ) . '.</li>
		<li>Administration Email Address from Settings->General - ' . esc_html( $admin_email ) . '.</li>
	</ol>';
}

/**
 * Displays the settings page for Error Logs Emailer for WooCommerce.
 *
 * This function displays the settings page for Error Logs Emailer for WooCommerce.
 */
function rs_elew_wc_daily_logs_emailer_settings_page() {
	?>
	<div class="wrap">
		<h1>Error Logs Emailer for WooCommerce Settings</h1>
		<form action='options.php' method='post'>
			<?php
			settings_fields( 'rs_elew_wc_daily_logs_emailer' );
			do_settings_sections( 'rs_elew_wc_daily_logs_emailer' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}



/**
 * Schedule the daily error log email.
 *
 * This function checks if the 'wc_daily_error_log_emailer_send_log' action is scheduled.
 * If it is not scheduled, it schedules the action to run at 5:00 am the next day.
 *
 * @see as_next_scheduled_action() For checking if the action is scheduled.
 * @see as_schedule_recurring_action() For scheduling the action.
 */
function rs_elew_wc_schedule_daily_error_log_email() {
	if ( ! as_next_scheduled_action( 'rs_elew_wc_daily_error_log_emailer_send_log' ) ) {
		as_schedule_recurring_action( strtotime( 'tomorrow 5:00 am' ), DAY_IN_SECONDS, 'rs_elew_wc_daily_error_log_emailer_send_log' );
	}
}

// Make sure to register the action with Action Scheduler.
add_action( 'rs_elew_wc_daily_error_log_emailer_send_log', 'rs_elew_wc_daily_error_log_emailer_send_log' );

/**
 * Sends the WooCommerce fatal error log of the previous day to a specified email.
 *
 * This function retrieves the fatal error logs from the previous day, reads the content of each log file,
 * and sends it to the email address specified in the 'rs_elew_wc_log_email' option. If the 'rs_elew_wc_log_email' option
 * is not set, it sends the logs to the admin email. The email subject includes the site name and the date
 * of the logs.
 *
 * @see get_option() For retrieving the 'rs_elew_wc_log_email' and 'admin_email' options.
 * @see get_bloginfo() For retrieving the site name.
 * @see wp_mail() For sending the email with the log content.
 */
function rs_elew_wc_daily_error_log_emailer_send_log() {
	// Required files for WP_Filesystem_Direct.
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

	$yesterday    = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
	$log_filename = 'fatal-errors-' . $yesterday . '*.log';
	$log_files    = glob( WC_LOG_DIR . '/' . $log_filename );
	$options      = get_option( 'rs_elew_wc_log_email_settings' );

	$recovery_mode_email = defined( 'RECOVERY_MODE_EMAIL' ) ? RECOVERY_MODE_EMAIL : false;
	$default_email       = $recovery_mode_email ? $recovery_mode_email : get_option( 'admin_email' );

	$emails    = explode( ',', $options['rs_elew_wc_log_email'] ?? $default_email );
	$site_name = get_bloginfo( 'name' );

	foreach ( $emails as $email ) {
		$email = trim( $email );
		if ( is_email( $email ) && ! empty( $log_files ) ) {
			foreach ( $log_files as $log_file ) {
				if ( file_exists( $log_file ) ) {
					$wp_filesystem = new WP_Filesystem_Direct( null );
					$log_content   = $wp_filesystem->get_contents( $log_file );
					wp_mail( $email, "[$site_name] WooCommerce Fatal Errors Log for $yesterday", $log_content );
				}
			}
		}
	}
}



/**
 * Deactivate the plugin and clear the scheduled action.
 *
 * This function clears all scheduled actions with the hook 'wc_daily_error_log_emailer_send_log'
 * and deletes the 'wc_log_email' option from the database.
 *
 * @see as_unschedule_all_actions() For clearing all scheduled actions.
 * @see delete_option() For deleting the 'wc_log_email' option.
 */
function rs_elew_wc_daily_error_log_emailer_deactivate_action() {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'rs_elew_wc_daily_error_log_emailer_send_log' );
	}

	// Delete the 'wc_log_email' option from the database.
	delete_option( 'rs_elew_wc_log_email' );
}


add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rs_elew_wc_daily_logs_emailer_add_settings_link' );

/**
 * Add settings link to plugin page
 *
 * @param array $links Array of plugin action links.
 * @return array Modified array of plugin action links.
 */
function rs_elew_wc_daily_logs_emailer_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=error-logs-emailer-for-woocommerce' ) . '">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
