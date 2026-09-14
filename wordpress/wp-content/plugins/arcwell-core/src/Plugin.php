<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Plugin
{
    public function boot(): void
    {
        $urls = new Urls();
        $queue = new Queue();
        $preview = new Preview($urls);
        $publishing = new Publishing($queue, $urls);
        add_action('_wp_put_post_revision', [$preview, 'snapshotImage'], 20, 2);
        add_action('init', [Content::class, 'register']);
        add_filter('allowed_block_types_all', static function ($allowed, $context) {
            if (empty($context->post) || !in_array($context->post->post_type, Content::TYPES, true)) {
                return $allowed;
            }
            return ['core/paragraph', 'core/heading', 'core/image', 'core/gallery', 'core/list', 'core/list-item',
                'core/quote', 'core/code', 'core/table', 'core/buttons', 'core/button', 'core/embed', 'core/separator',
                'core/columns', 'core/column', 'core/group', 'core/cover'];
        }, 10, 2);
        add_action('clean_post_cache', [Content::class, 'clearSeriesMap']);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, static function ($metaId, $objectId, $key): void {
                if ($key === '_arcwell_post_ids') {
                    Content::clearSeriesMap();
                }
            }, 10, 3);
        }
        add_action('init', static function (): void {
            if (get_option('arcwell_db_version') !== '1') {
                Queue::install();
            }
            if (!wp_next_scheduled('arcwell_dispatch_webhook')) {
                wp_schedule_event(time() + 60, 'arcwell_minute', 'arcwell_dispatch_webhook');
            }
        }, 20);
        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['arcwell_minute'] = ['interval' => 60, 'display' => 'Arcwell: every minute'];
            return $schedules;
        });
        if (Config::graphqlReady()) {
            add_action('graphql_register_types', [(new GraphQL($urls)), 'register']);
            add_action('graphql_before_execute', [$preview, 'graphqlContext']);
            add_filter('graphql_pre_resolve_field', [$preview, 'guardField'], -10, 4);
            add_filter('preview_post_link', [$preview, 'link'], 10, 2);
            add_filter('rest_prepare_autosave', [$preview, 'autosaveResponse'], 10, 3);
            add_action('template_redirect', [$preview, 'redirectNative'], 1);
        }
        add_action('template_redirect', [$urls, 'redirect'], 20);
        foreach (Content::TYPES as $type) {
            add_filter('rest_pre_insert_' . $type, [Content::class, 'validateReferences'], 10, 2);
        }
        add_filter('rest_pre_insert_page', [$urls, 'validatePage'], 20, 2);
        add_action('arcwell_dispatch_webhook', [$queue, 'run']);
        add_action('rest_api_init', [(new Rest($queue, $preview)), 'register']);
        (new Admin($queue))->hooks();
        $publishing->hooks();
        // Expose only the service instance to local test/integration hooks, never a public API.
        do_action('arcwell_loaded', $publishing, $queue);
    }
    public static function activate(): void
    {
        Content::register();
        Queue::install();
        flush_rewrite_rules(false);
    }
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('arcwell_dispatch_webhook');
        flush_rewrite_rules(false);
    }
}
