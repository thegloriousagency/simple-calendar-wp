<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Support;

use function function_exists;

final class Language_Helper
{
    public static function is_polylang_active(): bool
    {
        return function_exists('pll_get_post_language');
    }

    public static function get_post_language(int $post_id): ?string
    {
        if (! self::is_polylang_active()) {
            return null;
        }

        return pll_get_post_language($post_id, 'slug') ?: null;
    }

    public static function get_default_language(): ?string
    {
        if (! self::is_polylang_active() || ! function_exists('pll_default_language')) {
            return null;
        }

        return pll_default_language('slug') ?: null;
    }

    public static function is_primary_post(int $post_id): bool
    {
        if (! self::is_polylang_active()) {
            return true;
        }

        $language = self::get_post_language($post_id);
        $default = self::get_default_language();

        if ($language === null || $default === null) {
            return true;
        }

        return $language === $default;
    }

    /**
     * @return array<string,int>
     */
    public static function get_post_translations(int $post_id): array
    {
        if (! self::is_polylang_active() || ! function_exists('pll_get_post_translations')) {
            return [];
        }

        $translations = pll_get_post_translations($post_id);

        return is_array($translations) ? $translations : [];
    }

    public static function get_primary_post_id(int $post_id): ?int
    {
        $translations = self::get_post_translations($post_id);
        if ($translations === []) {
            return null;
        }

        $default = self::get_default_language();
        if ($default !== null && isset($translations[$default])) {
            return (int) $translations[$default];
        }

        return (int) array_values($translations)[0];
    }
}

