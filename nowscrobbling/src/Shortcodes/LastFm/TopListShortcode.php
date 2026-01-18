<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\LastFm;

use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Last.fm Top List Shortcode
 *
 * Unified shortcode for top artists, albums, and tracks.
 * Tags: nowscr_lastfm_top_artists, nowscr_lastfm_top_albums, nowscr_lastfm_top_tracks
 *
 * @package NowScrobbling\Shortcodes\LastFm
 */
final class TopListShortcode extends AbstractShortcode
{
    private readonly LastFmClient $client;
    private readonly string $type;

    public function __construct(
        OutputRenderer $renderer,
        HashGenerator $hasher,
        LastFmClient $client,
        string $type
    ) {
        parent::__construct($renderer, $hasher);
        $this->client = $client;
        $this->type = $type;
    }

    public function getTag(): string
    {
        return "nowscr_lastfm_top_{$this->type}";
    }

    protected function getDefaultAttributes(): array
    {
        $limitOption = match ($this->type) {
            'artists' => 'ns_top_artists_count',
            'albums' => 'ns_top_albums_count',
            'tracks' => 'ns_top_tracks_count',
            default => 'ns_top_artists_count',
        };

        return [
            'max_length' => 45,
            'limit' => (int) get_option($limitOption, 5),
            'period' => '7day',
        ];
    }

    protected function getData(array $atts): mixed
    {
        $response = match ($this->type) {
            'artists' => $this->client->getTopArtists($atts['period'], (int) $atts['limit']),
            'albums' => $this->client->getTopAlbums($atts['period'], (int) $atts['limit']),
            'tracks' => $this->client->getTopTracks($atts['period'], (int) $atts['limit']),
            default => null,
        };

        if ($response === null || $response->isError()) {
            return null;
        }

        return $response->data;
    }

    protected function formatOutput(mixed $data, array $atts): string
    {
        $singular = rtrim($this->type, 's');
        $key = "top{$this->type}";

        $items = $data[$key][$singular] ?? [];

        if (empty($items)) {
            return esc_html($this->getEmptyMessage());
        }

        $maxLength = (int) $atts['max_length'];
        $formatted = [];

        foreach (array_slice($items, 0, (int) $atts['limit']) as $item) {
            $text = $this->formatItem($item, $maxLength);
            $url = $item['url'] ?? '';

            $formatted[] = $url !== ''
                ? $this->renderer->link($url, $text)
                : esc_html($text);
        }

        return $this->renderer->list($formatted);
    }

    private function formatItem(array $item, int $maxLength): string
    {
        return match ($this->type) {
            'artists' => $this->truncate($item['name'] ?? '', $maxLength),
            'albums' => $this->truncate(
                ($item['artist']['name'] ?? '') . ' - ' . ($item['name'] ?? ''),
                $maxLength
            ),
            'tracks' => $this->truncate(
                ($item['artist']['name'] ?? '') . ' - ' . ($item['name'] ?? ''),
                $maxLength
            ),
            default => $item['name'] ?? '',
        };
    }

    protected function getEmptyMessage(): string
    {
        return match ($this->type) {
            'artists' => __('No top artists found.', 'nowscrobbling'),
            'albums' => __('No top albums found.', 'nowscrobbling'),
            'tracks' => __('No top tracks found.', 'nowscrobbling'),
            default => __('No data found.', 'nowscrobbling'),
        };
    }
}
