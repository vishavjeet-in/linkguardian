/**
 * LinkGuardian Admin JavaScript
 *
 * @package LinkGuardian
 */

(function ($) {
	'use strict';

	const LinkGuardianAdmin = {
		scanInProgress: false,
		currentLogId: null,

		/**
		 * Initialize
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function () {
			// Start scan button
			$('#linkguardian-start-scan').on('click', this.startScan.bind(this));

			// Clear all links button
			$('#linkguardian-clear-all').on('click', this.clearAllLinks.bind(this));

			// Recheck single link
			$(document).on('click', '.linkguardian-recheck-link', this.recheckLink.bind(this));

			// Delete single link
			$(document).on('click', '.linkguardian-delete-link', this.deleteLink.bind(this));
		},

		/**
		 * Start scan
		 */
		startScan: function (e) {
			e.preventDefault();

			if (this.scanInProgress) {
				alert(linkguardianAdmin.strings.scanning);
				return;
			}

			const $button = $('#linkguardian-start-scan');
			const $progress = $('.linkguardian-scan-progress');

			// Disable button
			$button.prop('disabled', true);
			this.scanInProgress = true;

			// Show progress bar
			$progress.show();
			$('.progress-fill').css('width', '0%').text('');
			$('.progress-text').text(linkguardianAdmin.strings.scanning);

			// Start scan via AJAX
			$.ajax({
				url: linkguardianAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'linkguardian_start_scan',
					nonce: linkguardianAdmin.nonce
				},
				success: (response) => {
					if (response.success) {
						this.currentLogId = response.data.log_id;
						// Start processing batches
						this.processBatch();
					} else {
						this.showError(response.data.message || linkguardianAdmin.strings.scan_error);
						this.resetScanUI();
					}
				},
				error: () => {
					this.showError(linkguardianAdmin.strings.scan_error);
					this.resetScanUI();
				}
			});
		},

		/**
		 * Process batch
		 */
		processBatch: function () {
			$.ajax({
				url: linkguardianAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'linkguardian_scan_batch',
					nonce: linkguardianAdmin.nonce,
					log_id: this.currentLogId
				},
				success: (response) => {
					if (response.success) {
						if (response.data.complete) {
							// Scan complete
							this.scanComplete(response.data.stats);
						} else {
							// Update progress
							const progress = response.data.progress || 0;
							const current = response.data.current || 0;
							const total = response.data.total || 0;

							$('.progress-fill')
								.css('width', progress + '%')
								.text(Math.round(progress) + '%');
							$('.progress-text').text(
								`Processing... ${current} of ${total} posts`
							);

							// Continue processing
							setTimeout(() => {
								this.processBatch();
							}, 100);
						}
					} else {
						this.showError(response.data.message || linkguardianAdmin.strings.scan_error);
						this.resetScanUI();
					}
				},
				error: () => {
					this.showError(linkguardianAdmin.strings.scan_error);
					this.resetScanUI();
				}
			});
		},

		/**
		 * Scan complete
		 */
		scanComplete: function (stats) {
			$('.progress-fill')
				.css('width', '100%')
				.text('100%');
			$('.progress-text').text(linkguardianAdmin.strings.scan_complete);

			// Show success message
			this.showSuccess(
				`Scan completed! Found ${stats.total} links (${stats.broken} broken, ${stats.warnings} warnings)`
			);

			// Reset UI after delay
			setTimeout(() => {
				this.resetScanUI();
				// Reload page to show results
				location.reload();
			}, 2000);
		},

		/**
		 * Reset scan UI
		 */
		resetScanUI: function () {
			$('#linkguardian-start-scan').prop('disabled', false);
			$('.linkguardian-scan-progress').hide();
			this.scanInProgress = false;
			this.currentLogId = null;
		},

		/**
		 * Recheck single link
		 */
		recheckLink: function (e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const linkId = $button.data('link-id');
			const $row = $button.closest('tr');

			if (!linkId) {
				return;
			}

			// Disable button
			$button.prop('disabled', true);
			$button.html('<span class="linkguardian-spinner"></span> Checking...');

			$.ajax({
				url: linkguardianAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'linkguardian_check_link',
					nonce: linkguardianAdmin.nonce,
					link_id: linkId
				},
				success: (response) => {
					if (response.success) {
						this.showSuccess('Link rechecked successfully!');
						
						// Update row
						const statusCode = response.data.status_code;
						const statusText = response.data.status_text;
						const isBroken = response.data.is_broken;

						// Update status badge
						const $statusBadge = $row.find('.status-badge');
						$statusBadge
							.removeClass('status-ok status-broken')
							.addClass(isBroken ? 'status-broken' : 'status-ok')
							.text(statusCode + (statusText ? ' - ' + statusText : ''));

						// Update row class
						$row.toggleClass('link-broken', isBroken);

						// Reset button
						$button.html('<span class="dashicons dashicons-update-alt"></span> Recheck');
					} else {
						this.showError(response.data.message || 'Failed to recheck link');
					}
					
					$button.prop('disabled', false);
				},
				error: () => {
					this.showError('Failed to recheck link');
					$button.prop('disabled', false);
					$button.html('<span class="dashicons dashicons-update-alt"></span> Recheck');
				}
			});
		},

		/**
		 * Delete single link
		 */
		deleteLink: function (e) {
			e.preventDefault();

			if (!confirm(linkguardianAdmin.strings.confirm_delete)) {
				return;
			}

			const $button = $(e.currentTarget);
			const linkId = $button.data('link-id');
			const $row = $button.closest('tr');

			if (!linkId) {
				return;
			}

			// Disable button
			$button.prop('disabled', true);

			$.ajax({
				url: linkguardianAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'linkguardian_delete_link',
					nonce: linkguardianAdmin.delete_nonce,
					link_id: linkId
				},
				success: (response) => {
					if (response.success) {
						// Remove row with animation
						$row.fadeOut(300, function () {
							$(this).remove();
							
							// Check if table is empty
							if ($('.linkguardian-links-table tbody tr').length === 0) {
								location.reload();
							}
						});
						
						this.showSuccess('Link deleted successfully!');
					} else {
						this.showError(response.data.message || 'Failed to delete link');
						$button.prop('disabled', false);
					}
				},
				error: () => {
					this.showError('Failed to delete link');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Clear all links
		 */
		clearAllLinks: function (e) {
			e.preventDefault();

			if (!confirm(linkguardianAdmin.strings.confirm_clear_all)) {
				return;
			}

			const $button = $('#linkguardian-clear-all');

			// Disable button
			$button.prop('disabled', true);

			$.ajax({
				url: linkguardianAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'linkguardian_clear_all_links',
					nonce: linkguardianAdmin.delete_nonce
				},
				success: (response) => {
					if (response.success) {
						this.showSuccess('All links cleared successfully!');
						
						// Reload page after delay
						setTimeout(() => {
							location.reload();
						}, 1000);
					} else {
						this.showError(response.data.message || 'Failed to clear links');
						$button.prop('disabled', false);
					}
				},
				error: () => {
					this.showError('Failed to clear links');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Show success message
		 */
		showSuccess: function (message) {
			this.showNotice(message, 'success');
		},

		/**
		 * Show error message
		 */
		showError: function (message) {
			this.showNotice(message, 'error');
		},

		/**
		 * Show notice
		 */
		showNotice: function (message, type) {
			const $notice = $('<div>')
				.addClass('notice notice-' + type + ' is-dismissible')
				.html('<p>' + message + '</p>')
				.hide();

			$('.wrap > h1').after($notice);
			$notice.fadeIn();

			// Auto dismiss after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(300, function () {
					$(this).remove();
				});
			}, 5000);

			// Make dismissible
			$notice.on('click', '.notice-dismiss', function () {
				$notice.fadeOut(300, function () {
					$(this).remove();
				});
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function () {
		LinkGuardianAdmin.init();
	});

})(jQuery);