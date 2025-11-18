<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Admin;

use DateInterval;
use DateTimeImmutable;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Recurrence\Recurrence_Rule;
use Glorious\ChurchEvents\Support\Cache_Helper;
use Glorious\ChurchEvents\Support\Hooks;
use Glorious\ChurchEvents\Support\Log_Helper;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule as RecurrRule;
use WP_Post;
use function absint;
use function add_submenu_page;
use function array_slice;
use function check_admin_referer;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function get_post;
use function get_posts;
use function implode;
use function sanitize_text_field;
use function selected;
use function wp_nonce_field;
use function wp_unslash;
use function wp_timezone;

/**
 * Admin Tools page for debugging events, caches, and logs.
 */
final class Tools
{
    private Event_Meta_Repository $repository;
    private Recurrence_Engine $engine;
    /** @var array<int, array{type:string,message:string}> */
    private array $notices = [];

    public function __construct(?Event_Meta_Repository $repository = null, ?Recurrence_Engine $engine = null)
    {
        $this->repository = $repository ?? new Event_Meta_Repository();
        $this->engine = $engine ?? new Recurrence_Engine();
    }

    public function register(): void
    {
        Hooks::add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . Event_Post_Type::POST_TYPE,
            esc_html__('Church Events Tools', 'church-events-calendar'),
            esc_html__('Tools', 'church-events-calendar'),
            'manage_options',
            'church-events-tools',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $this->handle_post_actions();

        $selectedEventId = isset($_GET['cec_event_id']) ? absint($_GET['cec_event_id']) : 0;
        $event = $selectedEventId ? get_post($selectedEventId) : null;
        $eventData = null;

        if ($event instanceof WP_Post && $event->post_type === Event_Post_Type::POST_TYPE) {
            $eventData = $this->build_event_debug_payload($event);
        } elseif ($selectedEventId) {
            $this->add_notice('error', esc_html__('Event not found or invalid ID.', 'church-events-calendar'));
        }

        $versions = Cache_Helper::get_versions();
        $cacheEnabled = Cache_Helper::is_cache_enabled();
        $monthTtl = Cache_Helper::get_month_cache_ttl();
        $eventsTtl = Cache_Helper::get_events_cache_ttl();
        $logEntries = $this->get_log_entries();
        $logPath = $this->get_log_path_safe();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Church Events Tools', 'church-events-calendar'); ?></h1>
            <?php $this->render_notices(); ?>

            <?php $this->render_event_inspector($selectedEventId, $eventData); ?>
            <?php $this->render_cache_inspector($versions, $cacheEnabled, $monthTtl, $eventsTtl); ?>
            <?php $this->render_log_viewer($logEntries, $logPath); ?>
        </div>
        <?php
    }

    private function handle_post_actions(): void
    {
        if (empty($_POST['cec_tools_action'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash((string) $_POST['cec_tools_action']));

        switch ($action) {
            case 'clear_cache':
                check_admin_referer('cec_clear_cache');
                Cache_Helper::flush_all_caches();
                $this->add_notice('updated', esc_html__('Church Events caches cleared.', 'church-events-calendar'));
                break;
            case 'clear_log':
                check_admin_referer('cec_clear_log');
                Log_Helper::clear();
                $this->add_notice('updated', esc_html__('Church Events log file cleared.', 'church-events-calendar'));
                break;
        }
    }

    private function render_event_inspector(int $selectedEventId, ?array $eventData): void
    {
        $events = get_posts(
            [
                'post_type' => Event_Post_Type::POST_TYPE,
                'post_status' => 'any',
                'posts_per_page' => 50,
                'orderby' => 'date',
                'order' => 'DESC',
            ]
        );

        ?>
        <hr>
        <h2><?php esc_html_e('Event Debug Inspector', 'church-events-calendar'); ?></h2>
        <form method="get">
            <input type="hidden" name="page" value="church-events-tools">
            <input type="hidden" name="post_type" value="<?php echo esc_attr(Event_Post_Type::POST_TYPE); ?>">
            <label for="cec_event_id"><?php esc_html_e('Select Event', 'church-events-calendar'); ?></label>
            <select id="cec_event_id" name="cec_event_id">
                <option value="0"><?php esc_html_e('Choose an event…', 'church-events-calendar'); ?></option>
                <?php foreach ($events as $event) : ?>
                    <option value="<?php echo esc_attr((string) $event->ID); ?>" <?php selected($selectedEventId, $event->ID); ?>>
                        <?php echo esc_html(sprintf('%s (#%d)', $event->post_title ?: esc_html__('(no title)', 'church-events-calendar'), $event->ID)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Inspect Event', 'church-events-calendar'); ?>
            </button>
        </form>
        <?php if ($eventData) : ?>
            <h3><?php echo esc_html(sprintf(__('Event: %s (#%d)', 'church-events-calendar'), $eventData['title'], $eventData['id'])); ?></h3>
            <?php $this->render_meta_table(__('Raw Meta', 'church-events-calendar'), $eventData['raw_meta']); ?>
            <?php $this->render_meta_table(__('Parsed RRULE', 'church-events-calendar'), $eventData['parsed_rrule']); ?>
            <?php $this->render_occurrences_table($eventData['occurrences']); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render_meta_table(string $title, array $data): void
    {
        ?>
        <h4><?php echo esc_html($title); ?></h4>
        <?php if ($data === []) : ?>
            <p><?php esc_html_e('No data available.', 'church-events-calendar'); ?></p>
            <?php return; ?>
        <?php endif; ?>
        <table class="widefat striped">
            <tbody>
                <?php foreach ($data as $key => $value) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) $key); ?></th>
                        <td>
                            <?php
                            if (is_array($value)) {
                                echo '<code>' . esc_html(implode(', ', array_map('strval', $value))) . '</code>';
                            } else {
                                echo '<code>' . esc_html((string) $value) . '</code>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<int, array{start:string,end:?string,all_day:bool}> $occurrences
     */
    private function render_occurrences_table(array $occurrences): void
    {
        ?>
        <h4><?php esc_html_e('Next 10 Occurrences', 'church-events-calendar'); ?></h4>
        <?php if ($occurrences === []) : ?>
            <p><?php esc_html_e('No upcoming occurrences were found in the next year.', 'church-events-calendar'); ?></p>
            <?php return; ?>
        <?php endif; ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Start', 'church-events-calendar'); ?></th>
                    <th><?php esc_html_e('End', 'church-events-calendar'); ?></th>
                    <th><?php esc_html_e('All Day?', 'church-events-calendar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($occurrences as $occurrence) : ?>
                    <tr>
                        <td><?php echo esc_html($occurrence['start']); ?></td>
                        <td><?php echo esc_html($occurrence['end'] ?? '—'); ?></td>
                        <td><?php echo $occurrence['all_day'] ? esc_html__('Yes', 'church-events-calendar') : esc_html__('No', 'church-events-calendar'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_cache_inspector(array $versions, bool $cacheEnabled, int $monthTtl, int $eventsTtl): void
    {
        ?>
        <hr>
        <h2><?php esc_html_e('Cache Inspector', 'church-events-calendar'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Month cache version', 'church-events-calendar'); ?></th>
                    <td><?php echo esc_html((string) $versions['month']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Events cache version', 'church-events-calendar'); ?></th>
                    <td><?php echo esc_html((string) $versions['events']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Month cache TTL', 'church-events-calendar'); ?></th>
                    <td><?php echo esc_html(sprintf('%d seconds (~%0.2f hours)', $monthTtl, $monthTtl / 3600)); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Events cache TTL', 'church-events-calendar'); ?></th>
                    <td><?php echo esc_html(sprintf('%d seconds (~%0.2f minutes)', $eventsTtl, $eventsTtl / 60)); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Caching enabled', 'church-events-calendar'); ?></th>
                    <td><?php echo $cacheEnabled ? esc_html__('Yes', 'church-events-calendar') : esc_html__('No (via cec_enable_cache)', 'church-events-calendar'); ?></td>
                </tr>
            </tbody>
        </table>
        <form method="post">
            <?php wp_nonce_field('cec_clear_cache'); ?>
            <input type="hidden" name="cec_tools_action" value="clear_cache">
            <button type="submit" class="button button-secondary">
                <?php esc_html_e('Clear all Church Events caches', 'church-events-calendar'); ?>
            </button>
        </form>
        <?php
    }

    private function render_log_viewer(array $entries, ?string $path): void
    {
        ?>
        <hr>
        <h2><?php esc_html_e('Log Viewer', 'church-events-calendar'); ?></h2>
        <?php if ($path === null) : ?>
            <p><?php esc_html_e('Unable to determine log path.', 'church-events-calendar'); ?></p>
        <?php else : ?>
            <p>
                <?php esc_html_e('Log file:', 'church-events-calendar'); ?>
                <code><?php echo esc_html($path); ?></code>
            </p>
        <?php endif; ?>

        <pre style="max-height: 300px; overflow:auto; background:#fff; padding:1rem; border:1px solid #ccd0d4;"><?php
            if ($entries === []) {
                echo esc_html__('(Log is empty)', 'church-events-calendar');
            } else {
                echo esc_html(implode("\n", $entries));
            }
        ?></pre>

        <form method="post" style="margin-top:1rem;">
            <?php wp_nonce_field('cec_clear_log'); ?>
            <input type="hidden" name="cec_tools_action" value="clear_log">
            <button type="submit" class="button button-secondary">
                <?php esc_html_e('Clear log', 'church-events-calendar'); ?>
            </button>
        </form>
        <?php
    }

    /**
     * @return array<string, mixed>
     */
    private function build_event_debug_payload(WP_Post $post): array
    {
        $meta = $this->repository->get_meta($post->ID);
        $rule = Recurrence_Rule::fromMeta($post->ID, $this->repository);
        $parsedRrule = $this->parse_rrule_details($rule->getRrule());
        $occurrences = $this->preview_occurrences($rule, (bool) ($meta[Event_Meta_Repository::META_ALL_DAY] ?? false));

        return [
            'id' => $post->ID,
            'title' => $post->post_title ?: esc_html__('(no title)', 'church-events-calendar'),
            'raw_meta' => [
                '_event_start' => $meta[Event_Meta_Repository::META_START] ?? '',
                '_event_end' => $meta[Event_Meta_Repository::META_END] ?? '',
                '_event_rrule' => $meta[Event_Meta_Repository::META_RRULE] ?? '',
                '_event_exdates' => $meta[Event_Meta_Repository::META_EXDATES] ?? [],
                '_event_rdates' => $meta[Event_Meta_Repository::META_RDATES] ?? [],
                '_event_all_day' => $meta[Event_Meta_Repository::META_ALL_DAY] ?? false,
                '_event_location' => $meta[Event_Meta_Repository::META_LOCATION] ?? '',
                '_event_location_mode' => $meta[Event_Meta_Repository::META_LOCATION_MODE] ?? 'default',
                '_event_location_id' => $meta[Event_Meta_Repository::META_LOCATION_ID] ?? 0,
                '_event_is_recurring' => $meta[Event_Meta_Repository::META_IS_RECURRING] ?? false,
                '_event_recurrence_interval' => $meta[Event_Meta_Repository::META_RECURRENCE_INTERVAL] ?? '',
                '_event_recurrence_weekdays' => $meta[Event_Meta_Repository::META_RECURRENCE_WEEKDAYS] ?? [],
            ],
            'parsed_rrule' => $parsedRrule,
            'occurrences' => $occurrences,
        ];
    }

    /**
     * @return array<string, string|int|null|array>
     */
    private function parse_rrule_details(string $rrule): array
    {
        if ($rrule === '') {
            return [
                'status' => esc_html__('Non-recurring event (no RRULE).', 'church-events-calendar'),
            ];
        }

        try {
            $rule = new RecurrRule($rrule);

            return [
                'frequency' => strtoupper($rule->getFreqAsText()),
                'interval' => $rule->getInterval(),
                'byday' => implode(', ', $rule->getByDay() ?? []),
                'bymonthday' => implode(', ', $rule->getByMonthDay() ?? []),
                'count' => $rule->getCount(),
                'until' => $rule->getUntil() ? $rule->getUntil()->format('Y-m-d H:i:s') : null,
            ];
        } catch (InvalidRRule $exception) {
            return [
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array{start:string,end:?string,all_day:bool}>
     */
    private function preview_occurrences(Recurrence_Rule $rule, bool $allDay): array
    {
        $timezone = wp_timezone();
        $start = new DateTimeImmutable('now', $timezone);
        $end = $start->add(new DateInterval('P1Y'));
        $occurrences = $this->engine->expand($rule, $start, $end);
        $occurrences = array_slice($occurrences, 0, 10);

        return array_map(
            static fn(array $occurrence): array => [
                'start' => $occurrence['start']->format('Y-m-d H:i:s'),
                'end' => $occurrence['end'] ? $occurrence['end']->format('Y-m-d H:i:s') : null,
                'all_day' => $allDay,
            ],
            $occurrences
        );
    }

    private function render_notices(): void
    {
        foreach ($this->notices as $notice) {
            printf(
                '<div class="%1$s notice"><p>%2$s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    private function add_notice(string $type, string $message): void
    {
        $this->notices[] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function get_log_entries(): array
    {
        try {
            return Log_Helper::get_tail(200);
        } catch (\Throwable $exception) {
            $this->add_notice('error', esc_html($exception->getMessage()));

            return [];
        }
    }

    private function get_log_path_safe(): ?string
    {
        try {
            return Log_Helper::get_log_path();
        } catch (\Throwable) {
            return null;
        }
    }
}

