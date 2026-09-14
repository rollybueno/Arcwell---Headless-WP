<?php
declare(strict_types=1);
use Arcwell\Core\{Config, Content, Preview, Publishing, Queue, Signer, Urls};
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    private Publishing $publisher;
    protected function setUp(): void
    {
        if (!getenv('ARCWELL_TEST_WP_ROOT')) { self::markTestSkipped('Set ARCWELL_TEST_WP_ROOT for isolated WordPress integration tests.'); }
        global $wpdb, $wp_filter;
        $wpdb->query('START TRANSACTION');
        wp_cache_flush();
        wp_set_current_user(1);
        unset($_SERVER['HTTP_X_GRAPHQL_PREVIEW'], $_SERVER['HTTP_X_ARCWELL_REVISION']);
        foreach ($wp_filter['shutdown']->callbacks as $callbacks) {
            foreach ($callbacks as $entry) {
                $function = $entry['function'];
                if (is_array($function) && $function[0] instanceof Publishing) { $this->publisher = $function[0]; }
            }
        }
        $this->publisher->flush();
        $wpdb->query('DELETE FROM ' . Queue::table());
        delete_option('arcwell_queue_error');
    }
    protected function tearDown(): void
    {
        if (!isset($this->publisher)) { return; }
        $this->publisher->flush();
        global $wpdb;
        $wpdb->query('ROLLBACK');
        wp_cache_flush();
        wp_set_current_user(0);
        unset($_SERVER['HTTP_X_GRAPHQL_PREVIEW'], $_SERVER['HTTP_X_ARCWELL_REVISION']);
    }
    private function post(string $type = 'post', string $status = 'publish', array $extra = []): int
    {
        $id = wp_insert_post(array_merge(['post_type' => $type, 'post_title' => 'Fixture ' . wp_generate_uuid4(), 'post_status' => $status,
            'post_content' => '<!-- wp:paragraph --><p>Fixture body.</p><!-- /wp:paragraph -->', 'post_author' => 1], $extra), true);
        self::assertFalse(is_wp_error($id), is_wp_error($id) ? $id->get_error_message() : '');
        return (int) $id;
    }
    private function query(string $query): array
    {
        $result = graphql(['query' => $query]);
        self::assertArrayNotHasKey('errors', $result, wp_json_encode($result, JSON_PRETTY_PRINT));
        return $result['data'];
    }
    private function rest(string $method, string $path, array $data = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, $path);
        if ($method === 'GET') { $request->set_query_params($data); }
        else { $request->set_header('content-type', 'application/json'); $request->set_body(wp_json_encode($data)); }
        return rest_do_request($request);
    }
    private function clearEvents(): void
    {
        $this->publisher->flush();
        global $wpdb; $wpdb->query('DELETE FROM ' . Queue::table());
    }
    private function events(): array
    {
        $this->publisher->flush();
        global $wpdb;
        return array_map(static fn ($json) => json_decode($json, true), $wpdb->get_col('SELECT payload FROM ' . Queue::table() . ' ORDER BY id'));
    }
    public function testSchemaAndNativeQueries(): void
    {
        $post = $this->post();
        $data = $this->query('{ arcwell { contractVersion frontendOrigin } post(id:"' . $post . '",idType:DATABASE_ID) { title frontendUrl } }');
        self::assertSame('1', $data['arcwell']['contractVersion']);
        self::assertStringContainsString('/articles/', $data['post']['frontendUrl']);
        self::assertTrue(taxonomy_exists('arcwell_topic'));
        self::assertTrue(post_type_exists('arcwell_series'));
    }
    public function testHomepageFiltersHiddenSelectionsAndRetainsOrder(): void
    {
        $a = $this->post(); $b = $this->post(); $draft = $this->post('post', 'draft');
        $home = $this->post('page');
        update_option('show_on_front', 'page'); update_option('page_on_front', $home);
        update_post_meta($home, '_arcwell_homepage', ['heading' => 'A wider view', 'heroPostId' => $a, 'editorsPickIds' => [$b, $draft, $a]]);
        wp_set_current_user(0);
        $data = $this->query('{ arcwellHomepage { heading sourcePage { databaseId } hero { databaseId } editorsPicks { databaseId } } }')['arcwellHomepage'];
        self::assertSame('A wider view', $data['heading']);
        self::assertSame([['databaseId' => $b]], $data['editorsPicks']);
        self::assertSame($a, $data['hero']['databaseId']);
    }
    public function testSeriesOrderPaginationAndReverseMembership(): void
    {
        $a = $this->post(); $b = $this->post(); $draft = $this->post('post', 'draft');
        $series = $this->post('arcwell_series');
        update_post_meta($series, '_arcwell_post_ids', [$b, $draft, $a]);
        wp_set_current_user(0);
        $data = $this->query('{ arcwellSeries(id:"' . $series . '",idType:DATABASE_ID) { publishedPostCount arcwellPosts(first:1) { nodes { databaseId } pageInfo { endCursor hasNextPage } } } }')['arcwellSeries'];
        self::assertSame(2, $data['publishedPostCount']);
        self::assertSame([['databaseId' => $b]], $data['arcwellPosts']['nodes']);
        self::assertTrue($data['arcwellPosts']['pageInfo']['hasNextPage']);
        $cursor = $data['arcwellPosts']['pageInfo']['endCursor'];
        $next = $this->query('{ arcwellSeries(id:"' . $series . '",idType:DATABASE_ID) { arcwellPosts(first:1,after:"' . $cursor . '") { nodes { databaseId } } } }');
        self::assertSame([['databaseId' => $a]], $next['arcwellSeries']['arcwellPosts']['nodes']);
        $reverse = $this->query('{ post(id:"' . $a . '",idType:DATABASE_ID) { arcwellSeries { nodes { databaseId } } } }');
        self::assertSame([['databaseId' => $series]], $reverse['post']['arcwellSeries']['nodes']);
    }
    public function testPaginationRejectsOversizedRequests(): void
    {
        $id = $this->post('arcwell_series');
        $result = graphql(['query' => '{ arcwellSeries(id:"' . $id . '",idType:DATABASE_ID) { arcwellPosts(first:1000) { nodes { databaseId } } } }']);
        self::assertArrayHasKey('errors', $result);
    }
    public function testNativeRestSavesRevisionableFieldsAndRejectsWrongReferenceType(): void
    {
        $series = $this->post('arcwell_series'); $post = $this->post(); $page = $this->post('page');
        $ok = $this->rest('POST', '/wp/v2/arcwell_series/' . $series, ['meta' => ['_arcwell_post_ids' => [$post], '_arcwell_art_label' => 'Observe.']]);
        self::assertSame(200, $ok->get_status(), wp_json_encode($ok->get_data()));
        self::assertSame([$post], get_post_meta($series, '_arcwell_post_ids', true));
        $bad = $this->rest('POST', '/wp/v2/arcwell_series/' . $series, ['meta' => ['_arcwell_post_ids' => [$page]]]);
        self::assertSame(400, $bad->get_status());
    }
    public function testAnonymousCannotEditOrUseDiagnostics(): void
    {
        $page = $this->post('page'); wp_set_current_user(0);
        self::assertContains($this->rest('POST', '/wp/v2/pages/' . $page, ['meta' => ['_arcwell_homepage' => ['heading' => 'bad']]])->get_status(), [401,403]);
        foreach (['/arcwell/v1/diagnostics', '/arcwell/v1/webhooks/test'] as $route) { self::assertContains($this->rest(str_ends_with($route, 'test') ? 'POST' : 'GET', $route)->get_status(), [401,403]); }
        $status = $this->rest('GET', '/arcwell/v1/status')->get_data();
        self::assertSame(['status', 'contractVersion'], array_keys($status));
    }
    public function testTypedUrlsAndReservedPageRoutes(): void
    {
        $urls = new Urls(); $page = $this->post('page', 'publish', ['post_name' => 'about']);
        self::assertSame(Config::origin() . '/about/', $urls->post($page));
        $child = $this->post('page', 'publish', ['post_name' => 'team', 'post_parent' => $page]);
        self::assertSame(Config::origin() . '/about/team/', $urls->post($child));
        $result = $this->rest('POST', '/wp/v2/pages/' . $page, ['slug' => 'articles']);
        self::assertSame(400, $result->get_status());
        $draft = $this->post('post', 'draft'); self::assertNull($urls->post($draft));
    }
    public function testPreviewLinkClaimsAndImageRemoval(): void
    {
        $post = $this->post('post', 'draft');
        $data = $this->rest('GET', '/wp/v2/posts/' . $post, ['context' => 'edit', 'arcwell_featured_image' => '0'])->get_data();
        self::assertNotEmpty($data['arcwell_preview']);
        parse_str((string) parse_url($data['arcwell_preview'], PHP_URL_QUERY), $token);
        $claims = Signer::verifyPreview($token['payload'], $token['signature'], Config::value('ARCWELL_PREVIEW_SECRET'), time(), Config::value('ARCWELL_SOURCE_ID'), Config::origin());
        self::assertSame($post, $claims['databaseId']); self::assertSame(0, $claims['featuredImageDatabaseId']);
        wp_set_current_user(0);
        $this->expectException(RuntimeException::class);
        (new Preview(new Urls()))->launch(get_post($post));
    }
    public function testExactRevisionPreviewAndCustomMeta(): void
    {
        $page = $this->post('page'); update_post_meta($page, '_arcwell_homepage', ['heading' => 'Old homepage']);
        $revision = _wp_put_post_revision($page);
        // Revisions must use update_metadata; update_post_meta deliberately redirects revisions to the parent.
        update_metadata('post', $revision, '_arcwell_homepage', ['heading' => 'Preview homepage']);
        $_SERVER['HTTP_X_GRAPHQL_PREVIEW'] = 'database_id=' . $page . ', featured_image_database_id=0';
        $_SERVER['HTTP_X_ARCWELL_REVISION'] = (string) $revision;
        $data = $this->query('{ page(id:"' . $page . '",idType:DATABASE_ID) { isPreview previewRevisionDatabaseId arcwellHomepage { heading } featuredImage { node { databaseId } } } }')['page'];
        self::assertTrue($data['isPreview']); self::assertSame($revision, $data['previewRevisionDatabaseId']);
        self::assertSame('Preview homepage', $data['arcwellHomepage']['heading']); self::assertNull($data['featuredImage']);
        unset($_SERVER['HTTP_X_GRAPHQL_PREVIEW'], $_SERVER['HTTP_X_ARCWELL_REVISION']); wp_set_current_user(0);
        self::assertSame('Old homepage', $this->query('{ page(id:"' . $page . '",idType:DATABASE_ID) { arcwellHomepage { heading } } }')['page']['arcwellHomepage']['heading']);
    }
    public function testCrossPostRevisionCannotBeRead(): void
    {
        $a = $this->post(); $b = $this->post(); $revision = _wp_put_post_revision($b);
        $_SERVER['HTTP_X_GRAPHQL_PREVIEW'] = 'database_id=' . $a;
        $_SERVER['HTTP_X_ARCWELL_REVISION'] = (string) $revision;
        $result = graphql(['query' => '{ post(id:"' . $a . '",idType:DATABASE_ID) { title } }']);
        self::assertArrayHasKey('errors', $result);
    }
    public function testPublishMoveAndWithdrawEventsIncludeOldRelationships(): void
    {
        $id = $this->post(); $old = wp_insert_term('Old', 'arcwell_topic')['term_id']; $new = wp_insert_term('New', 'arcwell_topic')['term_id'];
        wp_set_object_terms($id, [$old], 'arcwell_topic'); $this->clearEvents();
        $oldUrl = (new Urls())->post($id);
        wp_update_post(['ID' => $id, 'post_name' => 'changed-slug']); wp_set_object_terms($id, [$new], 'arcwell_topic');
        $events = array_values(array_filter($this->events(), static fn ($e) => $e['entity']['id'] === $id && $e['entity']['type'] === 'post'));
        self::assertCount(1, $events); self::assertSame('content.updated', $events[0]['event']);
        self::assertSame($oldUrl, $events[0]['before']['frontendUrl']); self::assertContains($old, $events[0]['affected']['topics']); self::assertContains($new, $events[0]['affected']['topics']);
        $this->clearEvents(); wp_update_post(['ID' => $id, 'post_status' => 'private']);
        $events = $this->events(); self::assertSame('content.unpublished', $events[0]['event']); self::assertSame(['public' => false], $events[0]['after']);
    }
    public function testDraftAndAutosaveDoNotEmitPublicEvents(): void
    {
        $draft = $this->post('post', 'draft'); _wp_put_post_revision($draft, true);
        self::assertSame([], $this->events());
    }
    public function testDeletionRetainsFormerUrl(): void
    {
        $post = $this->post(); $url = (new Urls())->post($post); $this->clearEvents(); wp_delete_post($post, true);
        $events = $this->events(); self::assertSame('content.deleted', $events[0]['event']); self::assertSame($url, $events[0]['before']['frontendUrl']); self::assertNull($events[0]['after']);
    }
    public function testMetaOnlyEditProducesDependencyEvent(): void
    {
        $home = $this->post('page'); $this->clearEvents(); update_post_meta($home, '_arcwell_homepage', ['heading' => 'Changed']);
        $events = $this->events(); self::assertCount(1, $events); self::assertContains('homepage', $events[0]['affected']['collections']);
    }
    public function testQueueSignsExactBodyAndRetriesThenSucceeds(): void
    {
        $queue = new Queue(); $id = $queue->enqueue(['event' => 'arcwell.test']); self::assertIsString($id);
        $seen = [];
        $filter = static function ($response, $args, $url) use (&$seen) {
            $seen[] = $args;
            return ['headers' => [], 'body' => '', 'response' => ['code' => count($seen) === 1 ? 503 : 200]];
        };
        add_filter('pre_http_request', $filter, 10, 3);
        try {
            $queue->run(); global $wpdb;
            $row = $wpdb->get_row('SELECT * FROM ' . Queue::table()); self::assertSame('pending', $row->state); self::assertSame(1, (int) $row->attempts);
            self::assertSame(Signer::webhook($seen[0]['body'], (int) $seen[0]['headers']['X-Arcwell-Timestamp'], Config::value('ARCWELL_WEBHOOK_SECRET')), $seen[0]['headers']['X-Arcwell-Signature']);
            $wpdb->query('UPDATE ' . Queue::table() . ' SET available_at=0'); $queue->run();
            $row = $wpdb->get_row('SELECT * FROM ' . Queue::table()); self::assertSame('sent', $row->state); self::assertSame($seen[0]['body'], $seen[1]['body']); self::assertNotSame($seen[0]['headers']['X-Arcwell-Delivery'], $seen[1]['headers']['X-Arcwell-Delivery']);
        } finally { remove_filter('pre_http_request', $filter, 10); }
    }
    public function testPermanentFailureStopsAndCanBeRetried(): void
    {
        $queue = new Queue(); $id = $queue->enqueue(['event' => 'arcwell.test']);
        $filter = static fn () => ['headers' => [], 'body' => 'secret-response-must-not-be-stored', 'response' => ['code' => 403]];
        add_filter('pre_http_request', $filter);
        try { $queue->run(); } finally { remove_filter('pre_http_request', $filter); }
        $data = $queue->diagnostics(); self::assertSame('failed', $data['recent'][0]['state']); self::assertStringNotContainsString('secret-response', wp_json_encode($data)); self::assertTrue($queue->retry($id));
    }
    public function testTermAndAuthorPresentation(): void
    {
        $this->post(); $term = wp_insert_term('Architecture ' . wp_generate_uuid4(), 'arcwell_topic')['term_id']; update_term_meta($term, '_arcwell_promo_heading', 'Room to breathe'); update_user_meta(1, '_arcwell_specialty', 'Design');
        $data = $this->query('{ arcwellTopic(id:"' . $term . '",idType:DATABASE_ID) { name arcwellPromoHeading frontendUrl } user(id:"1",idType:DATABASE_ID) { arcwellSpecialty } }');
        self::assertSame('Room to breathe', $data['arcwellTopic']['arcwellPromoHeading']); self::assertSame('Design', $data['user']['arcwellSpecialty']);
    }
    public function testHistoricalImageAndAutosaveRemovalRemainPinned(): void
    {
        $post = $this->post();
        $image = $this->post('attachment', 'inherit', ['post_mime_type' => 'image/jpeg']);
        update_post_meta($post, '_thumbnail_id', $image);
        $revision = _wp_put_post_revision($post);
        delete_post_meta($post, '_thumbnail_id');
        $preview = new Preview(new Urls());
        parse_str(parse_url($preview->launch(get_post($post), $revision), PHP_URL_QUERY), $query);
        $claims = Signer::verifyPreview($query['payload'], $query['signature'], Config::value('ARCWELL_PREVIEW_SECRET'), time(), Config::value('ARCWELL_SOURCE_ID'), Config::origin());
        self::assertSame($image, $claims['featuredImageDatabaseId']);
        $request = new WP_REST_Request('POST'); $request->set_param('arcwell_featured_image', 0);
        $preview->autosaveResponse(new WP_REST_Response([]), get_post($revision), $request);
        self::assertSame(0, (int) get_metadata('post', $revision, '_arcwell_preview_image', true));
        self::assertSame(0, (int) get_post_thumbnail_id($post));
    }
    public function testNativeAutosaveKeepsHomepageEditsOffPublishedPage(): void
    {
        $post = $this->post('page');
        update_post_meta($post, '_arcwell_homepage', ['heading' => 'Published heading']);
        $this->clearEvents();
        $response = $this->rest('POST', '/wp/v2/pages/' . $post . '/autosaves', [
            'title' => 'Unsaved page title', 'content' => 'Unsaved page body',
            'meta' => ['_arcwell_homepage' => ['heading' => 'Preview heading']], 'arcwell_featured_image' => 0,
        ]);
        self::assertLessThan(300, $response->get_status(), wp_json_encode($response->get_data()));
        $revision = $response->get_data()['id'];
        self::assertSame('Published heading', get_post_meta($post, '_arcwell_homepage', true)['heading']);
        self::assertSame('Preview heading', get_metadata('post', $revision, '_arcwell_homepage', true)['heading']);
        self::assertSame([], $this->events());
    }
    public function testWorkerRecoversExpiredLeaseAndRejectsForeignSource(): void
    {
        global $wpdb;
        $queue = new Queue(); $id = $queue->enqueue(['event' => 'arcwell.test']);
        $wpdb->update(Queue::table(), ['state' => 'processing', 'lease_until' => time() - 1, 'source' => 'foreign-source-instance'], ['event_id' => $id]);
        $calls = 0;
        $filter = static function () use (&$calls) { $calls++; return new WP_Error('unexpected_transport'); };
        add_filter('pre_http_request', $filter);
        try { $queue->run(); } finally { remove_filter('pre_http_request', $filter); }
        self::assertSame(0, $calls);
        self::assertSame('source_mismatch', $queue->diagnostics()['recent'][0]['error_code']);
        self::assertFalse($queue->retry($id));
    }
}
