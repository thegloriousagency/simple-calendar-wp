<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Support;

/**
 * Convenience wrapper for WordPress hook registration.
 */
final class Hooks
{
    public static function register(): void
    {
        // Placeholder for centralized hook registration.
    }

    /**
     * Adds a WordPress action.
     */
    public static function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        add_action($hook, $callback, $priority, $accepted_args);
    }

    /**
     * Adds a WordPress filter.
     */
    public static function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        add_filter($hook, $callback, $priority, $accepted_args);
    }
}
