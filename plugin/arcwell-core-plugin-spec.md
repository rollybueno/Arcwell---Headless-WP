# Arcwell Core WordPress Plugin Specification

> Arcwell Core is the WordPress companion plugin for Arcwell Web. It extends WPGraphQL with the editorial structures the approved frontend needs, maps public URLs, and coordinates preview and publishing events. It is not an AI assistant or a second content API.

**Revision:** September 14, 2026 — audited against the HTML prototype and [design audit](../docs/design-headless-audit.md).
**Status:** implementation specification; no plugin code or CMS integration exists yet.

## 1. Identity and architectural boundary

- Plugin name / slug: **Arcwell Core** / `arcwell-core`.
- PHP namespace: `Arcwell\Core`; text domain: `arcwell-core`.
- Install as an ordinary WordPress plugin ZIP; an MU loader is optional deployment tooling, not required.
- Required API dependency: WPGraphQL. WordPress Application Passwords is the selected native server-to-server authentication mechanism, not a separate optional plugin.
- Optional adapter: a selected WordPress SEO plugin with verified API exposure. No ACF dependency is required.
- WordPress stores content and editorial configuration. WPGraphQL exposes native entities. Arcwell Core adds the missing editorial contract. Next.js renders the website and owns public caches, sitemap, feed, metadata output, and browser interactions.

The plugin must not implement React components, arbitrary visual page-building, frontend accounts, commerce, comments, external search, or duplicate post/page/media REST endpoints. It must not introduce a custom authentication framework.

If WPGraphQL is missing or unsupported, keep WordPress and the Arcwell content model usable, show an actionable admin notice, disable incompatible API/preview integration, and report degraded health. Do not fatal or silently claim readiness.

## 2. Contract decisions from the prototype audit

| Requirement | Decision for v1 |
| --- | --- |
| Categories versus Topics | Design, Culture, Nature, Ideas are native categories. Architecture, Objects & rituals, Future of living, etc. are examples of separate Topic terms. No seed content is installed automatically. |
| Article identity | Native Post IDs internally; public slugs/URLs externally. Never use an author's display name as a routing key. |
| Standfirst | Native post excerpt. Do not create a duplicate `customDeck` field. |
| Homepage editing | Structured, revisionable metadata on the designated static front Page, edited through constrained controls. |
| Series membership | Series CPT stores one ordered list of Post IDs. Article membership is computed from it, never maintained as a second editable copy. Multiple series per article are allowed; the frontend displays the list when nonempty. |
| Related stories | Native post queries in Next.js: shared Topic, then category, exclude current post, de-duplicate, date-descending fallback. No manual override or custom recommendation API in v1. |
| Reading time | Derived from the content actually rendered by Next.js. No stored override in v1. Series duration is derived consistently from its published articles. |
| Table of contents | Next.js derives stable, unique anchors from headings. No CMS field/API for a second copy of headings. |
| Search | Native article title/excerpt/body search, plus explicit author/taxonomy filters where needed. Do not promise general author/topic-label keyword matching. |
| About layout | Native Page body plus a constrained About template, image, and up to three value statements. No arbitrary section builder. |
| Credits | Public attachment credit metadata; initial credits Page is maintained editorially. No automatic global credit directory/query in v1. |
| Decorative series artwork | CSS/SVG belongs to Next.js; expose only short optional art text, not CSS or layout instructions. |
| Public issue label | Optional site setting, not an issue-management subsystem. |

These choices preserve the design while avoiding unnecessary custom API surface.

## 3. Package layout and delivery

Use this repository path for source and build an installable ZIP with `arcwell-core/` as its top-level directory:

```text
wordpress/wp-content/plugins/arcwell-core/
├── arcwell-core.php
├── composer.json
├── readme.txt
├── src/
│   ├── Plugin.php
│   ├── Config/          # runtime secrets/environment; public editorial settings
│   ├── Content/         # Topic, Series, registered metadata, menu locations
│   ├── Admin/           # homepage/series selectors, field controls, diagnostics
│   ├── GraphQL/         # public types, fields, bounded connections, access policy
│   ├── URLs/            # typed public URL resolver, conditional CMS redirects
│   ├── Preview/         # launch signing, context validation, compatibility adapter
│   ├── Publishing/      # change detection, old/new snapshots, semantic events
│   ├── Webhook/         # outbox, signing, delivery, retries, logs
│   ├── Rest/            # operational endpoints only
│   └── Support/         # injectable clock, HTTP, logging
├── assets/admin/
├── tests/{Unit,Integration,Fixtures}/
└── vendor/              # runtime dependencies included in release ZIP if needed
```

Keep `Plugin.php` as module wiring. Do not create a provider class for every field if a small cohesive module is sufficient. Use Composer PSR-4 autoloading, reproducible build scripts, and no WordPress core in Git. Package without tests, development dependencies, credentials, HTML prototype assets, or sample posts.

First implementation milestone pins maintained WordPress, PHP, and WPGraphQL versions and proves the required preview/revision APIs. Record minimums and tested versions in the plugin header, readme, CI, and compatibility document. Do not infer support for preview field options merely because registration accepts unknown keys.

Activation registers content structures and queue schema without sending webhooks or installing sample data. Deactivation stops workers without deleting editorial content. Normal uninstall retains content/configuration; destructive cleanup requires a separately invoked, explicit maintenance action.

## 4. Native content and editable metadata

Do not recreate WPGraphQL's native Post, Page, User, Category, Tag, MediaItem, menu, or connection APIs. Register custom fields with schemas, type limits, sanitization, and authorization. REST editing support does not mean anonymous write access or automatic GraphQL exposure.

### 4.1 Topics and categories

Register taxonomy `arcwell_topic` on `post`, with REST/editor support and GraphQL exposure. Use GraphQL type names `ArcwellTopic` / `ArcwellTopics`; verify generated root/connection names by schema introspection. Topics are non-hierarchical in v1; native categories remain hierarchical.

Topic/category fields:

| Value | Storage | Editing / exposure |
| --- | --- | --- |
| Name, slug, description | Native term fields | Native API; use description for intro copy |
| Feature image | `_arcwell_image_id` term meta | Valid attachment ID; public GraphQL resolves a MediaItem or null |
| Promotional headline | `_arcwell_promo_heading` term meta | Optional plain text, max 120 characters; fallback to term name |

Taxonomy management and assignment use mapped WordPress capabilities, not a blanket grant to every authenticated user. Public term URLs must come from the URL resolver. Return all assigned categories/Topics through native connections; the frontend must define a stable card-badge choice (v1: first assigned term by slug) rather than rely on database ordering. A separate editorial primary-term feature is not implied by the single badge in the prototype.

### 4.2 Series

Register CPT `arcwell_series` with title, editor, excerpt, thumbnail, author, revisions, and registered custom-field support; enable REST and GraphQL. Use GraphQL `ArcwellSeries` / `ArcwellSeriesItems`.

Revisionable metadata:

- `_arcwell_post_ids`: ordered unique array of positive Post IDs; limit 100 per Series in v1. Validate post type and the saving editor's ability to select the referenced post. Never silently truncate; return a useful validation error.
- `_arcwell_art_label`: optional plain text, max 24 characters (for example, “Observe.”).
- `_arcwell_edition_label`: optional short label, max 40 characters (for example, “No. 01”).

Use native excerpt for the promotional summary and native content for the Series introduction. Do not store React layout fragments.

Public Series connections return only published, publicly readable posts in stored order. Missing, deleted, private, password-protected, and unpublished references are omitted. `publishedPostCount` counts that same visible set. An empty published Series can render a designed empty state; the homepage promotion is hidden until it has a readable entry.

Public Post → Series membership returns only published readable Series that include the Post. Cache/batch this reverse lookup per request; avoid one full scan per card. A derived index may accelerate it later but must be rebuildable from the canonical Series list.

### 4.3 Page presentation

Revisionable Page metadata:

- `_arcwell_page_template`: `standard` or `about`.
- `_arcwell_about_values`: array of at most three objects `{ heading, body }`, with plain-text heading ≤80 and body ≤500 characters.

Use native title, excerpt, content, and featured image for the rest of the About page. Next.js owns the split layout and visual numbering. Unsupported Page templates fall back to `standard`.

### 4.4 Author and media presentation

- User meta `_arcwell_specialty`: optional public editorial specialty, ≤120 characters. Native public display name, biography, slug, and avatar remain authoritative. Initials are derived in Next.js.
- Attachment meta `_arcwell_credit`: `{ photographer, sourceUrl, licenseLabel, licenseUrl }`. Names/labels are plain text, links must use approved `https`/`http` schemes. Attachment alt, caption, URL, and dimensions remain native.
- Saving user fields requires self-profile permission or `edit_user` as appropriate. Saving media fields requires `edit_post` for the attachment. No email, login name, capabilities, credentials, or private biography fields are added to public GraphQL.
- Meta additions, updates, and deletions affect events; deleting a caption or credit is also a change.

## 5. Homepage and site configuration

### 5.1 Homepage: a revisionable Page document

Use the Page selected by WordPress `page_on_front`; require static-front-page mode for curated-homepage readiness. Store a registered structured object `_arcwell_homepage` on that Page:

| Field | Type / limit | Frontend use |
| --- | --- | --- |
| `heading` | Plain text ≤100 | “A wider view.” |
| `emphasis` | Plain text ≤100 | “A deeper read.”; frontend decides italic treatment |
| `intro` | Plain text ≤400 | Homepage introduction |
| `heroPostId` | Post ID or null | Selected cover story |
| `heroOverlay` | Plain text ≤120 | Optional photo overlay sentence |
| `editorsPickIds` | Ordered unique Post IDs, maximum 3 | Considered edit |
| `featuredTopicId` | Topic term ID or null | In-focus panel |
| `featuredSeriesId` | Series ID or null | Series promotion |
| `manifestoHeading` | Plain text ≤180 | Lower-page publication statement |
| `manifestoEmphasis` | Plain text ≤180 | Optional emphasized second line |
| `aboutPageId` | Page ID or null | About call to action |

Do not put these values in options: editors must be able to revise and preview this document. Implement schema-aware Gutenberg controls/sidebar with native components and permission-checked selectors. Changes must participate in save/autosave, not bypass revisions through a separate options form.

Public lookup returns null if the designated Page is unavailable or not published/readable. On a valid homepage with invalid selections: hero falls back to newest public Post, missing picks are omitted, invalid Topic/Series/About links are hidden. Pick order is preserved; duplicate hero/pick entries are excluded in presentation. Latest stories query excludes IDs already shown in hero/picks and requests enough remaining posts to fill its slots. With no posts, Next.js renders an honest empty state.

The resolver never exposes the raw IDs of rejected private references. Save-time permission checks do not replace request-time visibility checks.

### 5.2 Site configuration: non-secret, saved immediately

Use a validated WordPress option for the public publication tagline, founding year, and optional issue label. Native site title/description remain native WPGraphQL settings. Register menu locations `ARCWELL_PRIMARY`, `ARCWELL_FOOTER_EXPLORE`, and `ARCWELL_FOOTER_ABOUT`; use native menu queries and editing.

Site settings and menus are immediate configuration in v1, not revision-previewed documents. Clearly distinguish that behavior from Page/Post/Series preview. An administrator manages settings; editors manage content according to WordPress capabilities.

Expose only public presentation fields and the configured public origin. Keep environment labels, CMS infrastructure details, delivery logs, and secret-readiness diagnostics out of the public config type.

## 6. GraphQL contract

Register extensions on `graphql_register_types`. Reuse WPGraphQL models/loaders so native visibility and field behavior are preserved; do not return raw `WP_Post` objects or IDs without checking access.

The following is the target contract for Arcwell-added fields. Connection/edge types must use WPGraphQL's connection registration APIs; generated names and native fields are locked by schema fixtures before frontend code generation.

```graphql
extend type RootQuery {
  arcwell: ArcwellConfig!
  arcwellHomepage: ArcwellHomepage
}

type ArcwellConfig {
  contractVersion: String!
  frontendOrigin: String!
  tagline: String
  foundingYear: Int
  issueLabel: String
}

type ArcwellHomepage {
  sourcePage: Page!
  heading: String
  emphasis: String
  intro: String
  hero: Post
  heroOverlay: String
  editorsPicks: [Post!]!
  featuredTopic: ArcwellTopic
  featuredSeries: ArcwellSeries
  manifestoHeading: String
  manifestoEmphasis: String
  aboutPage: Page
}

extend type Post {
  frontendUrl: String
  arcwellSeries(first: Int = 10, after: String): ArcwellPostSeriesConnection
}

extend type Page {
  frontendUrl: String
  arcwellHomepage: ArcwellHomepage
  arcwellPresentation: ArcwellPagePresentation!
}

type ArcwellPagePresentation {
  template: String!
  values: [ArcwellValue!]!
}

type ArcwellValue {
  heading: String!
  body: String!
}

extend type ArcwellSeries {
  frontendUrl: String
  arcwellArtLabel: String
  arcwellEditionLabel: String
  arcwellPosts(first: Int = 10, after: String): ArcwellSeriesPostsConnection
  publishedPostCount: Int!
}

extend type ArcwellTopic {
  frontendUrl: String
  arcwellImage: MediaItem
  arcwellPromoHeading: String
}

extend type Category {
  frontendUrl: String
  arcwellImage: MediaItem
  arcwellPromoHeading: String
}

extend type Tag { frontendUrl: String }
extend type User { frontendUrl: String, arcwellSpecialty: String }
extend type MediaItem { arcwellCredit: ArcwellMediaCredit }

type ArcwellMediaCredit {
  photographer: String
  sourceUrl: String
  licenseLabel: String
  licenseUrl: String
}
```

`ArcwellPostSeriesConnection` has normal `nodes`, `edges`, and `pageInfo`, with ArcwellSeries nodes; `ArcwellSeriesPostsConnection` has Post nodes. Add maximum `first=50`, positive integer validation, and opaque cursor pagination. Do not expose unbounded root collection fields. The homepage picks list is deliberately bounded at three.

Do not add `arcwellPosts`, `arcwellAuthors`, `arcwellMedia`, custom search endpoints, a serialized Gutenberg block tree, or duplicate SEO graphs. Do not add reading-time, contents, or decorative-layout fields when the frontend can derive them reliably.

The Page-level homepage field is essential: the public root resolves the published front Page; an authenticated scoped preview can query the actual Page node and its revision-aware configuration. Both paths share the same field resolver and validation rules.

### Visibility, errors, and batching

- Anonymous queries may resolve only published/readable entities. Native WPGraphQL access checks still apply; Arcwell's curated public fields additionally omit password-protected content because v1 has no password-entry frontend.
- Lists return `[]` for no visible members; optional relationships return null. Do not substitute drafts or hidden IDs into a public response.
- Authentication alone must not make all curated lists show drafts. Preview overlay is explicitly scoped to the authorized target; referenced posts remain public unless separately authorized under a future broader preview design.
- Unsupported routable objects return null URLs. Infrastructure/query errors must remain errors rather than being converted to “no content.”
- Invalid runtime configuration yields explicit degraded readiness; no invented frontend origin. Required non-null config fields may produce a configuration error when not configured.
- Batch referenced entity loading and cache derived lookups per request. Verify bounded SQL behavior with 50-card fixtures.

## 7. Frontend query inventory and ownership

| View | Query native WPGraphQL | Arcwell additions needed |
| --- | --- | --- |
| Shared layout | Site settings, menus | Public config, typed frontend URLs |
| Homepage | Latest public posts and native card fields | `arcwellHomepage` selections/copy |
| Journal | Paginated posts, category/author filters | Public URL helpers only |
| Topic landing/detail | Registered Topic terms and native post connections | Topic image/promo and URL |
| Article | Native post content, excerpt, author, media, terms | Series membership, URL, media credits |
| Author | Public User and published post connection | Specialty, public URL |
| Series | Registered Series entity | Ordered post connection, published count, art/edition labels |
| About / ordinary Page | Native Page content and image | Approved presentation metadata |
| Search | Native `posts(where: { search: ... })` and pagination | None for base search; no client-wide fixture-style index |
| Credits Page | Native Page content and selected media | Credit metadata as used by the template |
| Sitemap and feed | Paginated published entities | Public URL helper; existing SEO adapter data if applicable |
| Preview | Authenticated native target and revision context | Scoped launch token, revision-aware custom fields |

The frontend must validate GraphQL documents and generate TypeScript types against a real fixture CMS. Exact term-filter inputs and custom-root names are discovered from that schema, not guessed from the prototype's query strings.

## 8. Public URL service and route contract

Use typed identities, not `$resolver->resolve(123)` for every object. Post, term, and user numeric IDs can overlap. Expose distinct `forPost()`, `forTerm()`, `forUser()` methods or an equivalent typed value object.

| Entity | Public path |
| --- | --- |
| Post | `/articles/{slug}/` |
| Front Page | `/` |
| Other Page | `/{hierarchical-uri}/` |
| Series | `/series/{slug}/` |
| Topic | `/topics/{slug}/` |
| Category | `/category/{slug}/` |
| Tag | `/tags/{slug}/` |
| Public author | `/authors/{slug}/` |

Indexes owned by Next.js: `/articles/`, `/topics/`, `/series/`, `/authors/`, `/search/`. Keep `/sitemap.xml` and `/feed.xml`. Prefixes and static routes are reserved; detect conflicting Page paths and surface an editor/admin error. Handle ancestor Page slug changes and descendant URLs.

Register URL fields on specific routed types, not only `ContentNode`: terms/users need coverage, and media is not an article route. Preserve WordPress `uri` as CMS identity; do not globally mutate it to masquerade as the frontend path. Use `frontendUrl` for public links.

The same resolver supplies GraphQL, public CMS redirects, preview destinations, and webhook snapshots. SEO/sitemap/feed consumers use these URLs rather than re-implementing post path rules. SEO canonical overrides are separate editorial values requiring a documented validation policy; they must not change the content's actual routing identity.

CMS redirects run only for supported public GET/HEAD requests. Exclude admin, login, APIs, media/uploads, cron, authentication, preview launches, and internal tools. Do not redirect a missing or unpublished object to a plausible public URL. Check redirect loops/origin configuration and use a fixed approved frontend origin. Preserve safe pagination/search semantics explicitly; never append arbitrary user-controlled redirect destinations.

## 9. Preview launch and authentication

Canonical frontend endpoints shared with the project spec:

```text
GET  /api/arcwell/preview
GET  /api/arcwell/preview/exit
POST /api/arcwell/revalidate
```

Arcwell Core uses `preview_post_link` plus the required editor bridge to capture the actual autosave context. Verify current editor permissions for the target before signing. An editor must never get a signed token for arbitrary requested IDs without that check.

Use a versioned base64url JSON payload and a separate hex HMAC-SHA256 signature. Sign the exact encoded payload bytes with a purpose prefix (`arcwell-preview-v1.`), so PHP and TypeScript do not independently serialize/reorder JSON for validation. Reject unknown versions/fields, wrong origin/environment, invalid IDs, expired/future timestamps, and invalid signatures using constant-time comparison.

Implemented v1 claims:

```json
{
  "v": 1,
  "source": "configured-cms-instance-id",
  "audience": "https://www.example.com",
  "entityType": "post",
  "databaseId": 123,
  "mode": "autosave",
  "revisionDatabaseId": null,
  "featuredImageDatabaseId": 456,
  "issuedAt": 1789344000,
  "expiresAt": 1789344300,
  "frontendUrl": "https://www.example.com/articles/example/"
}
```

`databaseId` is the parent content ID. WordPress's `preview_id` must not be assumed to be a revision ID. An explicit historical revision selection uses a separately validated `revisionDatabaseId` that belongs to the signed parent and is authorized. The compatibility adapter maps these claims to supported native authenticated queries; do not invent a revision endpoint or silently substitute a different autosave.

Next.js validates the launch, confirms authorized content retrieval, establishes Draft Mode plus an application-controlled scoped preview session, and redirects to a trusted route. Draft Mode alone is not authorization. The session is bound to the signed content ID/mode and expires after 30 minutes in v1; navigation to unrelated unpublished IDs must fail. Use HttpOnly/Secure production cookies and clear both application state and Draft Mode on exit. Launch TTL is five minutes. Shared links act as short-lived bearer access; they are not public content.

Only Next.js holds `WP_PREVIEW_USERNAME` and `WP_PREVIEW_APP_PASSWORD`. Use a dedicated non-admin account with only the required read/edit capabilities for supported content; test those permissions with posts owned by other editors. WordPress supplies the launch signature; it does not send Application Passwords to the browser. HTTPS is required for production authenticated transport.

Preview responses bypass public/CDN caches and use noindex; avoid leaking launch URLs through referers/logs. Exiting an unpublished draft returns to a safe published destination instead of a nonexistent canonical article.

## 10. Preview acceptance and compatibility

Required in v1:

- Draft, saved content, current autosave, and authorized saved-revision views for Post/Page/Series.
- Title, excerpt, body, image changes (including removal), and Arcwell revisionable metadata are previewed accurately for the signed target.
- Homepage config preview goes through its Page node, not the public singleton root.
- About values and Series ordering are revision-aware; their referenced articles are still publication-filtered.
- Topic/category relationships, user profiles, site options, and menus are saved/immediate in v1; do not promise unsaved taxonomy/profile/menu preview. Show a clear editor explanation where appropriate.
- Detect when a requested autosave/revision could not be applied. Do not label stale published content as a successful unpublished preview.

Revision-enabled storage and GraphQL preview resolution are separate requirements. Pin a WPGraphQL version and prove its supported custom-field preview configuration before adopting `isPreviewable`/request-context APIs. Integration tests must prove the actual stored revision is read, including removal/null values. The current official [preview documentation](https://www.wpgraphql.com/docs/previews) describes authenticated request-level preview and field opt-in; compatibility must be verified against the chosen release.

Featured-image preview is not deferred: the prototype makes images prominent and the original project requires autosave previews. If the pinned stack cannot provide an acceptance case, implement a narrowly scoped adapter or identify it as a release blocker rather than silently dropping it to v1.1.

## 11. Gutenberg and SEO integration boundaries

Use native rendered `content` through WPGraphQL for v1 and a documented frontend HTML/block allowlist. Constrain the supported editor block set to the project spec, and supply editor styles where useful. Prove real saved markup for paragraphs, headings, image/gallery, lists, quote, code, table, buttons, separator, columns, group, cover, and approved embeds. The HTML showcase alone is not evidence of compatibility.

Next.js owns rich-content rendering, sanitization rules, responsive images, heading anchors, approved provider scripts, and any consent behavior. Arcwell Core does not send arbitrary script/CSS fields or build a universal Gutenberg parser API. Unsupported third-party blocks must be flagged or use an explicitly safe fallback.

Choose one SEO source adapter in the first integration milestone. Reuse the selected SEO plugin's existing GraphQL or REST exposure where available; verify title, description, robots, canonical override, social image, and structured data. Add a minimal adapter only for a demonstrated missing field. Do not expose competing schema graphs or silently replace configured SEO with fixture metadata. Next.js emits the public HTML and normalizes public entity URLs.

## 12. Publishing events and dependency coverage

Hook callbacks collect changes, not HTTP calls. Compare meaningful public state and capture both previous and current dependencies. A status hook alone is insufficient: terms and metadata may be saved later.

Use `wp_after_insert_post` for completed native saves, status transitions for prior state, and the concrete term/meta/menu/config hooks required by the storage model. Coalesce changes within one request after relevant writes, then persist the final event. Tests must cover REST/Gutenberg saves, meta-only updates, scheduling, deletion, and direct supported WordPress API updates.

Track:

- Post/Page/Series publish, update, withdrawal (draft/private/password protection/trash), permanent deletion, slug changes.
- Old/new author, categories, tags, Topics, Series membership, and public URLs.
- Topic/category/tag rename/deletion, term imagery/promo changes.
- Author public name/biography/specialty changes and deletion/reassignment.
- Homepage fields, selected front Page/static-front-page mode, site config, and menus.
- Attachment file/metadata/alt/caption/credit updates or deletion; dependent content and term images.
- Page ancestor URL changes and descendants.

Revision/autosave writes do not trigger public revalidation unless a separate change actually affects public output. A transition from published to private must still create a withdrawal event even though the final entity is not public. A draft-only edit should not leak its title, content, or private relationships into events.

### Snapshot and dependency policy

Maintain enough previous public state to reconstruct removals. Before deletion, capture the former public URL and relationships; they cannot be recovered afterward. For reassignments, invalidate dependents of both old and new entities. Use typed ID sets to avoid cross-taxonomy collisions.

The plugin reports semantic relationships and affected collections, not Next.js tags. For broad configuration/media effects where precise reverse references cannot be proven, send a documented logical collection scope (for example, `media-dependent-content`) so the frontend can perform a safe bounded broad invalidation. Do not silently miss references hidden in content blocks. The scope vocabulary is part of the contract.

## 13. Versioned webhook payload

Canonical endpoint: `POST {ARCWELL_FRONTEND_URL}/api/arcwell/revalidate`.

```http
Content-Type: application/json
X-Arcwell-Event: content.updated
X-Arcwell-Delivery: delivery-uuid
X-Arcwell-Timestamp: 1789344000
X-Arcwell-Signature: sha256=<hex-hmac>
```

```json
{
  "version": "1",
  "eventId": "event-uuid",
  "source": "configured-cms-instance-id",
  "event": "content.updated",
  "occurredAt": "2026-09-14T01:30:00Z",
  "entity": { "type": "post", "id": 123 },
  "before": {
    "public": true,
    "frontendUrl": "https://www.example.com/articles/old-slug/",
    "authors": [8], "categories": [5], "tags": [], "topics": [14], "series": [3]
  },
  "after": {
    "public": true,
    "frontendUrl": "https://www.example.com/articles/new-slug/",
    "authors": [9], "categories": [5], "tags": [], "topics": [22], "series": []
  },
  "affected": {
    "posts": [123], "pages": [], "authors": [8, 9],
    "categories": [5], "tags": [], "topics": [14, 22], "series": [3], "media": [],
    "collections": ["journal", "homepage"]
  }
}
```

Allowed event family: `content.published`, `content.updated`, `content.unpublished`, `content.deleted`, `taxonomy.updated`, `taxonomy.deleted`, `author.updated`, `author.deleted`, `media.updated`, `media.deleted`, `homepage.updated`, `site.updated`, `navigation.updated`, `arcwell.test`.

`before`/`after` are public snapshots or null. A withdrawal may use `after: { "public": false }` with no draft details. `affected` is the union needed to refresh old/new public dependents. Define typed payload schemas per event; a test event need not fabricate an entity. Cap payload bytes and array counts; explicit collection scopes cover oversized dependency sets. Never truncate silently.

`eventId` is stable across retries; delivery ID is unique per transport attempt. `source` is checked against the frontend environment's configured allowlist. The receiver rejects unsupported versions/events, validates the body, and only marks an event processed after invalidation succeeds. Successfully processed event IDs make duplicate delivery harmless. Events trigger rereads rather than writing payload content into caches, so delayed events cannot restore obsolete article data.

## 14. Signing, durable dispatch, retries

HMAC-SHA256 input is `timestamp + "." + exact raw body bytes`, signed with `ARCWELL_WEBHOOK_SECRET`. Use a fresh transport timestamp/signature per retry, a five-minute freshness window with documented clock tolerance, and constant-time validation. Validate that unsigned descriptive headers agree with signed payload fields; do not trust header event names alone.

Use a small durable database outbox/delivery table via a versioned migration. Store event ID, source/entity, JSON payload, state, attempts, next attempt, lease, response category, and timestamps. No transient-only queue, and no growing autoloaded option containing logs. Check persistence failures and show actionable admin diagnostics.

Use WP-Cron as the replaceable worker trigger, backed by host/system cron or WP-CLI on a reliable interval (target one minute). Headless CMS traffic may be too low to drive request-triggered cron. Editor saves do not wait for a network request.

Worker claims use a lease/atomic update to prevent concurrent duplicate processing; abandoned leases become available again. Receiver idempotency still handles crashes after successful delivery but before local acknowledgment.

Attempt schedule: immediately eligible, then +30 seconds, +2 minutes, +10 minutes. These are earliest eligible times, not timing guarantees; actual dispatch depends on the worker interval. Retry network failures, 408, 429, and transient 5xx; respect bounded `Retry-After`. Stop and surface configuration/authentication/version errors. Final failures remain visible with a capability-checked manual retry action.

Use the WordPress HTTP API with explicit timeout/response limits. Only deliver to the configured approved frontend endpoint; disallow redirects to arbitrary origins. No URL submitted to the test endpoint may override the configured destination.

Keep processed logs bounded (default 30 days / 5,000 rows); retain unresolved failures until addressed under a documented cap/retention policy. Never log secrets, Authorization headers, preview URLs/tokens, draft content, or complete sensitive responses. Record summarized status, attempt, latency, and error category.

## 15. Admin and operational REST

Separate editorial controls from administrator diagnostics:

- Page editor: homepage fields and About template values.
- Series editor: ordered Post selector, art/edition labels, native text/image fields.
- Topic/category forms: image/promo.
- Profile/media forms: specialty/credits.
- Settings → Arcwell: configuration readiness, schema compatibility, latest successful delivery, pending/failed jobs, worker last-run, signed test and retry controls. Never display secret values.

REST namespace `/wp-json/arcwell/v1`:

| Endpoint | Access | Behavior |
| --- | --- | --- |
| `GET /status` | Public | Minimal `status: ok/degraded`, `contractVersion: 1`; no environment/origin/credential/config dump. Return degraded status when required dependencies/config are unavailable. |
| `GET /diagnostics` | `manage_options` | Dependency versions, redacted readiness, worker age, queue counts, last delivery; no secrets. |
| `POST /webhooks/test` | `manage_options` + authenticated request | Queue a signed `arcwell.test`, return 202 + event ID. Poll diagnostics; do not falsely claim synchronous delivery succeeded. |
| `POST /webhooks/{eventId}/retry` | `manage_options` + authenticated request | Retry an eligible existing failed event; no user-supplied body or destination. |

Use real permission callbacks and WordPress REST nonce checks for cookie-authenticated admin requests. Nonce is CSRF protection, not a replacement for capability checks. Operational endpoints are not public content read APIs.

## 16. Environment and shared naming

CMS runtime configuration:

```text
ARCWELL_FRONTEND_URL       # approved frontend origin, no arbitrary endpoint path
ARCWELL_PREVIEW_SECRET
ARCWELL_WEBHOOK_SECRET
ARCWELL_ENVIRONMENT       # diagnostics / isolation, not public GraphQL
ARCWELL_SOURCE_ID         # unique instance identifier per environment
```

Next.js server configuration uses the same preview/webhook secrets and expected source ID, plus `WORDPRESS_URL`, `WORDPRESS_GRAPHQL_URL`, `WP_PREVIEW_USERNAME`, and `WP_PREVIEW_APP_PASSWORD`. `NEXT_PUBLIC_SITE_URL` may expose the public site origin only. Environment examples contain placeholders, never working secrets.

Do not store secrets in editable options or auto-copy production credentials into staging. A restored CMS must update source ID, frontend origin, and secrets before enabling outbound events. Retain queued events' original source and prevent replay into a different environment.

These `/api/arcwell/*` paths and `ARCWELL_*` names supersede earlier conceptual `/api/preview`, `/api/revalidate`, `PREVIEW_SECRET`, and `REVALIDATION_SECRET` examples. The project spec must use the same names.

## 17. Hooks and extension points

Use concrete hooks according to the tested write path:

| Area | Hooks / integration |
| --- | --- |
| Model/schema | `init`, `graphql_register_types` |
| Metadata editing | Registered REST meta and schema-aware editor controls; nonce/capability-checked legacy form handlers where used |
| Preview | `preview_post_link`, minimal editor-context bridge |
| Post lifecycle | `transition_post_status`, `wp_after_insert_post`, pre-deletion capture and post-deletion event |
| Relationships | `set_object_terms`, term edit/delete hooks |
| Meta | Added/updated/deleted post, term, and user meta hooks, filtered to exposed fields |
| Author | `profile_update`, deletion/reassignment hooks |
| Configuration | Relevant option updates, front-page change, menu updates/deletions/location assignment changes |
| Media | Attachment update/delete and relevant attachment meta hooks |
| Ops | `rest_api_init`, scheduled worker hook, activation/deactivation migrations |
| Redirects | `template_redirect` with public-request guards |

Provide stable actions/filters for `arcwell_content_changed`, `arcwell_frontend_url`, and `arcwell_webhook_payload`. Validate extension output against the same payload/URL rules. Adding supported post types requires matching route/schema/access support, not just disabling an event guard.

## 18. Acceptance tests

The plugin is ready only when these tests run against actual WordPress + WPGraphQL:

1. ZIP installs/activates; dependency absence degrades safely; upgrade/deactivation preserves content.
2. Native post/page/media APIs remain authoritative; no generic duplicate REST routes.
3. Generated schema contains the target custom fields/connections and compiles every frontend query.
4. Category/Topic separation, term imagery, About values, author specialties, and attachment credits save and resolve correctly.
5. Homepage revisionable metadata saves through Gutenberg, retains ordering, handles null/deleted/unpublished references, and previews via its Page node.
6. Series order, pagination, visible count, and reverse membership agree; two-series membership is supported; hidden references never leak.
7. Public query tests cover drafts, private/password-protected posts, nested relationships, and missing entities.
8. URL tests cover all entity types, numeric ID collisions, hierarchical pages, front Page, reserved routes, renamed/deleted URLs, and CMS redirect exclusions.
9. Preview tests cover target scoping, tampering, expiry, wrong audience/source, image replacement/removal, custom meta autosave, authorized historical revision, denied historical revision, and exit behavior.
10. Publishing tests include status-stable update, scheduled publish, unpublish/private/password-protection/trash/delete, meta-only edits, old/new term/author/Series changes, media/config/menu updates, and Page ancestor rename.
11. Before/after dependency payloads include removals; draft-only saves do not emit private information; save hooks do not double-send.
12. PHP signing fixtures verify against TypeScript; body tampering, stale signatures, unknown versions, and wrong source fail.
13. Durable queue survives worker failures; leases recover; retries use fresh signatures/stable event IDs; exhausted jobs remain visible; duplicate/out-of-order delivery is harmless.
14. Operational routes enforce capabilities; tests cannot send requests to arbitrary targets; logs are bounded/redacted.
15. Real core block fixtures render correctly in Next.js; selected SEO adapter supplies required values without duplicate schema output.
16. Integration E2E: editor saves → secure preview → publish → queued webhook → relevant frontend refresh; withdrawal removes content from all public dependent views.

CI: Composer validation, PHPCS, PHPStan, PHPUnit unit/integration tests, schema export/diff + GraphQL code generation, and frontend contract/E2E checks. Unit tests focus on decisions and failure boundaries; integration tests prove actual hooks, access checks, and stored revisions.

## 19. Build sequence and review gates

| Milestone | Work | Demonstrable result |
| --- | --- | --- |
| 1. Compatibility and scaffold | Pin/test dependencies; bootstrap; local WordPress; packaging/CI; native query and authenticated preview spike; select SEO adapter | Installable minimal ZIP, schema fixture, one native published/draft read, verified preview API support |
| 2. Editorial model and controls | Topics, Series, typed metadata, menus, homepage/About editor controls, term/profile/media forms | Editor can populate every design field without editing code or raw IDs |
| 3. Public GraphQL contract | Access-filtered resolvers, ordered/reverse connections, config, URL resolver, query fixtures | All page queries compile and resolve real content, including sparse/private cases |
| 4. Preview integration | Revision-aware custom fields, signed launch, image context, scoped Next.js session and exit | Draft/autosave/selected-revision changes appear accurately without public exposure |
| 5. Publishing pipeline | Before/after snapshots, semantic events, durable queue, signatures, retries, worker setup | Real publish/update/withdrawal refreshes correct frontend collections |
| 6. Operations and release | Diagnostics/test/retry, redirect guards, security/compatibility suite, release ZIP and runbooks | Client-installable package with proven acceptance cases and recovery instructions |

Keep each milestone reviewable. Build the native-content/preview spike before committing to a broad field implementation. Do not build an admin dashboard before proving the API contract. Next.js preview/revalidation handlers are coordinated frontend deliverables, not PHP-plugin features.

## 20. Documentation and references

Required release docs: installation/configuration, supported versions, content field map, GraphQL query examples/schema, signed payload/token contracts with shared fixtures, editor guidance, preview limits, system-cron setup, queue recovery, secret rotation, and upgrade/rollback procedure.

Official references checked during this audit:

- [WPGraphQL native content](https://www.wpgraphql.com/docs/posts-and-pages), [custom post types](https://www.wpgraphql.com/docs/custom-post-types), [custom taxonomies](https://www.wpgraphql.com/docs/custom-taxonomies), and [menus](https://www.wpgraphql.com/docs/menus).
- [WPGraphQL preview behavior](https://www.wpgraphql.com/docs/previews); verify against the pinned plugin release.
- [WordPress registered post metadata](https://developer.wordpress.org/reference/functions/register_post_meta/) and [completed post-save hook](https://developer.wordpress.org/reference/hooks/wp_after_insert_post/).
- [WordPress cron](https://developer.wordpress.org/plugins/cron/); request-triggered scheduling is not an always-running job worker.
- [WordPress search](https://developer.wordpress.org/reference/classes/wp_query/) and [Next.js revalidation semantics](https://nextjs.org/docs/app/api-reference/functions/revalidateTag).

The durable principle remains: **WordPress describes content and publishing changes. Arcwell Web decides how those changes are rendered and cached.**

## Implementation handoff (0.1.0)

The implementation is in `wordpress/wp-content/plugins/arcwell-core/`. Its README and `docs/frontend.md` document installation, tested versions, the exact revision adapter and frontend integration. The signed launch includes a trusted `frontendUrl`. Historical image snapshots are recorded for revisions created after installation; legacy revisions without snapshots are rejected rather than showing a current image. Oversized dependency sets switch to all documented collection scopes. Sent records retain the 30-day/5,000-row bounds; unresolved events are retained without automatic pruning and require monitored operational resolution. Next.js handlers, durable receiver idempotency and deployed cache acceptance remain frontend integration work.
