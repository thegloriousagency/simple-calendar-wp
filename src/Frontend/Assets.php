<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Frontend;

use Glorious\ChurchEvents\Support\Hooks;
use function current_time;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function trailingslashit;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_create_nonce;

/**
 * Handles frontend styles and scripts.
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
        Hooks::add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'church-events-frontend',
            $this->resolve_asset_url('frontend.css', 'assets/css/frontend.css'),
            [],
            $this->version
        );

        wp_register_script(
            'church-events-frontend',
            $this->resolve_asset_url('frontend.js', 'assets/js/frontend.js'),
            [],
            $this->version,
            true
        );

        wp_localize_script(
            'church-events-frontend',
            'CECCalendarSettings',
            [
                'restUrl' => esc_url_raw(rest_url('church-events/v1/month-view')),
                'nonce' => wp_create_nonce('wp_rest'),
                'today' => [
                    'year' => (int) current_time('Y'),
                    'month' => (int) current_time('n'),
                ],
            ]
        );
    }

    public function enqueue(): void
    {
        wp_enqueue_style('church-events-frontend');
        wp_enqueue_script('church-events-frontend');
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
