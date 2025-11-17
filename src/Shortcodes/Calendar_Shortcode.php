<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Shortcodes;

use DateTimeImmutable;
use Glorious\ChurchEvents\Calendar\Calendar_Query;
use Glorious\ChurchEvents\Calendar\Month_View;
use Glorious\ChurchEvents\Frontend\Assets as Frontend_Assets;
use Glorious\ChurchEvents\Templates\Template_Loader;
use function add_shortcode;
use function current_time;
use function plugin_dir_url;
use function shortcode_atts;

/**
 * Renders the `[church_event_calendar]` shortcode.
 */
final class Calendar_Shortcode
{
    private Calendar_Query $calendar_query;
    private Month_View $month_view;
    private Template_Loader $template_loader;
    private Frontend_Assets $assets;

    public function __construct(
        ?Calendar_Query $calendar_query = null,
        ?Month_View $month_view = null,
        ?Template_Loader $template_loader = null,
        ?Frontend_Assets $assets = null
    ) {
        $this->calendar_query = $calendar_query ?? new Calendar_Query();
        $this->month_view = $month_view ?? new Month_View();
        $this->template_loader = $template_loader ?? new Template_Loader();
        if ($assets) {
            $this->assets = $assets;
        } else {
            $plugin_root = dirname(__DIR__, 2);
            $plugin_file = $plugin_root . '/church-events-calendar.php';
            $this->assets = new Frontend_Assets($plugin_root, plugin_dir_url($plugin_file));
        }
    }

    public function register(): void
    {
        add_shortcode('church_event_calendar', [$this, 'render_shortcode']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render_shortcode(array $atts): string
    {
        $atts = shortcode_atts(
            [
                'year' => (int) current_time('Y'),
                'month' => (int) current_time('n'),
                'category' => null,
                'tag' => null,
            ],
            $atts
        );

        $monthDate = new DateTimeImmutable(sprintf('%d-%02d-01', (int) $atts['year'], (int) $atts['month']));
        $events = $this->calendar_query->get_month_events($atts);
        $weeks = $this->month_view->render($events, $monthDate);

        $this->assets->enqueue();

        return $this->template_loader->render(
            'calendar-month.php',
            [
                'month_date' => $monthDate,
                'weeks_data' => $weeks,
            ]
        );
    }
}
