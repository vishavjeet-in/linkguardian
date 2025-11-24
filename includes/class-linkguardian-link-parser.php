<?php
/**
 * Link Parser for LinkGuardian
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkGuardian Link Parser Class
 */
class LinkGuardian_Link_Parser {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

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
	 * Parse content and extract all links.
	 *
	 * @param string $content Content to parse.
	 * @param int    $post_id Post ID.
	 * @return array Array of links.
	 */
	public function parse_content( $content, $post_id = 0 ) {
		$links = array();

		if ( empty( $content ) ) {
			return $links;
		}

		// Use DOMDocument for reliable HTML parsing.
		$dom = new DOMDocument();
		// Suppress errors for malformed HTML.
		libxml_use_internal_errors( true );
		
		// Load HTML with UTF-8 encoding.
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		// Extract anchor links.
		$anchors = $dom->getElementsByTagName( 'a' );
		foreach ( $anchors as $anchor ) {
			$href = $anchor->getAttribute( 'href' );
			if ( ! empty( $href ) && '#' !== $href && 'javascript:' !== substr( $href, 0, 11 ) ) {
				$links[] = array(
					'url'       => $this->normalize_url( $href ),
					'link_text' => wp_strip_all_tags( $anchor->textContent ),
					'link_type' => $this->get_link_type( $href ),
					'post_id'   => $post_id,
				);
			}
		}

		// Extract image sources.
		$images = $dom->getElementsByTagName( 'img' );
		foreach ( $images as $image ) {
			$src = $image->getAttribute( 'src' );
			if ( ! empty( $src ) ) {
				$links[] = array(
					'url'       => $this->normalize_url( $src ),
					'link_text' => $image->getAttribute( 'alt' ),
					'link_type' => $this->get_link_type( $src ),
					'post_id'   => $post_id,
				);
			}
		}

		// Extract iframe sources.
		$iframes = $dom->getElementsByTagName( 'iframe' );
		foreach ( $iframes as $iframe ) {
			$src = $iframe->getAttribute( 'src' );
			if ( ! empty( $src ) ) {
				$links[] = array(
					'url'       => $this->normalize_url( $src ),
					'link_text' => 'iframe',
					'link_type' => $this->get_link_type( $src ),
					'post_id'   => $post_id,
				);
			}
		}

		// Extract script sources.
		$scripts = $dom->getElementsByTagName( 'script' );
		foreach ( $scripts as $script ) {
			$src = $script->getAttribute( 'src' );
			if ( ! empty( $src ) ) {
				$links[] = array(
					'url'       => $this->normalize_url( $src ),
					'link_text' => 'script',
					'link_type' => $this->get_link_type( $src ),
					'post_id'   => $post_id,
				);
			}
		}

		// Extract link tags (CSS, etc.).
		$link_tags = $dom->getElementsByTagName( 'link' );
		foreach ( $link_tags as $link_tag ) {
			$href = $link_tag->getAttribute( 'href' );
			if ( ! empty( $href ) ) {
				$links[] = array(
					'url'       => $this->normalize_url( $href ),
					'link_text' => $link_tag->getAttribute( 'rel' ),
					'link_type' => $this->get_link_type( $href ),
					'post_id'   => $post_id,
				);
			}
		}

		return $this->deduplicate_links( $links );
	}

	/**
	 * Normalize URL.
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	private function normalize_url( $url ) {
		$url = trim( $url );

		// Handle protocol-relative URLs.
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		// Handle relative URLs.
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$url = home_url( $url );
		}

		// Remove fragment identifiers.
		$url = preg_replace( '/#.*$/', '', $url );

		return esc_url_raw( $url );
	}

	/**
	 * Determine link type (internal or external).
	 *
	 * @param string $url URL to check.
	 * @return string Link type.
	 */
	public function get_link_type( $url ) {
		$site_url = get_site_url();
		$url      = $this->normalize_url( $url );

		if ( 0 === strpos( $url, $site_url ) || 0 === strpos( $url, '/' ) ) {
			return 'internal';
		}

		return 'external';
	}

	/**
	 * Remove duplicate links.
	 *
	 * @param array $links Array of links.
	 * @return array Deduplicated links.
	 */
	private function deduplicate_links( $links ) {
		$unique = array();
		$seen   = array();

		foreach ( $links as $link ) {
			$key = $link['url'] . '|' . $link['post_id'];
			if ( ! isset( $seen[ $key ] ) ) {
				$unique[] = $link;
				$seen[ $key ] = true;
			}
		}

		return $unique;
	}

	/**
	 * Parse post/page for links.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of links.
	 */
	public function parse_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		// Parse post content.
		$content = apply_filters( 'the_content', $post->post_content );
		$links   = $this->parse_content( $content, $post_id );

		// Add source URL to each link.
		$source_url = get_permalink( $post_id );
		foreach ( $links as &$link ) {
			$link['source_url'] = $source_url;
		}

		return $links;
	}

	/**
	 * Get all posts to scan.
	 *
	 * @param array $post_types Post types to scan.
	 * @return array Array of post IDs.
	 */
	public function get_posts_to_scan( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Parse navigation menus for links.
	 *
	 * @return array Array of links.
	 */
	public function parse_menus() {
		$links = array();
		$menus = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );

			if ( $menu_items ) {
				foreach ( $menu_items as $item ) {
					if ( ! empty( $item->url ) ) {
						$links[] = array(
							'url'        => $this->normalize_url( $item->url ),
							'link_text'  => $item->title,
							'link_type'  => $this->get_link_type( $item->url ),
							'post_id'    => 0,
							'source_url' => 'Menu: ' . $menu->name,
						);
					}
				}
			}
		}

		return $this->deduplicate_links( $links );
	}
}