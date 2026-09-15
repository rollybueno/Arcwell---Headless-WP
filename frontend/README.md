# Arcwell dynamic frontend

Next.js implementation of the HTML prototype, connected to **http://localhost:10048/arcwell/graphql**. Open **http://localhost:3000**. The original `HTML/` directory remains the static design reference.

## Run locally

Use Node.js 22 (see `.nvmrc`). The machine's default Node 18 cannot run this Next.js version; use `nvm use` first if needed.

```sh
cd frontend
nvm use
npm install
cp .env.example .env.local # First-time setup only; do not overwrite an existing configuration.
npm run dev
```

`.env.local` is ignored by Git. Its two public connection values are already configured in this workspace:

```dotenv
WP_GRAPHQL_URL=http://localhost:10048/arcwell/graphql
FRONTEND_URL=http://localhost:3000
```

The WordPress plugin's **Website address** should remain `http://localhost:3000`. If either application runs in a container, localhost refers to that container: configure a reachable host address for server-to-server requests. The supplied source works from this host.

## Live routes and editing

| Route | WordPress source |
| --- | --- |
| `/` | Designated front Page's Arcwell homepage fields and published Posts |
| `/journal/` | Published Posts, with cursor pagination |
| `/articles/{slug}/` | Post title, excerpt, Gutenberg content, author, imagery and credits |
| `/category/{slug}/`, `/tags/{slug}/` | Native taxonomy archives |
| `/topics/`, `/topics/{slug}/` | Arcwell Topics and their Posts |
| `/series/`, `/series/{slug}/` | Arcwell Series and their ordered Posts |
| `/authors/{slug}/` | Contributor profile and Posts |
| `/search/?q=...` | WordPress server-side post search |
| `/{page-path}/` | Native hierarchical Pages and About presentation values |
| `/feed.xml`, `/sitemap.xml` | Public RSS and content sitemap |

Navigation reads the plugin's WordPress menu locations and resolves linked content to frontend URLs. An unassigned menu falls back to the journal, Topics and Series; About appears when a published About-template Page exists. Top-level menu links are supported in this first frontend version; nested dropdown navigation remains to be implemented.

Homepage copy keeps the prototype's defaults until editors set custom copy. Unselected hero falls back to the most recent public Post; unselected editor picks use recent Posts. Curated selections exclude unavailable content. Cards use featured images, then the first image from the Post body, then a neutral placeholder. No sample articles are inserted into WordPress and no prototype fixture stories are served as live content. Reading time uses the real body text.

The supplied CMS currently includes “Hello world!” as its newest post, so it appears as the default cover story. Select an architecture Post in the front Page's **Arcwell homepage → Cover story** field to change this. Topics, Series, menus and homepage selections were empty during initial verification; their published appearance needs populated editorial content.

## Finish the publishing and preview connection

Public pages work with the two URL settings above. To enable the plugin's connection test and immediate publishing refresh, add these values to the frontend's private `.env.local`, using **Copy ID / Copy key** under WordPress Settings → Arcwell:

```dotenv
ARCWELL_SOURCE_ID=the-saved-website-identity
ARCWELL_PREVIEW_SECRET=the-saved-preview-security-key
ARCWELL_WEBHOOK_SECRET=the-saved-publishing-security-key
```

For unpublished previews, also set `WP_PREVIEW_USERNAME` and `WP_PREVIEW_APP_PASSWORD` for a dedicated WordPress editorial account. These values are server-only; do not prefix them with `NEXT_PUBLIC_`. Restart the frontend after changing configuration. This implementation does not retrieve WordPress secrets automatically.

The handlers are `/api/arcwell/preview`, `/api/arcwell/preview/exit`, and `/api/arcwell/revalidate`. Unconfigured handlers return 503 with a setup message. Preview links are verified before authenticated GraphQL reads; sessions are scoped to the signed target path/ID and expire after 30 minutes. Public pages never receive the preview credentials. Authenticated fetches bypass caches. Preview pages request noindex and offer an exit link. Historical revisions use the plugin's exact-revision adapter.

The WordPress scheduler must run for queued deliveries to arrive. Public GraphQL data has a 60-second fallback revalidation interval; successful signed content events expire the `arcwell` cache tag immediately. This first frontend version uses a broad Arcwell invalidation instead of selective per-entity tags, covering removals and relationship changes reliably. Keep the frontend's persistent Next.js cache writable.

Webhook acknowledgments are persisted in `.arcwell-deliveries/`, keyed by source and event ID. A leased filesystem lock recovers after 30 seconds without a heartbeat; failures do not acknowledge success. The storage is appropriate for this persistent local/single-host deployment. Multi-instance or ephemeral hosting must provide shared durable idempotency storage and shared cache invalidation. Keep acknowledgment storage through restarts; records contain hashes/timestamps, not content or secrets. Archive old acknowledgments under the deployment's retention policy; invalidation is safe to repeat after archival.

## Validation and current scope

```sh
npm test
npm run build
npm start
```

Verified against the supplied live WordPress instance: homepage, journal, article body and imagery, search, category and author archives, next-page navigation, empty Topics/Series, mobile menu, missing-article 404, feed and sitemap. Focused tests cover sanitization, unavailable-content filtering and delivery locking/idempotency. Public HTML is rendered on the server. Gutenberg HTML is sanitized; core typography, images, galleries, columns, tables, buttons, quotes and supported video embeds have frontend styles. Arbitrary inline styles and third-party block scripts are intentionally not executed.

The actual CMS-to-frontend signed delivery and authenticated draft/revision flow still need the matching private values and editorial account above. No successful end-to-end preview is claimed without those credentials. The endpoint implementation is present; this is not yet full production/client acceptance. Populate Topics/Series/menus, verify their actual content, then complete the signed publish/withdrawal and cross-editor preview checks on staging.
