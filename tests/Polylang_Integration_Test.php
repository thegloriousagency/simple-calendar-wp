<?php

declare(strict_types=1);

use Glorious\ChurchEvents\Calendar\Calendar_Query;
use Glorious\ChurchEvents\Integrations\Polylang_Integration;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Rest\Events_Controller;
use PHPUnit\Framework\TestCase;

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID;

        public function __construct(int $ID)
        {
            $this->ID = $ID;
        }
    }
}

final class Polylang_Test_Registry
{
    public static array $post_languages = [];
    public static array $post_meta = [];
    public static array $translations = [];
    public static array $permalinks = [];
    public static ?string $current_language = null;
    public static ?string $default_language = 'en';
    public static array $thumbnails = [];

    public static function reset(): void
    {
        self::$post_languages = [];
        self::$post_meta = [];
        self::$translations = [];
        self::$permalinks = [];
        self::$current_language = null;
        self::$default_language = 'en';
        self::$thumbnails = [];
    }
}

if (! function_exists('pll_get_post_language')) {
    function pll_get_post_language(int $post_id, string $field = 'slug'): ?string
    {
        unset($field);

        return Polylang_Test_Registry::$post_languages[$post_id] ?? null;
    }
}

if (! function_exists('pll_current_language')) {
    function pll_current_language(string $field = 'slug'): ?string
    {
        unset($field);

        return Polylang_Test_Registry::$current_language;
    }
}

if (! function_exists('pll_default_language')) {
    function pll_default_language(string $field = 'slug'): ?string
    {
        unset($field);

        return Polylang_Test_Registry::$default_language;
    }
}

if (! function_exists('pll_get_post')) {
    function pll_get_post(int $post_id, string $language)
    {
        return Polylang_Test_Registry::$translations[$post_id][$language] ?? null;
    }
}

if (! function_exists('pll_get_post_translations')) {
    function pll_get_post_translations(int $post_id): array
    {
        $language = Polylang_Test_Registry::$post_languages[$post_id] ?? (Polylang_Test_Registry::$default_language ?? 'en');

        return Polylang_Test_Registry::$translations[$post_id] ?? [$language => $post_id];
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return is_string($value) ? trim($value) : $value;
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key, bool $single = false)
    {
        unset($single);

        return Polylang_Test_Registry::$post_meta[$post_id][$key] ?? '';
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $key, $value): void
    {
        Polylang_Test_Registry::$post_meta[$post_id][$key] = $value;
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta(int $post_id, string $key): void
    {
        unset(Polylang_Test_Registry::$post_meta[$post_id][$key]);
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(int $post_id): string
    {
        return Polylang_Test_Registry::$permalinks[$post_id] ?? 'permalink-' . $post_id;
    }
}

if (! function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id(int $post_id): ?int
    {
        return Polylang_Test_Registry::$thumbnails[$post_id] ?? null;
    }
}

if (! function_exists('set_post_thumbnail')) {
    function set_post_thumbnail(int $post_id, int $thumbnail_id): void
    {
        Polylang_Test_Registry::$thumbnails[$post_id] = $thumbnail_id;
    }
}

if (! function_exists('delete_post_thumbnail')) {
    function delete_post_thumbnail(int $post_id): void
    {
        unset(Polylang_Test_Registry::$thumbnails[$post_id]);
    }
}

final class Polylang_Integration_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Polylang_Test_Registry::reset();
    }

    public function test_sync_includes_all_event_meta_keys(): void
    {
        Polylang_Test_Registry::$post_languages = [
            1 => 'en',
            2 => 'uk',
        ];
        $integration = new Polylang_Integration();

        $keys = $integration->sync_event_meta_keys([], true, 1, 2);

        $this->assertContains('_event_rrule', $keys);
        $this->assertContains('_event_start', $keys);
        $this->assertContains('_event_location', $keys);
        $this->assertContains('_thumbnail_id', $keys);
    }

    public function test_sync_skips_meta_keys_when_source_is_translation(): void
    {
        Polylang_Test_Registry::$post_languages = [
            1 => 'en',
            2 => 'uk',
        ];
        $integration = new Polylang_Integration();

        $keys = $integration->sync_event_meta_keys([], true, 2, 1);

        $this->assertNotContains('_event_start', $keys);
    }

    public function test_sync_translations_copies_meta_and_thumbnail(): void
    {
        Polylang_Test_Registry::$post_languages = [
            1 => 'en',
            2 => 'uk',
        ];
        Polylang_Test_Registry::$translations = [
            1 => ['en' => 1, 'uk' => 2],
            2 => ['en' => 1, 'uk' => 2],
        ];
        Polylang_Test_Registry::$post_meta = [
            1 => [
                '_event_start' => '2024-01-01 09:00:00',
                '_event_end' => '2024-01-01 10:00:00',
                '_event_all_day' => '',
                '_event_rrule' => 'FREQ=WEEKLY',
            ],
        ];
        Polylang_Test_Registry::$thumbnails = [
            1 => 555,
        ];

        $integration = new Polylang_Integration();
        $integration->sync_translations(1);

        $this->assertSame('2024-01-01 09:00:00', Polylang_Test_Registry::$post_meta[2]['_event_start']);
        $this->assertSame('FREQ=WEEKLY', Polylang_Test_Registry::$post_meta[2]['_event_rrule']);
        $this->assertSame(555, Polylang_Test_Registry::$thumbnails[2]);
    }

    public function test_sync_translations_copy_when_translation_saved(): void
    {
        Polylang_Test_Registry::$post_languages = [
            1 => 'en',
            2 => 'uk',
        ];
        Polylang_Test_Registry::$translations = [
            1 => ['en' => 1, 'uk' => 2],
            2 => ['en' => 1, 'uk' => 2],
        ];
        Polylang_Test_Registry::$post_meta = [
            1 => [
                '_event_start' => '2024-03-01 08:00:00',
            ],
            2 => [
                '_event_start' => '',
            ],
        ];

        $integration = new Polylang_Integration();
        $integration->sync_translations(2);

        $this->assertSame('2024-03-01 08:00:00', Polylang_Test_Registry::$post_meta[2]['_event_start']);
    }

    public function test_calendar_query_filters_posts_by_language(): void
    {
        Polylang_Test_Registry::$post_languages = [
            10 => 'en',
            20 => 'es',
        ];

        $query = new Calendar_Query();
        $reflection = new ReflectionClass(Calendar_Query::class);
        $method = $reflection->getMethod('filter_posts_by_language');
        $method->setAccessible(true);

        $posts = [new WP_Post(10), new WP_Post(20)];
        $filtered = $method->invoke($query, $posts, 'es');

        $this->assertCount(1, $filtered);
        $this->assertSame(20, $filtered[0]->ID);
    }

    public function test_events_controller_filters_and_returns_translated_permalink(): void
    {
        Polylang_Test_Registry::$post_languages = [
            100 => 'en',
            200 => 'es',
        ];
        Polylang_Test_Registry::$translations = [
            100 => ['es' => 200],
        ];
        Polylang_Test_Registry::$permalinks = [
            100 => 'https://example.test/en/event',
            200 => 'https://example.test/es/event',
        ];

        $controller = new Events_Controller(new Event_Meta_Repository(), new Recurrence_Engine());
        $reflection = new ReflectionClass(Events_Controller::class);

        $filter_method = $reflection->getMethod('filter_posts_by_language');
        $filter_method->setAccessible(true);

        $posts = [new WP_Post(100), new WP_Post(200)];
        $filtered = $filter_method->invoke($controller, $posts, 'es');

        $this->assertCount(1, $filtered);
        $this->assertSame(200, $filtered[0]->ID);

        $permalink_method = $reflection->getMethod('resolve_permalink');
        $permalink_method->setAccessible(true);

        $link = $permalink_method->invoke($controller, new WP_Post(100), 'es');
        $this->assertSame('https://example.test/es/event', $link);
    }
}

