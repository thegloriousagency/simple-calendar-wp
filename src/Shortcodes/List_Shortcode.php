<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Shortcodes;

use Glorious\ChurchEvents\Calendar\Calendar_Query;
use Glorious\ChurchEvents\Frontend\Assets as Frontend_Assets;
use Glorious\ChurchEvents\Templates\Template_Loader;
use function add_shortcode;
use function plugin_dir_url;
use function shortcode_atts;

/**
 * Renders the `[church_event_list]` shortcode.
 */
final class List_Shortcode
{
    private Calendar_Query $calendar_query;
    private Template_Loader $template_loader;
    private Frontend_Assets $assets;

    public function __construct(
        ?Calendar_Query $calendar_query = null,
        ?Template_Loader $template_loader = null,
        ?Frontend_Assets $assets = null
    ) {
        $this->calendar_query = $calendar_query ?? new Calendar_Query();
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
        add_shortcode('church_event_list', [$this, 'render_shortcode']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render_shortcode(array $atts): string
    {
        $atts = shortcode_atts(
            [
                'limit' => 5,
                'category' => null,
                'tag' => null,
            ],
            $atts
        );

        $limit = max(1, (int) $atts['limit']);
        $events = $this->calendar_query->get_upcoming_events($limit, $atts);

        $this->assets->enqueue();

        return $this->template_loader->render(
            'event-list.php',
            [
                'event_items' => $events,
            ]
        );
    }
}
