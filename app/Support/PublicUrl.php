<?php

namespace App\Support;

final class PublicUrl
{
    public const PRODUCTION_APP_URL = 'https://task.devline.studio';

    public static function resolveAppUrl(
        ?string $appUrl = null,
        ?string $publicAppUrl = null,
        ?string $laravelAppUrl = null,
        ?string $environment = null,
    ): string {
        $environment = self::normalizeEnvironment($environment);
        $candidate = self::firstResolvedUrl($publicAppUrl, $laravelAppUrl, $appUrl);

        if ($candidate === null) {
            return self::fallbackAppUrl($environment);
        }

        if ($environment === 'production' && self::isUnsafeProductionUrl($candidate)) {
            return self::PRODUCTION_APP_URL;
        }

        return $candidate;
    }

    public static function resolveApiUrl(
        ?string $apiUrl = null,
        ?string $appUrl = null,
        ?string $publicAppUrl = null,
        ?string $laravelAppUrl = null,
        ?string $environment = null,
    ): string {
        $environment = self::normalizeEnvironment($environment);
        $candidate = self::resolveUrl($apiUrl);

        if ($candidate !== null) {
            if ($environment === 'production' && self::isUnsafeProductionUrl($candidate, '/api')) {
                return self::PRODUCTION_APP_URL.'/api';
            }

            return $candidate;
        }

        return self::resolveAppUrl($appUrl, $publicAppUrl, $laravelAppUrl, $environment).'/api';
    }

    public static function resolveStorageUrl(
        ?string $appUrl = null,
        ?string $publicAppUrl = null,
        ?string $laravelAppUrl = null,
        ?string $environment = null,
    ): string {
        return self::resolveAppUrl($appUrl, $publicAppUrl, $laravelAppUrl, $environment).'/storage';
    }

    private static function firstResolvedUrl(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $resolved = self::resolveUrl($value);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private static function resolveUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $parsed = parse_url($value);

        if (
            ! is_array($parsed)
            || trim((string) ($parsed['scheme'] ?? '')) === ''
            || trim((string) ($parsed['host'] ?? '')) === ''
        ) {
            return null;
        }

        $scheme = strtolower((string) $parsed['scheme']);
        $host = strtolower((string) $parsed['host']);
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = trim((string) ($parsed['path'] ?? ''));
        $path = $path === '' || $path === '/' ? '' : '/'.ltrim($path, '/');
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

        return $scheme.'://'.$host.$port.$path.$query.$fragment;
    }

    private static function isUnsafeProductionUrl(string $url, string $expectedPathPrefix = ''): bool
    {
        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            return true;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $path = '/'.ltrim((string) ($parsed['path'] ?? ''), '/');

        if ($scheme !== 'https' || $host !== 'task.devline.studio') {
            return true;
        }

        if ($port !== null && $port !== 443) {
            return true;
        }

        if ($expectedPathPrefix !== '' && ! str_starts_with($path, $expectedPathPrefix)) {
            return true;
        }

        return false;
    }

    private static function fallbackAppUrl(string $environment): string
    {
        return $environment === 'production'
            ? self::PRODUCTION_APP_URL
            : 'http://localhost:8000';
    }

    private static function normalizeEnvironment(?string $environment): string
    {
        $environment = strtolower(trim((string) $environment));

        return $environment === '' ? 'production' : $environment;
    }
}
