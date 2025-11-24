<?php
/**
 * Logs View
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap linkguardian-logs">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'View the history of all link scans performed on your site.', 'linkguardian' ); ?>
	</p>

	<?php if ( empty( $logs ) ) : ?>
		<div class="linkguardian-empty-state">
			<span class="dashicons dashicons-clipboard"></span>
			<h2><?php esc_html_e( 'No scan logs found', 'linkguardian' ); ?></h2>
			<p><?php esc_html_e( 'Scan logs will appear here after you run your first scan.', 'linkguardian' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=linkguardian' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Start Your First Scan', 'linkguardian' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped linkguardian-logs-table">
			<thead>
				<tr>
					<th class="column-id"><?php esc_html_e( 'ID', 'linkguardian' ); ?></th>
					<th class="column-type"><?php esc_html_e( 'Scan Type', 'linkguardian' ); ?></th>
					<th class="column-stats"><?php esc_html_e( 'Statistics', 'linkguardian' ); ?></th>
					<th class="column-duration"><?php esc_html_e( 'Duration', 'linkguardian' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'linkguardian' ); ?></th>
					<th class="column-started"><?php esc_html_e( 'Started', 'linkguardian' ); ?></th>
					<th class="column-completed"><?php esc_html_e( 'Completed', 'linkguardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr class="log-status-<?php echo esc_attr( $log->status ); ?>">
						<td class="column-id">
							<strong>#<?php echo esc_html( $log->id ); ?></strong>
						</td>
						<td class="column-type">
							<span class="scan-type-badge type-<?php echo esc_attr( $log->scan_type ); ?>">
								<?php
								switch ( $log->scan_type ) {
									case 'manual':
										esc_html_e( 'Manual Scan', 'linkguardian' );
										break;
									case 'scheduled':
										esc_html_e( 'Scheduled Scan', 'linkguardian' );
										break;
									case 'automatic':
										esc_html_e( 'Automatic Scan', 'linkguardian' );
										break;
									default:
										echo esc_html( ucfirst( $log->scan_type ) );
								}
								?>
							</span>
						</td>
						<td class="column-stats">
							<div class="log-stats">
								<span class="stat-item">
									<span class="dashicons dashicons-admin-links"></span>
									<strong><?php echo esc_html( number_format_i18n( $log->total_links ) ); ?></strong>
									<?php esc_html_e( 'Total', 'linkguardian' ); ?>
								</span>
								<?php if ( $log->broken_links > 0 ) : ?>
									<span class="stat-item stat-broken">
										<span class="dashicons dashicons-warning"></span>
										<strong><?php echo esc_html( number_format_i18n( $log->broken_links ) ); ?></strong>
										<?php esc_html_e( 'Broken', 'linkguardian' ); ?>
									</span>
								<?php endif; ?>
								<?php if ( $log->warnings > 0 ) : ?>
									<span class="stat-item stat-warning">
										<span class="dashicons dashicons-info"></span>
										<strong><?php echo esc_html( number_format_i18n( $log->warnings ) ); ?></strong>
										<?php esc_html_e( 'Warnings', 'linkguardian' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td class="column-duration">
							<?php
							if ( $log->scan_duration > 0 ) {
								if ( $log->scan_duration < 60 ) {
									/* translators: %d: number of seconds */
									echo esc_html( sprintf( _n( '%d second', '%d seconds', $log->scan_duration, 'linkguardian' ), $log->scan_duration ) );
								} else {
									$minutes = floor( $log->scan_duration / 60 );
									$seconds = $log->scan_duration % 60;
									/* translators: 1: number of minutes, 2: number of seconds */
									echo esc_html( sprintf( __( '%1$dm %2$ds', 'linkguardian' ), $minutes, $seconds ) );
								}
							} else {
								echo '—';
							}
							?>
						</td>
						<td class="column-status">
							<?php
							$status_class = '';
							$status_text  = '';
							switch ( $log->status ) {
								case 'completed':
									$status_class = 'status-completed';
									$status_text  = __( 'Completed', 'linkguardian' );
									break;
								case 'in_progress':
									$status_class = 'status-in-progress';
									$status_text  = __( 'In Progress', 'linkguardian' );
									break;
								case 'failed':
									$status_class = 'status-failed';
									$status_text  = __( 'Failed', 'linkguardian' );
									break;
								default:
									$status_class = 'status-unknown';
									$status_text  = ucfirst( $log->status );
							}
							?>
							<span class="status-badge <?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $status_text ); ?>
							</span>
							<?php if ( 'failed' === $log->status && $log->error_message ) : ?>
								<br>
								<small class="error-message">
									<?php echo esc_html( $log->error_message ); ?>
								</small>
							<?php endif; ?>
						</td>
						<td class="column-started">
							<?php
							if ( $log->started_at ) {
								echo esc_html( human_time_diff( strtotime( $log->started_at ), current_time( 'timestamp' ) ) );
								echo ' ' . esc_html__( 'ago', 'linkguardian' );
								?>
								<br>
								<small class="log-date">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->started_at ) ) ); ?>
								</small>
								<?php
							} else {
								echo '—';
							}
							?>
						</td>
						   <td class="column-completed">
							   <?php
							   $completed_timestamp = strtotime( $log->completed_at );
							   if ( $log->completed_at && $completed_timestamp && $completed_timestamp > 0 ) {
								   echo esc_html( human_time_diff( $completed_timestamp, current_time( 'timestamp' ) ) );
								   echo ' ' . esc_html__( 'ago', 'linkguardian' );
								   ?>
								   <br>
								   <small class="log-date">
									   <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completed_timestamp ) ); ?>
								   </small>
								   <?php
							   } else {
								   echo '—';
							   }
							   ?>
						   </td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="linkguardian-logs-summary">
			<p class="description">
				<?php
				/* translators: %d: number of log entries */
				echo esc_html( sprintf( __( 'Showing the last %d scan logs.', 'linkguardian' ), count( $logs ) ) );
				?>
			</p>
		</div>
	<?php endif; ?>
</div>