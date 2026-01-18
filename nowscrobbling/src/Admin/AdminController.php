<?php

declare(strict_types=1);

namespace NowScrobbling\Admin;

use NowScrobbling\Container;
use NowScrobbling\Plugin;

/**
 * Admin Controller
 *
 * Handles admin menu registration and settings page rendering.
 *
 * @package NowScrobbling\Admin
 */
final class AdminController
{
    private readonly Container $container;
    private readonly SettingsManager $settings;

    /**
     * Constructor
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = new SettingsManager();
    }

    /**
     * Register admin menu
     */
    public function registerMenu(): void
    {
        add_options_page(
            __('NowScrobbling Settings', 'nowscrobbling'),
            __('NowScrobbling', 'nowscrobbling'),
            'manage_options',
            SettingsManager::PAGE_SLUG,
            $this->renderPage(...)
        );
    }

    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        $this->settings->registerSettings();
    }

    /**
     * Render the settings page
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'nowscrobbling'),
                403
            );
        }

        // Include the settings page view
        $viewPath = Plugin::getInstance()->getPath('src/Admin/Views/settings-page.php');

        if (file_exists($viewPath)) {
            // Make settings manager available to the view
            $settings = $this->settings;
            include $viewPath;
        } else {
            // Fallback inline render
            $this->renderInline();
        }
    }

    /**
     * Fallback inline render if view file is missing
     */
    private function renderInline(): void
    {
        ?>
        <div class="wrap nowscrobbling-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->settings->renderTabNavigation(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields(SettingsManager::SETTINGS_GROUP);
                $this->settings->renderActiveTab();
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get the settings manager
     */
    public function getSettingsManager(): SettingsManager
    {
        return $this->settings;
    }
}
