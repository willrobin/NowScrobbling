/**
 * NowScrobbling v2.0 - Vanilla JavaScript Client
 *
 * Features:
 * - IntersectionObserver for lazy loading
 * - Hash-based content diffing (avoids unnecessary DOM updates)
 * - Exponential backoff polling
 * - Visibility API integration (pauses when tab is hidden)
 * - Smooth height transitions on content updates
 * - Focus management for accessibility
 *
 * @package NowScrobbling
 */

(function () {
    'use strict';

    /**
     * Main NowScrobbling class
     */
    class NowScrobbling {
        /**
         * @type {string}
         */
        #apiBase;

        /**
         * @type {Object<string, string>}
         */
        #nonces;

        /**
         * @type {Object}
         */
        #polling;

        /**
         * @type {IntersectionObserver|null}
         */
        #observer = null;

        /**
         * @type {Map<HTMLElement, Object>}
         */
        #elements = new Map();

        /**
         * @type {boolean}
         */
        #debug;

        /**
         * @param {Object} config Configuration object
         * @param {string} config.restUrl REST API base URL
         * @param {Object} config.nonces Nonce tokens
         * @param {Object} config.polling Polling configuration
         * @param {boolean} config.debug Debug mode
         */
        constructor(config) {
            this.#apiBase = config.restUrl;
            this.#nonces = config.nonces || {};
            this.#debug = config.debug || false;
            this.#polling = {
                baseInterval: 30000,
                maxInterval: 300000,
                backoffMultiplier: 2,
                ...config.polling
            };
        }

        /**
         * Initialize the client
         */
        init() {
            this.#log('Initializing NowScrobbling v2.0');

            // Set up IntersectionObserver for lazy loading
            this.#setupObserver();

            // Observe all shortcode elements
            document.querySelectorAll('[data-nowscrobbling-shortcode]').forEach(el => {
                this.#observer.observe(el);
            });

            // Handle visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.#log('Tab became visible, refreshing all');
                    this.refreshAll();
                }
            });

            this.#log(`Found ${this.#elements.size} shortcode elements`);
        }

        /**
         * Set up the IntersectionObserver
         */
        #setupObserver() {
            this.#observer = new IntersectionObserver(
                (entries) => this.#onIntersect(entries),
                {
                    rootMargin: '200px 0px', // Start loading 200px before visible
                    threshold: 0
                }
            );
        }

        /**
         * Handle intersection events
         * @param {IntersectionObserverEntry[]} entries
         */
        #onIntersect(entries) {
            entries.forEach(entry => {
                const el = entry.target;

                if (entry.isIntersecting) {
                    // Element is visible, start tracking and polling
                    if (!this.#elements.has(el)) {
                        this.#initElement(el);
                    }
                }
            });
        }

        /**
         * Initialize an element for polling
         * @param {HTMLElement} el
         */
        #initElement(el) {
            const shortcode = el.dataset.nowscrobblingShortcode;
            const isNowPlaying = el.dataset.nsNowplaying === '1';

            this.#elements.set(el, {
                shortcode,
                isNowPlaying,
                interval: this.#polling.baseInterval,
                fails: 0,
                timerId: null
            });

            // Start polling if it's a now-playing indicator
            if (isNowPlaying) {
                this.#startPolling(el);
            }

            this.#log(`Initialized element: ${shortcode}`);
        }

        /**
         * Start polling for an element
         * @param {HTMLElement} el
         */
        #startPolling(el) {
            const data = this.#elements.get(el);
            if (!data) return;

            const poll = async () => {
                // Stop if element removed from DOM
                if (!document.body.contains(el)) {
                    this.#log(`Element removed, stopping poll: ${data.shortcode}`);
                    return;
                }

                // Pause if tab is hidden
                if (document.visibilityState !== 'visible') {
                    this.#log(`Tab hidden, pausing poll: ${data.shortcode}`);
                    data.timerId = setTimeout(poll, data.interval);
                    return;
                }

                try {
                    const result = await this.#fetch(data.shortcode, el.dataset.nsHash);

                    if (!result.unchanged) {
                        this.#updateElement(el, result);
                    }

                    // Reset on success
                    data.fails = 0;
                    data.interval = this.#polling.baseInterval;

                } catch (error) {
                    this.#log(`Poll error: ${error.message}`);
                    data.fails++;
                    // Exponential backoff on failure
                    data.interval = Math.min(
                        data.interval * this.#polling.backoffMultiplier,
                        this.#polling.maxInterval
                    );
                }

                // Continue polling for now-playing elements
                if (data.isNowPlaying || el.dataset.nsNowplaying === '1') {
                    data.timerId = setTimeout(poll, data.interval);
                }
            };

            // Start with initial delay
            data.timerId = setTimeout(poll, data.interval);
        }

        /**
         * Fetch shortcode content from REST API
         * @param {string} shortcode
         * @param {string|null} hash
         * @returns {Promise<Object>}
         */
        async #fetch(shortcode, hash = null) {
            const url = new URL(
                `${this.#apiBase}/render/${shortcode}`,
                window.location.origin
            );

            if (hash) {
                url.searchParams.set('hash', hash);
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.#nonces.render || ''
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        }

        /**
         * Update an element with new content
         * @param {HTMLElement} el
         * @param {Object} data
         */
        #updateElement(el, data) {
            // Check if hash actually changed
            if (data.hash === el.dataset.nsHash) {
                this.#log('Hash unchanged, skipping update');
                return;
            }

            // Preserve focus state
            const hadFocus = el.contains(document.activeElement);
            const focusedIndex = this.#getFocusedLinkIndex(el);

            // Store current height for smooth transition
            const oldHeight = el.offsetHeight;

            // Update content
            const newContent = this.#extractContent(data.html);
            el.innerHTML = newContent;
            el.dataset.nsHash = data.hash;

            // Update now-playing state
            const isNowPlaying = data.html.includes('data-ns-nowplaying="1"');
            if (isNowPlaying) {
                el.dataset.nsNowplaying = '1';
                el.classList.add('ns-nowplaying');
            } else {
                delete el.dataset.nsNowplaying;
                el.classList.remove('ns-nowplaying');
            }

            // Smooth height transition
            this.#animateHeight(el, oldHeight);

            // Restore focus
            if (hadFocus) {
                this.#restoreFocus(el, focusedIndex);
            }

            // Announce to screen readers
            this.#announceUpdate(el);

            this.#log('Updated element content');
        }

        /**
         * Extract inner content from response HTML
         * @param {string} html
         * @returns {string}
         */
        #extractContent(html) {
            // Create a temporary element to parse the HTML
            const temp = document.createElement('div');
            temp.innerHTML = html;

            // Get the first child (the wrapper span)
            const wrapper = temp.querySelector('[data-nowscrobbling-shortcode]');
            return wrapper ? wrapper.innerHTML : html;
        }

        /**
         * Animate height transition
         * @param {HTMLElement} el
         * @param {number} oldHeight
         */
        #animateHeight(el, oldHeight) {
            // Check for reduced motion preference
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            // Set explicit height for transition
            el.style.height = `${oldHeight}px`;
            el.style.overflow = 'hidden';
            el.style.transition = 'height 250ms ease-out';

            // Trigger reflow
            el.offsetHeight; // eslint-disable-line no-unused-expressions

            // Animate to new height
            requestAnimationFrame(() => {
                el.style.height = `${el.scrollHeight}px`;

                // Clean up after transition
                const cleanup = () => {
                    el.style.height = '';
                    el.style.overflow = '';
                    el.style.transition = '';
                    el.removeEventListener('transitionend', cleanup);
                };

                el.addEventListener('transitionend', cleanup, { once: true });

                // Fallback cleanup
                setTimeout(cleanup, 300);
            });
        }

        /**
         * Get the index of the focused link
         * @param {HTMLElement} el
         * @returns {number}
         */
        #getFocusedLinkIndex(el) {
            const links = el.querySelectorAll('a');
            const focused = document.activeElement;

            for (let i = 0; i < links.length; i++) {
                if (links[i] === focused) {
                    return i;
                }
            }

            return -1;
        }

        /**
         * Restore focus after content update
         * @param {HTMLElement} el
         * @param {number} index
         */
        #restoreFocus(el, index) {
            if (index < 0) return;

            const links = el.querySelectorAll('a');
            const targetLink = links[index] || links[0];

            if (targetLink) {
                targetLink.focus();
            }
        }

        /**
         * Announce update to screen readers
         * @param {HTMLElement} el
         */
        #announceUpdate(el) {
            // Only announce for now-playing elements
            if (el.dataset.nsNowplaying !== '1') return;

            // Set aria-live if not already set
            if (!el.hasAttribute('aria-live')) {
                el.setAttribute('aria-live', 'polite');
                el.setAttribute('role', 'status');
            }
        }

        /**
         * Refresh all visible elements
         */
        async refreshAll() {
            const promises = [];

            this.#elements.forEach((data, el) => {
                if (document.body.contains(el)) {
                    promises.push(
                        this.#fetch(data.shortcode, el.dataset.nsHash)
                            .then(result => {
                                if (!result.unchanged) {
                                    this.#updateElement(el, result);
                                }
                            })
                            .catch(error => this.#log(`Refresh error: ${error.message}`))
                    );
                }
            });

            await Promise.allSettled(promises);
        }

        /**
         * Manually refresh a specific shortcode
         * @param {string} shortcode
         */
        async refresh(shortcode) {
            for (const [el, data] of this.#elements) {
                if (data.shortcode === shortcode && document.body.contains(el)) {
                    try {
                        const result = await this.#fetch(shortcode, el.dataset.nsHash);
                        if (!result.unchanged) {
                            this.#updateElement(el, result);
                        }
                    } catch (error) {
                        this.#log(`Refresh error for ${shortcode}: ${error.message}`);
                    }
                }
            }
        }

        /**
         * Log debug message
         * @param {string} message
         */
        #log(message) {
            if (this.#debug) {
                console.log(`[NowScrobbling] ${message}`);
            }
        }

        /**
         * Destroy the client and clean up
         */
        destroy() {
            // Stop all polling
            this.#elements.forEach((data) => {
                if (data.timerId) {
                    clearTimeout(data.timerId);
                }
            });

            // Disconnect observer
            if (this.#observer) {
                this.#observer.disconnect();
            }

            // Clear elements
            this.#elements.clear();

            this.#log('Destroyed NowScrobbling client');
        }
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        if (typeof nowscrobblingConfig !== 'undefined') {
            window.nowscrobbling = new NowScrobbling(nowscrobblingConfig);
            window.nowscrobbling.init();
        }
    }

    // Export for manual usage
    window.NowScrobbling = NowScrobbling;

})();
