/**
 * NowScrobbling Admin JavaScript
 *
 * Handles:
 * - API connection testing
 * - Cache clearing
 * - UI interactions
 *
 * Uses admin-ajax.php as primary method (works when REST API is disabled)
 * Falls back to REST API if available
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
        #ajaxUrl;

        /**
         * @type {string}
         */
        #restUrl;

        /**
         * @type {Object<string, string>}
         */
        #nonces;

        /**
         * @param {Object} config
         */
        constructor(config) {
            this.#ajaxUrl = config.ajaxUrl;
            this.#restUrl = config.restUrl;
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
                        resultSpan.classList.add(result.status === 'success' ? 'ns-success' : 'ns-error');

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
                    if (resultSpan) {
                        resultSpan.textContent = '';
                        resultSpan.className = 'ns-cache-result';
                    }

                    try {
                        const result = await this.#clearCache(type);

                        if (resultSpan) {
                            resultSpan.textContent = result.message;
                            resultSpan.classList.add(result.success ? 'ns-success' : 'ns-error');
                        }

                    } catch (error) {
                        if (resultSpan) {
                            resultSpan.textContent = error.message || 'Failed to clear cache';
                            resultSpan.classList.add('ns-error');
                        }
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
         * Test API connection using admin-ajax.php
         * @param {string} service
         * @returns {Promise<Object>}
         */
        async #testConnection(service) {
            const formData = new FormData();
            formData.append('action', 'nowscrobbling_test_api');
            formData.append('service', service);
            formData.append('nonce', this.#nonces.apiTest || '');

            const response = await fetch(this.#ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data?.message || 'Connection failed');
            }

            return data.data;
        }

        /**
         * Clear cache using admin-ajax.php
         * @param {string} type
         * @returns {Promise<Object>}
         */
        async #clearCache(type) {
            const formData = new FormData();
            formData.append('action', 'nowscrobbling_clear_cache');
            formData.append('type', type || 'all');
            formData.append('nonce', this.#nonces.cacheClear || '');

            const response = await fetch(this.#ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data?.message || 'Failed to clear cache');
            }

            return {
                success: true,
                message: data.data?.message || 'Cache cleared'
            };
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
