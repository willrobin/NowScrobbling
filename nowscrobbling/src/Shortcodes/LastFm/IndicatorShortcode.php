<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\LastFm;

use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Last.fm Indicator Shortcode
 *
 * Displays the currently playing or last played track.
 * Tag: nowscr_lastfm_indicator
 *
 * @package NowScrobbling\Shortcodes\LastFm
 */
final class IndicatorShortcode extends AbstractShortcode
{
    private readonly LastFmClient $client;

    public function __construct(
        OutputRenderer $renderer,
        HashGenerator $hasher,
        LastFmClient $client
    ) {
        parent::__construct($renderer, $hasher);
        $this->client = $client;
    }

    public function getTag(): string
    {
        return 'nowscr_lastfm_indicator';
    }

    protected function getData(array $atts): mixed
    {
        $response = $this->client->getRecentTracks(1);

        if ($response->isError()) {
            return null;
        }

        return $response->data;
    }

    protected function formatOutput(mixed $data, array $atts): string
    {
        $tracks = $data['recenttracks']['track'] ?? [];

        if (empty($tracks)) {
            return esc_html($this->getEmptyMessage());
        }

        // Handle both single track and array of tracks
        $track = isset($tracks['name']) ? $tracks : ($tracks[0] ?? null);

        if ($track === null) {
            return esc_html($this->getEmptyMessage());
        }

        $artist = $track['artist']['#text'] ?? $track['artist'] ?? '';
        $name = $track['name'] ?? '';
        $url = $track['url'] ?? '';

        $text = $this->truncate("$artist - $name", (int) $atts['max_length']);

        /* translators: %1$s: artist name, %2$s: track name */
        $title = sprintf(__('%1$s – %2$s on Last.fm', 'nowscrobbling'), $artist, $name);

        // Use style attribute if set, otherwise indicator() uses global setting
        $style = !empty($atts['style']) ? $atts['style'] : null;

        return $this->renderer->indicator($text, $url, $this->isNowPlaying($data), $title, $style);
    }

    protected function getEmptyMessage(): string
    {
        return __('No recent tracks.', 'nowscrobbling');
    }

    protected function isNowPlaying(mixed $data): bool
    {
        return $this->client->isNowPlaying($data);
    }
}
