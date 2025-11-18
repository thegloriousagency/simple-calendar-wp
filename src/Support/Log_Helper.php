<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Support;

use DateTimeImmutable;
use RuntimeException;
use function array_slice;
use function dirname;
use function file;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_dir;
use function sprintf;
use function strtoupper;
use function unlink;
use function wp_json_encode;
use function wp_mkdir_p;
use function wp_upload_dir;
use function wp_timezone;
use const FILE_APPEND;
use const FILE_IGNORE_NEW_LINES;
use const LOCK_EX;

/**
 * Simple logging utility for Church Events Calendar.
 *
 * Logs are stored under uploads/church-events/logs.log.
 */
final class Log_Helper
{
    /**
     * Writes a log entry.
     *
     * @param string $level   Info | Warning | Error etc.
     * @param string $message Main log message.
     * @param array<string, mixed> $context Optional context data.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $path = self::get_log_path();
        self::ensure_directory(dirname($path));

        $timestamp = new DateTimeImmutable('now', wp_timezone());
        $line = sprintf(
            '[%s] %s: %s %s',
            $timestamp->format('Y-m-d H:i:sP'),
            strtoupper($level),
            $message,
            $context ? wp_json_encode($context) : ''
        );

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Returns the last $lines lines from the log (newest last).
     *
     * @return array<int, string>
     */
    public static function get_tail(int $lines = 200): array
    {
        $path = self::get_log_path();
        if (! file_exists($path)) {
            return [];
        }

        $contents = file($path, FILE_IGNORE_NEW_LINES);
        if (! $contents) {
            return [];
        }

        return array_slice($contents, -1 * $lines);
    }

    /**
     * Deletes the log file.
     */
    public static function clear(): void
    {
        $path = self::get_log_path();
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Returns the absolute path to the plugin log file.
     */
    public static function get_log_path(): string
    {
        $uploads = wp_upload_dir();
        if (! empty($uploads['error'])) {
            throw new RuntimeException($uploads['error']);
        }

        return $uploads['basedir'] . '/church-events/logs.log';
    }

    private static function ensure_directory(string $directory): void
    {
        if (! is_dir($directory)) {
            wp_mkdir_p($directory);
        }
    }
}

