<?php
/**
 * Settings View
 *
 * @package LinkGuardian
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap linkguardian-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'linkguardian_settings_group' );
		?>

		<table class="form-table" role="presentation">
			<!-- Data Management Section -->
			<tr>
				<th scope="row" colspan="2">
					<h2 class="title"><?php esc_html_e( 'Data Management', 'linkguardian' ); ?></h2>
				</th>
			</tr>

			<tr>
				<th scope="row">
					<label for="delete_data_on_deactivation">
						<?php esc_html_e( 'Delete Data on Deactivation', 'linkguardian' ); ?>
					</label>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Delete Data on Deactivation', 'linkguardian' ); ?></span>
						</legend>
						<label for="delete_data_on_deactivation">
							<input
								type="checkbox"
								name="linkguardian_settings[delete_data_on_deactivation]"
								id="delete_data_on_deactivation"
								value="yes"
								<?php checked( isset( $settings['delete_data_on_deactivation'] ) && 'yes' === $settings['delete_data_on_deactivation'] ); ?>
							>
							<?php esc_html_e( 'Remove all plugin data when deactivating', 'linkguardian' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Warning: This will permanently delete all scanned links and logs when you deactivate the plugin.', 'linkguardian' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- Scan Options Section -->
			<tr>
				<th scope="row" colspan="2">
					<h2 class="title"><?php esc_html_e( 'Scan Options', 'linkguardian' ); ?></h2>
				</th>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Post Types to Scan', 'linkguardian' ); ?>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Post Types to Scan', 'linkguardian' ); ?></span>
						</legend>
						<?php
						$selected_post_types = isset( $settings['scan_post_types'] ) ? $settings['scan_post_types'] : array( 'post', 'page' );
						foreach ( $post_types as $post_type ) :
							?>
							<label>
								<input
									type="checkbox"
									name="linkguardian_settings[scan_post_types][]"
									value="<?php echo esc_attr( $post_type->name ); ?>"
									<?php checked( in_array( $post_type->name, $selected_post_types, true ) ); ?>
								>
								<?php echo esc_html( $post_type->label ); ?>
							</label><br>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'Select which post types to scan for links.', 'linkguardian' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="check_external_links">
						<?php esc_html_e( 'Check External Links', 'linkguardian' ); ?>
					</label>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Check External Links', 'linkguardian' ); ?></span>
						</legend>
						<label for="check_external_links">
							<input
								type="checkbox"
								name="linkguardian_settings[check_external_links]"
								id="check_external_links"
								value="yes"
								<?php checked( isset( $settings['check_external_links'] ) && 'yes' === $settings['check_external_links'] ); ?>
							>
							<?php esc_html_e( 'Enable checking of external links', 'linkguardian' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Check links pointing to external websites.', 'linkguardian' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="check_internal_links">
						<?php esc_html_e( 'Check Internal Links', 'linkguardian' ); ?>
					</label>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Check Internal Links', 'linkguardian' ); ?></span>
						</legend>
						<label for="check_internal_links">
							<input
								type="checkbox"
								name="linkguardian_settings[check_internal_links]"
								id="check_internal_links"
								value="yes"
								<?php checked( isset( $settings['check_internal_links'] ) && 'yes' === $settings['check_internal_links'] ); ?>
							>
							<?php esc_html_e( 'Enable checking of internal links', 'linkguardian' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Check links pointing to pages within your own website.', 'linkguardian' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- Cloud Scanner Section -->
			<tr>
				<th scope="row" colspan="2">
					<h2 class="title"><?php esc_html_e( 'Cloud Scanner', 'linkguardian' ); ?></h2>
				</th>
			</tr>

			<tr>
				<th scope="row">
					<label for="cloud_scanner_enabled">
						<?php esc_html_e( 'Enable Cloud Scanner', 'linkguardian' ); ?>
					</label>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Enable Cloud Scanner', 'linkguardian' ); ?></span>
						</legend>
						<label for="cloud_scanner_enabled">
							<input
								type="checkbox"
								name="linkguardian_settings[cloud_scanner_enabled]"
								id="cloud_scanner_enabled"
								value="yes"
								<?php checked( isset( $settings['cloud_scanner_enabled'] ) && 'yes' === $settings['cloud_scanner_enabled'] ); ?>
								disabled
							>
							<?php esc_html_e( 'Use cloud-based link scanning', 'linkguardian' ); ?>
							<span class="linkguardian-badge-coming-soon"><?php esc_html_e( 'Coming Soon', 'linkguardian' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Cloud scanning provides faster results for large sites and additional data like page load times.', 'linkguardian' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- Notifications Section -->
			<tr>
				<th scope="row" colspan="2">
					<h2 class="title"><?php esc_html_e( 'Notifications', 'linkguardian' ); ?></h2>
				</th>
			</tr>

			<tr>
				<th scope="row">
					<label for="email_notifications">
						<?php esc_html_e( 'Email Notifications', 'linkguardian' ); ?>
					</label>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Email Notifications', 'linkguardian' ); ?></span>
						</legend>
						<label for="email_notifications">
							<input
								type="checkbox"
								name="linkguardian_settings[email_notifications]"
								id="email_notifications"
								value="yes"
								<?php checked( isset( $settings['email_notifications'] ) && 'yes' === $settings['email_notifications'] ); ?>
							>
							<?php esc_html_e( 'Send email when broken links are found', 'linkguardian' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Receive notifications about broken links after each scan.', 'linkguardian' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="notification_email">
						<?php esc_html_e( 'Notification Email', 'linkguardian' ); ?>
					</label>
				</th>
				<td>
					<input
						type="email"
						name="linkguardian_settings[notification_email]"
						id="notification_email"
						value="<?php echo esc_attr( isset( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' ) ); ?>"
						class="regular-text"
					>
					<p class="description">
						<?php esc_html_e( 'Email address to receive notifications.', 'linkguardian' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Settings', 'linkguardian' ) ); ?>
	</form>

	<!-- System Information -->
	<div class="linkguardian-system-info">
		<h2><?php esc_html_e( 'System Information', 'linkguardian' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Plugin Version:', 'linkguardian' ); ?></strong></td>
					<td><?php echo esc_html( LINKGUARDIAN_VERSION ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WordPress Version:', 'linkguardian' ); ?></strong></td>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Version:', 'linkguardian' ); ?></strong></td>
					<td><?php echo esc_html( phpversion() ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Database Tables:', 'linkguardian' ); ?></strong></td>
					<td>
						<?php
						$tables = LinkGuardian_Database::get_instance()->get_table_names();
						echo esc_html( implode( ', ', $tables ) );
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>