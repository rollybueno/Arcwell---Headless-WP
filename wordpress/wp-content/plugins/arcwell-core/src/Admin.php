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
        add_action('admin_post_arcwell_connection', [$this, 'saveConnection']);
        add_action('wp_ajax_arcwell_reveal_key', [$this, 'revealKey']);
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
            if (get_current_screen()?->id !== 'settings_page_arcwell' && current_user_can('manage_options') && (!Config::graphqlReady() || get_option('arcwell_queue_error'))) {
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
        require dirname(__DIR__) . '/views/settings.php';
    }

    public function revealKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Administrator access required.'], 403);
            return;
        }
        check_ajax_referer('arcwell_connection', 'nonce');
        nocache_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(['message' => 'Use POST.'], 405);
            return;
        }
        $key = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
        if (!in_array($key, ['ARCWELL_PREVIEW_SECRET', 'ARCWELL_WEBHOOK_SECRET'], true) || Config::managed($key)) {
            wp_send_json_error(['message' => 'This key is managed by your host.'], 400);
            return;
        }
        wp_send_json_success(['value' => Config::value($key)]);
    }
    public function saveConnection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Administrator access required.', '', ['response' => 403]);
        }
        check_admin_referer('arcwell_connection');
        $input = isset($_POST['connection']) && is_array($_POST['connection']) ? wp_unslash($_POST['connection']) : [];
        $values = (array) get_option('arcwell_connection', []);
        $error = '';
        foreach (Config::FIELDS as $key) {
            if (Config::managed($key)) {
                continue;
            }
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
            if (str_ends_with($key, '_SECRET') && $value === '') {
                continue; // A blank password field preserves the saved key.
            }
            if ($key === 'ARCWELL_FRONTEND_URL' && !Config::validateOrigin($value)) {
                $error = 'Enter a valid website address, such as https://www.example.com, without a page path.';
            } elseif ($key === 'ARCWELL_SOURCE_ID' && !preg_match('/^[a-zA-Z0-9_-]{8,100}$/D', $value)) {
                $error = 'Use Generate ID to create a valid website identity.';
            } elseif (str_ends_with($key, '_SECRET') && (strlen($value) < 32 || strlen($value) > 512 || preg_match('/[^\x21-\x7e]/', $value))) {
                $error = 'Use Generate key to create a valid security key (32–512 printable characters, without spaces).';
            } elseif ($key === 'ARCWELL_ENVIRONMENT' && strlen($value) > 100) {
                $error = 'Keep the environment label under 100 characters.';
            }
            $values[$key] = $value;
        }
        $effective = static fn ($key) => Config::managed($key) ? Config::value($key) : ($values[$key] ?? '');
        if ($effective('ARCWELL_PREVIEW_SECRET') !== '' && $effective('ARCWELL_PREVIEW_SECRET') === $effective('ARCWELL_WEBHOOK_SECRET')) {
            $error = 'Generate a different key for each security field.';
        }
        if (!$error) {
            update_option('arcwell_connection', $values, false);
        }
        set_transient('arcwell_setup_notice_' . get_current_user_id(), $error ?: 'Connection settings saved. Complete any remaining steps, then test your connection.', 60);
        wp_safe_redirect(admin_url('options-general.php?page=arcwell'));
        exit;
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
