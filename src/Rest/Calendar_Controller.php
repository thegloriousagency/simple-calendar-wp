<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Rest;

use Glorious\ChurchEvents\Shortcodes\Calendar_Shortcode;
use WP_REST_Request;
use WP_REST_Server;
use function current_time;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_unslash;

/**
 * REST controller responsible for returning rendered calendar markup.
 */
final class Calendar_Controller
{
    private Calendar_Shortcode $shortcode;

    public function __construct(Calendar_Shortcode $shortcode)
    {
        $this->shortcode = $shortcode;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'church-events/v1',
            '/month-view',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_month_view'],
                'permission_callback' => '__return_true',
                'args' => [
                    'year' => [
                        'type' => 'integer',
                        'required' => false,
                    ],
                    'month' => [
                        'type' => 'integer',
                        'required' => false,
                    ],
                    'category' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public function handle_month_view(WP_REST_Request $request)
    {
        $year = $this->sanitize_year($request->get_param('year'));
        $month = $this->sanitize_month($request->get_param('month'));
        $category = $this->sanitize_category($request->get_param('category'));

        $html = $this->shortcode->render_calendar_markup(
            [
                'year' => $year,
                'month' => $month,
                'category' => $category,
            ]
        );

        return rest_ensure_response(
            [
                'html' => $html,
                'meta' => [
                    'year' => $year,
                    'month' => $month,
                    'category' => $category,
                ],
            ]
        );
    }

    private function sanitize_year($value): int
    {
        $year = (int) ($value ?? current_time('Y'));

        return max(1970, min(2100, $year));
    }

    private function sanitize_month($value): int
    {
        $month = (int) ($value ?? current_time('n'));

        return max(1, min(12, $month));
    }

    private function sanitize_category($value): string
    {
        if ($value === null || $value === '' || $value === 'all') {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }
}

