<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Meta;

use DateTimeImmutable;
use DateTimeZone;
use Glorious\ChurchEvents\Admin\Settings;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;
use Glorious\ChurchEvents\Support\Language_Helper;
use Glorious\ChurchEvents\Support\Location_Helper;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule as RecurrRule;
use WP_Post;
use function absint;
use function add_meta_box;
use function remove_meta_box;
use function array_map;
use function array_unique;
use function checked;
use function current_user_can;
use function defined;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_html__;
use function explode;
use function implode;
use function in_array;
use function sanitize_text_field;
use function selected;
use function sort;
use function strpos;
use function strtoupper;
use function trim;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;
use function function_exists;

/**
 * Handles rendering and saving of event meta boxes.
 */
final class Event_Meta_Boxes
{
    private const WEEKDAY_NAME_TO_CODE = [
        'sunday' => 'SU',
        'monday' => 'MO',
        'tuesday' => 'TU',
        'wednesday' => 'WE',
        'thursday' => 'TH',
        'friday' => 'FR',
        'saturday' => 'SA',
    ];

    private const WEEKDAY_CODE_TO_NAME = [
        'SU' => 'sunday',
        'MO' => 'monday',
        'TU' => 'tuesday',
        'WE' => 'wednesday',
        'TH' => 'thursday',
        'FR' => 'friday',
        'SA' => 'saturday',
    ];

    private const FREQUENCY_OPTIONS = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ];

    private Event_Meta_Repository $repository;

    public function __construct(Event_Meta_Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Hooks WordPress actions for meta boxes.
     */
    public function register(): void
    {
        Hooks::add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        Hooks::add_action('add_meta_boxes', [$this, 'maybe_lock_featured_image'], 20, 2);
        Hooks::add_action('save_post', [$this, 'handle_save'], 10, 2);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'church_event_details',
            esc_html__('Event Details', 'church-events-calendar'),
            [$this, 'render_meta_box'],
            Event_Post_Type::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Locks the featured image meta box for translations.
     *
     * @param string $post_type
     * @param WP_Post $post
     */
    public function maybe_lock_featured_image(string $post_type, $post): void
    {
        if ($post_type !== Event_Post_Type::POST_TYPE || ! $post instanceof WP_Post) {
            return;
        }

        if (Language_Helper::is_primary_post((int) $post->ID)) {
            return;
        }

        remove_meta_box('postimagediv', Event_Post_Type::POST_TYPE, 'side');

        add_meta_box(
            'postimagediv',
            esc_html__('Featured image', 'church-events-calendar'),
            static function (): void {
                echo '<p>' . esc_html__('Featured images are shared across translations. Edit the primary language event to change it.', 'church-events-calendar') . '</p>';
            },
            Event_Post_Type::POST_TYPE,
            'side',
            'low'
        );
    }

    /**
     * @param WP_Post $post
     */
    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('church_event_meta', 'church_event_meta_nonce');

        $meta = $this->repository->get_meta((int) $post->ID);
        $settings = Settings::instance();
        $form_state = $this->resolve_form_state($meta);

        $show_advanced = $settings->is_advanced_recurrence_enabled();
        $is_primary = Language_Helper::is_primary_post((int) $post->ID);
        $is_translation = ! $is_primary;

        if ($form_state['enabled']) {
            $meta[Event_Meta_Repository::META_IS_RECURRING] = true;
            $meta[Event_Meta_Repository::META_RECURRENCE_INTERVAL] = $form_state['interval'];
            $meta[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS] = $form_state['weekdays'];
        }
        $weekdays = [
            'monday' => esc_html__('Monday', 'church-events-calendar'),
            'tuesday' => esc_html__('Tuesday', 'church-events-calendar'),
            'wednesday' => esc_html__('Wednesday', 'church-events-calendar'),
            'thursday' => esc_html__('Thursday', 'church-events-calendar'),
            'friday' => esc_html__('Friday', 'church-events-calendar'),
            'saturday' => esc_html__('Saturday', 'church-events-calendar'),
            'sunday' => esc_html__('Sunday', 'church-events-calendar'),
        ];
        $location_state = $this->resolve_location_form_state($meta, $settings);
        $location_posts = Location_Helper::get_location_posts();
        $default_location_label = $settings->get_default_location_string();
        ?>
        <?php if ($is_translation) : ?>
            <div class="notice notice-info">
                <p>
                    <?php esc_html_e('Event schedule, location, and recurrence are managed in the primary language. Edit the original event to change these details.', 'church-events-calendar'); ?>
                </p>
            </div>
            <fieldset class="church-event-meta-readonly" disabled="disabled">
        <?php endif; ?>
        <div class="church-event-meta-fields">
            <?php
            [$start_date, $start_hour, $start_minute, $start_meridiem] = $this->split_datetime($meta[Event_Meta_Repository::META_START] ?? '');
            [$end_date, $end_hour, $end_minute, $end_meridiem] = $this->split_datetime($meta[Event_Meta_Repository::META_END] ?? '');
            ?>
            <div class="church-event-meta-field church-event-meta-field--datetime">
                <label for="church-event-start-date">
                    <?php esc_html_e('Start Date', 'church-events-calendar'); ?>
                </label>
                <input type="date"
                    id="church-event-start-date"
                    name="_event_start_date"
                    value="<?php echo esc_attr($start_date); ?>"
                />
                <?php echo $this->render_time_picker('start', $start_hour, $start_minute, $start_meridiem); ?>
            </div>
            <div class="church-event-meta-field church-event-meta-field--datetime">
                <label for="church-event-end-date">
                    <?php esc_html_e('End Date', 'church-events-calendar'); ?>
                </label>
                <input type="date"
                    id="church-event-end-date"
                    name="_event_end_date"
                    value="<?php echo esc_attr($end_date); ?>"
                />
                <?php echo $this->render_time_picker('end', $end_hour, $end_minute, $end_meridiem); ?>
            </div>
            <div class="church-event-meta-field">
                <label>
                    <input type="checkbox"
                        name="<?php echo esc_attr(Event_Meta_Repository::META_ALL_DAY); ?>"
                        value="1" <?php checked($meta[Event_Meta_Repository::META_ALL_DAY], true); ?>
                    />
                    <?php esc_html_e('All Day Event', 'church-events-calendar'); ?>
                </label>
            </div>
            <div class="church-event-meta-field church-event-meta-field--location">
                <label><?php esc_html_e('Location', 'church-events-calendar'); ?></label>
                <fieldset>
                    <label>
                        <input type="radio"
                            name="event_location_mode"
                            value="default"
                            data-cec-location-radio
                            <?php checked($location_state['mode'], 'default'); ?>>
                        <?php esc_html_e('Use default location (from Settings)', 'church-events-calendar'); ?>
                        <?php if ($default_location_label !== '') : ?>
                            <span class="description">
                                <?php echo esc_html(sprintf(
                                    /* translators: %s default location */
                                    __('Current default: %s', 'church-events-calendar'),
                                    $default_location_label
                                )); ?>
                            </span>
                        <?php else : ?>
                            <span class="description">
                                <?php esc_html_e('No default location configured yet.', 'church-events-calendar'); ?>
                            </span>
                        <?php endif; ?>
                    </label><br>
                    <label>
                        <input type="radio"
                            name="event_location_mode"
                            value="saved"
                            data-cec-location-radio
                            <?php checked($location_state['mode'], 'saved'); ?>>
                        <?php esc_html_e('Use a saved location', 'church-events-calendar'); ?>
                    </label><br>
                    <label>
                        <input type="radio"
                            name="event_location_mode"
                            value="custom"
                            data-cec-location-radio
                            <?php checked($location_state['mode'], 'custom'); ?>>
                        <?php esc_html_e('Use custom location text', 'church-events-calendar'); ?>
                    </label>
                </fieldset>
                <div class="church-event-location-control"
                    data-cec-location-field="saved"
                    style="<?php echo $location_state['mode'] === 'saved' ? '' : 'display:none;'; ?>">
                    <label for="church-event-location-saved">
                        <?php esc_html_e('Saved location', 'church-events-calendar'); ?>
                    </label>
                    <select id="church-event-location-saved" name="event_location_saved_id">
                        <option value="0"><?php esc_html_e('Select a location', 'church-events-calendar'); ?></option>
                        <?php foreach ($location_posts as $location_post) : ?>
                            <option value="<?php echo esc_attr((string) $location_post->ID); ?>"
                                <?php selected($location_state['saved_id'], $location_post->ID); ?>>
                                <?php echo esc_html($location_post->post_title ?: sprintf(
                                    __('Location #%d', 'church-events-calendar'),
                                    $location_post->ID
                                )); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="church-event-location-control"
                    data-cec-location-field="custom"
                    style="<?php echo $location_state['mode'] === 'custom' ? '' : 'display:none;'; ?>">
                    <label for="church-event-location">
                        <?php esc_html_e('Custom location', 'church-events-calendar'); ?>
                    </label>
                    <input type="text"
                        id="church-event-location"
                        name="event_location_custom"
                        value="<?php echo esc_attr($location_state['custom_value']); ?>"
                        class="widefat"
                    />
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const radios = document.querySelectorAll('[data-cec-location-radio]');
                    const sections = document.querySelectorAll('[data-cec-location-field]');
                    const toggle = () => {
                        const checked = document.querySelector('[data-cec-location-radio]:checked');
                        const mode = checked ? checked.value : 'default';
                        sections.forEach(section => {
                            if (section.getAttribute('data-cec-location-field') === mode) {
                                section.style.display = '';
                            } else {
                                section.style.display = 'none';
                            }
                        });
                    };
                    radios.forEach(radio => radio.addEventListener('change', toggle));
                    toggle();
                });
            </script>
            <div class="church-event-meta-field church-event-meta-recurring">
                <fieldset class="church-event-recurring-fieldset">
                    <legend class="screen-reader-text">
                        <?php esc_html_e('Recurrence settings', 'church-events-calendar'); ?>
                    </legend>
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr(Event_Meta_Repository::META_IS_RECURRING); ?>"
                            value="1" <?php checked($form_state['enabled'], true); ?>
                        />
                        <?php esc_html_e('Recurring Event', 'church-events-calendar'); ?>
                    </label>
                    <div class="church-event-recurring-details">
                    <label>
                        <?php esc_html_e('Repeat every', 'church-events-calendar'); ?>
                        <input type="number"
                            min="1"
                            name="<?php echo esc_attr(Event_Meta_Repository::META_RECURRENCE_INTERVAL); ?>"
                            value="<?php echo esc_attr((string) $form_state['interval']); ?>"
                        />
                    </label>
                    <?php if ($show_advanced) : ?>
                        <label for="cec-recurrence-frequency">
                            <?php esc_html_e('Frequency', 'church-events-calendar'); ?>
                        </label>
                        <select name="cec_recurrence_frequency" id="cec-recurrence-frequency">
                            <?php foreach (self::FREQUENCY_OPTIONS as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($form_state['frequency'], $value); ?>>
                                    <?php echo esc_html__($label, 'church-events-calendar'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <fieldset>
                        <legend><?php esc_html_e('Weekdays', 'church-events-calendar'); ?></legend>
                        <?php foreach ($weekdays as $key => $label) : ?>
                            <label class="church-event-weekday">
                                <input type="checkbox"
                                    name="<?php echo esc_attr(Event_Meta_Repository::META_RECURRENCE_WEEKDAYS); ?>[]"
                                    value="<?php echo esc_attr($key); ?>"
                                    <?php checked(in_array($key, $form_state['weekdays'], true), true); ?>
                                />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if ($show_advanced) : ?>
                        <?php
                        $end_count_value = $form_state['end_count'] ? (string) $form_state['end_count'] : '';
                        $end_date_value = $form_state['end_date'] ?? '';
                        ?>
                        <div class="church-event-recurring-end">
                            <span><?php esc_html_e('Ends', 'church-events-calendar'); ?></span>
                            <label>
                                <input type="radio"
                                    name="cec_recurrence_end_type"
                                    value="never" <?php checked($form_state['end_type'], 'never'); ?>
                                />
                                <?php esc_html_e('Never', 'church-events-calendar'); ?>
                            </label>
                            <label>
                                <input type="radio"
                                    name="cec_recurrence_end_type"
                                    value="count" <?php checked($form_state['end_type'], 'count'); ?>
                                />
                                <?php esc_html_e('After', 'church-events-calendar'); ?>
                                <input type="number"
                                    min="1"
                                    name="cec_recurrence_end_count"
                                    value="<?php echo esc_attr($end_count_value); ?>"
                                />
                                <?php esc_html_e('occurrences', 'church-events-calendar'); ?>
                            </label>
                            <label>
                                <input type="radio"
                                    name="cec_recurrence_end_type"
                                    value="until" <?php checked($form_state['end_type'], 'until'); ?>
                                />
                                <?php esc_html_e('On date', 'church-events-calendar'); ?>
                                <input type="date"
                                    name="cec_recurrence_end_date"
                                    value="<?php echo esc_attr($end_date_value); ?>"
                                />
                            </label>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Weekly settings apply only when “Weekly” frequency is selected. Monthly repeats occur on the same day of the month as the event start date.', 'church-events-calendar'); ?>
                        </p>
                    <?php endif; ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php if ($is_translation) : ?>
            </fieldset>
        <?php endif; ?>
        <?php
    }

    /**
     * @param int $post_id
     * @param WP_Post $post
     */
    public function handle_save(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== Event_Post_Type::POST_TYPE) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! isset($_POST['church_event_meta_nonce']) || ! wp_verify_nonce(
            sanitize_text_field(wp_unslash((string) $_POST['church_event_meta_nonce'])),
            'church_event_meta'
        )) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (! Language_Helper::is_primary_post($post_id)) {
            return;
        }

        $start_date = isset($_POST['_event_start_date'])
            ? sanitize_text_field(wp_unslash($_POST['_event_start_date']))
            : '';
        $start_hour = isset($_POST['_event_start_hour'])
            ? sanitize_text_field(wp_unslash($_POST['_event_start_hour']))
            : '';
        $start_minute = isset($_POST['_event_start_minute'])
            ? sanitize_text_field(wp_unslash($_POST['_event_start_minute']))
            : '';
        $start_meridiem = isset($_POST['_event_start_meridiem'])
            ? sanitize_text_field(wp_unslash($_POST['_event_start_meridiem']))
            : '';
        $event_end_date = isset($_POST['_event_end_date'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_date']))
            : '';
        $event_end_hour = isset($_POST['_event_end_hour'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_hour']))
            : '';
        $event_end_minute = isset($_POST['_event_end_minute'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_minute']))
            : '';
        $event_end_meridiem = isset($_POST['_event_end_meridiem'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_meridiem']))
            : '';
        $recurrence_weekdays = isset($_POST[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS])
            ? array_map(
                static fn($day): string => sanitize_text_field((string) $day),
                (array) wp_unslash($_POST[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS])
            )
            : [];
        $recurrence_interval = isset($_POST[Event_Meta_Repository::META_RECURRENCE_INTERVAL])
            ? (int) wp_unslash($_POST[Event_Meta_Repository::META_RECURRENCE_INTERVAL])
            : 1;
        $settings = Settings::instance();
        $frequency = isset($_POST['cec_recurrence_frequency'])
            ? $this->sanitize_frequency(sanitize_text_field(wp_unslash((string) $_POST['cec_recurrence_frequency'])))
            : 'weekly';
        $end_type = isset($_POST['cec_recurrence_end_type'])
            ? $this->sanitize_end_type(sanitize_text_field(wp_unslash((string) $_POST['cec_recurrence_end_type'])))
            : 'never';
        $end_count = isset($_POST['cec_recurrence_end_count'])
            ? max(1, (int) wp_unslash($_POST['cec_recurrence_end_count']))
            : null;
        $recurrence_end_date = isset($_POST['cec_recurrence_end_date'])
            ? sanitize_text_field(wp_unslash($_POST['cec_recurrence_end_date']))
            : '';

        if (! $settings->is_advanced_recurrence_enabled()) {
            $frequency = 'weekly';
            $end_type = 'never';
            $end_count = null;
            $recurrence_end_date = '';
        }

        if ($end_type !== 'count') {
            $end_count = null;
        }

        if ($end_type !== 'until') {
            $recurrence_end_date = '';
        }

        $location_mode = isset($_POST['event_location_mode'])
            ? $this->sanitize_location_mode(sanitize_text_field(wp_unslash((string) $_POST['event_location_mode'])))
            : 'default';
        $location_saved_id = $location_mode === 'saved'
            ? Location_Helper::sanitize_location_id(absint((int) ($_POST['event_location_saved_id'] ?? 0)))
            : 0;
        $custom_location_value = isset($_POST['event_location_custom'])
            ? sanitize_text_field(wp_unslash((string) $_POST['event_location_custom']))
            : '';

        [$final_location, $location_mode, $location_saved_id] = $this->resolve_location_save_state(
            $location_mode,
            $location_saved_id,
            $custom_location_value,
            $settings
        );

        $data = [
            Event_Meta_Repository::META_START => $this->combine_datetime($start_date, $start_hour, $start_minute, $start_meridiem),
            Event_Meta_Repository::META_END => $this->combine_datetime($event_end_date, $event_end_hour, $event_end_minute, $event_end_meridiem),
            Event_Meta_Repository::META_ALL_DAY => ! empty($_POST[Event_Meta_Repository::META_ALL_DAY]),
            Event_Meta_Repository::META_IS_RECURRING => ! empty($_POST[Event_Meta_Repository::META_IS_RECURRING]),
            Event_Meta_Repository::META_RECURRENCE_INTERVAL => max(1, $recurrence_interval),
            Event_Meta_Repository::META_RECURRENCE_WEEKDAYS => $recurrence_weekdays,
        ];

        $data[Event_Meta_Repository::META_LOCATION] = $final_location;
        $data[Event_Meta_Repository::META_LOCATION_MODE] = $location_mode;
        $data[Event_Meta_Repository::META_LOCATION_ID] = $location_saved_id;

        $start_datetime = null;
        if ($data[Event_Meta_Repository::META_START] !== '') {
            try {
                $start_datetime = new DateTimeImmutable($data[Event_Meta_Repository::META_START]);
            } catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
                $start_datetime = null;
            }
        }

        $data[Event_Meta_Repository::META_RRULE] = $this->build_rrule_from_form(
            [
                'enabled' => (bool) $data[Event_Meta_Repository::META_IS_RECURRING],
                'frequency' => $frequency,
                'interval' => (int) $data[Event_Meta_Repository::META_RECURRENCE_INTERVAL],
                'weekdays' => $recurrence_weekdays,
                'end_type' => $end_type,
                'end_count' => $end_count,
                'end_date' => $recurrence_end_date,
            ],
            $start_datetime
        );

        $this->repository->save_meta($post_id, $data);
    }

    /**
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function split_datetime(?string $value): array
    {
        if (empty($value)) {
            return ['', '12', '00', 'am'];
        }

        try {
            $datetime = new DateTimeImmutable($value);

            return [
                $datetime->format('Y-m-d'),
                $datetime->format('g'),
                $this->sanitize_minute($datetime->format('i')),
                strtolower($datetime->format('a')),
            ];
        } catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
        }

        return ['', '12', '00', 'am'];
    }

    private function combine_datetime(string $date, string $hour, string $minute, string $meridiem): string
    {
        if ($date === '') {
            return '';
        }

        $hour = (int) $hour;
        if ($hour < 1 || $hour > 12) {
            $hour = 12;
        }

        $minute = $this->sanitize_minute($minute);
        $meridiem = strtolower($meridiem) === 'pm' ? 'pm' : 'am';

        if ($meridiem === 'pm' && $hour !== 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        $time = sprintf('%02d:%s:00', $hour, $minute);

        try {
            $datetime = new DateTimeImmutable(sprintf('%s %s', $date, $time));

            return $datetime->format('Y-m-d H:i:s');
        } catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
        }

        return '';
    }

    private function render_time_picker(string $context, string $hour, string $minute, string $meridiem): string
    {
        $hour_field = sprintf('_event_%s_hour', $context);
        $minute_field = sprintf('_event_%s_minute', $context);
        $meridiem_field = sprintf('_event_%s_meridiem', $context);

        $output  = '<div class="church-event-time-picker">';
        $output .= $this->render_select(
            $hour_field,
            $hour !== '' ? $hour : '12',
            $this->get_hour_options(),
            sprintf('church-event-%s-hour', $context),
            esc_html__('Hour', 'church-events-calendar')
        );
        $output .= $this->render_select(
            $minute_field,
            $minute !== '' ? $minute : '00',
            ['00', '15', '30', '45'],
            sprintf('church-event-%s-minute', $context),
            esc_html__('Minutes', 'church-events-calendar')
        );
        $output .= $this->render_select(
            $meridiem_field,
            $meridiem !== '' ? $meridiem : 'am',
            ['am' => esc_html__('AM', 'church-events-calendar'), 'pm' => esc_html__('PM', 'church-events-calendar')],
            sprintf('church-event-%s-meridiem', $context),
            esc_html__('AM/PM', 'church-events-calendar'),
            true
        );
        $output .= '</div>';

        return $output;
    }

    /**
     * @param array<int|string, string> $options
     */
    private function render_select(string $name, string $value, array $options, string $id, string $label, bool $assoc = false): string
    {
        $output  = '<label class="screen-reader-text" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        $output .= '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '">';
        foreach ($options as $key => $option) {
            $option_value = $assoc ? (string) $key : (string) $option;
            $option_label = $assoc ? $option : $option_value;
            $output .= sprintf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($option_value),
                selected($option_value, $value, false),
                esc_html($option_label)
            );
        }
        $output .= '</select>';

        return $output;
    }

    /**
     * @return array<int, string>
     */
    private function get_hour_options(): array
    {
        $hours = [];

        for ($i = 1; $i <= 12; $i++) {
            $hours[] = (string) $i;
        }

        return $hours;
    }

    private function sanitize_minute(string $minute): string
    {
        $allowed = ['00', '15', '30', '45'];

        if (! in_array($minute, $allowed, true)) {
            return '00';
        }

        return $minute;
    }

    /**
     * @return array{mode:string,saved_id:int,custom_value:string}
     */
    private function resolve_location_form_state(array $meta, Settings $settings): array
    {
        $mode = $this->sanitize_location_mode((string) ($meta[Event_Meta_Repository::META_LOCATION_MODE] ?? ''));
        $saved_id = isset($meta[Event_Meta_Repository::META_LOCATION_ID])
            ? Location_Helper::sanitize_location_id((int) $meta[Event_Meta_Repository::META_LOCATION_ID])
            : 0;
        $custom_value = (string) ($meta[Event_Meta_Repository::META_LOCATION] ?? '');

        if ($mode === 'saved' && $saved_id === 0) {
            $mode = 'default';
        }

        if ($mode === 'custom' && $custom_value === '') {
            $mode = $settings->get_default_location_string() !== '' ? 'default' : 'custom';
        }

        if ($mode === '') {
            $mode = $custom_value !== '' ? 'custom' : 'default';
        }

        return [
            'mode' => $mode,
            'saved_id' => $saved_id,
            'custom_value' => $mode === 'custom' ? $custom_value : '',
        ];
    }

    /**
     * @return array{0:string,1:string,2:int}
     */
    private function resolve_location_save_state(string $mode, int $location_id, string $custom_location, Settings $settings): array
    {
        $mode = $this->sanitize_location_mode($mode);

        if ($mode === 'saved') {
            $label = Location_Helper::get_location_label($location_id);
            if ($label !== '') {
                return [$label, 'saved', $location_id];
            }
            $mode = 'default';
            $location_id = 0;
        }

        if ($mode === 'custom') {
            if ($custom_location !== '') {
                return [$custom_location, 'custom', 0];
            }
            $mode = 'default';
        }

        $default = $settings->get_default_location_string();
        if ($default !== '') {
            return [$default, 'default', 0];
        }

        return ['', 'default', 0];
    }

    private function sanitize_location_mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        $allowed = ['default', 'custom', 'saved'];

        return in_array($mode, $allowed, true) ? $mode : 'default';
    }

    /**
     * Data flow:
     * UI form fields → structured form state → RRULE string stored in meta.
     * On edit, the RRULE is parsed back into form state so legacy weekly inputs stay in sync.
     *
     * Supported RRULE parts: FREQ (DAILY/WEEKLY/MONTHLY), INTERVAL, BYDAY (weekly),
     * BYMONTHDAY (monthly), COUNT, and UNTIL.
     */
    private function build_rrule_from_form(array $form, ?DateTimeImmutable $start): string
    {
        if (! $form['enabled']) {
            return '';
        }

        $frequency = strtoupper($form['frequency'] ?? 'WEEKLY');
        $interval = max(1, (int) ($form['interval'] ?? 1));
        $parts = ["FREQ={$frequency}", "INTERVAL={$interval}"];

        if ($frequency === 'WEEKLY') {
            $codes = [];
            foreach ($form['weekdays'] as $weekday) {
                $code = $this->weekday_name_to_code($weekday);
                if ($code) {
                    $codes[] = $code;
                }
            }

            if (empty($codes) && $start instanceof DateTimeImmutable) {
                $codes[] = $this->weekday_name_to_code(strtolower($start->format('l'))) ?? 'MO';
            }

            $codes = array_values(array_unique($codes));
            sort($codes);

            if (empty($codes)) {
                return '';
            }

            $parts[] = 'BYDAY=' . implode(',', $codes);
        } elseif ($frequency === 'MONTHLY') {
            if (! $start instanceof DateTimeImmutable) {
                return '';
            }

            $parts[] = 'BYMONTHDAY=' . $start->format('j');
        }

        if (($form['end_type'] ?? 'never') === 'count' && ! empty($form['end_count'])) {
            $parts[] = 'COUNT=' . max(1, (int) $form['end_count']);
        } elseif (($form['end_type'] ?? 'never') === 'until' && ! empty($form['end_date'])) {
            $until = $this->format_until_value((string) $form['end_date'], $start);
            if ($until) {
                $parts[] = 'UNTIL=' . $until;
            }
        }

        return implode(';', $parts);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     frequency: string,
     *     interval: int,
     *     weekdays: array<int, string>,
     *     end_type: string,
     *     end_count: ?int,
     *     end_date: string
     * }
     */
    private function parse_rrule_to_form_state(string $rrule): array
    {
        // TODO: Add PHPUnit coverage for daily/monthly RRULE parsing once admin tests exist.
        $state = [
            'enabled' => false,
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => [],
            'end_type' => 'never',
            'end_count' => null,
            'end_date' => '',
        ];

        $rrule = trim($rrule);
        if ($rrule === '') {
            return $state;
        }

        try {
            $rule = new RecurrRule($rrule, null, null, $this->get_site_timezone()->getName());
        } catch (InvalidRRule $e) {
            return $state;
        }

        $frequency = strtolower($rule->getFreqAsText() ?? '');
        if (! in_array($frequency, array_keys(self::FREQUENCY_OPTIONS), true)) {
            return $state;
        }

        $state['enabled'] = true;
        $state['frequency'] = $frequency;
        $state['interval'] = max(1, (int) $rule->getInterval());

        if ($frequency === 'weekly') {
            $codes = $rule->getByDay() ?? [];
            $names = [];
            foreach ($codes as $code) {
                $name = $this->weekday_code_to_name($code);
                if ($name) {
                    $names[] = $name;
                }
            }
            $state['weekdays'] = array_values(array_unique($names));
        }

        $count = $rule->getCount();
        if ($count) {
            $state['end_type'] = 'count';
            $state['end_count'] = max(1, (int) $count);
        } elseif ($rule->getUntil() instanceof \DateTimeInterface) {
            $until = $rule->getUntil();
            if (! $until instanceof DateTimeImmutable) {
                $until = DateTimeImmutable::createFromInterface($until);
            }
            $state['end_type'] = 'until';
            $state['end_date'] = $until
                ->setTimezone($this->get_site_timezone())
                ->format('Y-m-d');
        }

        return $state;
    }

    private function resolve_form_state(array $meta): array
    {
        $state = $this->parse_rrule_to_form_state((string) ($meta[Event_Meta_Repository::META_RRULE] ?? ''));

        if (! $state['enabled']) {
            $state['enabled'] = ! empty($meta[Event_Meta_Repository::META_IS_RECURRING]);
            $state['frequency'] = 'weekly';
            $state['interval'] = max(1, (int) ($meta[Event_Meta_Repository::META_RECURRENCE_INTERVAL] ?? 1));
            $state['weekdays'] = $meta[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS] ?? [];
        }

        return $state;
    }

    private function format_until_value(string $date, ?DateTimeImmutable $start): ?string
    {
        if ($date === '') {
            return null;
        }

        $timezone = $start?->getTimezone() ?? $this->get_site_timezone();

        try {
            $until = new DateTimeImmutable($date . ' 23:59:59', $timezone);
        } catch (\Exception $e) {
            return null;
        }

        return $until
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }

    private function sanitize_frequency(string $value): string
    {
        $value = strtolower($value);

        return in_array($value, array_keys(self::FREQUENCY_OPTIONS), true) ? $value : 'weekly';
    }

    private function sanitize_end_type(string $value): string
    {
        $value = strtolower($value);
        $allowed = ['never', 'count', 'until'];

        return in_array($value, $allowed, true) ? $value : 'never';
    }

    private function weekday_name_to_code(string $day): ?string
    {
        $day = strtolower($day);

        return self::WEEKDAY_NAME_TO_CODE[$day] ?? null;
    }

    private function weekday_code_to_name(string $code): ?string
    {
        $code = strtoupper($code);

        return self::WEEKDAY_CODE_TO_NAME[$code] ?? null;
    }

    private function get_site_timezone(): DateTimeZone
    {
        static $timezone;

        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        if (function_exists('wp_timezone')) {
            $candidate = wp_timezone();
            if ($candidate instanceof DateTimeZone) {
                return $timezone = $candidate;
            }
        }

        return $timezone = new DateTimeZone(date_default_timezone_get());
    }

}
