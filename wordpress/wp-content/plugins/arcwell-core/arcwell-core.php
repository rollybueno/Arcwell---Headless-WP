<?php
/**
 * Plugin Name: Arcwell Core
 * Description: Editorial data, previews and reliable publishing events for Arcwell headless WordPress.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Author: Arcwell
 * License: GPL-2.0-or-later
 * Text Domain: arcwell-core
 */
declare(strict_types=1);
if (!defined('ABSPATH')) {
    exit;
}
define('ARCWELL_CORE_VERSION', '0.1.0');
define('ARCWELL_CORE_FILE', __FILE__);
spl_autoload_register(static function (string $class): void {
    $prefix = 'Arcwell\\Core\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
register_activation_hook(__FILE__, [Arcwell\Core\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Arcwell\Core\Plugin::class, 'deactivate']);
add_action('plugins_loaded', static function (): void {
    (new Arcwell\Core\Plugin())->boot(); }, 20);
