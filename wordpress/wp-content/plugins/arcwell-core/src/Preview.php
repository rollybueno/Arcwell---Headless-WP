<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Preview
{
    private ?\WeakMap $errors = null;
    public function __construct(private Urls $urls)
    {
    }
    public function snapshotImage(int $revisionId, int $parentId): void
    {
        if (in_array(get_post_type($parentId), Content::TYPES, true)) {
            update_metadata('post', $revisionId, '_arcwell_preview_image', (int) get_post_thumbnail_id($parentId));
        }
    }
    public function link(string $original, \WP_Post $post): string
    {
        if (
            !Config::graphqlReady() || Config::origin() === '' || strlen(Config::value('ARCWELL_PREVIEW_SECRET')) < 32
            || !current_user_can('edit_post', $post->ID) || !in_array($post->post_type, Content::TYPES, true)
        ) {
            return $original;
        }
        $query = [];
        parse_str((string) wp_parse_url($original, PHP_URL_QUERY), $query);
        $image = isset($query['_thumbnail_id']) && is_numeric($query['_thumbnail_id']) ? max(0, (int) $query['_thumbnail_id']) : null;
        try {
            return $this->launch($post, null, $image);
        } catch (\Throwable $error) {
            return $original;
        }
    }
    public function launch(\WP_Post $post, ?int $revisionId = null, ?int $image = null): string
    {
        if (!current_user_can('edit_post', $post->ID) || !in_array($post->post_type, Content::TYPES, true)) {
            throw new \RuntimeException('Not authorized to preview this content.');
        }
        if ($image !== null && $image > 0 && (get_post_type($image) !== 'attachment' || !current_user_can('edit_post', $image))) {
            throw new \InvalidArgumentException('The preview image is not available to this editor.');
        }
        $mode = 'saved';
        if ($revisionId) {
            if ((int) wp_is_post_revision($revisionId) !== (int) $post->ID) {
                throw new \InvalidArgumentException('Revision does not belong to this content.');
            }
            $mode = 'revision';
        } else {
            $autosave = wp_get_post_autosave($post->ID, get_current_user_id());
            if ($autosave) {
                $revisionId = (int) $autosave->ID;
                $mode = 'autosave';
            }
        }
        if ($revisionId && $image === null) {
            if (!metadata_exists('post', $revisionId, '_arcwell_preview_image')) {
                throw new \InvalidArgumentException('This legacy revision has no image snapshot. Preview the saved content or create a new revision.');
            }
            $image = (int) get_metadata('post', $revisionId, '_arcwell_preview_image', true);
        }
        $url = $this->urls->post((int) $post->ID, true);
        if (!$url) {
            $prefix = ['post' => 'articles/', 'page' => '', 'arcwell_series' => 'series/'][$post->post_type];
            $url = $this->urls->path($prefix . 'draft-' . $post->ID . '/');
        }
        if (!$url || Config::value('ARCWELL_SOURCE_ID') === '') {
            throw new \RuntimeException('Arcwell preview is not configured.');
        }
        $claims = ['v' => 1, 'source' => Config::value('ARCWELL_SOURCE_ID'), 'audience' => Config::origin(),
            'entityType' => $post->post_type, 'databaseId' => (int) $post->ID, 'mode' => $mode,
            'revisionDatabaseId' => $revisionId, 'featuredImageDatabaseId' => $image,
            'issuedAt' => time(), 'expiresAt' => time() + 300, 'frontendUrl' => $url];
        return add_query_arg(Signer::preview($claims, Config::value('ARCWELL_PREVIEW_SECRET')), Config::origin() . '/api/arcwell/preview');
    }
    public function graphqlContext($request): void
    {
        // The standard WPGraphQL header establishes authenticated parent context. This narrow
        // adapter pins an exact revision rather than silently choosing a different editor's autosave.
        if ($this->errors && isset($this->errors[$request->app_context])) {
            unset($this->errors[$request->app_context]);
        }
        $raw = $_SERVER['HTTP_X_ARCWELL_REVISION'] ?? null;
        if ($raw === null) {
            return;
        }
        $context = $request->app_context;
        $parent = (int) ($context->preview['databaseId'] ?? 0);
        if (!$parent || !current_user_can('edit_post', $parent) || !is_scalar($raw) || !ctype_digit((string) $raw)) {
            $this->deny($context, 'Invalid or unauthorized Arcwell revision context.');
            return;
        }
        $id = (int) $raw;
        if ($id && (int) wp_is_post_revision($id) !== $parent) {
            $this->deny($context, 'Revision does not belong to the previewed content.');
            return;
        }
        $context->preview['revisionDatabaseId'] = $id ?: null;
    }
    private function deny($context, string $message): void
    {
        $context->preview = null;
        $this->errors ??= new \WeakMap();
        $this->errors[$context] = $message;
    }
    public function guardField($nil, $source, $args, $context)
    {
        if ($this->errors && isset($this->errors[$context])) {
            throw new \GraphQL\Error\UserError($this->errors[$context]);
        }
        return $nil;
    }
    public function autosaveResponse($response, \WP_Post $revision, \WP_REST_Request $request)
    {
        $parent = get_post($revision->post_parent ?: $revision->ID);
        if (!$parent || !current_user_can('edit_post', $parent->ID) || !Config::origin()) {
            return $response;
        }
        $image = $request->get_param('arcwell_featured_image');
        if ($image !== null && (!is_scalar($image) || !ctype_digit((string) $image))) {
            return $response;
        }
        try {
            $data = $response->get_data();
            $data['preview_link'] = $this->launch($parent, wp_is_post_revision($revision->ID) ? (int) $revision->ID : null, $image !== null ? (int) $image : null);
            if ($request->get_method() === 'POST' && wp_is_post_revision($revision->ID) && $image !== null) {
                update_metadata('post', $revision->ID, '_arcwell_preview_image', (int) $image);
            }
            $response->set_data($data);
        } catch (\Throwable $error) {
/* Leave native preview usable if configuration is incomplete. */
        }
        return $response;
    }
    public function redirectNative(): void
    {
        if (!is_preview() || !is_user_logged_in()) {
            return;
        }
        $post = get_queried_object();
        if (!$post instanceof \WP_Post || !current_user_can('edit_post', $post->ID) || !in_array($post->post_type, Content::TYPES, true)) {
            return;
        }
        $image = isset($_GET['_thumbnail_id']) ? absint($_GET['_thumbnail_id']) : null;
        try {
            $url = $this->launch($post, null, $image);
        } catch (\Throwable $error) {
            return;
        }
        nocache_headers();
        wp_redirect($url, 302, 'Arcwell Preview');
        exit;
    }
}
