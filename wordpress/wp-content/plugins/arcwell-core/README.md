# Arcwell Core 0.1.2

WordPress companion for the Arcwell headless frontend. Runtime PHP has no Composer dependencies. WordPress owns editorial content; WPGraphQL supplies native content queries. Arcwell adds presentation metadata, curated relationships, preview signing and publishing events.

## Configuration reference

See [the complete configuration guide](docs/configuration.md) for every constant/environment variable, copyable setup examples, secret generation, precedence, local development and troubleshooting. The same essentials are available directly in Settings → Arcwell.

## Installation

1. Use PHP 8.2+ and WordPress 6.4+. Install and activate **WPGraphQL 2.22.3+** first. This release was integration-tested on WordPress 7.1, WPGraphQL 2.22.3, PHP 8.2 and MySQL 8.0; the minimum WordPress version has not yet been integration-tested.
2. Upload `dist/arcwell-core-0.1.2.zip` through WordPress Plugins, or copy `arcwell-core/` into `wp-content/plugins/`. Activate Arcwell Core.
3. Open **Settings → Arcwell**. Enter the frontend website address and use **Generate ID** and **Generate key** to fill the connection fields. Save, then copy the identity and both keys to your frontend hosting configuration. No code is needed for WordPress setup. See [configuration.md](docs/configuration.md) for storage, copy/reveal controls and optional hosting overrides.

4. Create a Page and select it under Settings → Reading → A static page. Its Gutenberg sidebar now contains homepage controls. Create Topics and Series, and select ordered content through the sidebar controls. A Page's Arcwell presentation can select the About layout and value entries. Edit category/Topic images, author specialty and attachment credits in their native WordPress screens.
5. Configure site presentation under Settings → Arcwell, assign native navigation menus, and review readiness checks.
6. Integrate the frontend handlers described in `docs/frontend.md`. A green configuration check does not prove the frontend is connected: send a test webhook and verify successful delivery.

## Worker and operations

Activation creates a prefixed `arcwell_outbox` table. Events are persisted during request shutdown; outbound HTTP runs separately in a leased WP-Cron worker. Configure the host to run this every minute, using the site's correct path/user:

```sh
wp cron event run --due-now --path=/path/to/wordpress
```

When using system cron, set `DISABLE_WP_CRON` to true. Low visitor traffic must not determine delivery latency. Each pass processes up to ten jobs with five-second request timeouts. Network failures, 408, 429 and 5xx retry after at least 30 seconds, 2 minutes and 10 minutes (four attempts total). Retry-After is respected up to one hour. Other errors require intervention. Requests follow no redirects.

Settings → Arcwell shows worker timestamps, counts, recent summarized deliveries and failed-event retry controls. REST operations require `manage_options`; cookie authentication also requires the normal WordPress REST nonce. `/wp-json/arcwell/v1/status` publicly reports only contract version and readiness. Administrator diagnostics are under `/wp-json/arcwell/v1/diagnostics`.

Sent logs expire after 30 days and are capped at 5,000 rows. Pending and failed events are retained until addressed, with no automatic deletion: monitor database capacity and backlog, investigate failure growth, and archive resolved failures under the site's retention policy. Do not delete unresolved events without arranging a full frontend invalidation. Logs omit response bodies, draft content, credentials and preview tokens. A database-write failure is surfaced in diagnostics; no database outbox can guarantee recovery from a failed database write or a process killed before shutdown. A full frontend revalidation is the recovery procedure.

Cloning a database does not authorize replay to a new source. Old-source jobs fail source validation and cannot be manually retried into the new environment. Back up options, post/term/user metadata and the outbox before upgrades. Deactivation stops the scheduled worker but retains editorial data and delivery records. There is deliberately no destructive uninstall hook.

## Verification and release

From this plugin directory:

```sh
composer install
composer test
php tests/connection-settings.php
composer lint
composer analyse
```

Without a CMS, integration tests are explicitly skipped. For a **disposable** activated WordPress installation, run:

```sh
ARCWELL_TEST_WP_ROOT=/path/to/test-wordpress ARCWELL_TEST_ALLOW_DATABASE=1 vendor/bin/phpunit
```

The fixture database must have administrator ID 1 and the configuration above. Tests use transactions and clear the test outbox; never point them at a client database. From the repository root, run `node --test packages/arcwell-contract/server.test.mjs` for cross-language signing/session/webhook checks and `python3 scripts/build-plugin.py` to build the uploadable ZIP and checksum. Production ZIPs exclude test tools, vendors, fixtures and configuration secrets.

This release includes the WordPress implementation and server contract helper. The Next.js application, cache adapter, authenticated preview transport, deployed cron and production end-to-end acceptance are separate integration work. A running WordPress instance alone cannot demonstrate those frontend behaviors.
