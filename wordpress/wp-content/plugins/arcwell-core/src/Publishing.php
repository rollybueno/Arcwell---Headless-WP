<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Publishing
{
    private array $pending = [];
    public function __construct(private Queue $queue, private Urls $urls)
    {
    }
    public function hooks(): void
    {
        add_action('pre_post_update', fn ($id) => $this->post((int) $id));
        add_action('wp_after_insert_post', function ($id, $post, $update): void {
            if ($post->post_type === 'nav_menu_item') {
                $this->mark('navigation', 0);
                return;
            }
            $this->post((int) $id, $update ? false : null);
        }, 10, 3);
        add_action('before_delete_post', fn ($id) => $this->post((int) $id));
        add_action('delete_attachment', fn ($id) => $this->post((int) $id));
        foreach (['add_term_relationship', 'delete_term_relationships'] as $hook) {
            add_action($hook, fn ($id) => $this->post((int) $id));
        }
        add_action('set_object_terms', fn ($id) => $this->post((int) $id));
        add_action('edit_terms', fn ($id, $taxonomy) => $this->mark((string) $taxonomy, (int) $id), 10, 2);
        add_action('pre_delete_term', fn ($id, $taxonomy) => $this->mark((string) $taxonomy, (int) $id), 10, 2);
        foreach (['created_term', 'edited_term', 'delete_term'] as $hook) {
            add_action($hook, fn ($id, $tt, $taxonomy) => $this->mark((string) $taxonomy, (int) $id), 10, 3);
        }
        foreach (['post', 'term', 'user'] as $kind) {
            // Capture before metadata writes; observe deletions as well as additions/updates.
            foreach (['add', 'update', 'delete'] as $operation) {
                add_filter($operation . '_' . $kind . '_metadata', function ($check, $id, $key) use ($kind) {
                    if ($this->publicMeta((string) $key)) {
                        if ($kind === 'post') {
                            $this->post((int) $id);
                        } elseif ($kind === 'user') {
                            $this->mark('user', (int) $id);
                        } else {
                            $term = get_term((int) $id);
                            if ($term instanceof \WP_Term) {
                                $this->mark($term->taxonomy, (int) $id);
                            }
                        }
                    }
                    return $check;
                }, 10, 3);
            }
        }
        add_filter('wp_pre_insert_user_data', function ($data, $update, $id) {
            if ($update) {
                $this->mark('user', (int) $id);
            } return $data;
        }, 10, 3);
        add_action('profile_update', fn ($id) => $this->mark('user', (int) $id));
        add_action('delete_user', fn ($id) => $this->mark('user', (int) $id));
        add_action('deleted_user', fn ($id) => $this->mark('user', (int) $id));
        add_filter('pre_update_option', function ($value, $option, $old) {
            if (in_array($option, ['arcwell_site', 'show_on_front', 'page_on_front', 'blogname', 'blogdescription'], true)) {
                $this->mark('site', 0);
            }
            if (str_starts_with($option, 'theme_mods_')) {
                $this->mark('navigation', 0);
            }
            return $value;
        }, 10, 3);
        foreach (['wp_update_nav_menu', 'wp_delete_nav_menu'] as $hook) {
            add_action($hook, fn () => $this->mark('navigation', 0));
        }
        add_action('shutdown', [$this, 'flush'], 5);
    }
    private function publicMeta(string $key): bool
    {
        return in_array($key, ['_arcwell_homepage', '_arcwell_page_template', '_arcwell_about_values', '_arcwell_post_ids',
            '_arcwell_art_label', '_arcwell_edition_label', '_arcwell_image_id', '_arcwell_promo_heading', '_arcwell_specialty',
            '_arcwell_credit', '_thumbnail_id', '_wp_attachment_image_alt', '_wp_attached_file', '_wp_attachment_metadata'], true)
            || str_starts_with($key, '_menu_item_') || str_starts_with($key, '_yoast_wpseo_');
    }
    public function post(int $id, $before = false): void
    {
        $type = get_post_type($id);
        if ($type === 'nav_menu_item') {
            $this->mark('navigation', 0);
            return;
        }
        if (!$type || !in_array($type, [...Content::TYPES, 'attachment'], true) || wp_is_post_revision($id) || wp_is_post_autosave($id)) {
            return;
        }
        $this->mark($type, $id, $before);
    }
    public function mark(string $type, int $id, $before = false): void
    {
        if (!in_array($type, [...Content::TYPES, ...Content::TAXONOMIES, 'attachment', 'user', 'site', 'navigation'], true)) {
            return;
        }
        $key = $type . ':' . $id;
        if (!array_key_exists($key, $this->pending)) {
            $this->pending[$key] = ['type' => $type, 'id' => $id, 'before' => $before === false ? $this->snapshot($type, $id) : $before];
        }
    }
    private function snapshot(string $type, int $id): ?array
    {
        if (in_array($type, Content::TYPES, true)) {
            $post = get_post($id);
            if (!$post) {
                return null;
            }
            if (!Access::publicPost($id)) {
                return ['public' => false];
            }
            $state = ['public' => true, 'frontendUrl' => $this->urls->post($id), 'authors' => [(int) $post->post_author],
                'categories' => [], 'tags' => [], 'topics' => [], 'series' => [], 'posts' => [], 'media' => []];
            foreach (['category' => 'categories', 'post_tag' => 'tags', 'arcwell_topic' => 'topics'] as $tax => $name) {
                $ids = wp_get_object_terms($id, $tax, ['fields' => 'ids']);
                $state[$name] = is_wp_error($ids) ? [] : array_map('intval', $ids);
            }
            $state['series'] = $type === 'post' ? Content::seriesIds($id) : [];
            if ($type === 'arcwell_series') {
                $state['posts'] = Access::publicIds((array) get_post_meta($id, '_arcwell_post_ids', true));
            }
            $thumb = get_post_thumbnail_id($id);
            if ($thumb) {
                $state['media'][] = (int) $thumb;
            }
            $state['_hash'] = hash('sha256', wp_json_encode([$post->post_title, $post->post_content, $post->post_excerpt,
                $post->post_name, $post->post_parent, $post->post_date, $post->post_modified, $state, $this->exposedMeta('post', $id)]));
            return $state;
        }
        if (in_array($type, Content::TAXONOMIES, true)) {
            $term = get_term($id, $type);
            return $term instanceof \WP_Term ? ['public' => true, 'frontendUrl' => $this->urls->term($id, $type),
                '_hash' => hash('sha256', wp_json_encode([$term->name, $term->slug, $term->description, $this->exposedMeta('term', $id)]))] : null;
        }
        if ($type === 'user') {
            $user = get_userdata($id);
            if ($user && !$this->urls->user($id)) {
                return ['public' => false];
            }
            return $user ? ['public' => true, 'frontendUrl' => $this->urls->user($id),
                '_hash' => hash('sha256', wp_json_encode([$user->display_name, $user->user_nicename, $user->description, get_user_meta($id, '_arcwell_specialty', true)]))] : null;
        }
        if ($type === 'attachment') {
            $post = get_post($id);
            if ($post && !Access::media($id)) {
                return ['public' => false];
            }
            return $post ? ['public' => true, '_hash' => hash('sha256', wp_json_encode([$post->post_title, $post->post_excerpt, $post->post_content, $this->exposedMeta('post', $id)]))] : null;
        }
        return ['public' => true, '_hash' => hash('sha256', wp_json_encode($type === 'site'
            ? [get_option('arcwell_site'), get_option('show_on_front'), get_option('page_on_front'), get_option('blogname'), get_option('blogdescription')]
            : [get_nav_menu_locations(), wp_get_nav_menus(), array_map(static fn ($item) => [$item, get_post_meta($item->ID)], get_posts(['post_type' => 'nav_menu_item', 'numberposts' => -1, 'post_status' => 'any']))]))];
    }
    private function exposedMeta(string $kind, int $id): array
    {
        $meta = get_metadata($kind, $id);
        return array_filter((array) $meta, fn ($key) => $this->publicMeta($key), ARRAY_FILTER_USE_KEY);
    }
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $change) {
            ['type' => $type, 'id' => $id, 'before' => $before] = $change;
            $after = $this->snapshot($type, $id);
            if ($before === $after || (empty($before['public']) && empty($after['public']))) {
                continue;
            }
            if (in_array($type, Content::TYPES, true)) {
                $event = !$after ? 'content.deleted' : (empty($after['public']) ? 'content.unpublished' : (empty($before['public']) ? 'content.published' : 'content.updated'));
            } else {
                $event = match ($type) {
                    'user' => 'author.', 'attachment' => 'media.', 'site' => 'site.', 'navigation' => 'navigation.', default => 'taxonomy.'
                } . ($after ? 'updated' : 'deleted');
            }
            $affected = ['posts' => [], 'pages' => [], 'authors' => [], 'categories' => [], 'tags' => [], 'topics' => [], 'series' => [], 'media' => [], 'collections' => []];
            foreach ([$before, $after] as $state) {
                foreach (['posts', 'authors', 'categories', 'tags', 'topics', 'series', 'media'] as $key) {
                    $affected[$key] = array_values(array_unique(array_merge($affected[$key], $state[$key] ?? [])));
                }
            }
            $map = ['post' => 'posts', 'page' => 'pages', 'arcwell_series' => 'series', 'attachment' => 'media', 'user' => 'authors', 'category' => 'categories', 'post_tag' => 'tags', 'arcwell_topic' => 'topics'];
            if (isset($map[$type])) {
                $affected[$map[$type]] = array_values(array_unique([...$affected[$map[$type]], $id]));
            }
            $affected['collections'] = match ($type) {
                'post' => ['journal', 'homepage'], 'page' => ['homepage', 'page-routes'], 'arcwell_series' => ['series', 'homepage'],
                'attachment' => ['media-dependent-content', 'homepage'], 'user' => ['author-dependent-content', 'journal', 'homepage'],
                'site' => ['site', 'homepage', 'page-routes'], 'navigation' => ['navigation'],
                default => ['taxonomy-dependent-content', 'journal', 'homepage'],
            };
            foreach (['before', 'after'] as $key) {
                if (is_array($$key)) {
                    unset(${$key}['_hash']);
                    if (empty(${$key}['frontendUrl'])) {
                        unset(${$key}['frontendUrl']);
                    }
                }
            }
            $payload = ['event' => $event, 'entity' => ['type' => $type, 'id' => $id], 'before' => $before, 'after' => $after, 'affected' => $affected];
            // Oversized relationship sets require broad invalidation, never a truncated ID list.
            foreach (['before', 'after', 'affected'] as $part) {
                foreach ((array) $payload[$part] as $key => $value) {
                    if (is_array($value) && count($value) > 1000) {
                        unset($payload[$part][$key]);
                        $payload['affected']['collections'] = EventSchema::SCOPES;
                    }
                }
            }
            do_action('arcwell_content_changed', $payload);
            $this->queue->enqueue($payload);
        }
    }
}
