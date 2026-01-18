<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\Renderer;

/**
 * Output Renderer
 *
 * Wraps shortcode output with data attributes for AJAX refresh.
 *
 * @package NowScrobbling\Shortcodes\Renderer
 */
final class OutputRenderer
{
    /**
     * Wrap HTML content with shortcode container
     *
     * @param string               $shortcode  Shortcode tag name
     * @param string               $html       HTML content
     * @param string               $hash       Content hash for diffing
     * @param bool                 $nowPlaying Whether this is a now-playing state
     * @param array<string, mixed> $attrs      Shortcode attributes
     * @param string               $tag        HTML tag to use
     */
    public function wrap(
        string $shortcode,
        string $html,
        string $hash,
        bool $nowPlaying = false,
        array $attrs = [],
        string $tag = 'span'
    ): string {
        $classes = ['nowscrobbling', 'ns-' . $this->slugify($shortcode)];

        if ($nowPlaying) {
            $classes[] = 'ns-nowplaying';
        }

        $attrsJson = wp_json_encode($attrs);

        return sprintf(
            '<%1$s class="%2$s" data-nowscrobbling-shortcode="%3$s" data-ns-hash="%4$s"%5$s data-ns-attrs="%6$s">%7$s</%1$s>',
            esc_attr($tag),
            esc_attr(implode(' ', $classes)),
            esc_attr($shortcode),
            esc_attr($hash),
            $nowPlaying ? ' data-ns-nowplaying="1"' : '',
            esc_attr($attrsJson ?: '{}'),
            $html
        );
    }

    /**
     * Render a link element (respects ns_show_links setting)
     *
     * @param string      $url   URL to link to
     * @param string      $text  Link text
     * @param string      $class Optional CSS class
     * @param string|null $title Optional title attribute for hover text
     */
    public function link(string $url, string $text, string $class = '', ?string $title = null): string
    {
        // Check if links are disabled
        if (!$this->shouldShowLinks()) {
            return esc_html($text);
        }

        $classAttr = $class !== '' ? sprintf(' class="%s"', esc_attr($class)) : '';
        $titleAttr = $title !== null ? sprintf(' title="%s"', esc_attr($title)) : '';

        return sprintf(
            '<a href="%s"%s%s target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($url),
            $classAttr,
            $titleAttr,
            esc_html($text)
        );
    }

    /**
     * Check if links should be shown
     */
    private function shouldShowLinks(): bool
    {
        return (bool) get_option('ns_show_links', true);
    }

    /**
     * Render an indicator element using the configured style
     *
     * @param string      $text       Text content
     * @param string|null $url        Optional URL
     * @param bool        $nowPlaying Whether this is now-playing
     * @param string|null $title      Optional title attribute for hover text
     * @param string|null $style      Style override ('inline' or 'bubble', null = use setting)
     */
    public function indicator(
        string $text,
        ?string $url = null,
        bool $nowPlaying = false,
        ?string $title = null,
        ?string $style = null
    ): string {
        $activeStyle = $style ?? $this->getDisplayStyle();

        if ($activeStyle === 'bubble') {
            return $this->bubble($text, $url, $nowPlaying, $title);
        }

        return $this->inline($text, $url, $nowPlaying, $title);
    }

    /**
     * Get the configured display style
     */
    private function getDisplayStyle(): string
    {
        $style = get_option('ns_display_style', 'inline');

        return in_array($style, ['inline', 'bubble'], true) ? $style : 'inline';
    }

    /**
     * Render an inline-style element (like normal text/links)
     *
     * @param string      $text       Text content
     * @param string|null $url        Optional URL
     * @param bool        $nowPlaying Whether this is now-playing
     * @param string|null $title      Optional title attribute for hover text
     */
    public function inline(string $text, ?string $url = null, bool $nowPlaying = false, ?string $title = null): string
    {
        $content = esc_html($text);

        // Add now-playing indicator
        if ($nowPlaying) {
            $content = '<span class="ns-inline-nowplaying">' . $content . '</span>';
        }

        if ($url !== null && $url !== '' && $this->shouldShowLinks()) {
            $titleAttr = $title !== null ? sprintf(' title="%s"', esc_attr($title)) : '';
            $class = $nowPlaying ? 'ns-inline-link ns-inline-nowplaying' : 'ns-inline-link';

            return sprintf(
                '<a href="%s" class="%s"%s target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url($url),
                esc_attr($class),
                $titleAttr,
                esc_html($text)
            );
        }

        return $content;
    }

    /**
     * Render a bubble-style element (respects ns_show_links setting)
     *
     * @param string      $text       Text content
     * @param string|null $url        Optional URL
     * @param bool        $nowPlaying Whether this is now-playing
     * @param string|null $title      Optional title attribute for hover text
     */
    public function bubble(string $text, ?string $url = null, bool $nowPlaying = false, ?string $title = null): string
    {
        $class = 'bubble';
        if ($nowPlaying) {
            $class .= ' bubble-nowplaying';
        }

        $content = esc_html($text);

        if ($url !== null && $url !== '' && $this->shouldShowLinks()) {
            $titleAttr = $title !== null ? sprintf(' title="%s"', esc_attr($title)) : '';

            return sprintf(
                '<a href="%s" class="%s"%s target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url($url),
                esc_attr($class),
                $titleAttr,
                $content
            );
        }

        return sprintf(
            '<span class="%s">%s</span>',
            esc_attr($class),
            $content
        );
    }

    /**
     * Render a list of items
     *
     * @param array<string> $items List items
     * @param string        $separator Item separator
     */
    public function list(array $items, string $separator = ', '): string
    {
        return implode($separator, $items);
    }

    /**
     * Convert shortcode name to CSS slug
     *
     * @param string $shortcode Shortcode tag name
     */
    private function slugify(string $shortcode): string
    {
        return str_replace('_', '-', $shortcode);
    }
}
