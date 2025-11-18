<?php
/**
 * @var array{post: WP_Post, start: DateTimeImmutable, end: DateTimeImmutable, meta: array<string, mixed>} $current_event
 */

$location = $current_event['meta'][Glorious\ChurchEvents\Meta\Event_Meta_Repository::META_LOCATION] ?? '';
?>
<li class="cec-events-list__item">
    <div class="cec-events-list__date">
        <time class="cec-events-list__time" datetime="<?php echo esc_attr($current_event['start']->format('c')); ?>">
            <?php echo esc_html($current_event['start']->format('M j, Y g:i a')); ?>
        </time>
    </div>
    <div class="cec-events-list__body">
        <a class="cec-events-list__title" href="<?php echo esc_url(get_permalink($current_event['post'])); ?>">
            <?php echo esc_html(get_the_title($current_event['post'])); ?>
        </a>
        <?php if (! empty($location)) : ?>
            <div class="cec-events-list__meta">
                <?php echo esc_html($location); ?>
            </div>
        <?php endif; ?>
    </div>
</li>
