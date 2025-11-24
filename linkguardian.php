<?php
/**
 * Plugin Name: LinkGuardian
 * Plugin URI: https://vishavjeet.in/
 * Description: A fast, reliable, and lightweight broken link checker for WordPress. Scan your entire website for broken or redirected links and fix them instantly. Improve SEO, user experience, and site credibility with LinkGuardian.
 * Version: 1.0.0
 * Author: Vishavjeet Choubey
 * Author URI: https://vishavjeet.in/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: linkguardian
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.8
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'LINKGUARDIAN_VERSION', '1.0.0' );
define( 'LINKGUARDIAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LINKGUARDIAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LINKGUARDIAN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main LinkGuardian Class
 */
class LinkGuardian {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Load plugin text domain.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Include required files.
		$this->includes();

		// Initialize components.
		$this->init();

		// Activation and deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Add settings link on plugin page.
		add_filter( 'plugin_action_links_' . LINKGUARDIAN_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'linkguardian', false, dirname( LINKGUARDIAN_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once LINKGUARDIAN_PLUGIN_DIR . 'includes/class-linkguardian-database.php';
		require_once LINKGUARDIAN_PLUGIN_DIR . 'includes/class-linkguardian-scanner.php';
		require_once LINKGUARDIAN_PLUGIN_DIR . 'includes/class-linkguardian-link-parser.php';
		require_once LINKGUARDIAN_PLUGIN_DIR . 'includes/class-linkguardian-cloud-api.php';
		require_once LINKGUARDIAN_PLUGIN_DIR . 'admin/class-linkguardian-admin.php';
	}

	/**
	 * Initialize plugin components.
	 */
	private function init() {
		// Initialize database.
		LinkGuardian_Database::get_instance();

		// Initialize scanner.
		LinkGuardian_Scanner::get_instance();

		// Initialize admin.
		if ( is_admin() ) {
			LinkGuardian_Admin::get_instance();
		}
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Create database tables.
		LinkGuardian_Database::get_instance()->create_tables();

		// Set default options.
		$default_options = array(
			'delete_data_on_deactivation' => 'no',
			'scan_post_types'             => array( 'post', 'page' ),
			'check_external_links'        => 'yes',
			'check_internal_links'        => 'yes',
			'cloud_scanner_enabled'       => 'no',
			'email_notifications'         => 'no',
			'notification_email'          => get_option( 'admin_email' ),
		);

		add_option( 'linkguardian_settings', $default_options );
		add_option( 'linkguardian_version', LINKGUARDIAN_VERSION );

		// Clear scheduled hooks if any.
		wp_clear_scheduled_hook( 'linkguardian_scheduled_scan' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'linkguardian_scheduled_scan' );

		// Check if user wants to delete data on deactivation.
		$settings = get_option( 'linkguardian_settings', array() );
		if ( isset( $settings['delete_data_on_deactivation'] ) && 'yes' === $settings['delete_data_on_deactivation'] ) {
			// Delete database tables.
			LinkGuardian_Database::get_instance()->drop_tables();

			// Delete options.
			delete_option( 'linkguardian_settings' );
			delete_option( 'linkguardian_version' );
			delete_option( 'linkguardian_scan_progress' );
		}
	}

	/**
	 * Add settings link on plugin page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=linkguardian' ) ) . '">' . esc_html__( 'Settings', 'linkguardian' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

/**
 * Initialize the plugin.
 */
function linkguardian_init() {
	return LinkGuardian::get_instance();
}

// Start the plugin.
linkguardian_init();