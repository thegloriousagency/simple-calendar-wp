<?php
/**
 * @var array<int, array{post: WP_Post, start: DateTimeImmutable, end: DateTimeImmutable, meta: array<string, mixed>}> $event_items
 */

$event_items = $event_items ?? [];
?>
<div class="church-events-list">
    <?php if (empty($event_items)) : ?>
        <p class="church-events-list__empty">
            <?php esc_html_e('No upcoming events found.', 'church-events-calendar'); ?>
        </p>
    <?php else : ?>
        <ul class="church-events-list__items">
            <?php foreach ($event_items as $current_event) : ?>
                <?php include __DIR__ . '/event-loop-item.php'; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
