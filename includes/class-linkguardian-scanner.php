<?php
/**
 * Scanner for LinkGuardian
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkGuardian Scanner Class
 */
class LinkGuardian_Scanner {

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
	 * Link parser instance.
	 *
	 * @var object
	 */
	private $parser;

	/**
	 * Initialize the class.
	 */
	private function __construct() {
		$this->database = LinkGuardian_Database::get_instance();
		$this->parser   = LinkGuardian_Link_Parser::get_instance();

		// Register AJAX handlers.
		add_action( 'wp_ajax_linkguardian_start_scan', array( $this, 'ajax_start_scan' ) );
		add_action( 'wp_ajax_linkguardian_scan_batch', array( $this, 'ajax_scan_batch' ) );
		add_action( 'wp_ajax_linkguardian_check_link', array( $this, 'ajax_check_single_link' ) );
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
	 * AJAX handler to start a new scan.
	 */
	public function ajax_start_scan() {
		// Security check.
		check_ajax_referer( 'linkguardian_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'linkguardian' ) ) );
		}

		// Get settings.
		$settings   = get_option( 'linkguardian_settings', array() );
		$post_types = isset( $settings['scan_post_types'] ) ? $settings['scan_post_types'] : array( 'post', 'page' );

		// Get all posts to scan.
		$post_ids = $this->parser->get_posts_to_scan( $post_types );

		// Also get menu links.
		$menu_links = $this->parser->parse_menus();

		// Store scan progress.
		$scan_data = array(
			'post_ids'    => $post_ids,
			'menu_links'  => $menu_links,
			'total_posts' => count( $post_ids ),
			'current'     => 0,
			'status'      => 'in_progress',
		);

		update_option( 'linkguardian_scan_progress', $scan_data );

		// Create log entry.
		$log_id = $this->database->insert_log(
			array(
				'scan_type' => 'manual',
				'status'    => 'in_progress',
			)
		);

		wp_send_json_success(
			array(
				'message'     => esc_html__( 'Scan started successfully.', 'linkguardian' ),
				'total_posts' => count( $post_ids ),
				'log_id'      => $log_id,
			)
		);
	}

	/**
	 * AJAX handler to scan a batch of posts.
	 */
	public function ajax_scan_batch() {
		// Security check.
		check_ajax_referer( 'linkguardian_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'linkguardian' ) ) );
		}

		// Get scan progress.
		$scan_data = get_option( 'linkguardian_scan_progress', array() );

		if ( empty( $scan_data['post_ids'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No scan in progress.', 'linkguardian' ) ) );
		}

		$batch_size = 5; // Process 5 posts at a time.
		$current    = isset( $scan_data['current'] ) ? $scan_data['current'] : 0;
		$post_ids   = array_slice( $scan_data['post_ids'], $current, $batch_size );

		$settings = get_option( 'linkguardian_settings', array() );

		// Process each post in the batch.
		foreach ( $post_ids as $post_id ) {
			$links = $this->parser->parse_post( $post_id );

			foreach ( $links as $link ) {
				// Check the link.
				$check_result = $this->check_link( $link['url'], $settings );

				// Save to database.
				$this->database->save_link(
					array(
						'url'            => $link['url'],
						'link_text'      => $link['link_text'],
						'source_post_id' => $link['post_id'],
						'source_url'     => $link['source_url'],
						'link_type'      => $link['link_type'],
						'status_code'    => $check_result['status_code'],
						'status_text'    => $check_result['status_text'],
						'is_broken'      => $check_result['is_broken'],
						'redirect_url'   => $check_result['redirect_url'],
					)
				);
			}

			$current++;
		}

		// Update scan progress.
		$scan_data['current'] = $current;

		// Check if scan is complete.
		if ( $current >= $scan_data['total_posts'] ) {
			// Process menu links.
			if ( ! empty( $scan_data['menu_links'] ) ) {
				foreach ( $scan_data['menu_links'] as $link ) {
					$check_result = $this->check_link( $link['url'], $settings );

					$this->database->save_link(
						array(
							'url'            => $link['url'],
							'link_text'      => $link['link_text'],
							'source_post_id' => 0,
							'source_url'     => $link['source_url'],
							'link_type'      => $link['link_type'],
							'status_code'    => $check_result['status_code'],
							'status_text'    => $check_result['status_text'],
							'is_broken'      => $check_result['is_broken'],
							'redirect_url'   => $check_result['redirect_url'],
						)
					);
				}
			}

			$scan_data['status'] = 'completed';

			// Get final statistics.
			$stats = $this->database->get_stats();

			// Update log entry.
			if ( isset( $_POST['log_id'] ) ) {

                $log_id = intval( $_POST['log_id'] );

                // Calculate the time difference
                $scan_duration = $this->database->get_scan_duration( $log_id );



                
				$this->database->update_log(
					$log_id,
					array(
						'total_links'  => $stats['total'],
						'broken_links' => $stats['broken'],
						'warnings'     => $stats['warnings'],
                        'scan_duration' => $scan_duration,
						'status'       => 'completed',
						'completed_at' => current_time( 'mysql' ),
					)
				);
			}

			update_option( 'linkguardian_scan_progress', $scan_data );

			wp_send_json_success(
				array(
					'message'  => esc_html__( 'Scan completed successfully.', 'linkguardian' ),
					'complete' => true,
					'stats'    => $stats,
				)
			);
		}

		update_option( 'linkguardian_scan_progress', $scan_data );

		$progress = $scan_data['total_posts'] > 0 ? ( $current / $scan_data['total_posts'] ) * 100 : 0;

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: %d: number of posts processed */
					esc_html__( 'Processed %d posts...', 'linkguardian' ),
					$current
				),
				'complete' => false,
				'progress' => round( $progress, 2 ),
				'current'  => $current,
				'total'    => $scan_data['total_posts'],
			)
		);
	}

	/**
	 * Check a single link.
	 *
	 * @param string $url URL to check.
	 * @param array  $settings Plugin settings.
	 * @return array Check result.
	 */
	private function check_link( $url, $settings = array() ) {
		$result = array(
			'status_code'  => null,
			'status_text'  => '',
			'is_broken'    => 0,
			'redirect_url' => '',
		);

		// Skip checking if empty.
		if ( empty( $url ) ) {
			return $result;
		}

		// Determine link type.
		$link_type = $this->parser->get_link_type( $url );

		// Check settings.
		$check_external = isset( $settings['check_external_links'] ) && 'yes' === $settings['check_external_links'];
		$check_internal = isset( $settings['check_internal_links'] ) && 'yes' === $settings['check_internal_links'];

		if ( 'external' === $link_type && ! $check_external ) {
			return $result;
		}

		if ( 'internal' === $link_type && ! $check_internal ) {
			return $result;
		}

		// Check internal links.
		if ( 'internal' === $link_type ) {
			return $this->check_internal_link( $url );
		}

		// Check external links.
		return $this->check_external_link( $url );
	}

	/**
	 * Check internal link.
	 *
	 * @param string $url URL to check.
	 * @return array Check result.
	 */
	private function check_internal_link( $url ) {
		$result = array(
			'status_code'  => 200,
			'status_text'  => 'OK',
			'is_broken'    => 0,
			'redirect_url' => '',
		);

		// Parse URL to get path.
		$parsed = wp_parse_url( $url );
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';

		// Try to find the post/page.
		$post_id = url_to_postid( $url );

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post && 'publish' === $post->post_status ) {
				return $result;
			} else {
				$result['status_code'] = 404;
				$result['status_text'] = 'Not Found';
				$result['is_broken']   = 1;
			}
		} else {
			// Could be an attachment or other resource.
			$response = wp_remote_head( $url, array( 'timeout' => 10 ) );
			if ( is_wp_error( $response ) ) {
				$result['status_code'] = 0;
				$result['status_text'] = $response->get_error_message();
				$result['is_broken']   = 1;
			} else {
				$status_code           = wp_remote_retrieve_response_code( $response );
				$result['status_code'] = $status_code;
				$result['status_text'] = wp_remote_retrieve_response_message( $response );
				$result['is_broken']   = $status_code >= 400 ? 1 : 0;
			}
		}

		return $result;
	}

	/**
	 * Check external link.
	 *
	 * @param string $url URL to check.
	 * @return array Check result.
	 */
	private function check_external_link( $url ) {
		$result = array(
			'status_code'  => 0,
			'status_text'  => '',
			'is_broken'    => 0,
			'redirect_url' => '',
		);

		// Make HTTP request.
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 5,
				'user-agent'  => 'LinkGuardian/' . LINKGUARDIAN_VERSION . '; ' . get_bloginfo( 'url' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$result['status_code'] = 0;
			$result['status_text'] = $response->get_error_message();
			$result['is_broken']   = 1;
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );
			$result['status_code'] = $status_code;
			$result['status_text'] = wp_remote_retrieve_response_message( $response );

			// Check for redirects.
			$redirect_url = wp_remote_retrieve_header( $response, 'location' );
			if ( ! empty( $redirect_url ) ) {
				$result['redirect_url'] = $redirect_url;
			}

			// Mark as broken if status code is 400 or higher.
			$result['is_broken'] = $status_code >= 400 ? 1 : 0;
		}

		return $result;
	}

	/**
	 * AJAX handler to check a single link.
	 */
	public function ajax_check_single_link() {
		// Security check.
		check_ajax_referer( 'linkguardian_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'linkguardian' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? intval( $_POST['link_id'] ) : 0;

		if ( ! $link_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid link ID.', 'linkguardian' ) ) );
		}

		global $wpdb;
		$tables = $this->database->get_table_names();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$link = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tables['links']} WHERE id = %d",
				$link_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( ! $link ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Link not found.', 'linkguardian' ) ) );
		}

		// Check the link.
		$settings     = get_option( 'linkguardian_settings', array() );
		$check_result = $this->check_link( $link->url, $settings );

		// Update the link in database.
		$this->database->save_link(
			array(
				'url'            => $link->url,
				'link_text'      => $link->link_text,
				'source_post_id' => $link->source_post_id,
				'source_url'     => $link->source_url,
				'link_type'      => $link->link_type,
				'status_code'    => $check_result['status_code'],
				'status_text'    => $check_result['status_text'],
				'is_broken'      => $check_result['is_broken'],
				'redirect_url'   => $check_result['redirect_url'],
			)
		);

		wp_send_json_success(
			array(
				'message'     => esc_html__( 'Link rechecked successfully.', 'linkguardian' ),
				'status_code' => $check_result['status_code'],
				'status_text' => $check_result['status_text'],
				'is_broken'   => $check_result['is_broken'],
			)
		);
	}
}