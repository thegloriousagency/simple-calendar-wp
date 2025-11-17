<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Post_Types;

use Glorious\ChurchEvents\Support\Hooks;
use Glorious\ChurchEvents\Templates\Template_Loader;

/**
 * Registers and manages the `church_event` custom post type.
 */
final class Event_Post_Type
{
    public const POST_TYPE = 'church_event';

    private Template_Loader $template_loader;

    public function __construct(?Template_Loader $template_loader = null)
    {
        $this->template_loader = $template_loader ?? new Template_Loader();
    }

    /**
     * Hooks WordPress actions and filters for the post type.
     */
    public function register(): void
    {
        Hooks::add_action('init', [$this, 'register_post_type']);
        Hooks::add_filter('single_template', [$this, 'filter_single_template']);
        Hooks::add_filter('archive_template', [$this, 'filter_archive_template']);
    }

    /**
     * Registers the custom post type with WordPress.
     */
    public function register_post_type(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => $this->get_labels(),
                'public' => true,
                'show_in_rest' => true,
                'has_archive' => true,
                'menu_icon' => 'dashicons-calendar-alt',
                'rewrite' => [
                    'slug' => 'church-events',
                    'with_front' => false,
                ],
                'supports' => ['title', 'editor', 'thumbnail'],
                'taxonomies' => [
                    'church_event_category',
                    'church_event_tag',
                ],
            ]
        );
    }

    /**
     * Returns localized labels for the post type.
     *
     * @return array<string, string>
     */
    private function get_labels(): array
    {
        return [
            'name' => __('Events', 'church-events-calendar'),
            'singular_name' => __('Event', 'church-events-calendar'),
            'menu_name' => __('Events', 'church-events-calendar'),
            'name_admin_bar' => __('Event', 'church-events-calendar'),
            'add_new' => __('Add New', 'church-events-calendar'),
            'add_new_item' => __('Add New Event', 'church-events-calendar'),
            'edit_item' => __('Edit Event', 'church-events-calendar'),
            'new_item' => __('New Event', 'church-events-calendar'),
            'view_item' => __('View Event', 'church-events-calendar'),
            'search_items' => __('Search Events', 'church-events-calendar'),
            'not_found' => __('No events found.', 'church-events-calendar'),
            'not_found_in_trash' => __('No events found in Trash.', 'church-events-calendar'),
            'all_items' => __('All Events', 'church-events-calendar'),
            'archives' => __('Event Archives', 'church-events-calendar'),
            'attributes' => __('Event Attributes', 'church-events-calendar'),
        ];
    }

    /**
     * Resolves the single template path for events.
     */
    public function filter_single_template(string $template): string
    {
        if (! is_singular(self::POST_TYPE)) {
            return $template;
        }

        $located = $this->template_loader->locate('single-event.php');

        return $located ?: $template;
    }

    /**
     * Resolves the archive template path for events.
     */
    public function filter_archive_template(string $template): string
    {
        if (! is_post_type_archive(self::POST_TYPE)) {
            return $template;
        }

        $located = $this->template_loader->locate('calendar-month.php');

        return $located ?: $template;
    }
}
