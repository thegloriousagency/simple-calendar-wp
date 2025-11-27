<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents;

use Glorious\ChurchEvents\Admin\Assets as Admin_Assets;
use Glorious\ChurchEvents\Admin\Settings;
use Glorious\ChurchEvents\Admin\Settings_Page;
use Glorious\ChurchEvents\Admin\Tools as Admin_Tools;
use Glorious\ChurchEvents\Frontend\Assets as Frontend_Assets;
use Glorious\ChurchEvents\Meta\Event_Meta_Boxes;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use Glorious\ChurchEvents\Meta\Location_Meta_Boxes;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Post_Types\Location_Post_Type;
use Glorious\ChurchEvents\Integrations\Polylang_Integration;
use Glorious\ChurchEvents\Rest\Calendar_Controller;
use Glorious\ChurchEvents\Rest\Events_Controller;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Shortcodes\Calendar_Shortcode;
use Glorious\ChurchEvents\Shortcodes\List_Shortcode;
use Glorious\ChurchEvents\Support\Cache_Helper;
use Glorious\ChurchEvents\Support\Hooks;
use Glorious\ChurchEvents\Taxonomies\Event_Category_Taxonomy;
use Glorious\ChurchEvents\Taxonomies\Event_Tag_Taxonomy;
use Glorious\ChurchEvents\Templates\Template_Loader;
use function basename;
use function defined;
use function dirname;
use function flush_rewrite_rules;
use function load_plugin_textdomain;
use const CHURCH_EVENTS_CALENDAR_BASENAME;

/**
 * Primary plugin bootstrapper responsible for wiring all components.
 */
final class Plugin
{
    private string $base_path;
    private string $base_url;

    public function __construct(string $base_path, string $base_url)
    {
        $this->base_path = rtrim($base_path, '/');
        $this->base_url  = rtrim($base_url, '/');
    }

    /**
     * Bootstraps all plugin services.
     */
    public function register(): void
    {
        Hooks::register();

        Hooks::add_action('init', [$this, 'load_textdomain']);
        Cache_Helper::register_invalidation_hooks();

        $template_loader = new Template_Loader($this->path());
        $meta_repository = new Event_Meta_Repository();
        $settings = Settings::instance();
        (new Settings_Page($settings))->register();
        $frontend_assets = new Frontend_Assets($this->path(), $this->url());
        $frontend_assets->register();

        (new Event_Post_Type($template_loader))->register();
        (new Location_Post_Type())->register();
        (new Event_Category_Taxonomy())->register();
        (new Event_Tag_Taxonomy())->register();
        (new Event_Meta_Boxes($meta_repository))->register();
        (new Location_Meta_Boxes())->register();
        (new Admin_Assets($this->path(), $this->url()))->register();
        $calendar_shortcode = new Calendar_Shortcode(null, null, $template_loader, $frontend_assets);
        $calendar_shortcode->register();
        (new Calendar_Controller($calendar_shortcode))->register();
        (new Events_Controller(new Event_Meta_Repository(), new Recurrence_Engine()))->register();
        (new List_Shortcode(null, $template_loader, $frontend_assets))->register();
        (new Admin_Tools($meta_repository))->register();
        (new Polylang_Integration())->register();
    }

    /**
     * Handles rewrite setup tasks during plugin activation.
     */
    public function activate(): void
    {
        $template_loader = new Template_Loader($this->path());
        (new Event_Post_Type($template_loader))->register_post_type();
        (new Location_Post_Type())->register_post_type();
        (new Event_Category_Taxonomy())->register_taxonomy();
        (new Event_Tag_Taxonomy())->register_taxonomy();

        flush_rewrite_rules();
    }

    /**
     * Flushes rewrites on plugin deactivation.
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Loads the plugin textdomain.
     */
    public function load_textdomain(): void
    {
        $languages_path = defined('CHURCH_EVENTS_CALENDAR_BASENAME')
            ? dirname(CHURCH_EVENTS_CALENDAR_BASENAME) . '/languages'
            : basename($this->path()) . '/languages';

        load_plugin_textdomain(
            'church-events-calendar',
            false,
            $languages_path
        );
    }

    public function path(string $relative = ''): string
    {
        return $this->base_path . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    public function url(string $relative = ''): string
    {
        return $this->base_url . ($relative ? '/' . ltrim($relative, '/') : '');
    }
}