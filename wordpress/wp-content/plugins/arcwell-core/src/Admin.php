<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Admin
{
    public function __construct(private Queue $queue)
    {
    }
    public function hooks(): void
    {
        add_action('admin_menu', function (): void {
            add_options_page('Arcwell', 'Arcwell', 'manage_options', 'arcwell', [$this, 'page']);
        });
        add_action('admin_init', function (): void {
            register_setting('arcwell', 'arcwell_site', ['type' => 'array', 'sanitize_callback' => static function ($input): array {
                $input = is_array($input) ? $input : [];
                return ['tagline' => mb_substr(sanitize_text_field($input['tagline'] ?? ''), 0, 240),
                    'issueLabel' => mb_substr(sanitize_text_field($input['issueLabel'] ?? ''), 0, 60),
                    'foundingYear' => min(9999, max(1000, (int) ($input['foundingYear'] ?? gmdate('Y'))))];
            }]);
        });
        add_action('admin_notices', static function (): void {
            if (current_user_can('manage_options') && (!Config::graphqlReady() || get_option('arcwell_queue_error'))) {
                echo '<div class="notice notice-warning"><p>' . esc_html__('Arcwell needs attention. Check Settings → Arcwell for dependency and delivery diagnostics.', 'arcwell-core') . '</p></div>';
            }
        });
        add_action('enqueue_block_editor_assets', [$this, 'editor']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        foreach (['category', 'arcwell_topic'] as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', fn () => $this->termFields(null));
            add_action($taxonomy . '_edit_form_fields', fn ($term) => $this->termFields($term));
            add_action('created_' . $taxonomy, [$this, 'saveTerm']);
            add_action('edited_' . $taxonomy, [$this, 'saveTerm']);
        }
        add_action('show_user_profile', [$this, 'profile']);
        add_action('edit_user_profile', [$this, 'profile']);
        add_action('personal_options_update', [$this, 'saveProfile']);
        add_action('edit_user_profile_update', [$this, 'saveProfile']);
        add_filter('attachment_fields_to_edit', [$this, 'mediaFields'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'saveMedia'], 10, 2);
    }
    public function editor(): void
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, Content::TYPES, true)) {
            return;
        }
        wp_enqueue_script(
            'arcwell-editor',
            plugins_url('assets/admin/editor.js', ARCWELL_CORE_FILE),
            ['wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n'],
            ARCWELL_CORE_VERSION,
            true
        );
        wp_localize_script('arcwell-editor', 'arcwellEditor', ['frontPage' => (int) get_option('page_on_front'),
            'previewReady' => Config::graphqlReady() && Config::origin() !== '' && strlen(Config::value('ARCWELL_PREVIEW_SECRET')) >= 32]);
        wp_enqueue_style('arcwell-editor', plugins_url('assets/admin/admin.css', ARCWELL_CORE_FILE), [], ARCWELL_CORE_VERSION);
    }
    public function assets(string $hook): void
    {
        if (!in_array($hook, ['settings_page_arcwell', 'term.php', 'edit-tags.php'], true)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('arcwell-admin', plugins_url('assets/admin/admin.js', ARCWELL_CORE_FILE), ['wp-api-fetch', 'wp-i18n'], ARCWELL_CORE_VERSION, true);
        wp_enqueue_style('arcwell-admin', plugins_url('assets/admin/admin.css', ARCWELL_CORE_FILE), [], ARCWELL_CORE_VERSION);
    }
    public function page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = (array) get_option('arcwell_site', []);
        $data = $this->queue->diagnostics();
        echo '<div class="wrap arcwell-admin"><h1>Arcwell</h1><p>WordPress editorial content and frontend publishing integration.</p><h2>Readiness</h2><ul>';
        foreach ($data['checks'] as $key => $ok) {
            echo '<li><strong>' . esc_html($ok ? 'Ready' : 'Needs configuration') . '</strong> — ' . esc_html(str_replace('_', ' ', $key)) . '</li>';
        }
        echo '</ul><p>Secrets and destination are configured by your host in wp-config.php or environment variables. Secrets are never displayed here.</p><h2>Publication settings</h2><p>These settings update immediately. Edit the front Page to preview homepage content and selections.</p><form method="post" action="options.php">';
        settings_fields('arcwell');
        foreach (['tagline' => 'Publication tagline', 'foundingYear' => 'Founding year', 'issueLabel' => 'Optional issue label'] as $key => $label) {
            echo '<p><label>' . esc_html($label) . '<br><input class="regular-text" name="arcwell_site[' . esc_attr($key) . ']" value="' . esc_attr((string) ($settings[$key] ?? '')) . '"></label></p>';
        }
        submit_button();
        echo '</form><h2>Delivery diagnostics</h2><p>Worker last run: ' . esc_html($data['workerLastRun'] ? gmdate('Y-m-d H:i:s', $data['workerLastRun']) . ' UTC' : 'Never — configure system cron') . '</p>';
        echo '<p><button class="button button-primary" id="arcwell-test">Queue connection test</button> <button class="button" id="arcwell-refresh">Refresh deliveries</button></p><p id="arcwell-status" role="status"></p><div id="arcwell-deliveries"></div></div>';
    }
    public function termFields(?\WP_Term $term): void
    {
        $id = $term ? (int) $term->term_id : 0;
        $image = $id ? (int) get_term_meta($id, '_arcwell_image_id', true) : 0;
        $heading = $id ? (string) get_term_meta($id, '_arcwell_promo_heading', true) : '';
        echo $term ? '<tr class="form-field"><th>Arcwell presentation</th><td>' : '<div class="form-field"><h3>Arcwell presentation</h3>';
        wp_nonce_field('arcwell_term', 'arcwell_term_nonce');
        echo '<p><label>Promotional heading<input name="arcwell_promo_heading" maxlength="120" value="' . esc_attr($heading) . '"></label></p><div class="arcwell-media"><input type="hidden" name="arcwell_image_id" value="' . esc_attr((string) $image) . '"><span class="arcwell-media-label">' . esc_html($image ? get_the_title($image) : 'No image selected') . '</span> <button type="button" class="button arcwell-select-media">Choose image</button> <button type="button" class="button arcwell-clear-media">Remove</button></div>';
        echo $term ? '</td></tr>' : '</div>';
    }
    public function saveTerm(int $id): void
    {
        if (!isset($_POST['arcwell_term_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['arcwell_term_nonce'])), 'arcwell_term') || !current_user_can('edit_term', $id)) {
            return;
        }
        $image = absint($_POST['arcwell_image_id'] ?? 0);
        if (!$image || (get_post_type($image) === 'attachment' && current_user_can('edit_post', $image))) {
            update_term_meta($id, '_arcwell_image_id', $image);
        }
        update_term_meta($id, '_arcwell_promo_heading', mb_substr(sanitize_text_field(wp_unslash($_POST['arcwell_promo_heading'] ?? '')), 0, 120));
    }
    public function profile(\WP_User $user): void
    {
        wp_nonce_field('arcwell_profile', 'arcwell_profile_nonce');
        echo '<h2>Arcwell contributor profile</h2><p><label>Editorial specialty<br><input class="regular-text" name="arcwell_specialty" maxlength="120" value="' . esc_attr((string) get_user_meta($user->ID, '_arcwell_specialty', true)) . '"></label></p>';
    }
    public function saveProfile(int $id): void
    {
        if (!isset($_POST['arcwell_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['arcwell_profile_nonce'])), 'arcwell_profile') || !current_user_can('edit_user', $id)) {
            return;
        }
        update_user_meta($id, '_arcwell_specialty', mb_substr(sanitize_text_field(wp_unslash($_POST['arcwell_specialty'] ?? '')), 0, 120));
    }
    public function mediaFields(array $fields, \WP_Post $post): array
    {
        $credit = (array) get_post_meta($post->ID, '_arcwell_credit', true);
        foreach (['photographer' => 'Photographer', 'sourceUrl' => 'Original source URL', 'licenseLabel' => 'License label', 'licenseUrl' => 'License URL'] as $key => $label) {
            $fields['arcwell_' . $key] = ['label' => $label, 'input' => 'text', 'value' => $credit[$key] ?? ''];
        }
        return $fields;
    }
    public function saveMedia(array $post, array $attachment): array
    {
        $id = (int) $post['ID'];
        if (!current_user_can('edit_post', $id)) {
            return $post;
        }
        $credit = (array) get_post_meta($id, '_arcwell_credit', true);
        foreach (['photographer' => 180, 'sourceUrl' => 2000, 'licenseLabel' => 120, 'licenseUrl' => 2000] as $key => $limit) {
            if (isset($attachment['arcwell_' . $key])) {
                $credit[$key] = mb_substr(sanitize_text_field($attachment['arcwell_' . $key]), 0, $limit);
            }
        }
        update_post_meta($id, '_arcwell_credit', Content::sanitize($credit));
        return $post;
    }
}
