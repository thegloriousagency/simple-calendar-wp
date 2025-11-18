<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Post_Types;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;
use function __;
use function add_action;
use function register_post_type;

/**
 * Registers the reusable Locations post type for storing venue details.
 */
final class Location_Post_Type
{
    public const POST_TYPE = 'church_location';

    public function register(): void
    {
        Hooks::add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        $labels = [
            'name' => __('Locations', 'church-events-calendar'),
            'singular_name' => __('Location', 'church-events-calendar'),
            'add_new' => __('Add New', 'church-events-calendar'),
            'add_new_item' => __('Add New Location', 'church-events-calendar'),
            'edit_item' => __('Edit Location', 'church-events-calendar'),
            'new_item' => __('New Location', 'church-events-calendar'),
            'view_item' => __('View Location', 'church-events-calendar'),
            'search_items' => __('Search Locations', 'church-events-calendar'),
            'not_found' => __('No locations found.', 'church-events-calendar'),
            'not_found_in_trash' => __('No locations found in Trash.', 'church-events-calendar'),
            'all_items' => __('All Locations', 'church-events-calendar'),
        ];

        register_post_type(
            self::POST_TYPE,
            [
                'labels' => $labels,
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'edit.php?post_type=' . Event_Post_Type::POST_TYPE,
                'supports' => ['title', 'editor'],
                'has_archive' => false,
                'rewrite' => false,
                'menu_position' => 21,
            ]
        );
    }
}

