/**
 * NowScrobbling Shortcode Builder
 *
 * Live preview shortcode builder for the admin dashboard.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

(function($) {
    'use strict';

    /**
     * Shortcode Builder
     */
    const ShortcodeBuilder = {

        $container: null,
        $shortcodeSelect: null,
        $attributesContainer: null,
        $preview: null,
        $output: null,
        debounceTimer: null,

        /**
         * Initialize.
         */
        init() {
            this.$container = $('.ns-shortcode-builder');
            if (!this.$container.length) return;

            this.$shortcodeSelect = this.$container.find('#ns-builder-shortcode');
            this.$attributesContainer = this.$container.find('.ns-builder-attributes');
            this.$preview = this.$container.find('.ns-builder-preview');
            this.$output = this.$container.find('.ns-builder-output');

            this.bindEvents();
            this.loadShortcode();
        },

        /**
         * Bind events.
         */
        bindEvents() {
            this.$shortcodeSelect.on('change', () => this.loadShortcode());
            this.$container.on('change input', '.ns-builder-attribute', () => this.updatePreview());
            this.$container.on('click', '.ns-copy-shortcode', (e) => this.copyShortcode(e));
        },

        /**
         * Load shortcode configuration.
         */
        loadShortcode() {
            const shortcode = this.$shortcodeSelect.val();
            if (!shortcode) {
                this.$attributesContainer.empty();
                this.$preview.empty();
                this.$output.val('');
                return;
            }

            // Get shortcode definition from data attribute.
            const definitions = this.$shortcodeSelect.data('definitions') || {};
            const definition = definitions[shortcode];

            if (!definition) return;

            this.renderAttributes(definition.attributes);
            this.updatePreview();
        },

        /**
         * Render attribute inputs.
         */
        renderAttributes(attributes) {
            let html = '';

            for (const [key, attr] of Object.entries(attributes)) {
                html += `<div class="ns-builder-field">`;
                html += `<label for="ns-attr-${key}">${attr.label || key}</label>`;

                switch (attr.type) {
                    case 'select':
                        html += `<select id="ns-attr-${key}" class="ns-builder-attribute" data-attribute="${key}">`;
                        for (const [value, label] of Object.entries(attr.options || {})) {
                            const selected = value === attr.default ? ' selected' : '';
                            html += `<option value="${value}"${selected}>${label}</option>`;
                        }
                        html += `</select>`;
                        break;

                    case 'checkbox':
                        const checked = attr.default ? ' checked' : '';
                        html += `<input type="checkbox" id="ns-attr-${key}" class="ns-builder-attribute" data-attribute="${key}"${checked}>`;
                        break;

                    case 'number':
                        html += `<input type="number" id="ns-attr-${key}" class="ns-builder-attribute" data-attribute="${key}"
                                 value="${attr.default || ''}" min="${attr.min || ''}" max="${attr.max || ''}">`;
                        break;

                    default:
                        html += `<input type="text" id="ns-attr-${key}" class="ns-builder-attribute" data-attribute="${key}"
                                 value="${attr.default || ''}" placeholder="${attr.placeholder || ''}">`;
                }

                if (attr.description) {
                    html += `<p class="description">${attr.description}</p>`;
                }

                html += `</div>`;
            }

            this.$attributesContainer.html(html);
        },

        /**
         * Update preview and output.
         */
        updatePreview() {
            // Debounce to avoid too many requests.
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.doUpdatePreview();
            }, 300);
        },

        /**
         * Perform the actual preview update.
         */
        doUpdatePreview() {
            const shortcode = this.$shortcodeSelect.val();
            if (!shortcode) return;

            // Collect attributes.
            const attributes = {};
            this.$container.find('.ns-builder-attribute').each(function() {
                const $input = $(this);
                const key = $input.data('attribute');
                let value;

                if ($input.is(':checkbox')) {
                    value = $input.is(':checked') ? 'true' : 'false';
                } else {
                    value = $input.val();
                }

                if (value && value !== 'false') {
                    attributes[key] = value;
                }
            });

            // Build shortcode string.
            let shortcodeStr = `[${shortcode}`;
            for (const [key, value] of Object.entries(attributes)) {
                shortcodeStr += ` ${key}="${value}"`;
            }
            shortcodeStr += `]`;

            this.$output.val(shortcodeStr);

            // Load preview via AJAX.
            this.loadPreview(shortcode, attributes);
        },

        /**
         * Load preview via AJAX.
         */
        loadPreview(shortcode, attributes) {
            this.$preview.addClass('ns-loading');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nowscrobbling_admin',
                    sub_action: 'preview_shortcode',
                    shortcode: shortcode,
                    attributes: JSON.stringify(attributes),
                    nonce: window.nowscrobblingAdmin?.nonce || ''
                },
                success: (response) => {
                    if (response.success && response.data.html) {
                        this.$preview.html(response.data.html);
                    } else {
                        this.$preview.html('<p class="ns-error">Preview not available</p>');
                    }
                },
                error: () => {
                    this.$preview.html('<p class="ns-error">Failed to load preview</p>');
                },
                complete: () => {
                    this.$preview.removeClass('ns-loading');
                }
            });
        },

        /**
         * Copy shortcode to clipboard.
         */
        copyShortcode(e) {
            e.preventDefault();
            const shortcode = this.$output.val();

            if (!shortcode) return;

            navigator.clipboard.writeText(shortcode).then(() => {
                const $button = $(e.currentTarget);
                const originalText = $button.text();
                $button.text('Copied!');
                setTimeout(() => $button.text(originalText), 2000);
            });
        }
    };

    // Initialize on document ready.
    $(document).ready(() => ShortcodeBuilder.init());

})(jQuery);
