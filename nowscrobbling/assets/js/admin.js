/**
 * NowScrobbling Admin JavaScript
 *
 * Handles:
 * - API connection testing
 * - Cache clearing
 * - UI interactions
 *
 * @package NowScrobbling
 */

(function () {
    'use strict';

    /**
     * Admin controller class
     */
    class NowScrobblingAdmin {
        /**
         * @type {string}
         */
        #apiBase;

        /**
         * @type {Object<string, string>}
         */
        #nonces;

        /**
         * @param {Object} config
         */
        constructor(config) {
            this.#apiBase = config.restUrl;
            this.#nonces = config.nonces || {};
        }

        /**
         * Initialize the admin controller
         */
        init() {
            this.#bindTestButtons();
            this.#bindCacheButtons();
            this.#initTabs();
        }

        /**
         * Bind test connection button handlers
         */
        #bindTestButtons() {
            document.querySelectorAll('.ns-test-connection').forEach(button => {
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const service = button.dataset.service;
                    const resultSpan = document.querySelector(`.ns-test-result[data-service="${service}"]`);

                    button.disabled = true;
                    button.classList.add('is-loading');
                    resultSpan.textContent = '';
                    resultSpan.className = 'ns-test-result';

                    try {
                        const result = await this.#testConnection(service);

                        resultSpan.textContent = result.message;
                        resultSpan.classList.add(result.success ? 'ns-success' : 'ns-error');

                    } catch (error) {
                        resultSpan.textContent = error.message || 'Connection failed';
                        resultSpan.classList.add('ns-error');
                    } finally {
                        button.disabled = false;
                        button.classList.remove('is-loading');
                    }
                });
            });
        }

        /**
         * Bind cache clear button handlers
         */
        #bindCacheButtons() {
            document.querySelectorAll('.ns-clear-cache').forEach(button => {
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const type = button.dataset.type;
                    const resultSpan = document.querySelector('.ns-cache-result');

                    button.disabled = true;
                    button.classList.add('is-loading');
                    resultSpan.textContent = '';
                    resultSpan.className = 'ns-cache-result';

                    try {
                        const result = await this.#clearCache(type);

                        resultSpan.textContent = result.message;
                        resultSpan.classList.add(result.success ? 'ns-success' : 'ns-error');

                    } catch (error) {
                        resultSpan.textContent = error.message || 'Failed to clear cache';
                        resultSpan.classList.add('ns-error');
                    } finally {
                        button.disabled = false;
                        button.classList.remove('is-loading');
                    }
                });
            });
        }

        /**
         * Initialize tab behavior (keyboard navigation)
         */
        #initTabs() {
            const tabWrapper = document.querySelector('.nav-tab-wrapper');
            if (!tabWrapper) return;

            const tabs = tabWrapper.querySelectorAll('.nav-tab');

            tabs.forEach((tab, index) => {
                tab.addEventListener('keydown', (e) => {
                    let targetIndex = null;

                    switch (e.key) {
                        case 'ArrowLeft':
                            targetIndex = index > 0 ? index - 1 : tabs.length - 1;
                            break;
                        case 'ArrowRight':
                            targetIndex = index < tabs.length - 1 ? index + 1 : 0;
                            break;
                        case 'Home':
                            targetIndex = 0;
                            break;
                        case 'End':
                            targetIndex = tabs.length - 1;
                            break;
                    }

                    if (targetIndex !== null) {
                        e.preventDefault();
                        tabs[targetIndex].focus();
                    }
                });
            });
        }

        /**
         * Test API connection
         * @param {string} service
         * @returns {Promise<Object>}
         */
        async #testConnection(service) {
            const response = await fetch(`${this.#apiBase}/test/${service}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.#nonces.apiTest || ''
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        }

        /**
         * Clear cache
         * @param {string} type
         * @returns {Promise<Object>}
         */
        async #clearCache(type) {
            const response = await fetch(`${this.#apiBase}/cache/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.#nonces.cacheClear || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({ type })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        }
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        if (typeof nowscrobblingAdmin !== 'undefined') {
            window.nowscrobblingAdminController = new NowScrobblingAdmin(nowscrobblingAdmin);
            window.nowscrobblingAdminController.init();
        }
    }

})();
