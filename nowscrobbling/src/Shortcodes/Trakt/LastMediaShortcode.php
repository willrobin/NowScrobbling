<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\Trakt;

use NowScrobbling\Api\TraktClient;
use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;

/**
 * Trakt Last Media Shortcode
 *
 * Unified shortcode for last movie, show, or episode.
 * Tags: nowscr_trakt_last_movie, nowscr_trakt_last_show, nowscr_trakt_last_episode
 *
 * @package NowScrobbling\Shortcodes\Trakt
 */
final class LastMediaShortcode extends AbstractShortcode
{
    private readonly TraktClient $client;
    private readonly string $type;

    public function __construct(
        OutputRenderer $renderer,
        HashGenerator $hasher,
        TraktClient $client,
        string $type
    ) {
        parent::__construct($renderer, $hasher);
        $this->client = $client;
        $this->type = $type;
    }

    public function getTag(): string
    {
        return "nowscr_trakt_last_{$this->type}";
    }

    protected function getDefaultAttributes(): array
    {
        $limitOption = match ($this->type) {
            'movie' => 'ns_last_movies_count',
            'show' => 'ns_last_shows_count',
            'episode' => 'ns_last_episodes_count',
            default => 'ns_last_movies_count',
        };

        return [
            'max_length' => 45,
            'limit' => (int) get_option($limitOption, 3),
        ];
    }

    protected function getData(array $atts): mixed
    {
        $type = $this->type === 'show' ? 'shows' : "{$this->type}s";
        $response = $this->client->getHistory($type, (int) $atts['limit']);

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
        return match ($this->type) {
            'movie' => $this->truncate($item['movie']['title'] ?? '', $maxLength),
            'show' => $this->truncate($item['show']['title'] ?? '', $maxLength),
            'episode' => $this->formatEpisode($item, $maxLength),
            default => '',
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
        return match ($this->type) {
            'movie' => __('No movies watched.', 'nowscrobbling'),
            'show' => __('No shows watched.', 'nowscrobbling'),
            'episode' => __('No episodes watched.', 'nowscrobbling'),
            default => __('No data found.', 'nowscrobbling'),
        };
    }
}
