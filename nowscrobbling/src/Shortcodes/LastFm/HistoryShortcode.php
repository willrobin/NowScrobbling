<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\LastFm;

use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Last.fm History Shortcode
 *
 * Displays recent scrobble history.
 * Tag: nowscr_lastfm_history
 *
 * @package NowScrobbling\Shortcodes\LastFm
 */
final class HistoryShortcode extends AbstractShortcode
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
        return 'nowscr_lastfm_history';
    }

    protected function getDefaultAttributes(): array
    {
        return [
            'max_length' => 45,
            'limit' => (int) get_option('ns_lastfm_activity_limit', 5),
        ];
    }

    protected function getData(array $atts): mixed
    {
        $response = $this->client->getRecentTracks((int) $atts['limit']);

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

        // Ensure it's an array of tracks
        if (isset($tracks['name'])) {
            $tracks = [$tracks];
        }

        $maxLength = (int) $atts['max_length'];
        $items = [];

        foreach ($tracks as $track) {
            $artist = $track['artist']['#text'] ?? $track['artist'] ?? '';
            $name = $track['name'] ?? '';
            $url = $track['url'] ?? '';

            $text = $this->truncate("$artist - $name", $maxLength);
            $items[] = $this->renderer->link($url, $text);
        }

        return $this->renderer->list($items);
    }

    protected function getEmptyMessage(): string
    {
        return __('No recent tracks.', 'nowscrobbling');
    }
}
