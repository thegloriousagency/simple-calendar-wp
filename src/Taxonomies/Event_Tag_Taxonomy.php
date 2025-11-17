<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Taxonomies;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;

/**
 * Registers the non-hierarchical event tag taxonomy.
 */
final class Event_Tag_Taxonomy
{
    public const TAXONOMY = 'church_event_tag';

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
                'hierarchical' => false,
                'show_in_rest' => true,
                'rewrite' => [
                    'slug' => 'church-event-tag',
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
            'name' => __('Event Tags', 'church-events-calendar'),
            'singular_name' => __('Event Tag', 'church-events-calendar'),
            'search_items' => __('Search Event Tags', 'church-events-calendar'),
            'popular_items' => __('Popular Event Tags', 'church-events-calendar'),
            'all_items' => __('All Event Tags', 'church-events-calendar'),
            'edit_item' => __('Edit Event Tag', 'church-events-calendar'),
            'update_item' => __('Update Event Tag', 'church-events-calendar'),
            'add_new_item' => __('Add New Event Tag', 'church-events-calendar'),
            'new_item_name' => __('New Event Tag Name', 'church-events-calendar'),
            'separate_items_with_commas' => __('Separate event tags with commas', 'church-events-calendar'),
            'add_or_remove_items' => __('Add or remove event tags', 'church-events-calendar'),
            'choose_from_most_used' => __('Choose from the most used event tags', 'church-events-calendar'),
            'menu_name' => __('Event Tags', 'church-events-calendar'),
        ];
    }
}
