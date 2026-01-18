<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\Trakt;

use NowScrobbling\Api\TraktClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Trakt Indicator Shortcode
 *
 * Displays currently watching or last watched item.
 * Tag: nowscr_trakt_indicator
 *
 * @package NowScrobbling\Shortcodes\Trakt
 */
final class IndicatorShortcode extends AbstractShortcode
{
    private readonly TraktClient $client;

    public function __construct(
        OutputRenderer $renderer,
        HashGenerator $hasher,
        TraktClient $client
    ) {
        parent::__construct($renderer, $hasher);
        $this->client = $client;
    }

    public function getTag(): string
    {
        return 'nowscr_trakt_indicator';
    }

    protected function getData(array $atts): mixed
    {
        // First check if currently watching
        $watching = $this->client->getCurrentlyWatching();

        if ($watching !== null) {
            return ['watching' => true, 'data' => $watching];
        }

        // Otherwise get last watched
        $response = $this->client->getHistory('all', 1);

        if ($response->isError() || empty($response->data)) {
            return null;
        }

        return ['watching' => false, 'data' => $response->data[0] ?? null];
    }

    protected function formatOutput(mixed $data, array $atts): string
    {
        if ($data === null || !isset($data['data'])) {
            return esc_html($this->getEmptyMessage());
        }

        $item = $data['data'];
        $isWatching = $data['watching'] ?? false;
        $maxLength = (int) $atts['max_length'];

        $text = $this->formatItem($item, $maxLength);

        return $this->renderer->bubble($text, null, $isWatching);
    }

    private function formatItem(array $item, int $maxLength): string
    {
        $type = $item['type'] ?? '';

        return match ($type) {
            'movie' => $this->truncate($item['title'] ?? $item['movie']['title'] ?? '', $maxLength),
            'episode' => $this->formatEpisode($item, $maxLength),
            'show' => $this->truncate($item['show']['title'] ?? '', $maxLength),
            default => __('Unknown', 'nowscrobbling'),
        };
    }

    private function formatEpisode(array $item, int $maxLength): string
    {
        $show = $item['show_title'] ?? $item['show']['title'] ?? '';
        $season = $item['season'] ?? $item['episode']['season'] ?? 0;
        $episode = $item['episode'] ?? $item['episode']['number'] ?? 0;

        if (is_array($episode)) {
            $episode = $episode['number'] ?? 0;
        }

        $text = sprintf('%s S%02dE%02d', $show, $season, $episode);

        return $this->truncate($text, $maxLength);
    }

    protected function getEmptyMessage(): string
    {
        return __('Nothing watched recently.', 'nowscrobbling');
    }

    protected function isNowPlaying(mixed $data): bool
    {
        return $data['watching'] ?? false;
    }
}
