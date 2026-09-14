<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class EventSchema
{
    public const EVENTS = ['content.published', 'content.updated', 'content.unpublished', 'content.deleted',
        'taxonomy.updated', 'taxonomy.deleted', 'author.updated', 'author.deleted', 'media.updated', 'media.deleted',
        'homepage.updated', 'site.updated', 'navigation.updated', 'arcwell.test'];
    public const SCOPES = ['journal', 'homepage', 'navigation', 'site', 'media-dependent-content', 'taxonomy-dependent-content', 'author-dependent-content', 'page-routes', 'series'];
    public static function valid(array $event): bool
    {
        if (
            ($event['version'] ?? '') !== '1' || !in_array($event['event'] ?? '', self::EVENTS, true)
            || !is_string($event['source'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{8,100}$/D', $event['source'])
            || !is_string($event['eventId'] ?? null) || !preg_match('/^[a-f0-9-]{36}$/D', $event['eventId'])
            || !is_string($event['occurredAt'] ?? null) || strtotime($event['occurredAt']) === false
        ) {
            return false;
        }
        if ($event['event'] === 'arcwell.test') {
            return count(array_diff(array_keys($event), ['version', 'event', 'source', 'eventId', 'occurredAt'])) === 0;
        }
        if (array_diff(array_keys($event), ['version', 'event', 'source', 'eventId', 'occurredAt', 'entity', 'before', 'after', 'affected'])) {
            return false;
        }
        if (
            !is_int($event['entity']['id'] ?? null) || $event['entity']['id'] < 0
            || !in_array($event['entity']['type'] ?? '', ['post', 'page', 'arcwell_series', 'attachment', 'category', 'post_tag', 'arcwell_topic', 'user', 'site', 'navigation'], true)
        ) {
            return false;
        }
        foreach (['before', 'after'] as $key) {
            if (!array_key_exists($key, $event)) {
                return false;
            }
            $state = $event[$key];
            if ($state === null) {
                continue;
            }
            if (!is_array($state) || !is_bool($state['public'] ?? null)) {
                return false;
            }
            if (array_diff(array_keys($state), ['public', 'frontendUrl', 'authors', 'categories', 'tags', 'topics', 'series', 'posts', 'media'])) {
                return false;
            }
            if ($state['public'] === false && count($state) !== 1) {
                return false;
            }
            if (isset($state['frontendUrl']) && !(new Urls())->approved($state['frontendUrl'])) {
                return false;
            }
            foreach (['authors', 'categories', 'tags', 'topics', 'series', 'posts', 'media'] as $field) {
                if (isset($state[$field]) && !self::ids($state[$field])) {
                    return false;
                }
            }
        }
        $affected = $event['affected'] ?? null;
        if (!is_array($affected) || array_diff(array_keys($affected), ['posts', 'pages', 'authors', 'categories', 'tags', 'topics', 'series', 'media', 'collections'])) {
            return false;
        }
        foreach ($affected as $field => $ids) {
            if ($field === 'collections') {
                if (!is_array($ids) || array_diff($ids, self::SCOPES)) {
                    return false;
                }
            } elseif (!self::ids($ids)) {
                return false;
            }
        }
        return true;
    }
    private static function ids($ids): bool
    {
        return is_array($ids) && count($ids) <= 1000 && count(array_filter($ids, static fn ($id) => !is_int($id) || $id < 1)) === 0;
    }
}
