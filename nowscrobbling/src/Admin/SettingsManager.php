<?php

declare(strict_types=1);

namespace NowScrobbling\Admin;

use NowScrobbling\Admin\Tabs\TabInterface;
use NowScrobbling\Admin\Tabs\CredentialsTab;
use NowScrobbling\Admin\Tabs\CachingTab;
use NowScrobbling\Admin\Tabs\DisplayTab;
use NowScrobbling\Admin\Tabs\DiagnosticsTab;
use NowScrobbling\Security\Sanitizer;

/**
 * Settings Manager
 *
 * Manages tab-based settings registration and rendering.
 *
 * @package NowScrobbling\Admin
 */
final class SettingsManager
{
    /**
     * @var array<TabInterface>
     */
    private array $tabs = [];

    /**
     * Settings page slug
     */
    public const PAGE_SLUG = 'nowscrobbling';

    /**
     * Settings group name
     */
    public const SETTINGS_GROUP = 'nowscrobbling';

    /**
     * Constructor - registers all tabs
     */
    public function __construct()
    {
        $this->tabs = [
            new CredentialsTab(),
            new CachingTab(),
            new DisplayTab(),
            new DiagnosticsTab(),
        ];

        // Sort tabs by order
        usort($this->tabs, fn(TabInterface $a, TabInterface $b) => $a->getOrder() <=> $b->getOrder());
    }

    /**
     * Register all settings
     */
    public function registerSettings(): void
    {
        foreach ($this->tabs as $tab) {
            $tab->registerSettings();
        }
    }

    /**
     * Get all registered tabs
     *
     * @return array<TabInterface>
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    /**
     * Get the active tab ID
     */
    public function getActiveTabId(): string
    {
        $tabId = Sanitizer::input('tab', 'key', 'GET');

        // Validate tab ID exists
        foreach ($this->tabs as $tab) {
            if ($tab->getId() === $tabId) {
                return $tabId;
            }
        }

        // Return first tab as default
        return $this->tabs[0]->getId();
    }

    /**
     * Get a tab by ID
     */
    public function getTab(string $id): ?TabInterface
    {
        foreach ($this->tabs as $tab) {
            if ($tab->getId() === $id) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * Render the tab navigation
     */
    public function renderTabNavigation(): void
    {
        $activeTab = $this->getActiveTabId();
        $baseUrl = admin_url('options-general.php?page=' . self::PAGE_SLUG);

        echo '<nav class="nav-tab-wrapper">';

        foreach ($this->tabs as $tab) {
            $isActive = $tab->getId() === $activeTab;
            $url = add_query_arg('tab', $tab->getId(), $baseUrl);

            printf(
                '<a href="%s" class="nav-tab%s">%s%s</a>',
                esc_url($url),
                $isActive ? ' nav-tab-active' : '',
                $tab->getIcon() ? '<span class="dashicons ' . esc_attr($tab->getIcon()) . '"></span> ' : '',
                esc_html($tab->getTitle())
            );
        }

        echo '</nav>';
    }

    /**
     * Render the active tab content
     */
    public function renderActiveTab(): void
    {
        $activeTabId = $this->getActiveTabId();
        $tab = $this->getTab($activeTabId);

        if ($tab !== null) {
            $tab->render();
        }
    }
}
