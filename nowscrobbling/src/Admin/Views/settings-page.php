<?php
/**
 * Settings Page View
 *
 * @package NowScrobbling\Admin\Views
 *
 * @var \NowScrobbling\Admin\SettingsManager $settings
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap nowscrobbling-admin">
    <h1>
        <span class="dashicons dashicons-format-audio"></span>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <?php
    // Show settings saved notice
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        add_settings_error(
            'nowscrobbling_messages',
            'nowscrobbling_message',
            __('Settings saved.', 'nowscrobbling'),
            'updated'
        );
    }

    settings_errors('nowscrobbling_messages');
    ?>

    <?php $settings->renderTabNavigation(); ?>

    <form method="post" action="options.php" class="ns-settings-form">
        <?php
        settings_fields(\NowScrobbling\Admin\SettingsManager::SETTINGS_GROUP);
        $settings->renderActiveTab();
        ?>

        <?php
        // Only show submit button for tabs that have settings
        $activeTabId = $settings->getActiveTabId();
        if ($activeTabId !== 'diagnostics') {
            submit_button();
        }
        ?>
    </form>

    <div class="ns-admin-footer">
        <p>
            <?php
            printf(
                /* translators: %s: Plugin version */
                esc_html__('NowScrobbling v%s', 'nowscrobbling'),
                esc_html(\NowScrobbling\Plugin::VERSION)
            );
            ?>
            |
            <a href="https://github.com/willrobin/NowScrobbling" target="_blank" rel="noopener">
                <?php esc_html_e('Documentation', 'nowscrobbling'); ?>
            </a>
        </p>
    </div>
</div>
