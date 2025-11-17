<?php
/**
 * @var array{post: WP_Post, start: DateTimeImmutable, end: DateTimeImmutable, meta: array<string, mixed>} $current_event
 */

$location = $current_event['meta'][Glorious\ChurchEvents\Meta\Event_Meta_Repository::META_LOCATION] ?? '';
?>
<li class="church-events-list__item">
    <div class="church-events-list__datetime">
        <time datetime="<?php echo esc_attr($current_event['start']->format('c')); ?>">
            <?php echo esc_html($current_event['start']->format('M j, Y g:i a')); ?>
        </time>
    </div>
    <div class="church-events-list__content">
        <a href="<?php echo esc_url(get_permalink($current_event['post'])); ?>">
            <?php echo esc_html(get_the_title($current_event['post'])); ?>
        </a>
        <?php if (! empty($location)) : ?>
            <div class="church-events-list__location">
                <?php echo esc_html($location); ?>
            </div>
        <?php endif; ?>
    </div>
</li>
