<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Config
{
    public const GRAPHQL_MIN = '2.22.3';
    public const FIELDS = ['ARCWELL_FRONTEND_URL', 'ARCWELL_SOURCE_ID', 'ARCWELL_PREVIEW_SECRET', 'ARCWELL_WEBHOOK_SECRET', 'ARCWELL_ENVIRONMENT'];
    public static function managed(string $name): bool
    {
        return defined($name) || getenv($name) !== false;
    }
    public static function value(string $name): string
    {
        $value = defined($name) ? constant($name) : getenv($name);
        if (!self::managed($name) && in_array($name, self::FIELDS, true)) {
            $value = ((array) get_option('arcwell_connection', []))[$name] ?? '';
        }
        return is_string($value) ? trim($value) : '';
    }
    public static function origin(): string
    {
        return self::validateOrigin(self::value('ARCWELL_FRONTEND_URL'));
    }
    public static function validateOrigin(string $value): string
    {
        $value = rtrim(trim($value), '/');
        $url = wp_parse_url($value);
        if (
            !$url || empty($url['host']) || !in_array($url['scheme'] ?? '', ['https', 'http'], true)
            || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment'])
            || !empty($url['path'])
        ) {
            return '';
        }
        if (($url['scheme'] ?? '') !== 'https' && !in_array(wp_get_environment_type(), ['local', 'development'], true)) {
            return '';
        }
        return $value;
    }
    public static function graphqlReady(): bool
    {
        return defined('WPGRAPHQL_VERSION') && version_compare(WPGRAPHQL_VERSION, self::GRAPHQL_MIN, '>=')
            && function_exists('register_graphql_field') && class_exists('WPGraphQL\\Utils\\Preview');
    }
    public static function checks(): array
    {
        return [
            'graphql' => self::graphqlReady(),
            'frontend' => self::origin() !== '',
            'source' => (bool) preg_match('/^[a-zA-Z0-9_-]{8,100}$/D', self::value('ARCWELL_SOURCE_ID')),
            'preview_secret' => strlen(self::value('ARCWELL_PREVIEW_SECRET')) >= 32,
            'webhook_secret' => strlen(self::value('ARCWELL_WEBHOOK_SECRET')) >= 32,
            'front_page' => get_option('show_on_front') === 'page' && Access::publicPost((int) get_option('page_on_front'), 'page'),
        ];
    }
    public static function transportReady(): bool
    {
        $checks = self::checks();
        return $checks['frontend'] && $checks['source'] && $checks['webhook_secret'];
    }
}
