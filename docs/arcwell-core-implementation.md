# Arcwell Core implementation handoff

The WordPress plugin is implemented at `wordpress/wp-content/plugins/arcwell-core/`. Build with `python3 scripts/build-plugin.py`; the uploadable ZIP and SHA256 checksum are written to `dist/`. Read the plugin's README for installation, worker operation, retention and configuration, and `docs/frontend.md` inside the plugin for the frontend contract.

## Delivered

- Native Series and Topics with REST schemas, editorial permission checks and revisionable presentation metadata.
- Gutenberg homepage, About and ordered Series controls; term media, author specialty and attachment credits.
- WPGraphQL public fields, filtered curated selections, forward-paginated Series relationships and typed frontend URLs.
- Signed, expiring preview launches; exact authenticated revision context, featured-image snapshots and autosave handling.
- Change/dependency detection for content and supporting entities, durable leased outbox, signed delivery, retries, diagnostics and administrator actions.
- Server-only JavaScript helpers for launch/session validation, preview headers and signed/idempotent webhook processing through an application-supplied durable adapter.
- Reproducible runtime-only packaging and CI for static checks, unit tests and contract tests. CI does not yet provision a WordPress integration environment.

## Local verification

Tested using an isolated WordPress 7.1 / WPGraphQL 2.22.3 / PHP 8.2.33 / MySQL 8.0.46 installation, not the client's CMS.

- PHP suite: 26 tests, 113 assertions, including native GraphQL, permissions, revisions, native REST autosave isolation, public change snapshots, delivery retries, source isolation and expired-lease recovery.
- Node contract suite: 5 tests, including PHP-generated signatures, tampering, expiry, redirect validation, scoped sessions, private snapshot rejection and receiver failure propagation.
- PHPStan level 3: passed without a baseline. PHP syntax and configured PHPCS checks passed.
- Real Chromium/Gutenberg smoke check: plugin settings opened; homepage and Series metadata saved and read back; preview panel opened; no page errors. A string/number mismatch in the localized homepage ID was found and fixed.
- Actual WordPress HTTP delivery to a localhost Node contract verifier returned 200 and the outbox marked the event sent. This verifies transport/signature interoperability, not Next.js cache invalidation.

## Remaining deployment acceptance

Install on staging, configure its own source/secrets and system cron, implement the Next.js routes and durable receiver idempotency, connect cache tags/path invalidation and renderer blocks, then perform publish/preview/withdrawal tests against the real frontend. The HTML prototype is not that application. Test the dedicated preview account across content owned by different editors, verify HTTP cookie/cache behavior, and benchmark representative content volumes. WordPress 6.4 is the declared minimum for revisionable REST metadata but has not been run in this verification environment.

Historical revisions from before plugin activation have no reliable featured-image snapshot and cannot use historical launch until a new revision exists. Successful delivery logs are bounded; unresolved jobs are retained and require operational monitoring. Failed database writes or fatal termination before shutdown require recovery via a full frontend revalidation. These limitations are documented in the plugin handoff rather than presented as completed deployment acceptance.
