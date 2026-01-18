<?php

declare(strict_types=1);

namespace NowScrobbling\Admin\Tabs;

/**
 * Interface for admin settings tabs
 *
 * Each tab handles a specific category of settings.
 *
 * @package NowScrobbling\Admin\Tabs
 */
interface TabInterface
{
    /**
     * Get the unique tab ID
     */
    public function getId(): string;

    /**
     * Get the tab title for display
     */
    public function getTitle(): string;

    /**
     * Get the tab icon (dashicon class or empty)
     */
    public function getIcon(): string;

    /**
     * Register settings for this tab
     */
    public function registerSettings(): void;

    /**
     * Render the tab content
     */
    public function render(): void;

    /**
     * Get the menu order (lower = earlier)
     */
    public function getOrder(): int;
}
