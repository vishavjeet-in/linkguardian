<?php
/**
 * Dashboard View
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap linkguardian-dashboard">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( get_admin_page_title() ); ?>
	</h1>

	<hr class="wp-header-end">

	<!-- Statistics Cards -->
	<div class="linkguardian-stats">
		<div class="linkguardian-stat-card">
			<div class="stat-icon stat-total">
				<span class="dashicons dashicons-admin-links"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></h3>
				<p><?php esc_html_e( 'Total Links', 'linkguardian' ); ?></p>
			</div>
		</div>

		<div class="linkguardian-stat-card">
			<div class="stat-icon stat-broken">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['broken'] ) ); ?></h3>
				<p><?php esc_html_e( 'Broken Links', 'linkguardian' ); ?></p>
			</div>
		</div>

		<div class="linkguardian-stat-card">
			<div class="stat-icon stat-warning">
				<span class="dashicons dashicons-info"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['warnings'] ) ); ?></h3>
				<p><?php esc_html_e( 'Warnings', 'linkguardian' ); ?></p>
			</div>
		</div>

		<div class="linkguardian-stat-card">
			<div class="stat-icon stat-healthy">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['healthy'] ) ); ?></h3>
				<p><?php esc_html_e( 'Healthy Links', 'linkguardian' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Scan Controls -->
	<div class="linkguardian-scan-controls">
		<button type="button" class="button button-primary button-hero" id="linkguardian-start-scan">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Start New Scan', 'linkguardian' ); ?>
		</button>

		<button type="button" class="button button-secondary" id="linkguardian-clear-all">
			<span class="dashicons dashicons-trash"></span>
			<?php esc_html_e( 'Clear All Links', 'linkguardian' ); ?>
		</button>

		<div class="linkguardian-scan-progress" style="display: none;">
			<div class="progress-bar">
				<div class="progress-fill" style="width: 0%;"></div>
			</div>
			<p class="progress-text">
				<?php esc_html_e( 'Preparing scan...', 'linkguardian' ); ?>
			</p>
		</div>
	</div>

	<!-- Filters -->
	<div class="linkguardian-filters">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=linkguardian' ) ); ?>" class="<?php echo 'all' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'linkguardian' ); ?>
					<span class="count">(<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=linkguardian&filter=broken' ) ); ?>" class="<?php echo 'broken' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Broken', 'linkguardian' ); ?>
					<span class="count">(<?php echo esc_html( number_format_i18n( $stats['broken'] ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=linkguardian&filter=warnings' ) ); ?>" class="<?php echo 'warnings' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Warnings', 'linkguardian' ); ?>
					<span class="count">(<?php echo esc_html( number_format_i18n( $stats['warnings'] ) ); ?>)</span>
				</a>
			</li>
		</ul>
	</div>

	<!-- Links Table -->
	<div class="linkguardian-table-wrapper">
		<?php if ( empty( $links ) ) : ?>
			<div class="linkguardian-empty-state">
				<span class="dashicons dashicons-admin-links"></span>
				<h2><?php esc_html_e( 'No links found', 'linkguardian' ); ?></h2>
				<p><?php esc_html_e( 'Start a new scan to check your site for broken links.', 'linkguardian' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped linkguardian-links-table">
				<thead>
					<tr>
						<th class="column-url"><?php esc_html_e( 'URL', 'linkguardian' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'linkguardian' ); ?></th>
						<th class="column-source"><?php esc_html_e( 'Found In', 'linkguardian' ); ?></th>
						<th class="column-type"><?php esc_html_e( 'Type', 'linkguardian' ); ?></th>
						<th class="column-checked"><?php esc_html_e( 'Last Checked', 'linkguardian' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'linkguardian' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $links as $link ) : ?>
						<tr data-link-id="<?php echo esc_attr( $link->id ); ?>" class="<?php echo $link->is_broken ? 'link-broken' : ''; ?>">
							<td class="column-url">
								<a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( wp_trim_words( $link->url, 10, '...' ) ); ?>
								</a>
								<?php if ( $link->link_text ) : ?>
									<br>
									<small class="link-text"><?php echo esc_html( wp_trim_words( $link->link_text, 8, '...' ) ); ?></small>
								<?php endif; ?>
							</td>
							<td class="column-status">
								<span class="status-badge status-<?php echo esc_attr( $link->is_broken ? 'broken' : 'ok' ); ?>">
									<?php
									if ( $link->status_code ) {
										echo esc_html( $link->status_code );
										if ( $link->status_text ) {
											echo ' - ' . esc_html( $link->status_text );
										}
									} else {
										esc_html_e( 'Not checked', 'linkguardian' );
									}
									?>
								</span>
								<?php if ( $link->redirect_url ) : ?>
									<br>
									<small class="redirect-info">
										<span class="dashicons dashicons-redo"></span>
										<?php esc_html_e( 'Redirects to:', 'linkguardian' ); ?>
										<a href="<?php echo esc_url( $link->redirect_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( wp_trim_words( $link->redirect_url, 8, '...' ) ); ?>
										</a>
									</small>
								<?php endif; ?>
							</td>
							<td class="column-source">
								<?php if ( $link->source_post_id ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $link->source_post_id ) ); ?>">
										<?php echo esc_html( get_the_title( $link->source_post_id ) ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $link->source_url ? $link->source_url : __( 'Unknown', 'linkguardian' ) ); ?>
								<?php endif; ?>
							</td>
							<td class="column-type">
								<span class="type-badge type-<?php echo esc_attr( $link->link_type ); ?>">
									<?php echo esc_html( ucfirst( $link->link_type ) ); ?>
								</span>
							</td>
							<td class="column-checked">
								<?php
								if ( $link->last_checked ) {
									echo esc_html( human_time_diff( strtotime( $link->last_checked ), current_time( 'timestamp' ) ) );
									echo ' ' . esc_html__( 'ago', 'linkguardian' );
								} else {
									esc_html_e( 'Never', 'linkguardian' );
								}
								?>
								<?php if ( $link->check_count > 0 ) : ?>
									<br>
									<small>
										<?php
										/* translators: %d: number of times checked */
										echo esc_html( sprintf( _n( 'Checked %d time', 'Checked %d times', $link->check_count, 'linkguardian' ), $link->check_count ) );
										?>
									</small>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<button type="button" class="button button-small linkguardian-recheck-link" data-link-id="<?php echo esc_attr( $link->id ); ?>">
									<span class="dashicons dashicons-update-alt"></span>
									<?php esc_html_e( 'Recheck', 'linkguardian' ); ?>
								</button>
								<button type="button" class="button button-small button-link-delete linkguardian-delete-link" data-link-id="<?php echo esc_attr( $link->id ); ?>">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Delete', 'linkguardian' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>