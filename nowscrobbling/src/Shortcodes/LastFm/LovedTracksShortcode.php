<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\LastFm;

use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Last.fm Loved Tracks Shortcode
 *
 * Displays user's loved tracks.
 * Tag: nowscr_lastfm_lovedtracks
 *
 * @package NowScrobbling\Shortcodes\LastFm
 */
final class LovedTracksShortcode extends AbstractShortcode
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
        return 'nowscr_lastfm_lovedtracks';
    }

    protected function getDefaultAttributes(): array
    {
        return [
            'max_length' => 45,
            'limit' => (int) get_option('ns_lovedtracks_count', 5),
        ];
    }

    protected function getData(array $atts): mixed
    {
        $response = $this->client->getLovedTracks((int) $atts['limit']);

        if ($response->isError()) {
            return null;
        }

        return $response->data;
    }

    protected function formatOutput(mixed $data, array $atts): string
    {
        $tracks = $data['lovedtracks']['track'] ?? [];

        if (empty($tracks)) {
            return esc_html($this->getEmptyMessage());
        }

        $maxLength = (int) $atts['max_length'];
        $items = [];

        foreach (array_slice($tracks, 0, (int) $atts['limit']) as $track) {
            $artist = $track['artist']['name'] ?? '';
            $name = $track['name'] ?? '';
            $url = $track['url'] ?? '';

            $text = $this->truncate("$artist - $name", $maxLength);
            $items[] = $url !== ''
                ? $this->renderer->link($url, $text)
                : esc_html($text);
        }

        return $this->renderer->list($items);
    }

    protected function getEmptyMessage(): string
    {
        return __('No loved tracks found.', 'nowscrobbling');
    }
}
