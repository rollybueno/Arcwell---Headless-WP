<?php

declare(strict_types=1);

namespace Arcwell\Core;

use WPGraphQL\Data\Connection\PostObjectConnectionResolver;

final class GraphQL
{
    public function __construct(private Urls $urls)
    {
    }
    private function field(string $type, string $name, $output, callable $resolver, bool $preview = false): void
    {
        register_graphql_field($type, $name, ['type' => self::output($output), 'resolve' => $resolver, 'isPreviewable' => $preview]);
    }
    private static function output($type)
    {
        return is_string($type) && str_ends_with($type, '!') ? ['non_null' => substr($type, 0, -1)] : $type;
    }
    private function object(string $name, array $fields): void
    {
        register_graphql_object_type($name, ['fields' => array_map(static fn ($type) => ['type' => self::output($type)], $fields)]);
    }
    private function node(int $id, $context, string $type = 'post')
    {
        return $id ? $context->get_loader($type)->load_deferred($id) : null;
    }
    public function register(): void
    {
        $this->object('ArcwellConfig', ['contractVersion' => 'String!', 'frontendOrigin' => 'String!', 'tagline' => 'String', 'foundingYear' => 'Int', 'issueLabel' => 'String']);
        $this->object('ArcwellValue', ['heading' => 'String!', 'body' => 'String!']);
        $this->object('ArcwellPagePresentation', ['template' => 'String!', 'values' => ['non_null' => ['list_of' => ['non_null' => 'ArcwellValue']]]]);
        $this->object('ArcwellMediaCredit', ['photographer' => 'String', 'sourceUrl' => 'String', 'licenseLabel' => 'String', 'licenseUrl' => 'String']);
        $fields = array_fill_keys(array_keys(Content::HOME), 'String');
        $fields += ['sourcePage' => 'Page!', 'hero' => 'Post', 'editorsPicks' => ['non_null' => ['list_of' => ['non_null' => 'Post']]],
            'featuredTopic' => 'ArcwellTopic', 'featuredSeries' => 'ArcwellSeries', 'aboutPage' => 'Page'];
        $this->object('ArcwellHomepage', $fields);
        $this->field('RootQuery', 'arcwell', 'ArcwellConfig!', static function (): array {
            if (Config::origin() === '') {
                throw new \GraphQL\Error\UserError('Arcwell frontend origin is not configured.');
            }
            return array_merge((array) get_option('arcwell_site', []), ['contractVersion' => '1', 'frontendOrigin' => Config::origin()]);
        });
        $this->field('RootQuery', 'arcwellHomepage', 'ArcwellHomepage', function ($source, $args, $context) {
            $id = (int) get_option('page_on_front');
            return get_option('show_on_front') === 'page' && Access::publicPost($id, 'page') ? $this->home($id, $context) : null;
        });
        $this->field('Page', 'arcwellHomepage', 'ArcwellHomepage', fn ($source, $args, $context) => $this->home((int) $source->databaseId, $context), true);
        $this->field('Page', 'arcwellPresentation', 'ArcwellPagePresentation!', static function ($source): array {
            $template = get_post_meta((int) $source->databaseId, '_arcwell_page_template', true);
            return ['template' => $template === 'about' ? 'about' : 'standard', 'values' => (array) get_post_meta((int) $source->databaseId, '_arcwell_about_values', true)];
        }, true);
        foreach (['Post', 'Page', 'ArcwellSeries'] as $type) {
            $this->field($type, 'frontendUrl', 'String', fn ($node) => $this->urls->post((int) $node->databaseId));
        }
        foreach (['Category' => 'category', 'Tag' => 'post_tag', 'ArcwellTopic' => 'arcwell_topic'] as $type => $taxonomy) {
            $this->field($type, 'frontendUrl', 'String', fn ($node) => $this->urls->term((int) $node->databaseId, $taxonomy));
            if ($type !== 'Tag') {
                $this->field($type, 'arcwellPromoHeading', 'String', static fn ($node) => get_term_meta((int) $node->databaseId, '_arcwell_promo_heading', true) ?: $node->name);
                $this->field($type, 'arcwellImage', 'MediaItem', function ($node, $args, $context) {
                    $id = (int) get_term_meta((int) $node->databaseId, '_arcwell_image_id', true);
                    return Access::media($id) ? $this->node($id, $context) : null;
                });
            }
        }
        $this->field('User', 'frontendUrl', 'String', fn ($node) => $this->urls->user((int) $node->databaseId));
        $this->field('User', 'arcwellSpecialty', 'String', static fn ($node) => get_user_meta((int) $node->databaseId, '_arcwell_specialty', true) ?: null);
        $this->field('MediaItem', 'arcwellCredit', 'ArcwellMediaCredit', static fn ($node) => get_post_meta((int) $node->databaseId, '_arcwell_credit', true) ?: null);
        foreach (['arcwellArtLabel' => '_arcwell_art_label', 'arcwellEditionLabel' => '_arcwell_edition_label'] as $field => $key) {
            $this->field('ArcwellSeries', $field, 'String', static fn ($node) => get_post_meta((int) $node->databaseId, $key, true) ?: null, true);
        }
        $this->field('ArcwellSeries', 'publishedPostCount', 'Int!', static fn ($node) => count(Access::publicIds((array) get_post_meta((int) $node->databaseId, '_arcwell_post_ids', true))), true);
        $this->connection('ArcwellSeries', 'Post', 'arcwellPosts', 'ArcwellSeriesPostsConnection', 'post', static fn ($node) => Access::publicIds((array) get_post_meta((int) $node->databaseId, '_arcwell_post_ids', true)), true);
        $this->connection('Post', 'ArcwellSeries', 'arcwellSeries', 'ArcwellPostSeriesConnection', 'arcwell_series', static fn ($node) => Content::seriesIds((int) $node->databaseId));
    }
    public function home(int $id, $context): array
    {
        $values = get_post_meta($id, '_arcwell_homepage', true);
        $values = is_array($values) ? $values : [];
        $parent = wp_is_post_revision($id) ?: $id;
        $result = array_intersect_key($values, Content::HOME);
        $result['sourcePage'] = $this->node((int) $parent, $context);
        $hero = (int) ($values['heroPostId'] ?? 0);
        if (!Access::publicPost($hero, 'post')) {
            $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'numberposts' => 1, 'fields' => 'ids']);
            $hero = (int) ($posts[0] ?? 0);
        }
        $result['hero'] = $this->node($hero, $context);
        $picks = array_values(array_diff(Access::publicIds(array_slice((array) ($values['editorsPickIds'] ?? []), 0, 3)), [$hero]));
        $result['editorsPicks'] = array_map(fn ($postId) => $this->node($postId, $context), $picks);
        $term = get_term((int) ($values['featuredTopicId'] ?? 0), 'arcwell_topic');
        $result['featuredTopic'] = $term instanceof \WP_Term ? $this->node((int) $term->term_id, $context, 'term') : null;
        $series = (int) ($values['featuredSeriesId'] ?? 0);
        $result['featuredSeries'] = Access::publicPost($series, 'arcwell_series') && Access::publicIds((array) get_post_meta($series, '_arcwell_post_ids', true)) ? $this->node($series, $context) : null;
        $about = (int) ($values['aboutPageId'] ?? 0);
        $result['aboutPage'] = Access::publicPost($about, 'page') ? $this->node($about, $context) : null;
        return $result;
    }
    private function connection(string $from, string $to, string $field, string $name, string $postType, callable $ids, bool $preview = false): void
    {
        register_graphql_connection(['fromType' => $from, 'toType' => $to, 'fromFieldName' => $field,
            'connectionTypeName' => $name, 'resolve' => static function ($node, $args, $context, $info) use ($ids, $postType, $preview) {
                $first = $args['first'] ?? 10;
                if ($first < 1 || $first > 50 || isset($args['last']) || isset($args['before'])) {
                    throw new \GraphQL\Error\UserError('Use forward pagination with first between 1 and 50.');
                }
                if (
                    $preview && is_array($context->preview) && (int) $context->preview['databaseId'] === (int) $node->databaseId
                    && current_user_can('edit_post', (int) $node->databaseId) && !empty($context->preview['revisionDatabaseId'])
                ) {
                    $node = new \WPGraphQL\Model\Post(get_post((int) $context->preview['revisionDatabaseId']));
                }
                $list = $ids($node);
                $args['first'] = $first;
                $args['where']['in'] = $list ?: [0];
                $resolver = new PostObjectConnectionResolver($node, $args, $context, $info, $postType);
                $resolver->set_query_arg('post_status', 'publish');
                $resolver->set_query_arg('has_password', false);
                return $resolver->get_connection();
            }]);
    }
}
