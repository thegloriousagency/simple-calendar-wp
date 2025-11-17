<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Meta;

use DateTimeImmutable;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule as RecurrRule;
use WP_Post;
use function add_meta_box;
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
use function sort;
use function sanitize_text_field;
use function selected;
use function strpos;
use function strtoupper;
use function trim;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;

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
     * @param WP_Post $post
     */
    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('church_event_meta', 'church_event_meta_nonce');

        $meta = $this->repository->get_meta((int) $post->ID);
        $parsed_rrule = $this->parse_weekly_rrule($meta[Event_Meta_Repository::META_RRULE] ?? '');

        if ($parsed_rrule['enabled']) {
            $meta[Event_Meta_Repository::META_IS_RECURRING] = true;
            $meta[Event_Meta_Repository::META_RECURRENCE_INTERVAL] = $parsed_rrule['interval'];
            $meta[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS] = $parsed_rrule['weekdays'];
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
        ?>
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
            <div class="church-event-meta-field">
                <label for="church-event-location">
                    <?php esc_html_e('Location', 'church-events-calendar'); ?>
                </label>
                <input type="text"
                    id="church-event-location"
                    name="<?php echo esc_attr(Event_Meta_Repository::META_LOCATION); ?>"
                    value="<?php echo esc_attr((string) $meta[Event_Meta_Repository::META_LOCATION]); ?>"
                    class="widefat"
                />
            </div>
            <div class="church-event-meta-field church-event-meta-recurring">
                <label>
                    <input type="checkbox"
                        name="<?php echo esc_attr(Event_Meta_Repository::META_IS_RECURRING); ?>"
                        value="1" <?php checked($meta[Event_Meta_Repository::META_IS_RECURRING], true); ?>
                    />
                    <?php esc_html_e('Recurring Event', 'church-events-calendar'); ?>
                </label>
                <div class="church-event-recurring-details">
                    <label>
                        <?php esc_html_e('Repeat Every (weeks)', 'church-events-calendar'); ?>
                        <input type="number"
                            min="1"
                            name="<?php echo esc_attr(Event_Meta_Repository::META_RECURRENCE_INTERVAL); ?>"
                            value="<?php echo esc_attr((string) $meta[Event_Meta_Repository::META_RECURRENCE_INTERVAL]); ?>"
                        />
                    </label>
                    <fieldset>
                        <legend><?php esc_html_e('Weekdays', 'church-events-calendar'); ?></legend>
                        <?php foreach ($weekdays as $key => $label) : ?>
                            <label class="church-event-weekday">
                                <input type="checkbox"
                                    name="<?php echo esc_attr(Event_Meta_Repository::META_RECURRENCE_WEEKDAYS); ?>[]"
                                    value="<?php echo esc_attr($key); ?>"
                                    <?php checked(in_array($key, $meta[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS], true), true); ?>
                                />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>
            </div>
        </div>
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
        $end_date = isset($_POST['_event_end_date'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_date']))
            : '';
        $end_hour = isset($_POST['_event_end_hour'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_hour']))
            : '';
        $end_minute = isset($_POST['_event_end_minute'])
            ? sanitize_text_field(wp_unslash($_POST['_event_end_minute']))
            : '';
        $end_meridiem = isset($_POST['_event_end_meridiem'])
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

        $data = [
            Event_Meta_Repository::META_START => $this->combine_datetime($start_date, $start_hour, $start_minute, $start_meridiem),
            Event_Meta_Repository::META_END => $this->combine_datetime($end_date, $end_hour, $end_minute, $end_meridiem),
            Event_Meta_Repository::META_LOCATION => isset($_POST[Event_Meta_Repository::META_LOCATION])
                ? wp_unslash($_POST[Event_Meta_Repository::META_LOCATION])
                : '',
            Event_Meta_Repository::META_ALL_DAY => ! empty($_POST[Event_Meta_Repository::META_ALL_DAY]),
            Event_Meta_Repository::META_IS_RECURRING => ! empty($_POST[Event_Meta_Repository::META_IS_RECURRING]),
            Event_Meta_Repository::META_RECURRENCE_INTERVAL => max(1, $recurrence_interval),
            Event_Meta_Repository::META_RECURRENCE_WEEKDAYS => $recurrence_weekdays,
        ];

        $data[Event_Meta_Repository::META_RRULE] = $this->build_weekly_rrule(
            (bool) $data[Event_Meta_Repository::META_IS_RECURRING],
            (int) $data[Event_Meta_Repository::META_RECURRENCE_INTERVAL],
            $recurrence_weekdays
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
     * Data flow: UI form fields → weekly form state → RRULE string stored in meta.
     * When editing, the RRULE becomes the canonical source and is parsed back
     * into form state so legacy weekly controls stay in sync.
     */
    private function build_weekly_rrule(bool $enabled, int $interval, array $weekdays): string
    {
        if (! $enabled) {
            return '';
        }

        $interval = max(1, $interval);
        $codes = [];

        foreach ($weekdays as $day) {
            $code = $this->weekday_name_to_code($day);
            if ($code) {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        if (empty($codes)) {
            return '';
        }

        return sprintf('FREQ=WEEKLY;INTERVAL=%d;BYDAY=%s', $interval, implode(',', $codes));
    }

    /**
     * @return array{enabled: bool, interval: int, weekdays: array<int, string>}
     */
    private function parse_weekly_rrule(string $rrule): array
    {
        $state = [
            'enabled' => false,
            'interval' => 1,
            'weekdays' => [],
        ];

        $rrule = trim($rrule);
        if ($rrule === '') {
            return $state;
        }

        $parts = [];

        foreach (explode(';', strtoupper($rrule)) as $segment) {
            if (strpos($segment, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $segment, 2));
            if ($key !== '') {
                $parts[$key] = $value;
            }
        }

        if (($parts['FREQ'] ?? '') !== 'WEEKLY') {
            return $state;
        }

        $interval = isset($parts['INTERVAL']) ? max(1, (int) $parts['INTERVAL']) : 1;
        $weekdays = [];

        if (isset($parts['BYDAY'])) {
            foreach (explode(',', $parts['BYDAY']) as $code) {
                $code = strtoupper(trim($code));
                if (isset(self::WEEKDAY_CODE_TO_NAME[$code])) {
                    $weekdays[] = self::WEEKDAY_CODE_TO_NAME[$code];
                }
            }
        }

        $weekdays = array_values(array_unique($weekdays));

        return [
            'enabled' => true,
            'interval' => $interval,
            'weekdays' => $weekdays,
        ];
    }

    private function weekday_name_to_code(string $day): ?string
    {
        $day = strtolower($day);

        return self::WEEKDAY_NAME_TO_CODE[$day] ?? null;
    }
}
