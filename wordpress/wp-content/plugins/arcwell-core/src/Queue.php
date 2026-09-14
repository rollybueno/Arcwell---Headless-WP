<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Queue
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'arcwell_outbox';
    }
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(64) NOT NULL,
            source varchar(100) NOT NULL,
            payload longtext NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'pending',
            attempts int unsigned NOT NULL DEFAULT 0,
            available_at bigint unsigned NOT NULL DEFAULT 0,
            lease_until bigint unsigned NOT NULL DEFAULT 0,
            lease_token varchar(64) NOT NULL DEFAULT '',
            http_status int unsigned NOT NULL DEFAULT 0,
            error_code varchar(100) NOT NULL DEFAULT '',
            duration_ms int unsigned NOT NULL DEFAULT 0,
            created_at bigint unsigned NOT NULL DEFAULT 0,
            updated_at bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id),
            KEY worker (state,available_at,lease_until)
        ) $charset;");
        update_option('arcwell_db_version', '1', false);
    }
    public function enqueue(array $event)
    {
        global $wpdb;
        $event['version'] = '1';
        $event['eventId'] = $event['eventId'] ?? wp_generate_uuid4();
        $event['source'] = Config::value('ARCWELL_SOURCE_ID');
        $event['occurredAt'] = $event['occurredAt'] ?? gmdate('c');
        $event = apply_filters('arcwell_webhook_payload', $event);
        if (!is_array($event) || !EventSchema::valid($event)) {
            update_option('arcwell_queue_error', 'invalid_event', false);
            return new \WP_Error('arcwell_invalid_event', 'Event failed contract validation.');
        }
        $payload = wp_json_encode($event, JSON_UNESCAPED_SLASHES);
        if (!is_string($payload) || strlen($payload) > 262144) {
            update_option('arcwell_queue_error', 'oversized_event', false);
            return new \WP_Error('arcwell_oversized_event', 'Event exceeds 256 KB.');
        }
        $result = $wpdb->insert(self::table(), ['event_id' => $event['eventId'], 'source' => $event['source'],
            'payload' => $payload, 'created_at' => time(), 'updated_at' => time(), 'available_at' => time()]);
        if (!$result) {
            update_option('arcwell_queue_error', 'persistence_failed', false);
            return new \WP_Error('arcwell_queue_failed', 'Could not persist the publishing event.');
        }
        delete_option('arcwell_queue_error');
        return $event['eventId'];
    }
    public function run(): void
    {
        global $wpdb;
        update_option('arcwell_worker_last_run', time(), false);
        $table = self::table();
        $now = time();
        $wpdb->query($wpdb->prepare("UPDATE $table SET state='pending', lease_token='' WHERE state='processing' AND lease_until < %d", $now));
        $jobs = $wpdb->get_results($wpdb->prepare("SELECT id FROM $table WHERE state='pending' AND available_at <= %d ORDER BY id LIMIT 10", $now));
        foreach ($jobs as $job) {
            $token = wp_generate_uuid4();
            $claimed = $wpdb->query($wpdb->prepare("UPDATE $table SET state='processing',lease_token=%s,lease_until=%d WHERE id=%d AND state='pending'", $token, $now + 120, $job->id));
            if ($claimed !== 1) {
                continue;
            }
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $job->id));
            $this->deliver($row, $token);
        }
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE state='sent' AND updated_at < %d", $now - 30 * DAY_IN_SECONDS));
        $cutoff = $wpdb->get_var("SELECT id FROM $table WHERE state='sent' ORDER BY id DESC LIMIT 4999,1");
        if ($cutoff) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE state='sent' AND id < %d", $cutoff));
        }
    }
    private function deliver(object $row, string $lease): void
    {
        global $wpdb;
        $attempt = (int) $row->attempts + 1;
        $start = microtime(true);
        $status = 0;
        $retryAfter = 0;
        $error = '';
        if (!Config::transportReady() || $row->source !== Config::value('ARCWELL_SOURCE_ID')) {
            $error = $row->source !== Config::value('ARCWELL_SOURCE_ID') ? 'source_mismatch' : 'configuration';
        } else {
            $event = json_decode($row->payload, true);
            $timestamp = time();
            $response = wp_remote_post(Config::origin() . '/api/arcwell/revalidate', [
                'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 2048, 'sslverify' => true,
                'headers' => ['Content-Type' => 'application/json', 'X-Arcwell-Event' => $event['event'],
                    'X-Arcwell-Delivery' => wp_generate_uuid4(), 'X-Arcwell-Timestamp' => (string) $timestamp,
                    'X-Arcwell-Signature' => Signer::webhook($row->payload, $timestamp, Config::value('ARCWELL_WEBHOOK_SECRET'))],
                'body' => $row->payload,
            ]);
            if (is_wp_error($response)) {
                $error = 'network';
            } else {
                $status = (int) wp_remote_retrieve_response_code($response);
                $header = wp_remote_retrieve_header($response, 'retry-after');
                $retryAfter = is_numeric($header) ? (int) $header : max(0, (int) strtotime((string) $header) - time());
                $error = $status >= 200 && $status < 300 ? '' : 'http_' . $status;
            }
        }
        $canRetry = $error === 'network' || in_array($status, [408, 429], true) || ($status >= 500 && $status <= 599);
        $state = $error === '' ? 'sent' : ($canRetry && $attempt < 4 ? 'pending' : 'failed');
        $delay = max([1 => 30, 2 => 120, 3 => 600][$attempt] ?? 0, min(3600, $retryAfter));
        $wpdb->update(self::table(), ['state' => $state, 'attempts' => $attempt, 'available_at' => time() + $delay,
            'lease_until' => 0, 'lease_token' => '', 'http_status' => $status, 'error_code' => $error,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000), 'updated_at' => time()], ['id' => $row->id, 'lease_token' => $lease]);
    }
    public function retry(string $eventId): bool
    {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            ['state' => 'pending', 'attempts' => 0, 'available_at' => time(), 'error_code' => ''],
            ['event_id' => $eventId, 'state' => 'failed', 'source' => Config::value('ARCWELL_SOURCE_ID')]
        ) === 1;
    }
    public function diagnostics(): array
    {
        global $wpdb;
        $table = self::table();
        return ['checks' => Config::checks(), 'environment' => Config::value('ARCWELL_ENVIRONMENT'),
            'pluginVersion' => ARCWELL_CORE_VERSION, 'graphqlVersion' => defined('WPGRAPHQL_VERSION') ? WPGRAPHQL_VERSION : null,
            'workerLastRun' => (int) get_option('arcwell_worker_last_run', 0), 'persistenceError' => get_option('arcwell_queue_error') ?: null,
            'counts' => $wpdb->get_results("SELECT state, COUNT(*) AS total FROM $table GROUP BY state", ARRAY_A),
            'recent' => $wpdb->get_results("SELECT event_id,state,attempts,http_status,error_code,duration_ms,created_at,updated_at FROM $table ORDER BY id DESC LIMIT 20", ARRAY_A)];
    }
}
