# Arcwell Core companion-plugin audit

Date: September 14, 2026.
Input: `plugin/arcwell-core-plugin-spec.md`, the HTML prototype, project spec, and committed design audit (`dd031b5`).

## Verdict

Keep Arcwell Core as the WordPress-side publishing integration. The original spec correctly separated WordPress content from frontend rendering and avoided duplicating WPGraphQL. It needed a concrete content contract and stronger preview/event requirements before implementation.

The updated [plugin specification](../plugin/arcwell-core-plugin-spec.md) is the implementation plan. It is a specification, not a built or installable plugin yet. The project spec now uses the same plugin name, endpoint paths, and environment-variable names.

## Findings and changes

| Original gap | Updated decision |
| --- | --- |
| Homepage only described hero/picks/Topic/Series selections | Added all visible homepage copy, overlay, manifesto, and About link fields; explicit bounds/fallbacks |
| Editorial configuration could live in options without preview semantics | Homepage lives on the front Page as revisionable structured metadata; site settings/menus remain saved-immediate |
| Series ordering storage and reverse membership were ambiguous | One ordered Post-ID list on Series; computed Post membership; multiple Series allowed; bounded paginated access |
| Category/Topic distinction did not match the prototype | Native broad categories plus a separately registered Topic taxonomy and dedicated URLs |
| Topic imagery, About values, specialties, and credits were missing | Added typed term/Page/user/attachment fields and editing permissions |
| Custom field example duplicated the native excerpt | Use native excerpt as standfirst; no `customDeck` |
| Derived UI values risked unnecessary plugin fields | Reading time, contents, initials, related-story selection, and decorative layout remain frontend responsibilities |
| Config exposed environment/CMS details | Public config is allowlisted presentation data only; diagnostics are administrator-only |
| URL resolver accepted a bare numeric ID and only illustrated ContentNode | Typed post/term/user resolver; routes for categories/tags/authors and static indexes; collision/ancestor handling |
| `/api/arcwell/*` paths conflicted with the project spec | Standardized preview, exit, and revalidation paths, secret names, and source identifier |
| Preview IDs and revision IDs were easy to confuse | Separate parent identity from explicit revision identity; signed scoped context; verify selected native APIs |
| Draft Mode session could outlive the launch without content scoping | Bounded application-controlled preview session; no unrestricted draft browsing through service credentials |
| Featured-image preview deferred to v1.1 | Required in v1, including image removal, alongside revisionable custom fields |
| Event dependencies described only current relationships | Previous/current public snapshots plus affected-union semantics, including deletion and old URLs |
| Status/term hooks omitted visible config/media/menu changes | Added concrete dependency coverage for all exposed frontend data and completed-save coalescing |
| Cron queue lacked durable/low-traffic delivery guarantees | Database outbox, leased workers, reliable host-triggered cron, bounded retries, idempotency and redacted logs |
| Webhook test implied immediate success despite asynchronous delivery | Return queued event ID/202, inspect delivery outcome through protected diagnostics |
| Editor data-entry controls were unspecified | Defined Gutenberg controls/selectors and native term/profile/media forms |
| Block and SEO integration was not an acceptance gate | Actual stored block fixtures and one verified SEO adapter required |
| Generic acceptance list did not prove the designed pages | Added complete frontend query inventory and real CMS integration/permission/failure tests |

## Scope discipline

Required API additions: public Arcwell config, homepage configuration, typed public URLs, ordered/reverse Series connections, term imagery/promo, Page presentation, author specialty, and media credit metadata.

Do not add generic content REST endpoints, a universal block-tree API, a custom authentication system, a search engine, issue management, manually duplicated headings/reading times, or React layout fields. ACF and Action Scheduler are not mandatory dependencies; v1 uses native registered metadata and a replaceable WP-Cron-triggered durable worker.

WordPress remains the editing system. A plugin ZIP cannot independently supply the Next.js preview session, `/api/arcwell/revalidate`, cache behavior, or rendered frontend; those must be developed and tested alongside the PHP package.

## Implementation order

1. Scaffold an installable plugin, pin the compatibility matrix, and prove native content + authenticated preview queries.
2. Build the content model and editor controls, including revisionable homepage/About/Series metadata.
3. Expose bounded, visibility-checked GraphQL fields/connections and typed URLs; compile all frontend query fixtures.
4. Implement signed previews with custom-meta/image/revision support and matching Next.js session/exit behavior.
5. Implement complete change snapshots, durable event delivery, receiver idempotency, retries, and publication E2E tests.
6. Add diagnostics/recovery, verify redirects and SEO/block integration, package a release ZIP, and document installation/operations.

## Remaining proof, not assumptions

- Pin maintained PHP/WordPress/WPGraphQL versions and verify their exact preview/revision behavior before writing the main integration.
- Confirm the selected SEO plugin/API exposes the required metadata in that matrix.
- Test actual saved Gutenberg markup against the frontend; do not infer compatibility from hand-authored HTML.
- Verify the host cron cadence and Next.js revalidation policy meet an agreed publication freshness target.

References and detailed acceptance criteria are included in the revised plugin spec. No PHP implementation or plugin deployment was performed in this audit.
