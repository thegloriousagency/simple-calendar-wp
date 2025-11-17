<?php
/**
 * Plugin Name: Church Events Calendar
 * Plugin URI: https://glorious.org/plugins/church-events-calendar
 * Description: Lightweight events calendar for churches with clean architecture scaffolding.
 * Version: 0.1.0
 * Author: Glorious
 * Author URI: https://glorious.org
 * Text Domain: church-events-calendar
 * Domain Path: /languages
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('CHURCH_EVENTS_CALENDAR_VERSION')) {
    define('CHURCH_EVENTS_CALENDAR_VERSION', '0.1.0');
}

if (! defined('CHURCH_EVENTS_CALENDAR_FILE')) {
    define('CHURCH_EVENTS_CALENDAR_FILE', __FILE__);
}

if (! defined('CHURCH_EVENTS_CALENDAR_PATH')) {
    define('CHURCH_EVENTS_CALENDAR_PATH', plugin_dir_path(__FILE__));
}

if (! defined('CHURCH_EVENTS_CALENDAR_URL')) {
    define('CHURCH_EVENTS_CALENDAR_URL', plugin_dir_url(__FILE__));
}

if (! defined('CHURCH_EVENTS_CALENDAR_BASENAME')) {
    define('CHURCH_EVENTS_CALENDAR_BASENAME', plugin_basename(__FILE__));
}

require_once __DIR__ . '/vendor/autoload.php';

if (! class_exists('Glorious\\ChurchEvents\\Plugin')) {
    spl_autoload_register(
        static function (string $class): void {
            $prefix = 'Glorious\\ChurchEvents\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $relative_path = CHURCH_EVENTS_CALENDAR_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';

            if (is_readable($relative_path)) {
                require_once $relative_path;
            }
        },
        true,
        true
    );
}

use Glorious\ChurchEvents\Plugin;

register_activation_hook(
    __FILE__,
    static function (): void {
        $plugin = new Plugin(CHURCH_EVENTS_CALENDAR_PATH, CHURCH_EVENTS_CALENDAR_URL);
        $plugin->activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        $plugin = new Plugin(CHURCH_EVENTS_CALENDAR_PATH, CHURCH_EVENTS_CALENDAR_URL);
        $plugin->deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        $plugin = new Plugin(CHURCH_EVENTS_CALENDAR_PATH, CHURCH_EVENTS_CALENDAR_URL);
        $plugin->register();
    }
);
