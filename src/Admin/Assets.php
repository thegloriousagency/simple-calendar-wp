<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Admin;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Support\Hooks;
use function file_exists;
use function function_exists;
use function get_current_screen;
use function trailingslashit;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

/**
 * Handles admin-only assets for editing events.
 */
final class Assets
{
    private string $base_path;
    private string $base_url;
    private string $version;

    public function __construct(string $base_path, string $base_url, string $version = '0.1.0')
    {
        $this->base_path = rtrim($base_path, '/');
        $this->base_url = rtrim($base_url, '/');
        $this->version = $version;
    }

    public function register(): void
    {
        Hooks::add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || $screen->post_type !== Event_Post_Type::POST_TYPE) {
            return;
        }

        wp_register_style(
            'church-events-admin',
            $this->resolve_asset_url('admin.css', 'assets/css/admin.css'),
            [],
            $this->version
        );

        wp_register_script(
            'church-events-admin',
            $this->resolve_asset_url('admin.js', 'assets/js/admin.js'),
            [],
            $this->version,
            true
        );

        wp_enqueue_style('church-events-admin');
        wp_enqueue_script('church-events-admin');
    }

    private function resolve_asset_url(string $dist_file, string $fallback): string
    {
        $dist_path = $this->base_path . '/assets/dist/' . $dist_file;

        if (file_exists($dist_path)) {
            return trailingslashit($this->base_url) . 'assets/dist/' . $dist_file;
        }

        return trailingslashit($this->base_url) . $fallback;
    }
}
