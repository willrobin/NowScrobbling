/**
 * NowScrobbling Public JavaScript
 *
 * Handles AJAX polling and dynamic updates for shortcodes.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

(function() {
    'use strict';

    // Configuration from localized data.
    const config = window.nowscrobblingData || {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: '',
        debug: false,
        polling: {
            nowPlayingInterval: 20000,
            maxInterval: 300000,
            backoffMultiplier: 2.0
        }
    };

    /**
     * Logger utility.
     */
    const log = {
        debug: function(...args) {
            if (config.debug) {
                console.log('[NowScrobbling]', ...args);
            }
        },
        error: function(...args) {
            console.error('[NowScrobbling]', ...args);
        }
    };

    /**
     * Polling Manager
     * Handles adaptive polling with exponential backoff.
     */
    class PollingManager {
        constructor(element, options = {}) {
            this.element = element;
            this.shortcode = element.dataset.shortcode;
            this.attributes = JSON.parse(element.dataset.attributes || '{}');
            this.interval = options.interval || config.polling.nowPlayingInterval;
            this.maxInterval = options.maxInterval || config.polling.maxInterval;
            this.backoffMultiplier = options.backoffMultiplier || config.polling.backoffMultiplier;
            this.currentInterval = this.interval;
            this.timer = null;
            this.lastData = null;
            this.consecutiveErrors = 0;
            this.isPaused = false;
        }

        /**
         * Start polling.
         */
        start() {
            log.debug('Starting polling for', this.shortcode);
            this.poll();
        }

        /**
         * Stop polling.
         */
        stop() {
            if (this.timer) {
                clearTimeout(this.timer);
                this.timer = null;
            }
            log.debug('Stopped polling for', this.shortcode);
        }

        /**
         * Pause polling (e.g., when tab is not visible).
         */
        pause() {
            this.isPaused = true;
            this.stop();
        }

        /**
         * Resume polling.
         */
        resume() {
            this.isPaused = false;
            this.poll();
        }

        /**
         * Perform a single poll.
         */
        async poll() {
            if (this.isPaused) return;

            try {
                const response = await this.fetchData();

                if (response.success) {
                    this.handleSuccess(response.data);
                } else {
                    this.handleError(response.data);
                }
            } catch (error) {
                this.handleError(error);
            }

            // Schedule next poll.
            this.scheduleNext();
        }

        /**
         * Fetch data from server.
         */
        async fetchData() {
            const formData = new FormData();
            formData.append('action', 'nowscrobbling_render');
            formData.append('nonce', config.nonce);
            formData.append('shortcode', this.shortcode);
            formData.append('attributes', JSON.stringify(this.attributes));

            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        }

        /**
         * Handle successful response.
         */
        handleSuccess(data) {
            this.consecutiveErrors = 0;
            this.currentInterval = this.interval;

            // Check if data changed.
            const dataString = JSON.stringify(data);
            if (dataString !== this.lastData) {
                this.lastData = dataString;
                this.updateElement(data);
                log.debug('Updated', this.shortcode, 'with new data');
            } else {
                log.debug('No changes for', this.shortcode);
            }
        }

        /**
         * Handle error response.
         */
        handleError(error) {
            this.consecutiveErrors++;
            log.error('Polling error for', this.shortcode, error);

            // Exponential backoff.
            this.currentInterval = Math.min(
                this.currentInterval * this.backoffMultiplier,
                this.maxInterval
            );

            log.debug('Backing off to', this.currentInterval, 'ms');
        }

        /**
         * Update the DOM element with new data.
         */
        updateElement(data) {
            if (data.html) {
                // Preserve wrapper, update content.
                const wrapper = this.element.querySelector('.ns-shortcode');
                if (wrapper) {
                    wrapper.innerHTML = data.html;
                } else {
                    this.element.innerHTML = data.html;
                }

                // Trigger event for other scripts.
                this.element.dispatchEvent(new CustomEvent('nowscrobbling:updated', {
                    detail: data,
                    bubbles: true
                }));
            }
        }

        /**
         * Schedule next poll.
         */
        scheduleNext() {
            if (this.isPaused) return;

            this.timer = setTimeout(() => {
                this.poll();
            }, this.currentInterval);
        }
    }

    /**
     * Main application.
     */
    const NowScrobbling = {
        pollers: [],

        /**
         * Initialize the application.
         */
        init() {
            log.debug('Initializing NowScrobbling v1.4.0');

            // Find all pollable elements.
            this.initPollers();

            // Setup visibility handling.
            this.setupVisibilityHandler();

            log.debug('Initialized with', this.pollers.length, 'pollers');
        },

        /**
         * Initialize polling for all elements.
         */
        initPollers() {
            const elements = document.querySelectorAll('[data-ns-poll="true"]');

            elements.forEach(element => {
                const poller = new PollingManager(element);
                this.pollers.push(poller);
                poller.start();
            });
        },

        /**
         * Setup page visibility handler.
         */
        setupVisibilityHandler() {
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.pauseAll();
                } else {
                    this.resumeAll();
                }
            });
        },

        /**
         * Pause all pollers.
         */
        pauseAll() {
            log.debug('Pausing all pollers (page hidden)');
            this.pollers.forEach(p => p.pause());
        },

        /**
         * Resume all pollers.
         */
        resumeAll() {
            log.debug('Resuming all pollers (page visible)');
            this.pollers.forEach(p => p.resume());
        },

        /**
         * Manually refresh a specific element.
         */
        refresh(element) {
            const poller = this.pollers.find(p => p.element === element);
            if (poller) {
                poller.poll();
            }
        }
    };

    // Auto-initialize when DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NowScrobbling.init());
    } else {
        NowScrobbling.init();
    }

    // Expose to window for manual control.
    window.NowScrobbling = NowScrobbling;

})();
