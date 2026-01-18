<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes;

use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Abstract Shortcode Base Class
 *
 * Eliminates ~40% code duplication from v1.3.
 * All shortcodes extend this class.
 *
 * @package NowScrobbling\Shortcodes
 */
abstract class AbstractShortcode
{
    protected readonly OutputRenderer $renderer;
    protected readonly HashGenerator $hasher;

    /**
     * Constructor
     */
    public function __construct(OutputRenderer $renderer, HashGenerator $hasher)
    {
        $this->renderer = $renderer;
        $this->hasher = $hasher;
    }

    /**
     * Get the shortcode tag name
     */
    abstract public function getTag(): string;

    /**
     * Get data for the shortcode
     *
     * @param array<string, mixed> $atts Shortcode attributes
     *
     * @return mixed The data to render
     */
    abstract protected function getData(array $atts): mixed;

    /**
     * Format the output HTML
     *
     * @param mixed                $data The data to format
     * @param array<string, mixed> $atts Shortcode attributes
     */
    abstract protected function formatOutput(mixed $data, array $atts): string;

    /**
     * Get the empty state message
     */
    abstract protected function getEmptyMessage(): string;

    /**
     * Register the shortcode with WordPress
     */
    public function register(): void
    {
        add_shortcode($this->getTag(), $this->render(...));
    }

    /**
     * Render the shortcode
     *
     * @param array<string, mixed>|string $atts Shortcode attributes
     *
     * @return string Rendered HTML
     */
    public function render(array|string $atts = []): string
    {
        // Ensure $atts is an array
        if (!is_array($atts)) {
            $atts = [];
        }

        $atts = shortcode_atts($this->getDefaultAttributes(), $atts, $this->getTag());

        try {
            $data = $this->getData($atts);

            if ($this->isEmpty($data)) {
                return $this->renderEmpty($atts);
            }

            $html = $this->formatOutput($data, $atts);
            $hash = $this->hasher->generate($data);
            $isNowPlaying = $this->isNowPlaying($data);

            return $this->renderer->wrap(
                shortcode: $this->getTag(),
                html: $html,
                hash: $hash,
                nowPlaying: $isNowPlaying,
                attrs: $atts
            );
        } catch (\Throwable $e) {
            /**
             * Fires when a shortcode encounters an error
             *
             * @param \Throwable $e   The exception
             * @param string     $tag The shortcode tag
             */
            do_action('nowscrobbling_shortcode_error', $e, $this->getTag());

            return $this->renderEmpty($atts);
        }
    }

    /**
     * Get default shortcode attributes
     *
     * @return array<string, mixed>
     */
    protected function getDefaultAttributes(): array
    {
        return [
            'max_length' => 45,
            'style' => '', // Empty = use global setting
        ];
    }

    /**
     * Check if data is empty
     *
     * @param mixed $data The data to check
     */
    protected function isEmpty(mixed $data): bool
    {
        if ($data === null) {
            return true;
        }

        if (is_array($data)) {
            // Check for error marker
            if (isset($data['__ns_error'])) {
                return true;
            }

            return empty($data);
        }

        return false;
    }

    /**
     * Check if this represents a "now playing" state
     *
     * @param mixed $data The data to check
     */
    protected function isNowPlaying(mixed $data): bool
    {
        return false;
    }

    /**
     * Render the empty state
     *
     * @param array<string, mixed> $atts Shortcode attributes
     */
    protected function renderEmpty(array $atts): string
    {
        $message = $this->getEmptyMessage();

        return $this->renderer->wrap(
            shortcode: $this->getTag(),
            html: esc_html($message),
            hash: md5($message),
            nowPlaying: false,
            attrs: $atts
        );
    }

    /**
     * Truncate text to max length
     *
     * @param string $text      Text to truncate
     * @param int    $maxLength Maximum length
     */
    protected function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1) . '…';
    }
}
