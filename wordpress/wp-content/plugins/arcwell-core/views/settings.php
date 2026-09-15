<?php
/** Administrator dashboard. Loaded only by Admin::page(). */
if (!defined('ABSPATH')) { exit; }
$ready = !in_array(false, $data['checks'], true);
$checks = [
    'graphql' => ['WPGraphQL', 'Install and activate WPGraphQL ' . \Arcwell\Core\Config::GRAPHQL_MIN . ' or newer.'],
    'frontend' => ['Frontend destination', 'Set ARCWELL_FRONTEND_URL to your frontend origin, for example https://www.example.com.'],
    'source' => ['CMS identity', 'Set ARCWELL_SOURCE_ID: 8–100 letters, numbers, underscores or hyphens. Use a unique value per environment.'],
    'preview_secret' => ['Preview signing', 'Set ARCWELL_PREVIEW_SECRET to an independent random secret of at least 32 bytes.'],
    'webhook_secret' => ['Webhook signing', 'Set ARCWELL_WEBHOOK_SECRET to a different random secret of at least 32 bytes.'],
    'front_page' => ['Homepage', 'Publish a Page, then select it under Settings → Reading → A static page.'],
];
?>
<div class="wrap arcwell-admin">
    <h1>Arcwell</h1>
    <header class="arcwell-header">
        <div><span class="arcwell-eyebrow">WORDPRESS COMPANION</span><h2>Your publication, connected.</h2><p>Manage publication details and monitor delivery to your frontend.</p></div>
        <span class="arcwell-badge <?php echo $ready ? 'is-ready' : 'is-pending'; ?>"><?php echo $ready ? 'Configuration ready' : 'Setup incomplete'; ?></span>
    </header>
    <?php settings_errors('arcwell_site');
    $setupNotice = get_transient('arcwell_setup_notice_' . get_current_user_id());
    if ($setupNotice) {
        delete_transient('arcwell_setup_notice_' . get_current_user_id());
        echo '<div class="notice notice-info"><p>' . esc_html($setupNotice) . '</p></div>';
    } ?>
    <div class="arcwell-layout">
        <section class="arcwell-card" aria-labelledby="arcwell-connection-title">
            <div class="arcwell-card-heading"><div><h2 id="arcwell-connection-title">Connect your website</h2><p>Fill in your website address, generate its security keys, and save. No code required.</p></div><span class="arcwell-count"><?php echo esc_html((string) count(array_filter($data['checks']))); ?> / 6 ready</span></div>
            <?php require __DIR__ . '/connection.php'; ?>
        </section>
        <section class="arcwell-card" aria-labelledby="arcwell-publication-title">
            <h2 id="arcwell-publication-title">Publication details</h2><p>These settings take effect immediately. Homepage content is edited in the front Page.</p>
            <form method="post" action="options.php">
                <?php settings_fields('arcwell'); ?>
                <?php foreach (['tagline' => ['Publication tagline', 'A short description of your publication.', 240], 'foundingYear' => ['Founding year', 'The year your publication began.', 4], 'issueLabel' => ['Issue label', 'Optional. For example, Autumn 2026.', 60]] as $key => [$label, $help, $length]) : ?>
                    <div class="arcwell-field"><label for="arcwell-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label><input id="arcwell-<?php echo esc_attr($key); ?>" name="arcwell_site[<?php echo esc_attr($key); ?>]" type="<?php echo $key === 'foundingYear' ? 'number' : 'text'; ?>" <?php if ($key === 'foundingYear') { echo 'min="1000" max="9999"'; } ?> maxlength="<?php echo esc_attr((string) $length); ?>" aria-describedby="arcwell-<?php echo esc_attr($key); ?>-help" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"><p id="arcwell-<?php echo esc_attr($key); ?>-help"><?php echo esc_html($help); ?></p></div>
                <?php endforeach; ?>
                <?php submit_button('Save publication details'); ?>
            </form>
            <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>">Manage homepage selection →</a>
        </section>
    </div>
    <section class="arcwell-card" id="arcwell-setup" aria-labelledby="arcwell-setup-title">
        <h2 id="arcwell-setup-title">Advanced hosting setup</h2><p>The form above is the easiest way to connect. Hosting teams can instead supply configuration through code or environment variables; those fields are locked in the form.</p>
        <details><summary>For developers: constants and hosting instructions</summary>
            <div class="arcwell-table-scroll"><table class="widefat arcwell-config-table"><caption class="screen-reader-text">Arcwell configuration reference</caption><thead><tr><th scope="col">Constant / environment variable</th><th scope="col">Requirement</th><th scope="col">Purpose</th></tr></thead><tbody>
                <tr><td><code>ARCWELL_FRONTEND_URL</code></td><td>Required</td><td>Frontend origin, e.g. https://www.example.com. No path, query or credentials. HTTPS is required in production.</td></tr>
                <tr><td><code>ARCWELL_SOURCE_ID</code></td><td>Required</td><td>Unique CMS identity, e.g. arcwell-production. 8–100 letters, numbers, underscores or hyphens. Use a different ID for staging.</td></tr>
                <tr><td><code>ARCWELL_PREVIEW_SECRET</code></td><td>Required</td><td>Signs preview links. Random secret of at least 32 bytes; must match the frontend preview configuration.</td></tr>
                <tr><td><code>ARCWELL_WEBHOOK_SECRET</code></td><td>Required</td><td>Signs publishing events. A separate random secret of at least 32 bytes; must match the frontend webhook configuration.</td></tr>
                <tr><td><code>ARCWELL_ENVIRONMENT</code></td><td>Optional</td><td>Diagnostic label such as production or staging. Does not control WordPress environment behavior.</td></tr>
            </tbody></table></div>
            <h3>1. Generate two independent secrets</h3><p>Run this command twice on a trusted machine. Each run produces a new 64-character secret. Keep the results private.</p><pre><code>php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'</code></pre>
            <h3>2. Add the configuration</h3><p>Place this in <code>wp-config.php</code> before the “stop editing” comment. Replace every placeholder; do not use the example secrets. A defined constant takes precedence over an environment variable, even when the constant is empty.</p>
            <pre><code><?php echo esc_html("define('ARCWELL_FRONTEND_URL', 'https://www.example.com');\ndefine('ARCWELL_SOURCE_ID', 'arcwell-production');\ndefine('ARCWELL_PREVIEW_SECRET', 'REPLACE_WITH_FIRST_GENERATED_SECRET');\ndefine('ARCWELL_WEBHOOK_SECRET', 'REPLACE_WITH_SECOND_GENERATED_SECRET');\ndefine('ARCWELL_ENVIRONMENT', 'production'); // Optional diagnostic label."); ?></code></pre>
            <p>Alternatively, set environment variables with exactly the same names in your hosting control panel. Do not also define these constants. Ensure the variables reach PHP / PHP-FPM and the cron process; restart services if your host requires it.</p>
            <h3>3. Connect the frontend and worker</h3><p>The frontend must use the same source ID and matching secrets, and implement <code>/api/arcwell/preview</code> and <code>/api/arcwell/revalidate</code>. A ready checklist confirms configuration, not successful delivery.</p><p>Schedule this command every minute using your host’s scheduler, with the correct WordPress path and operating-system user:</p><pre><code>wp cron event run --due-now --path=/path/to/wordpress</code></pre><p>When system cron is configured, add <code>define('DISABLE_WP_CRON', true);</code>. Then queue a connection test below and refresh after the worker runs.</p>
            <p>Local HTTP development additionally requires <code>define('WP_ENVIRONMENT_TYPE', 'local');</code> or <code>'development'</code>. Setting <code>ARCWELL_ENVIRONMENT</code> alone does not allow HTTP. Never reuse production secrets in staging or commit secrets to Git.</p>
            <p>Full installation and frontend integration references ship with the plugin in <code>README.md</code>, <code>docs/configuration.md</code> and <code>docs/frontend.md</code>.</p>
        </details>
    </section>
    <section class="arcwell-card" aria-labelledby="arcwell-delivery-title">
        <div class="arcwell-card-heading"><div><h2 id="arcwell-delivery-title">Publishing activity</h2><p id="arcwell-worker">Worker last run: <?php echo esc_html($data['workerLastRun'] ? gmdate('Y-m-d H:i:s', $data['workerLastRun']) . ' UTC' : 'Not yet run — configure the scheduler above.'); ?></p></div><div class="arcwell-actions"><button type="button" class="button button-primary" id="arcwell-test" <?php disabled(!\Arcwell\Core\Config::transportReady()); ?>>Queue connection test</button><button type="button" class="button" id="arcwell-refresh">Refresh</button></div></div>
        <?php if (!\Arcwell\Core\Config::transportReady()) : ?><p>Configure the frontend URL, source ID and webhook secret to enable a connection test.</p><?php endif; ?>
        <p id="arcwell-status" role="status" aria-live="polite"></p><div id="arcwell-deliveries" class="arcwell-table-scroll" aria-busy="true"><p>Loading publishing activity…</p></div>
    </section>
</div>
