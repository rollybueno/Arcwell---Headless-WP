<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Access
{
    public static function publicPost(int $id, ?string $type = null): bool
    {
        $post = get_post($id);
        return $post instanceof \WP_Post && (!$type || $post->post_type === $type)
            && $post->post_status === 'publish' && $post->post_password === ''
            && is_post_type_viewable($post->post_type);
    }
    public static function publicIds(array $ids, string $type = 'post'): array
    {
        $ids = array_values(array_unique(array_map('absint', $ids)));
        if (!$ids) {
            return [];
        }
        $posts = get_posts([
            'post_type' => $type,
            'post_status' => 'publish',
            'post__in' => $ids,
            'orderby' => 'post__in',
            'numberposts' => count($ids),
            'has_password' => false,
            'suppress_filters' => false
        ]);
        return array_values(array_map(static fn($post) => (int) $post->ID, $posts));
    }
    public static function media(int $id): bool
    {
        $post = get_post($id);
        return $post instanceof \WP_Post && $post->post_type === 'attachment'
            && in_array($post->post_status, ['inherit', 'publish'], true)
            && (!$post->post_parent || self::publicPost((int) $post->post_parent));
    }
}
