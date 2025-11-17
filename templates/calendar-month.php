<?php
/**
 * @var DateTimeImmutable $month_date
 * @var array<int, array<int, array{date: DateTimeImmutable, is_current_month: bool, events: array<int, array<string, mixed>>}>> $weeks
 */

$weeks = $weeks_data ?? [];
$month_label = $month_date ?? new DateTimeImmutable('first day of this month');
?>
<div class="church-events-calendar">
    <div class="church-events-calendar__header">
        <h2>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s date */
                    __('%s', 'church-events-calendar'),
                    $month_label->format('F Y')
                )
            );
            ?>
        </h2>
    </div>
    <div class="church-events-calendar__grid">
        <div class="church-events-calendar__weekdays">
            <?php
            $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            foreach ($weekdays as $weekday) :
                ?>
                <div class="church-events-calendar__weekday">
                    <?php echo esc_html($weekday); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php foreach ($weeks as $week) : ?>
            <div class="church-events-calendar__week">
                <?php foreach ($week as $day) : ?>
                    <?php
                    $day_classes = 'church-events-calendar__day';
                    if (empty($day['is_current_month'])) {
                        $day_classes .= ' church-events-calendar__day--muted';
                    }
                    ?>
                    <div class="<?php echo esc_attr($day_classes); ?>">
                        <span class="church-events-calendar__date">
                            <?php echo esc_html($day['date']->format('j')); ?>
                        </span>
                        <?php if (! empty($day['events'])) : ?>
                            <ul class="church-events-calendar__events">
                                <?php foreach ($day['events'] as $event) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_permalink($event['post'])); ?>">
                                            <?php echo esc_html(get_the_title($event['post'])); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <span class="church-events-calendar__no-events"><?php esc_html_e('No events', 'church-events-calendar'); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
