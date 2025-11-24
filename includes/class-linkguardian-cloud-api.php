<?php
/**
 * Cloud API for LinkGuardian
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkGuardian Cloud API Class
 */
class LinkGuardian_Cloud_API {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Cloud API endpoint.
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.linkguardian.com/v1/check'; // Placeholder.

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
	 * Check if cloud scanner is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled() {
		$settings = get_option( 'linkguardian_settings', array() );
		return isset( $settings['cloud_scanner_enabled'] ) && 'yes' === $settings['cloud_scanner_enabled'];
	}

	/**
	 * Check a link using cloud API.
	 *
	 * @param string $url URL to check.
	 * @return array Check result.
	 */
	public function check_link( $url ) {
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Cloud scanner is not enabled.', 'linkguardian' ),
			);
		}

		// Prepare API request.
		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'url'  => $url,
						'site' => get_site_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid response from cloud API.', 'linkguardian' ),
			);
		}

		return array(
			'success'      => true,
			'status_code'  => isset( $data['status_code'] ) ? $data['status_code'] : 0,
			'status_text'  => isset( $data['status_text'] ) ? $data['status_text'] : '',
			'is_broken'    => isset( $data['is_broken'] ) ? $data['is_broken'] : 0,
			'redirect_url' => isset( $data['redirect_url'] ) ? $data['redirect_url'] : '',
			'load_time'    => isset( $data['load_time'] ) ? $data['load_time'] : 0,
		);
	}

	/**
	 * Batch check multiple links.
	 *
	 * @param array $urls Array of URLs to check.
	 * @return array Batch check results.
	 */
	public function batch_check_links( $urls ) {
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Cloud scanner is not enabled.', 'linkguardian' ),
			);
		}

		// Prepare API request.
		$response = wp_remote_post(
			$this->api_endpoint . '/batch',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'urls' => $urls,
						'site' => get_site_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid response from cloud API.', 'linkguardian' ),
			);
		}

		return array(
			'success' => true,
			'results' => isset( $data['results'] ) ? $data['results'] : array(),
		);
	}

	/**
	 * Get API status.
	 *
	 * @return array API status.
	 */
	public function get_status() {
		$response = wp_remote_get(
			str_replace( '/check', '/status', $this->api_endpoint ),
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'online' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return array(
			'online'  => true,
			'version' => isset( $data['version'] ) ? $data['version'] : 'Unknown',
			'message' => isset( $data['message'] ) ? $data['message'] : '',
		);
	}
}