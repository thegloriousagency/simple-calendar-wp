<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Admin;

use Glorious\ChurchEvents\Support\Location_Helper;
use function absint;
use function get_option;
use function sanitize_text_field;

/**
 * Provides typed access to plugin-wide settings stored in cec_settings.
 */
final class Settings
{
    public const OPTION_KEY = 'cec_settings';

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $options;

    private function __construct()
    {
        $this->options = $this->load_options();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function refresh(): void
    {
        $this->options = $this->load_options();
    }

    public function get_week_start(): string
    {
        $value = sanitize_text_field((string) ($this->options['week_start'] ?? 'sunday'));

        return $value === 'monday' ? 'monday' : 'sunday';
    }

    public function get_default_view(): string
    {
        $value = sanitize_text_field((string) ($this->options['default_view'] ?? 'month'));

        return $value === 'month' ? 'month' : 'month';
    }

    public function get_list_default_limit(): int
    {
        $limit = absint((int) ($this->options['list_default_limit'] ?? 5));

        return max(1, $limit);
    }

    public function is_advanced_recurrence_enabled(): bool
    {
        return (bool) ($this->options['advanced_recurrence_enabled'] ?? true);
    }

    public function get_default_location_mode(): string
    {
        $mode = sanitize_text_field((string) ($this->options['default_location_mode'] ?? 'none'));
        $allowed = ['none', 'text', 'cpt'];

        return in_array($mode, $allowed, true) ? $mode : 'none';
    }

    public function get_default_location_text(): string
    {
        return sanitize_text_field((string) ($this->options['default_location_text'] ?? ''));
    }

    public function get_default_location_id(): int
    {
        return Location_Helper::sanitize_location_id((int) ($this->options['default_location_id'] ?? 0));
    }

    public function get_default_location_string(): string
    {
        $mode = $this->get_default_location_mode();

        if ($mode === 'text') {
            return $this->get_default_location_text();
        }

        if ($mode === 'cpt') {
            return Location_Helper::get_location_label($this->get_default_location_id());
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function load_options(): array
    {
        $defaults = [
            'week_start' => 'sunday',
            'default_view' => 'month',
            'list_default_limit' => 5,
            'advanced_recurrence_enabled' => true,
            'default_location_mode' => 'none',
            'default_location_text' => '',
            'default_location_id' => 0,
        ];

        $stored = (array) get_option(self::OPTION_KEY, []);

        return array_merge($defaults, $stored);
    }
}

