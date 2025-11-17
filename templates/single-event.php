<?php
/**
 * Single event template.
 *
 * @var WP_Query $wp_query
 */

use Glorious\ChurchEvents\Meta\Event_Meta_Repository;

wp_enqueue_style('church-events-frontend');
wp_enqueue_script('church-events-frontend');

get_header();

$repository = new Event_Meta_Repository();
$date_format = get_option('date_format', 'F j, Y');
$time_format = get_option('time_format', 'g:i a');
?>

<div class="church-event-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php
            $meta = $repository->get_meta(get_the_ID());
            $start = ! empty($meta[Event_Meta_Repository::META_START])
                ? new DateTimeImmutable($meta[Event_Meta_Repository::META_START])
                : null;
            $end = ! empty($meta[Event_Meta_Repository::META_END])
                ? new DateTimeImmutable($meta[Event_Meta_Repository::META_END])
                : null;
            $all_day = ! empty($meta[Event_Meta_Repository::META_ALL_DAY]);
            $location = $meta[Event_Meta_Repository::META_LOCATION] ?? '';
            ?>
            <article <?php post_class('church-event-single__article'); ?>>
                <header class="church-event-single__header">
                    <h1 class="church-event-single__title"><?php the_title(); ?></h1>
                    <div class="church-event-single__meta">
                        <?php if ($start) : ?>
                            <div class="church-event-single__meta-item">
                                <strong><?php esc_html_e('Starts:', 'church-events-calendar'); ?></strong>
                                <span>
                                    <?php
                                    echo esc_html(
                                        $start->format(
                                            $all_day
                                                ? $date_format
                                                : "{$date_format} {$time_format}"
                                        )
                                    );
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($end) : ?>
                            <div class="church-event-single__meta-item">
                                <strong><?php esc_html_e('Ends:', 'church-events-calendar'); ?></strong>
                                <span>
                                    <?php
                                    echo esc_html(
                                        $end->format(
                                            $all_day
                                                ? $date_format
                                                : "{$date_format} {$time_format}"
                                        )
                                    );
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($location) : ?>
                            <div class="church-event-single__meta-item">
                                <strong><?php esc_html_e('Location:', 'church-events-calendar'); ?></strong>
                                <span><?php echo esc_html($location); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="church-event-single__content">
                    <?php the_content(); ?>
                </div>

                <footer class="church-event-single__footer">
                    <?php
                    $categories = get_the_term_list(get_the_ID(), 'church_event_category', '', ', ');
                    $tags = get_the_term_list(get_the_ID(), 'church_event_tag', '', ', ');
                    ?>
                    <?php if ($categories) : ?>
                        <div class="church-event-single__taxonomy">
                            <strong><?php esc_html_e('Categories:', 'church-events-calendar'); ?></strong>
                            <span><?php echo wp_kses_post($categories); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($tags) : ?>
                        <div class="church-event-single__taxonomy">
                            <strong><?php esc_html_e('Tags:', 'church-events-calendar'); ?></strong>
                            <span><?php echo wp_kses_post($tags); ?></span>
                        </div>
                    <?php endif; ?>
                </footer>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p><?php esc_html_e('Event not found.', 'church-events-calendar'); ?></p>
    <?php endif; ?>
</div>

<?php
get_footer();
