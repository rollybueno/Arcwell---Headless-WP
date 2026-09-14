<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Urls
{
    public const RESERVED = ['articles', 'topics', 'series', 'authors', 'category', 'tags', 'search', 'api', 'sitemap.xml', 'feed.xml'];
    public function path(string $path): ?string
    {
        $origin = Config::origin();
        return $origin !== '' ? $origin . '/' . ltrim($path, '/') : null;
    }
    public function post(int $id, bool $preview = false): ?string
    {
        $post = get_post($id);
        if (!$post instanceof \WP_Post || !in_array($post->post_type, Content::TYPES, true)) {
            return null;
        }
        if (!$preview && !Access::publicPost($id)) {
            return null;
        }
        if ($post->post_type === 'page') {
            if (get_option('show_on_front') === 'page' && $id === (int) get_option('page_on_front')) {
                return $this->path('');
            }
            $uri = get_page_uri($id);
            if (in_array(explode('/', $uri)[0], self::RESERVED, true)) {
                return null;
            }
            return $uri !== '' ? $this->path(trailingslashit($uri)) : null;
        }
        if ($post->post_name === '') {
            return null;
        }
        $prefix = $post->post_type === 'arcwell_series' ? 'series' : 'articles';
        $url = $this->path($prefix . '/' . $post->post_name . '/');
        $filtered = apply_filters('arcwell_frontend_url', $url, ['type' => $post->post_type, 'id' => $id]);
        return $this->approved($filtered) ? $filtered : null;
    }
    public function term(int $id, string $taxonomy): ?string
    {
        $term = get_term($id, $taxonomy);
        $prefix = ['category' => 'category', 'post_tag' => 'tags', 'arcwell_topic' => 'topics'][$taxonomy] ?? null;
        return $term instanceof \WP_Term && $prefix ? $this->path($prefix . '/' . $term->slug . '/') : null;
    }
    public function user(int $id): ?string
    {
        $user = get_userdata($id);
        return $user && count_user_posts($id, 'post', true) > 0 ? $this->path('authors/' . $user->user_nicename . '/') : null;
    }
    public function approved($url): bool
    {
        return is_string($url) && Config::origin() !== '' && str_starts_with($url, Config::origin() . '/')
            && !str_contains($url, "\r") && !str_contains($url, "\n");
    }
    public function validatePage($prepared, \WP_REST_Request $request)
    {
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        $id = (int) $request->get_param('id');
        if ($id === (int) get_option('page_on_front')) {
            return $prepared;
        }
        $parent = isset($prepared->post_parent) ? (int) $prepared->post_parent : (int) get_post_field('post_parent', $id);
        $slug = $prepared->post_name ?? get_post_field('post_name', $id);
        $path = $parent ? get_page_uri($parent) . '/' . $slug : $slug;
        if (in_array(explode('/', (string) $path)[0], self::RESERVED, true)) {
            return new \WP_Error('arcwell_reserved_route', __('This Page address conflicts with an Arcwell application route.', 'arcwell-core'), ['status' => 400]);
        }
        return $prepared;
    }
    public function redirect(): void
    {
        if (
            !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) || is_admin() || is_preview()
            || is_feed() || is_404() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)
            || (function_exists('is_graphql_request') && is_graphql_request())
        ) {
            return;
        }
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('~/(wp-admin|wp-login\.php|graphql|wp-json|wp-content|wp-cron\.php)(/|$)~', $path)) {
            return;
        }
        // Preserve CMS search/pagination until an explicit frontend mapping is available.
        if (is_search() || is_paged() || get_query_var('page')) {
            return;
        }
        $object = get_queried_object();
        $url = null;
        if ($object instanceof \WP_Post) {
            $url = $this->post((int) $object->ID);
        } elseif ($object instanceof \WP_Term) {
            $url = $this->term((int) $object->term_id, $object->taxonomy);
        } elseif ($object instanceof \WP_User) {
            $url = $this->user((int) $object->ID);
        }
        if ($url && rtrim(Config::origin(), '/') !== rtrim(home_url(), '/') && $this->approved($url)) {
            wp_redirect($url, 301, 'Arcwell Core');
            exit;
        }
    }
}
