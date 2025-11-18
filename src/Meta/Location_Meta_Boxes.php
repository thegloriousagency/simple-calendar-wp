<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Meta;

use Glorious\ChurchEvents\Post_Types\Location_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;
use WP_Post;
use function add_meta_box;
use function current_user_can;
use function esc_attr;
use function esc_html_e;
use function get_post_meta;
use function sanitize_text_field;
use function update_post_meta;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Provides simple address/map inputs for the Locations CPT.
 */
final class Location_Meta_Boxes
{
    public const META_ADDRESS = '_church_location_address';
    public const META_MAP_LINK = '_church_location_map';

    public function register(): void
    {
        Hooks::add_action('add_meta_boxes', [$this, 'add_meta_box']);
        Hooks::add_action('save_post_' . Location_Post_Type::POST_TYPE, [$this, 'handle_save'], 10, 2);
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'church_location_details',
            esc_html__('Location Details', 'church-events-calendar'),
            [$this, 'render_meta_box'],
            Location_Post_Type::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('church_location_meta', 'church_location_meta_nonce');
        $address = get_post_meta($post->ID, self::META_ADDRESS, true);
        $map = get_post_meta($post->ID, self::META_MAP_LINK, true);
        ?>
        <p>
            <label for="church-location-address">
                <?php esc_html_e('Address / Directions', 'church-events-calendar'); ?>
            </label><br>
            <textarea id="church-location-address" name="church_location_address" class="large-text" rows="3"><?php echo esc_attr((string) $address); ?></textarea>
        </p>
        <p>
            <label for="church-location-map">
                <?php esc_html_e('Map URL (optional)', 'church-events-calendar'); ?>
            </label><br>
            <input type="url" id="church-location-map" name="church_location_map" class="large-text" value="<?php echo esc_attr((string) $map); ?>">
        </p>
        <?php
    }

    public function handle_save(int $post_id, WP_Post $post): void
    {
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (! isset($_POST['church_location_meta_nonce']) || ! wp_verify_nonce(
            sanitize_text_field(wp_unslash((string) $_POST['church_location_meta_nonce'])),
            'church_location_meta'
        )) {
            return;
        }

        $address = isset($_POST['church_location_address'])
            ? sanitize_text_field(wp_unslash((string) $_POST['church_location_address']))
            : '';
        $map = isset($_POST['church_location_map'])
            ? sanitize_text_field(wp_unslash((string) $_POST['church_location_map']))
            : '';

        update_post_meta($post_id, self::META_ADDRESS, $address);
        update_post_meta($post_id, self::META_MAP_LINK, $map);
    }
}

