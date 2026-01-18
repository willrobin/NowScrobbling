<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\Trakt;

use NowScrobbling\Api\TraktClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Trakt History Shortcode
 *
 * Displays watch history.
 * Tag: nowscr_trakt_history
 *
 * @package NowScrobbling\Shortcodes\Trakt
 */
final class HistoryShortcode extends AbstractShortcode
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
        return 'nowscr_trakt_history';
    }

    protected function getDefaultAttributes(): array
    {
        return [
            'max_length' => 45,
            'limit' => (int) get_option('ns_trakt_activity_limit', 5),
            'type' => 'all',
        ];
    }

    protected function getData(array $atts): mixed
    {
        $response = $this->client->getHistory($atts['type'], (int) $atts['limit']);

        if ($response->isError()) {
            return null;
        }

        return $response->data;
    }

    protected function formatOutput(mixed $data, array $atts): string
    {
        if (empty($data)) {
            return esc_html($this->getEmptyMessage());
        }

        $maxLength = (int) $atts['max_length'];
        $items = [];

        foreach (array_slice($data, 0, (int) $atts['limit']) as $item) {
            $text = $this->formatItem($item, $maxLength);
            $items[] = esc_html($text);
        }

        return $this->renderer->list($items);
    }

    private function formatItem(array $item, int $maxLength): string
    {
        $type = $item['type'] ?? '';

        return match ($type) {
            'movie' => $this->truncate($item['movie']['title'] ?? '', $maxLength),
            'episode' => $this->formatEpisode($item, $maxLength),
            'show' => $this->truncate($item['show']['title'] ?? '', $maxLength),
            default => __('Unknown', 'nowscrobbling'),
        };
    }

    private function formatEpisode(array $item, int $maxLength): string
    {
        $show = $item['show']['title'] ?? '';
        $season = $item['episode']['season'] ?? 0;
        $episode = $item['episode']['number'] ?? 0;

        $text = sprintf('%s S%02dE%02d', $show, $season, $episode);

        return $this->truncate($text, $maxLength);
    }

    protected function getEmptyMessage(): string
    {
        return __('No watch history.', 'nowscrobbling');
    }
}
