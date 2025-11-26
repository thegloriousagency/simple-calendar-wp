<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Support;

use Glorious\ChurchEvents\Meta\Location_Meta_Boxes;
use Glorious\ChurchEvents\Post_Types\Location_Post_Type;
use WP_Post;
use function absint;
use function get_post;
use function get_post_meta;
use function get_posts;
use function sanitize_text_field;

/**
 * Helper utilities for interacting with saved church locations.
 */
final class Location_Helper
{
    /**
     * @return array<int, WP_Post>
     */
    public static function get_location_posts(): array
    {
        return get_posts(
            [
                'post_type' => Location_Post_Type::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'orderby' => 'title',
                'order' => 'ASC',
            ]
        );
    }

    public static function sanitize_location_id(int $location_id): int
    {
        $location_id = absint($location_id);
        $post = $location_id ? get_post($location_id) : null;

        return ($post instanceof WP_Post && $post->post_type === Location_Post_Type::POST_TYPE) ? $location_id : 0;
    }

    public static function get_location_label(int $location_id): string
    {
        $location_id = self::sanitize_location_id($location_id);
        if ($location_id === 0) {
            return '';
        }

        $post = get_post($location_id);
        if (! ($post instanceof WP_Post)) {
            return '';
        }

        $address = sanitize_text_field((string) get_post_meta($location_id, Location_Meta_Boxes::META_ADDRESS, true));

        return trim($post->post_title . ($address ? ' – ' . $address : ''));
    }
}



