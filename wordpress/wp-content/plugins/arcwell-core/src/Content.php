<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Content
{
    private static ?array $seriesMap = null;
    public static function clearSeriesMap(): void
    {
        self::$seriesMap = null;
    }
    public const TYPES = ['post', 'page', 'arcwell_series'];
    public const TAXONOMIES = ['category', 'post_tag', 'arcwell_topic'];
    public const HOME = ['heading' => 100, 'emphasis' => 100, 'intro' => 400, 'heroOverlay' => 120,
        'manifestoHeading' => 180, 'manifestoEmphasis' => 180];
    public static function register(): void
    {
        register_taxonomy('arcwell_topic', ['post'], ['label' => __('Topics', 'arcwell-core'),
            'public' => true, 'hierarchical' => false, 'show_in_rest' => true, 'show_in_graphql' => true,
            'graphql_single_name' => 'ArcwellTopic', 'graphql_plural_name' => 'ArcwellTopics', 'rewrite' => ['slug' => 'topics']]);
        register_post_type('arcwell_series', ['label' => __('Series', 'arcwell-core'),
            'public' => true, 'show_in_rest' => true, 'show_in_graphql' => true, 'has_archive' => true,
            'graphql_single_name' => 'ArcwellSeries', 'graphql_plural_name' => 'ArcwellSeriesItems',
            'rewrite' => ['slug' => 'series'], 'menu_icon' => 'dashicons-book-alt',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields'],
            'capability_type' => 'post', 'map_meta_cap' => true]);
        add_post_type_support('page', ['excerpt', 'custom-fields', 'thumbnail']);
        add_post_type_support('post', ['custom-fields', 'thumbnail']);
        add_theme_support('post-thumbnails');
        register_nav_menus(['ARCWELL_PRIMARY' => __('Arcwell primary', 'arcwell-core'),
            'ARCWELL_FOOTER_EXPLORE' => __('Arcwell footer: Explore', 'arcwell-core'),
            'ARCWELL_FOOTER_ABOUT' => __('Arcwell footer: About', 'arcwell-core')]);
        foreach (self::postSchemas() as $type => $fields) {
            foreach ($fields as $key => $schema) {
                register_post_meta($type, $key, ['type' => $schema['type'], 'single' => true,
                    'show_in_rest' => ['schema' => $schema], 'revisions_enabled' => true,
                    'default' => $schema['default'] ?? match ($schema['type']) {
                    'array', 'object' => [], 'integer' => 0, default => ''
                    },
                    'auth_callback' => static fn ($allowed, $meta, $id) => current_user_can('edit_post', (int) $id),
                    'sanitize_callback' => [self::class, 'sanitize']]);
            }
        }
        foreach (['category', 'arcwell_topic'] as $taxonomy) {
            foreach (['_arcwell_image_id' => ['type' => 'integer', 'minimum' => 0], '_arcwell_promo_heading' => self::text(120)] as $key => $schema) {
                register_term_meta($taxonomy, $key, ['type' => $schema['type'], 'single' => true,
                    'show_in_rest' => ['schema' => $schema], 'sanitize_callback' => [self::class, 'sanitize'],
                    'auth_callback' => static fn ($allowed, $meta, $id) => current_user_can('edit_term', (int) $id)]);
            }
        }
        register_meta('user', '_arcwell_specialty', ['type' => 'string', 'single' => true,
            'show_in_rest' => ['schema' => self::text(120)], 'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => static fn ($allowed, $meta, $id) => current_user_can('edit_user', (int) $id)]);
        register_post_meta('attachment', '_arcwell_credit', ['type' => 'object', 'single' => true,
            'show_in_rest' => ['schema' => self::creditSchema()], 'sanitize_callback' => [self::class, 'sanitize'],
            'auth_callback' => static fn ($allowed, $meta, $id) => current_user_can('edit_post', (int) $id)]);
    }
    public static function text(int $length): array
    {
        return ['type' => 'string', 'maxLength' => $length];
    }
    public static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
    }
    public static function ids(int $max): array
    {
        return ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'maxItems' => $max, 'uniqueItems' => true];
    }
    public static function creditSchema(): array
    {
        return self::object(['photographer' => self::text(180), 'sourceUrl' => self::text(2000),
            'licenseLabel' => self::text(120), 'licenseUrl' => self::text(2000)]);
    }
    public static function postSchemas(): array
    {
        $home = array_map([self::class, 'text'], self::HOME);
        foreach (['heroPostId', 'featuredTopicId', 'featuredSeriesId', 'aboutPageId'] as $name) {
            $home[$name] = ['type' => 'integer', 'minimum' => 0];
        }
        $home['editorsPickIds'] = self::ids(3);
        return ['page' => ['_arcwell_homepage' => self::object($home),
            '_arcwell_page_template' => ['type' => 'string', 'enum' => ['standard', 'about'], 'default' => 'standard'],
            '_arcwell_about_values' => ['type' => 'array', 'maxItems' => 3, 'items' => self::object(['heading' => self::text(80), 'body' => self::text(500)])]],
            'arcwell_series' => ['_arcwell_post_ids' => self::ids(100), '_arcwell_art_label' => self::text(24), '_arcwell_edition_label' => self::text(40)]];
    }
    public static function sanitize($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = in_array($key, ['sourceUrl', 'licenseUrl'], true) && is_string($item)
                    ? esc_url_raw($item, ['http', 'https']) : self::sanitize($item);
            }
            return $out;
        }
        return is_string($value) ? sanitize_textarea_field($value) : $value;
    }
    public static function validateReferences($prepared, \WP_REST_Request $request)
    {
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        $meta = $request->get_param('meta') ?? [];
        $home = $meta['_arcwell_homepage'] ?? [];
        foreach ([$meta['_arcwell_post_ids'] ?? [], $home['editorsPickIds'] ?? []] as $list) {
            if (count($list) !== count(array_unique($list))) {
                return new \WP_Error('arcwell_duplicate_reference', __('Choose each item only once.', 'arcwell-core'), ['status' => 400]);
            }
        }
        $refs = [];
        foreach (($meta['_arcwell_post_ids'] ?? []) as $id) {
            $refs[] = [$id, 'post'];
        }
        foreach (($home['editorsPickIds'] ?? []) as $id) {
            $refs[] = [$id, 'post'];
        }
        foreach (['heroPostId' => 'post', 'featuredSeriesId' => 'arcwell_series', 'aboutPageId' => 'page'] as $key => $type) {
            if (!empty($home[$key])) {
                $refs[] = [$home[$key], $type];
            }
        }
        foreach ($refs as [$id, $type]) {
            if (get_post_type((int) $id) !== $type || !current_user_can('edit_post', (int) $id)) {
                return new \WP_Error('arcwell_invalid_reference', __('A selected item is unavailable or cannot be edited by you.', 'arcwell-core'), ['status' => 400]);
            }
        }
        if (!empty($home['featuredTopicId']) && !term_exists((int) $home['featuredTopicId'], 'arcwell_topic')) {
            return new \WP_Error('arcwell_invalid_topic', __('Select an existing Topic.', 'arcwell-core'), ['status' => 400]);
        }
        return $prepared;
    }
    public static function seriesIds(int $postId): array
    {
        if (self::$seriesMap === null) {
            self::$seriesMap = [];
            $ids = get_posts(['post_type' => 'arcwell_series', 'post_status' => 'publish', 'has_password' => false,
                'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
            update_meta_cache('post', $ids);
            foreach ($ids as $id) {
                foreach (array_slice((array) get_post_meta((int) $id, '_arcwell_post_ids', true), 0, 100) as $member) {
                    if (is_numeric($member) && (int) $member > 0) {
                        self::$seriesMap[(int) $member][] = (int) $id;
                    }
                }
            }
        }
        return array_values(array_unique(self::$seriesMap[$postId] ?? []));
    }
}
