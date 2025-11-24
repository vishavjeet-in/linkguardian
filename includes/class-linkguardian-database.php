<?php
/**
 * Database operations for LinkGuardian
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkGuardian Database Class
 */
class LinkGuardian_Database {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Table name for storing links.
	 *
	 * @var string
	 */
	private $links_table;

	/**
	 * Table name for storing scan logs.
	 *
	 * @var string
	 */
	private $logs_table;

	/**
	 * Initialize the class.
	 */
	private function __construct() {
		global $wpdb;
		$this->links_table = $wpdb->prefix . 'linkguardian_links';
		$this->logs_table  = $wpdb->prefix . 'linkguardian_logs';
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
	 * Create database tables.
	 */
	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Links table.
		$links_table_sql = "CREATE TABLE IF NOT EXISTS {$this->links_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			link_text varchar(500) DEFAULT NULL,
			source_post_id bigint(20) UNSIGNED DEFAULT NULL,
			source_url varchar(2048) DEFAULT NULL,
			link_type varchar(20) DEFAULT 'external',
			status_code int(4) DEFAULT NULL,
			status_text varchar(100) DEFAULT NULL,
			is_broken tinyint(1) DEFAULT 0,
			redirect_url varchar(2048) DEFAULT NULL,
			last_checked datetime DEFAULT NULL,
			check_count int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY url_index (url(191)),
			KEY source_post_id_index (source_post_id),
			KEY is_broken_index (is_broken),
			KEY last_checked_index (last_checked)
		) $charset_collate;";

		// Logs table.
		$logs_table_sql = "CREATE TABLE IF NOT EXISTS {$this->logs_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_type varchar(50) DEFAULT 'manual',
			total_links int(11) DEFAULT 0,
			broken_links int(11) DEFAULT 0,
			warnings int(11) DEFAULT 0,
			scan_duration int(11) DEFAULT 0,
			status varchar(20) DEFAULT 'completed',
			error_message text DEFAULT NULL,
			started_at datetime DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY started_at_index (started_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $links_table_sql );
		dbDelta( $logs_table_sql );
	}

	/**
	 * Drop database tables.
	 */
	public function drop_tables() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$this->links_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$this->logs_table}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Insert or update a link.
	 *
	 * @param array $link_data Link data to insert/update.
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function save_link( $link_data ) {
		global $wpdb;

		$link_data = wp_parse_args(
			$link_data,
			array(
				'url'            => '',
				'link_text'      => '',
				'source_post_id' => null,
				'source_url'     => '',
				'link_type'      => 'external',
				'status_code'    => null,
				'status_text'    => '',
				'is_broken'      => 0,
				'redirect_url'   => '',
				'last_checked'   => current_time( 'mysql' ),
			)
		);

		// Check if link already exists.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, check_count FROM {$this->links_table} WHERE url = %s AND source_post_id = %d",
				$link_data['url'],
				$link_data['source_post_id']
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( $existing ) {
			// Update existing link.
			$update_data = array(
				'link_text'    => $link_data['link_text'],
				'status_code'  => $link_data['status_code'],
				'status_text'  => $link_data['status_text'],
				'is_broken'    => $link_data['is_broken'],
				'redirect_url' => $link_data['redirect_url'],
				'last_checked' => $link_data['last_checked'],
				'check_count'  => $existing->check_count + 1,
			);

			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			return $wpdb->update(
				$this->links_table,
				$update_data,
				array( 'id' => $existing->id ),
				array( '%s', '%d', '%s', '%d', '%s', '%s', '%d' ),
				array( '%d' )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
		} else {
			// Insert new link.
			$link_data['check_count'] = 1;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			return $wpdb->insert(
				$this->links_table,
				$link_data,
				array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d' )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Get all links with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of links.
	 */
	public function get_links( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_broken'      => null,
			'link_type'      => null,
			'source_post_id' => null,
			'limit'          => 50,
			'offset'         => 0,
			'orderby'        => 'last_checked',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where   = array( '1=1' );
		$prepare = array();

		if ( null !== $args['is_broken'] ) {
			$where[]   = 'is_broken = %d';
			$prepare[] = $args['is_broken'];
		}

		if ( null !== $args['link_type'] ) {
			$where[]   = 'link_type = %s';
			$prepare[] = $args['link_type'];
		}

		if ( null !== $args['source_post_id'] ) {
			$where[]   = 'source_post_id = %d';
			$prepare[] = $args['source_post_id'];
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'last_checked DESC';
		}

		$prepare[] = $args['limit'];
		$prepare[] = $args['offset'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $prepare ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->links_table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
					$prepare
				)
			);
		} else {
			$results = $wpdb->get_results(
				"SELECT * FROM {$this->links_table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT 50 OFFSET 0"
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}

	/**
	 * Get link statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_stats() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->links_table}" );
		$broken = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->links_table} WHERE is_broken = 1" );
		$warnings = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->links_table} WHERE status_code >= 300 AND status_code < 400" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return array(
			'total'    => intval( $total ),
			'broken'   => intval( $broken ),
			'warnings' => intval( $warnings ),
			'healthy'  => intval( $total ) - intval( $broken ) - intval( $warnings ),
		);
	}

	/**
	 * Delete a link by ID.
	 *
	 * @param int $link_id Link ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete_link( $link_id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return $wpdb->delete(
			$this->links_table,
			array( 'id' => $link_id ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Clear all links.
	 *
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function clear_all_links() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return $wpdb->query( "TRUNCATE TABLE {$this->links_table}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Insert a scan log entry.
	 *
	 * @param array $log_data Log data.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public function insert_log( $log_data ) {
		global $wpdb;

		$log_data = wp_parse_args(
			$log_data,
			array(
				'scan_type'     => 'manual',
				'total_links'   => 0,
				'broken_links'  => 0,
				'warnings'      => 0,
				'scan_duration' => 0,
				'status'        => 'in_progress',
				'error_message' => null,
				'started_at'    => current_time( 'mysql' ),
				'completed_at'  => null,
			)
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->logs_table,
			$log_data,
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

    /**
     * Get scan duration for a log entry.
     *
     * @param int $log_id Log ID.
     * @return int Duration in seconds.
     */
    public function get_scan_duration( $log_id ) {
        global $wpdb;

        // 1. Get started_at from DB
        $start_time = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT started_at FROM {$this->logs_table} WHERE id = %d",
                $log_id
            )
        );

        if ( empty( $start_time ) ) {
            return 0; // No start time, avoid errors
        }

        // 2. Convert to timestamp
        $start_timestamp = strtotime( $start_time );
        $end_timestamp   = current_time( 'timestamp' );

        // 3. Duration in seconds
        $scan_duration = $end_timestamp - $start_timestamp;

        return max( 0, $scan_duration ); // Never return negative
    }


	/**
	 * Update a scan log entry.
	 *
	 * @param int   $log_id Log ID.
	 * @param array $log_data Log data to update.
	 * @return int|false The number of rows updated, or false on error.
	 */
    public function update_log( $log_id, $log_data ) {
        global $wpdb;

        return $wpdb->update(
            $this->logs_table,
            $log_data,
            array( 'id' => $log_id ),
            array( 
                '%d',  // total_links
                '%d',  // broken_links
                '%d',  // warnings
                '%d',  // scan_duration
                '%s',  // status
                '%s',  // completed_at
            ),
            array( '%d' ) // WHERE id = %d
        );
    }


	/**
	 * Get scan logs.
	 *
	 * @param int $limit Number of logs to retrieve.
	 * @return array Array of log entries.
	 */
	public function get_logs( $limit = 10 ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->logs_table} ORDER BY started_at DESC LIMIT %d",
				$limit
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get table names.
	 *
	 * @return array Table names.
	 */
	public function get_table_names() {
		return array(
			'links' => $this->links_table,
			'logs'  => $this->logs_table,
		);
	}
}