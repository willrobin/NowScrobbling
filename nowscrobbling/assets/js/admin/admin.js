/**
 * NowScrobbling Admin JavaScript
 *
 * Handles admin dashboard functionality.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

(function($) {
    'use strict';

    const config = window.nowscrobblingAdmin || {
        ajaxUrl: ajaxurl,
        nonce: '',
        i18n: {}
    };

    /**
     * Admin Dashboard
     */
    const AdminDashboard = {

        /**
         * Initialize.
         */
        init() {
            this.bindEvents();
            this.initTabs();
        },

        /**
         * Bind event handlers.
         */
        bindEvents() {
            // Clear cache button.
            $(document).on('click', '.ns-clear-cache', this.handleClearCache.bind(this));

            // Test connection buttons.
            $(document).on('click', '.ns-test-connection', this.handleTestConnection.bind(this));

            // Copy to clipboard.
            $(document).on('click', '.ns-copy', this.handleCopy.bind(this));
        },

        /**
         * Initialize tab navigation.
         */
        initTabs() {
            const $tabs = $('.ns-admin-tabs');
            if (!$tabs.length) return;

            $tabs.on('click', '.ns-tab', function(e) {
                e.preventDefault();
                const $tab = $(this);
                const target = $tab.data('tab');

                // Update tab states.
                $tabs.find('.ns-tab').removeClass('ns-tab--active');
                $tab.addClass('ns-tab--active');

                // Update panel states.
                $('.ns-tab-panel').removeClass('ns-tab-panel--active');
                $(`#ns-panel-${target}`).addClass('ns-tab-panel--active');

                // Update URL hash.
                history.replaceState(null, null, `#${target}`);
            });

            // Activate tab from URL hash.
            const hash = window.location.hash.substring(1);
            if (hash) {
                $tabs.find(`[data-tab="${hash}"]`).trigger('click');
            }
        },

        /**
         * Handle clear cache button.
         */
        handleClearCache(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);

            if (!confirm(config.i18n.confirmClearCache || 'Clear all caches?')) {
                return;
            }

            $button.prop('disabled', true).addClass('ns-loading');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nowscrobbling_admin',
                    sub_action: 'clear_cache',
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', config.i18n.cacheCleared || 'Cache cleared.');
                    } else {
                        this.showNotice('error', response.data || config.i18n.error);
                    }
                },
                error: () => {
                    this.showNotice('error', config.i18n.error || 'An error occurred.');
                },
                complete: () => {
                    $button.prop('disabled', false).removeClass('ns-loading');
                }
            });
        },

        /**
         * Handle test connection button.
         */
        handleTestConnection(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const provider = $button.data('provider');
            const $status = $button.siblings('.ns-connection-status');

            $button.prop('disabled', true).addClass('ns-loading');
            $status.removeClass('ns-status--success ns-status--error').text('Testing...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nowscrobbling_admin',
                    sub_action: 'test_connection',
                    provider: provider,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $status.addClass('ns-status--success').text(response.data.message || 'Connected');
                    } else {
                        $status.addClass('ns-status--error').text(response.data || 'Connection failed');
                    }
                },
                error: () => {
                    $status.addClass('ns-status--error').text('Request failed');
                },
                complete: () => {
                    $button.prop('disabled', false).removeClass('ns-loading');
                }
            });
        },

        /**
         * Handle copy to clipboard.
         */
        handleCopy(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const text = $button.data('copy') || $button.siblings('input, textarea').val();

            navigator.clipboard.writeText(text).then(() => {
                const originalText = $button.text();
                $button.text(config.i18n.copied || 'Copied!');
                setTimeout(() => $button.text(originalText), 2000);
            });
        },

        /**
         * Show admin notice.
         */
        showNotice(type, message) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible ns-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss</span>
                    </button>
                </div>
            `);

            $('.ns-admin-wrap').prepend($notice);

            // Auto-dismiss after 5 seconds.
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Manual dismiss.
            $notice.on('click', '.notice-dismiss', () => {
                $notice.fadeOut(() => $notice.remove());
            });
        }
    };

    // Initialize on document ready.
    $(document).ready(() => AdminDashboard.init());

})(jQuery);
