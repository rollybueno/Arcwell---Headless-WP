<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Rest
{
    public function __construct(private Queue $queue, private Preview $preview)
    {
    }
    public function register(): void
    {
        $admin = static fn () => current_user_can('manage_options');
        register_rest_route('arcwell/v1', '/status', ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => static function () {
            $ok = !in_array(false, Config::checks(), true) && !get_option('arcwell_queue_error');
            return new \WP_REST_Response(['status' => $ok ? 'ok' : 'degraded', 'contractVersion' => '1'], $ok ? 200 : 503);
        }]);
        register_rest_route('arcwell/v1', '/diagnostics', ['methods' => 'GET', 'permission_callback' => $admin,
            'callback' => fn () => new \WP_REST_Response($this->queue->diagnostics())]);
        register_rest_route('arcwell/v1', '/webhooks/test', ['methods' => 'POST', 'permission_callback' => $admin, 'callback' => function () {
            if (!Config::transportReady()) {
                return new \WP_Error('arcwell_configuration', 'Configure the frontend, source and webhook secret before testing.', ['status' => 409]);
            }
            $id = $this->queue->enqueue(['event' => 'arcwell.test']);
            return is_wp_error($id) ? $id : new \WP_REST_Response(['eventId' => $id, 'state' => 'pending'], 202);
        }]);
        register_rest_route('arcwell/v1', '/webhooks/(?P<eventId>[a-f0-9-]{36})/retry', ['methods' => 'POST', 'permission_callback' => $admin,
            'callback' => fn ($request) => $this->queue->retry($request['eventId'])
                ? new \WP_REST_Response(['state' => 'pending'], 202)
                : new \WP_Error('arcwell_not_retryable', 'This event is not eligible for retry in this environment.', ['status' => 409])]);
        // Extend native authenticated editor responses; this is not a replacement content endpoint.
        register_rest_field(Content::TYPES, 'arcwell_preview', [
            'schema' => ['type' => ['string', 'null'], 'context' => ['edit'], 'readonly' => true],
            'get_callback' => function (array $object, string $field, \WP_REST_Request $request) {
                $post = get_post((int) $object['id']);
                if (!$post || !current_user_can('edit_post', $post->ID) || !Config::graphqlReady() || !Config::origin()) {
                    return null;
                }
                $image = $request->get_param('arcwell_featured_image');
                $revision = $request->get_param('arcwell_revision');
                foreach ([$image, $revision] as $value) {
                    if ($value !== null && (!is_scalar($value) || !ctype_digit((string) $value))) {
                        return null;
                    }
                }
                try {
                    return $this->preview->launch($post, $revision !== null ? (int) $revision : null, $image !== null ? (int) $image : null);
                } catch (\Throwable $error) {
                    return null;
                }
            },
        ]);
    }
}
