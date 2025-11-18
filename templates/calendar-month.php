<?php
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
/**
 * Month view template.
 *
 * @var DateTimeImmutable $month_date
 * @var array<int, array<int, array{date: DateTimeImmutable, is_current_month: bool, events: array<int, array<string, mixed>>}>> $weeks_data
 * @var array<int, WP_Term> $categories
 * @var string $selected_category
 * @var array<string, array<string, mixed>> $nav
 */

$weeks = $weeks_data ?? [];
$month = $month_date ?? new DateTimeImmutable('first day of this month');
$selected_category = isset($selected_category) ? (string) $selected_category : '';
$nav = $nav ?? [];

$prev = $nav['prev'] ?? ['year' => (int) $month->format('Y'), 'month' => (int) $month->format('n'), 'url' => '#'];
$next = $nav['next'] ?? ['year' => (int) $month->format('Y'), 'month' => (int) $month->format('n'), 'url' => '#'];
$today = $nav['today'] ?? ['year' => (int) current_time('Y'), 'month' => (int) current_time('n'), 'url' => '#'];
?>
<div
    class="cec-calendar"
    data-cec-calendar="1"
    data-cec-year="<?php echo esc_attr($month->format('Y')); ?>"
    data-cec-month="<?php echo esc_attr($month->format('n')); ?>"
    data-cec-category="<?php echo esc_attr($selected_category); ?>"
>
    <div class="cec-calendar__nav">
        <div class="cec-calendar__nav-left">
            <button
                type="button"
                class="cec-calendar__nav-btn"
                data-cec-nav="prev"
                data-target-year="<?php echo esc_attr((string) $prev['year']); ?>"
                data-target-month="<?php echo esc_attr((string) $prev['month']); ?>"
            >
                <?php esc_html_e('Previous', 'church-events-calendar'); ?>
            </button>
            <div class="cec-calendar__current-label">
                <?php echo esc_html($month->format('F Y')); ?>
            </div>
            <button
                type="button"
                class="cec-calendar__nav-btn"
                data-cec-nav="next"
                data-target-year="<?php echo esc_attr((string) $next['year']); ?>"
                data-target-month="<?php echo esc_attr((string) $next['month']); ?>"
            >
                <?php esc_html_e('Next', 'church-events-calendar'); ?>
            </button>
        </div>
        <div class="cec-calendar__nav-right">
            <button
                type="button"
                class="cec-calendar__nav-btn cec-calendar__nav-btn--today"
                data-cec-nav="today"
                data-target-year="<?php echo esc_attr((string) $today['year']); ?>"
                data-target-month="<?php echo esc_attr((string) $today['month']); ?>"
            >
                <?php esc_html_e('Today', 'church-events-calendar'); ?>
            </button>
            <?php if (! empty($categories)) : ?>
                <label class="cec-calendar__filter">
                    <span class="screen-reader-text"><?php esc_html_e('Filter by category', 'church-events-calendar'); ?></span>
                    <select name="cec-category" class="cec-calendar__filter-select" data-cec-filter>
                        <option value=""><?php esc_html_e('All categories', 'church-events-calendar'); ?></option>
                        <?php foreach ($categories as $category) : ?>
                            <option
                                value="<?php echo esc_attr($category->slug); ?>"
                                <?php selected($selected_category, $category->slug); ?>
                            >
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    </div>
    <div class="cec-calendar__grid" data-cec-calendar-body>
        <div class="cec-calendar__weekdays">
            <?php
$weekday_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            foreach ($weekday_labels as $weekday_label) :
                ?>
                <div class="cec-calendar__weekdays-item">
                    <?php echo esc_html($weekday_label); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php foreach ($weeks as $week) : ?>
            <div class="cec-calendar__week">
                <?php foreach ($week as $day) : ?>
                    <?php
                    $day_classes = ['cec-calendar__day'];
                    if (empty($day['is_current_month'])) {
                        $day_classes[] = 'cec-calendar__day--muted';
                    }
                    ?>
                    <div class="<?php echo esc_attr(implode(' ', $day_classes)); ?>">
                        <div class="cec-calendar__day-header">
                            <span class="cec-calendar__day-number">
                                <?php echo esc_html($day['date']->format('j')); ?>
                            </span>
                        </div>
                        <div class="cec-calendar__day-events">
                            <?php if (! empty($day['events'])) : ?>
                                <?php foreach ($day['events'] as $event) : ?>
                                    <div class="cec-calendar__event">
                                        <a class="cec-calendar__event-title" href="<?php echo esc_url(get_permalink($event['post'])); ?>">
                                            <?php echo esc_html(get_the_title($event['post'])); ?>
                                        </a>
                                        <?php if (! empty($event['meta'][Event_Meta_Repository::META_START])) : ?>
                                            <div class="cec-calendar__event-time">
                                                <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($event['meta'][Event_Meta_Repository::META_START]))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="cec-calendar__event cec-calendar__event--empty">
                                    <?php esc_html_e('No events', 'church-events-calendar'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
