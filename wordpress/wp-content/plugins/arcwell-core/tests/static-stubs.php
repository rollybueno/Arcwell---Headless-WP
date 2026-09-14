<?php
/** Static analysis declarations only. Runtime compatibility is covered by the real CMS suite. */
namespace {
    define('ABSPATH', '/wordpress/');
    define('DAY_IN_SECONDS', 86400);
    define('ARRAY_A', 'ARRAY_A');
    define('ARCWELL_CORE_VERSION', '0.1.0');
    define('ARCWELL_CORE_FILE', 'arcwell-core.php');
    define('WPGRAPHQL_VERSION', '2.22.3');
    function register_graphql_field(string $type, string $name, array $config): void {}
    function register_graphql_object_type(string $name, array $config): void {}
    function register_graphql_connection(array $config): void {}
    function is_graphql_request(): bool { return false; }
}
namespace GraphQL\Error { class UserError extends \Exception {} }
namespace WPGraphQL\Model { class Post { public int $databaseId; public string $name; public function __construct($post) {} } }
namespace WPGraphQL\Data\Connection {
    class PostObjectConnectionResolver {
        public function __construct($source, array $args, $context, $info, string $type) {}
        public function set_query_arg(string $key, $value): void {}
        public function get_connection() { return null; }
    }
}
