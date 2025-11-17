<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Templates;

use function array_unique;
use function extract;
use function file_exists;
use function get_stylesheet_directory;
use function get_template_directory;
use function ob_get_clean;
use function ob_start;
use function trailingslashit;

/**
 * Resolves template overrides and renders template files.
 */
final class Template_Loader
{
    private string $templates_directory;

    public function __construct(?string $base_path = null)
    {
        $base_path = $base_path ? rtrim($base_path, '/\\') : dirname(__DIR__, 2);
        $this->templates_directory = $base_path . '/templates';
    }

    public function locate(string $template): string
    {
        $template = ltrim($template, '/');
        $theme_directories = array_unique([
            trailingslashit(get_stylesheet_directory()),
            trailingslashit(get_template_directory()),
        ]);

        foreach ($theme_directories as $theme_directory) {
            $theme_template = $theme_directory . 'church-events/' . $template;
            if (file_exists($theme_template)) {
                return $theme_template;
            }
        }

        $plugin_template = $this->templates_directory . '/' . $template;

        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        $file = $this->locate($template);

        if (! $file) {
            return '';
        }

        if (! empty($context)) {
            extract($context, EXTR_SKIP);
        }

        ob_start();
        include $file;

        return ob_get_clean() ?: '';
    }
}
