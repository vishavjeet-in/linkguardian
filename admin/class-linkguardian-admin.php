<?php
/**
 * Admin interface for LinkGuardian
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkGuardian Admin Class
 */
class LinkGuardian_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var object
	 */
	private $database;

	/**
	 * Initialize the class.
	 */
	private function __construct() {
		$this->database = LinkGuardian_Database::get_instance();

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Handle settings save.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX handlers for admin actions.
		add_action( 'wp_ajax_linkguardian_delete_link', array( $this, 'ajax_delete_link' ) );
		add_action( 'wp_ajax_linkguardian_clear_all_links', array( $this, 'ajax_clear_all_links' ) );
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
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		// Main menu page.
		add_menu_page(
			esc_html__( 'LinkGuardian', 'linkguardian' ),
			esc_html__( 'LinkGuardian', 'linkguardian' ),
			'manage_options',
			'linkguardian',
			array( $this, 'render_dashboard_page' ),
			'dashicons-admin-links',
			30
		);

		// Dashboard submenu.
		add_submenu_page(
			'linkguardian',
			esc_html__( 'Dashboard', 'linkguardian' ),
			esc_html__( 'Dashboard', 'linkguardian' ),
			'manage_options',
			'linkguardian',
			array( $this, 'render_dashboard_page' )
		);

		// Scan Logs submenu.
		add_submenu_page(
			'linkguardian',
			esc_html__( 'Scan Logs', 'linkguardian' ),
			esc_html__( 'Scan Logs', 'linkguardian' ),
			'manage_options',
			'linkguardian-logs',
			array( $this, 'render_logs_page' )
		);

		// Settings submenu.
		add_submenu_page(
			'linkguardian',
			esc_html__( 'Settings', 'linkguardian' ),
			esc_html__( 'Settings', 'linkguardian' ),
			'manage_options',
			'linkguardian-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on LinkGuardian pages.
		if ( strpos( $hook, 'linkguardian' ) === false ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'linkguardian-admin',
			LINKGUARDIAN_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			LINKGUARDIAN_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'linkguardian-admin',
			LINKGUARDIAN_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			LINKGUARDIAN_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'linkguardian-admin',
			'linkguardianAdmin',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'linkguardian_scan_nonce' ),
				'delete_nonce' => wp_create_nonce( 'linkguardian_delete_nonce' ),
				'strings'    => array(
					'scanning'          => esc_html__( 'Scanning...', 'linkguardian' ),
					'scan_complete'     => esc_html__( 'Scan completed!', 'linkguardian' ),
					'scan_error'        => esc_html__( 'Scan failed. Please try again.', 'linkguardian' ),
					'confirm_delete'    => esc_html__( 'Are you sure you want to delete this link?', 'linkguardian' ),
					'confirm_clear_all' => esc_html__( 'Are you sure you want to clear all links? This action cannot be undone.', 'linkguardian' ),
				),
			)
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'linkguardian_settings_group',
			'linkguardian_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['delete_data_on_deactivation'] ) ) {
			$sanitized['delete_data_on_deactivation'] = 'yes' === $input['delete_data_on_deactivation'] ? 'yes' : 'no';
		}

		if ( isset( $input['scan_post_types'] ) && is_array( $input['scan_post_types'] ) ) {
			$sanitized['scan_post_types'] = array_map( 'sanitize_text_field', $input['scan_post_types'] );
		}

		if ( isset( $input['check_external_links'] ) ) {
			$sanitized['check_external_links'] = 'yes' === $input['check_external_links'] ? 'yes' : 'no';
		}

		if ( isset( $input['check_internal_links'] ) ) {
			$sanitized['check_internal_links'] = 'yes' === $input['check_internal_links'] ? 'yes' : 'no';
		}

		if ( isset( $input['cloud_scanner_enabled'] ) ) {
			$sanitized['cloud_scanner_enabled'] = 'yes' === $input['cloud_scanner_enabled'] ? 'yes' : 'no';
		}

		if ( isset( $input['email_notifications'] ) ) {
			$sanitized['email_notifications'] = 'yes' === $input['email_notifications'] ? 'yes' : 'no';
		}

		if ( isset( $input['notification_email'] ) ) {
			$sanitized['notification_email'] = sanitize_email( $input['notification_email'] );
		}

		return $sanitized;
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		// Get statistics.
		$stats = $this->database->get_stats();

		// Get filter.
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';

		// Get links based on filter.
		$args = array(
			'limit'  => 50,
			'offset' => 0,
		);

		if ( 'broken' === $filter ) {
			$args['is_broken'] = 1;
		} elseif ( 'warnings' === $filter ) {
			// Status codes 300-399.
			$args['is_broken'] = 0;
		}

		$links = $this->database->get_links( $args );

		include LINKGUARDIAN_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render logs page.
	 */
	public function render_logs_page() {
		$logs = $this->database->get_logs( 20 );
		include LINKGUARDIAN_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$settings   = get_option( 'linkguardian_settings', array() );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		include LINKGUARDIAN_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * AJAX handler to delete a link.
	 */
	public function ajax_delete_link() {
		// Security check.
		check_ajax_referer( 'linkguardian_delete_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'linkguardian' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? intval( $_POST['link_id'] ) : 0;

		if ( ! $link_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid link ID.', 'linkguardian' ) ) );
		}

		$result = $this->database->delete_link( $link_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Link deleted successfully.', 'linkguardian' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete link.', 'linkguardian' ) ) );
		}
	}

	/**
	 * AJAX handler to clear all links.
	 */
	public function ajax_clear_all_links() {
		// Security check.
		check_ajax_referer( 'linkguardian_delete_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'linkguardian' ) ) );
		}

		$result = $this->database->clear_all_links();

		if ( false !== $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'All links cleared successfully.', 'linkguardian' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to clear links.', 'linkguardian' ) ) );
		}
	}
}