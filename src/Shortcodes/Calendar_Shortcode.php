<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Shortcodes;

use DateTimeImmutable;
use Glorious\ChurchEvents\Calendar\Calendar_Query;
use Glorious\ChurchEvents\Calendar\Month_View;
use Glorious\ChurchEvents\Frontend\Assets as Frontend_Assets;
use Glorious\ChurchEvents\Templates\Template_Loader;
use Glorious\ChurchEvents\Support\Cache_Helper;
use WP_Term;
use function add_query_arg;
use function add_shortcode;
use function current_time;
use function determine_locale;
use function filter_input;
use function get_terms;
use function is_wp_error;
use function get_locale;
use function plugin_dir_url;
use function sanitize_text_field;
use function shortcode_atts;
use function wp_unslash;
use function function_exists;
use function pll_current_language;
use const FILTER_SANITIZE_FULL_SPECIAL_CHARS;
use const FILTER_SANITIZE_NUMBER_INT;
use const INPUT_GET;

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
                'year' => '',
                'month' => '',
                'category' => '',
                'default_year' => (int) current_time('Y'),
                'default_month' => (int) current_time('n'),
                'default_category' => '',
            ],
            $atts
        );

        $params = $this->resolve_parameters($atts);

        $this->assets->enqueue();

        return $this->render_calendar_markup($params, $this->resolve_language_context($params['language'] ?? null));
    }

    /**
     * Render the calendar markup for a given year/month/category.
     *
     * @param array{year:int, month:int, category:string, language?:?string} $params
     */
    public function render_calendar_markup(array $params, ?string $language = null): string
    {
        $category = $this->sanitize_category($params['category']);
        $locale = $this->get_locale_code();
        $language = $language ?? $this->get_language_code();
        $cache_key = Cache_Helper::build_month_cache_key(
            $params['year'],
            $params['month'],
            $category,
            $locale,
            $language
        );

        $cached = Cache_Helper::get_cached($cache_key);
        if ($cached !== null) {
            return (string) $cached;
        }

        $context = $this->build_calendar_context(
            $params['year'],
            $params['month'],
            $category,
            $language
        );

        $html = $this->template_loader->render('calendar-month.php', $context);

        Cache_Helper::set_cached($cache_key, $html, Cache_Helper::get_month_cache_ttl());

        return $html;
    }

    /**
     * @return array{month_date: DateTimeImmutable, weeks_data: array, categories: array<int, WP_Term>, selected_category: string, nav: array<string, array<string, mixed>>, language: string}
     */
    private function build_calendar_context(int $year, int $month, string $category, string $language): array
    {
        $monthDate = new DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $queryArgs = [
            'year' => $year,
            'month' => $month,
        ];

        if ($language !== 'default' && $language !== '') {
            $queryArgs['language'] = $language;
        }

        if ($category !== '') {
            $queryArgs['category'] = $category;
        }

        $events = $this->calendar_query->get_month_events($queryArgs);
        $weeks = $this->month_view->render($events, $monthDate);

        return [
            'month_date' => $monthDate,
            'weeks_data' => $weeks,
            'categories' => $this->get_categories(),
            'selected_category' => $category,
            'nav' => $this->build_navigation_links($monthDate, $category, $language),
            'language' => $language,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve_parameters(array $atts): array
    {
        $defaultYear = (int) ($atts['default_year'] ?: current_time('Y'));
        $defaultMonth = (int) ($atts['default_month'] ?: current_time('n'));
        $defaultCategory = (string) ($atts['default_category'] ?? '');

        $year = (int) ($atts['year'] ?: $defaultYear);
        $month = (int) ($atts['month'] ?: $defaultMonth);
        $category = (string) ($atts['category'] ?: $defaultCategory);

        $getYear = filter_input(INPUT_GET, 'cec_year', FILTER_SANITIZE_NUMBER_INT);
        $getMonth = filter_input(INPUT_GET, 'cec_month', FILTER_SANITIZE_NUMBER_INT);
        $getCategory = filter_input(INPUT_GET, 'cec_category', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $getLanguage = filter_input(INPUT_GET, 'cec_lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($getYear !== null && $getYear !== false) {
            $year = (int) $getYear;
        }

        if ($getMonth !== null && $getMonth !== false) {
            $month = (int) $getMonth;
        }

        if ($getCategory !== null && $getCategory !== false) {
            $category = $getCategory;
        }

        $year = max(1970, min(2100, $year));
        $month = max(1, min(12, $month));
        $category = $this->sanitize_category($category);

        $language = null;
        if ($getLanguage !== null && $getLanguage !== false) {
            $language = $getLanguage;
        } elseif (isset($atts['language'])) {
            $language = (string) $atts['language'];
        } elseif (isset($atts['lang'])) {
            $language = (string) $atts['lang'];
        }

        return [
            'year' => $year,
            'month' => $month,
            'category' => $category,
            'language' => $language,
        ];
    }

    /**
     * @return array<int, WP_Term>
     */
    private function get_categories(): array
    {
        static $terms;

        if (isset($terms)) {
            return $terms;
        }

        $terms = get_terms(
            [
                'taxonomy' => 'church_event_category',
                'hide_empty' => false,
            ]
        );

        if (is_wp_error($terms)) {
            $terms = [];
        }

        return $terms;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function build_navigation_links(DateTimeImmutable $monthDate, string $category, string $language): array
    {
        $prev = $monthDate->modify('-1 month');
        $next = $monthDate->modify('+1 month');
        $today = new DateTimeImmutable('first day of this month');

        return [
            'prev' => $this->build_nav_item($prev, $category, $language),
            'next' => $this->build_nav_item($next, $category, $language),
            'today' => $this->build_nav_item($today, $category, $language),
            'current_label' => $this->format_month_label($monthDate, $language),
        ];
    }

    /**
     * @return array{year:int, month:int, url:string}
     */
    private function build_nav_item(DateTimeImmutable $date, string $category, string $language): array
    {
        $args = [
            'cec_year' => $date->format('Y'),
            'cec_month' => $date->format('n'),
        ];

        if ($category !== '') {
            $args['cec_category'] = $category;
        }
        if ($language !== '' && $language !== 'default') {
            $args['cec_lang'] = $language;
        }

        return [
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'url' => add_query_arg($args),
        ];
    }

    private function format_month_label(DateTimeImmutable $date, string $language): string
    {
        if (function_exists('pll__')) {
            $month_key = 'cec_month_' . strtolower($date->format('F'));
            $registered = pll__($month_key);
            if ($registered !== $month_key) {
                return sprintf('%s %s', $registered, $date->format('Y'));
            }
        }

        return $date->format('F Y');
    }

    private function sanitize_category(?string $category): string
    {
        if ($category === null) {
            return '';
        }

        $category = sanitize_text_field(wp_unslash($category));

        return $category === 'all' ? '' : $category;
    }

    private function get_locale_code(): string
    {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
            if ($locale) {
                return $locale;
            }
        }

        $locale = get_locale();

        return $locale ?: 'default';
    }

    private function get_language_code(): string
    {
        if (function_exists('pll_current_language')) {
            $language = (string) pll_current_language('slug');
            if ($language !== '') {
                return $language;
            }
        }

        return 'default';
    }

    private function resolve_language_context(?string $language): string
    {
        if ($language !== null && $language !== '') {
            $language = sanitize_text_field(wp_unslash($language));
            if ($language !== '') {
                return $language;
            }
        }

        return $this->get_language_code();
    }
}
