<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Taxonomies;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;

/**
 * Registers the hierarchical event category taxonomy.
 */
final class Event_Category_Taxonomy
{
    public const TAXONOMY = 'church_event_category';

    /**
     * Hooks WordPress to register the taxonomy.
     */
    public function register(): void
    {
        Hooks::add_action('init', [$this, 'register_taxonomy']);
    }

    /**
     * Registers the taxonomy with WordPress.
     */
    public function register_taxonomy(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            [Event_Post_Type::POST_TYPE],
            [
                'labels' => $this->get_labels(),
                'hierarchical' => true,
                'show_in_rest' => true,
                'rewrite' => [
                    'slug' => 'church-event-category',
                    'with_front' => false,
                ],
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    private function get_labels(): array
    {
        return [
            'name' => __('Event Categories', 'church-events-calendar'),
            'singular_name' => __('Event Category', 'church-events-calendar'),
            'search_items' => __('Search Event Categories', 'church-events-calendar'),
            'all_items' => __('All Event Categories', 'church-events-calendar'),
            'parent_item' => __('Parent Event Category', 'church-events-calendar'),
            'parent_item_colon' => __('Parent Event Category:', 'church-events-calendar'),
            'edit_item' => __('Edit Event Category', 'church-events-calendar'),
            'update_item' => __('Update Event Category', 'church-events-calendar'),
            'add_new_item' => __('Add New Event Category', 'church-events-calendar'),
            'new_item_name' => __('New Event Category Name', 'church-events-calendar'),
            'menu_name' => __('Event Categories', 'church-events-calendar'),
        ];
    }
}
