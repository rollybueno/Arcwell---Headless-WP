<?php
if (!defined('ABSPATH')) { exit; }
$fields = [
    'ARCWELL_FRONTEND_URL' => ['Website address', 'The address visitors use for your frontend website, for example https://www.example.com.', 'url'],
    'ARCWELL_SOURCE_ID' => ['Website identity', 'A unique ID that helps your website recognize this WordPress installation. Generate it once and keep it.', 'text'],
    'ARCWELL_PREVIEW_SECRET' => ['Preview security key', 'Lets your website safely open unpublished previews.', 'password'],
    'ARCWELL_WEBHOOK_SECRET' => ['Publishing security key', 'Lets your website verify updates sent by WordPress.', 'password'],
];
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="arcwell-connection-form" autocomplete="off" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('arcwell_connection')); ?>">
    <input type="hidden" name="action" value="arcwell_connection">
    <?php wp_nonce_field('arcwell_connection'); ?>
    <?php foreach ($fields as $name => [$label, $help, $type]) :
        $managed = \Arcwell\Core\Config::managed($name);
        $existing = \Arcwell\Core\Config::value($name) !== '';
        $secret = $type === 'password';
    ?>
        <div class="arcwell-field arcwell-connection-field" data-saved="<?php echo $existing ? 'yes' : 'no'; ?>">
            <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?> <?php if ($managed) : ?><span class="arcwell-badge is-ready">Managed by hosting</span><?php endif; ?></label>
            <input id="<?php echo esc_attr($name); ?>" name="connection[<?php echo esc_attr($name); ?>]" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($secret ? '' : \Arcwell\Core\Config::value($name)); ?>" placeholder="<?php echo esc_attr($secret && $existing ? 'Key saved — leave blank to keep it' : ($name === 'ARCWELL_FRONTEND_URL' ? 'https://www.example.com' : '')); ?>" <?php disabled($managed); ?> <?php if (!$managed && (!$secret || !$existing)) { echo 'required'; } ?> autocomplete="<?php echo $secret ? 'new-password' : 'off'; ?>" spellcheck="false" aria-describedby="<?php echo esc_attr($name); ?>-help">
            <p id="<?php echo esc_attr($name); ?>-help"><?php echo esc_html($help); ?><?php if ($managed) { echo ' Your hosting provider controls this value.'; } ?></p>
            <?php if (!$managed && ($secret || $name === 'ARCWELL_SOURCE_ID')) : ?>
                <div class="arcwell-actions">
                    <button type="button" class="button arcwell-generate" data-target="<?php echo esc_attr($name); ?>"><?php echo $existing ? ($secret ? 'Generate replacement key' : 'Generate replacement ID') : ($secret ? 'Generate key' : 'Generate ID'); ?></button>
                    <?php if ($secret) : ?><button type="button" class="button arcwell-show" data-target="<?php echo esc_attr($name); ?>" aria-pressed="false">Show key</button><?php endif; ?>
                    <button type="button" class="button arcwell-copy" data-target="<?php echo esc_attr($name); ?>"><?php echo $secret ? 'Copy key' : 'Copy ID'; ?></button>
                </div>
            <?php endif; ?>
            <details class="arcwell-technical-name"><summary>Technical name</summary><code><?php echo esc_html($name); ?></code></details>
        </div>
    <?php endforeach; ?>
    <p id="arcwell-setup-feedback" role="status" aria-live="polite"></p>
    <?php submit_button('Save connection settings'); ?>
    <p><strong>Next: connect both sides.</strong> Copy the website identity and both keys to the matching settings in your frontend hosting dashboard, or share them privately with the person managing your website. Saving here configures WordPress only.</p>
    <p>Keys are hidden on page load. Use Show or Copy when needed. Replacing a saved key requires updating the frontend too; old preview links will stop working.</p>
</form>
<ul class="arcwell-checks">
    <li><div><strong>WPGraphQL</strong><p><?php echo $data['checks']['graphql'] ? 'Installed and ready.' : 'Install and activate WPGraphQL ' . esc_html(\Arcwell\Core\Config::GRAPHQL_MIN) . ' or newer to connect your content.'; ?></p><a href="<?php echo esc_url(admin_url('plugin-install.php?s=wpgraphql&tab=search&type=term')); ?>">Manage plugin installation →</a></div><span class="arcwell-badge <?php echo $data['checks']['graphql'] ? 'is-ready' : 'is-pending'; ?>"><?php echo $data['checks']['graphql'] ? 'Ready' : 'Action needed'; ?></span></li>
    <li><div><strong>Homepage</strong><p>Select a published Page as your homepage in WordPress Reading settings.</p><a href="<?php echo esc_url(admin_url('options-reading.php')); ?>">Choose your homepage →</a></div><span class="arcwell-badge <?php echo $data['checks']['front_page'] ? 'is-ready' : 'is-pending'; ?>"><?php echo $data['checks']['front_page'] ? 'Ready' : 'Action needed'; ?></span></li>
</ul>
