<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Admin;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;
use Glorious\ChurchEvents\Support\Location_Helper;
use function absint;
use function add_settings_field;
use function add_settings_section;
use function add_submenu_page;
use function checked;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function register_setting;
use function sanitize_text_field;
use function selected;
use function settings_fields;
use function submit_button;

/**
 * Settings page for Church Events calendar preferences.
 */
final class Settings_Page
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? Settings::instance();
    }

    public function register(): void
    {
        Hooks::add_action('admin_menu', [$this, 'add_menu']);
        Hooks::add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . Event_Post_Type::POST_TYPE,
            esc_html__('Church Events Settings', 'church-events-calendar'),
            esc_html__('Settings', 'church-events-calendar'),
            'manage_options',
            'church-events-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            Settings::OPTION_KEY,
            Settings::OPTION_KEY,
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'cec_general_settings',
            esc_html__('General Settings', 'church-events-calendar'),
            static function (): void {
                echo '<p>' . esc_html__('Configure calendar defaults.', 'church-events-calendar') . '</p>';
            },
            Settings::OPTION_KEY
        );

        add_settings_field(
            'cec_week_start',
            esc_html__('Week Starts On', 'church-events-calendar'),
            [$this, 'render_week_start_field'],
            Settings::OPTION_KEY,
            'cec_general_settings'
        );

        add_settings_field(
            'cec_list_limit',
            esc_html__('Default List Limit', 'church-events-calendar'),
            [$this, 'render_list_limit_field'],
            Settings::OPTION_KEY,
            'cec_general_settings'
        );

        add_settings_field(
            'cec_advanced_recurrence',
            esc_html__('Advanced Recurrence UI', 'church-events-calendar'),
            [$this, 'render_advanced_recurrence_field'],
            Settings::OPTION_KEY,
            'cec_general_settings'
        );

        add_settings_section(
            'cec_location_settings',
            esc_html__('Default Location', 'church-events-calendar'),
            static function (): void {
                echo '<p>' . esc_html__('Configure the default location used when events opt-in to “Use default location”.', 'church-events-calendar') . '</p>';
            },
            Settings::OPTION_KEY
        );

        add_settings_field(
            'cec_default_location_mode',
            esc_html__('Location Mode', 'church-events-calendar'),
            [$this, 'render_location_mode_field'],
            Settings::OPTION_KEY,
            'cec_location_settings'
        );

        add_settings_field(
            'cec_default_location_text',
            esc_html__('Default Location Text', 'church-events-calendar'),
            [$this, 'render_location_text_field'],
            Settings::OPTION_KEY,
            'cec_location_settings'
        );

        add_settings_field(
            'cec_default_location_id',
            esc_html__('Default Saved Location', 'church-events-calendar'),
            [$this, 'render_location_select_field'],
            Settings::OPTION_KEY,
            'cec_location_settings'
        );
    }

    public function render_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Church Events Settings', 'church-events-calendar'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(Settings::OPTION_KEY);
                do_settings_sections(Settings::OPTION_KEY);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitize_settings(array $input): array
    {
        $output = [];

        $week_start = sanitize_text_field((string) ($input['week_start'] ?? 'sunday'));
        $output['week_start'] = $week_start === 'monday' ? 'monday' : 'sunday';

        $output['default_view'] = 'month';

        $list_limit = absint((int) ($input['list_default_limit'] ?? 5));
        $output['list_default_limit'] = max(1, $list_limit);

        $output['advanced_recurrence_enabled'] = ! empty($input['advanced_recurrence_enabled']);

        $mode = sanitize_text_field((string) ($input['default_location_mode'] ?? 'none'));
        $allowed_modes = ['none', 'text', 'cpt'];
        $output['default_location_mode'] = in_array($mode, $allowed_modes, true) ? $mode : 'none';

        $output['default_location_text'] = sanitize_text_field((string) ($input['default_location_text'] ?? ''));

        $location_id = absint((int) ($input['default_location_id'] ?? 0));
        $output['default_location_id'] = Location_Helper::sanitize_location_id($location_id);

        // Refresh settings cache so getters reflect latest changes immediately.
        $this->settings->refresh();

        return $output;
    }

    public function render_week_start_field(): void
    {
        $value = $this->settings->get_week_start();
        ?>
        <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[week_start]">
            <option value="sunday" <?php selected($value, 'sunday'); ?>>
                <?php esc_html_e('Sunday', 'church-events-calendar'); ?>
            </option>
            <option value="monday" <?php selected($value, 'monday'); ?>>
                <?php esc_html_e('Monday', 'church-events-calendar'); ?>
            </option>
        </select>
        <?php
    }

    public function render_list_limit_field(): void
    {
        $value = $this->settings->get_list_default_limit();
        ?>
        <input type="number"
            name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[list_default_limit]"
            min="1"
            value="<?php echo esc_attr((string) $value); ?>">
        <?php
    }

    public function render_advanced_recurrence_field(): void
    {
        $enabled = $this->settings->is_advanced_recurrence_enabled();
        ?>
        <label>
            <input type="checkbox"
                name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[advanced_recurrence_enabled]"
                value="1"
                <?php checked($enabled); ?>>
            <?php esc_html_e('Enable daily/monthly recurrence options in the event editor.', 'church-events-calendar'); ?>
        </label>
        <?php
    }

    public function render_location_mode_field(): void
    {
        $mode = $this->settings->get_default_location_mode();
        ?>
        <fieldset>
            <label>
                <input type="radio" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[default_location_mode]" value="none" <?php checked($mode, 'none'); ?>>
                <?php esc_html_e('No default location', 'church-events-calendar'); ?>
            </label><br>
            <label>
                <input type="radio" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[default_location_mode]" value="text" <?php checked($mode, 'text'); ?>>
                <?php esc_html_e('Use text-based location below', 'church-events-calendar'); ?>
            </label><br>
            <label>
                <input type="radio" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[default_location_mode]" value="cpt" <?php checked($mode, 'cpt'); ?>>
                <?php esc_html_e('Use saved Location entry', 'church-events-calendar'); ?>
            </label>
        </fieldset>
        <?php
    }

    public function render_location_text_field(): void
    {
        $value = $this->settings->get_default_location_text();
        ?>
        <input type="text"
            class="regular-text"
            name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[default_location_text]"
            value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Used when mode is “text-based location”.', 'church-events-calendar'); ?>
        </p>
        <?php
    }

    public function render_location_select_field(): void
    {
        $current = $this->settings->get_default_location_id();
        $locations = Location_Helper::get_location_posts();
        ?>
        <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[default_location_id]">
            <option value="0"><?php esc_html_e('Select a saved location', 'church-events-calendar'); ?></option>
            <?php foreach ($locations as $location) : ?>
                <option value="<?php echo esc_attr((string) $location->ID); ?>" <?php selected($current, $location->ID); ?>>
                    <?php echo esc_html($location->post_title ?: sprintf(__('Location #%d', 'church-events-calendar'), $location->ID)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Used when mode is “saved Location entry”.', 'church-events-calendar'); ?>
        </p>
        <?php
    }
}

