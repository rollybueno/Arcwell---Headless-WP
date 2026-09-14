<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';
$root = getenv('ARCWELL_TEST_WP_ROOT');
if ($root) {
    if (!getenv('ARCWELL_TEST_ALLOW_DATABASE')) { throw new RuntimeException('Set ARCWELL_TEST_ALLOW_DATABASE=1 only for a disposable database.'); }
    require_once $root . '/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/post.php';
    require_once ABSPATH . 'wp-admin/includes/user.php';
    if (!class_exists('WPGraphQL') || !defined('ARCWELL_CORE_VERSION')) { throw new RuntimeException('Activate WPGraphQL and Arcwell Core in the test CMS.'); }
}
