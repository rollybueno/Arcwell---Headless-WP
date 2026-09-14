# Frontend contract v1

The repository's `packages/arcwell-contract/server.mjs` exports Node server helpers; it has no browser-safe entry point. Import it only from server handlers. Configure `source`, `frontendOrigin`, `previewSecret` and `webhookSecret` from deployment secrets, matching WordPress. The helper is not a deployed Next.js route or cache implementation.

## Public data

Use `/graphql` for native Post, Page, user, menu, category, tag, Topic, media and Series queries. Use native `content` for rendered Gutenberg HTML and implement/style the permitted core blocks in the frontend. Sanitize any HTML at the rendering boundary. Site navigation uses native WPGraphQL menus; assign menu locations in WordPress. Arcwell adds:

```graphql
query Homepage {
  arcwell { contractVersion frontendOrigin tagline foundingYear issueLabel }
  arcwellHomepage {
    heading emphasis intro heroOverlay manifestoHeading manifestoEmphasis
    sourcePage { databaseId }
    hero { databaseId title frontendUrl featuredImage { node { sourceUrl altText } } }
    editorsPicks { databaseId title frontendUrl }
    featuredTopic { databaseId name frontendUrl arcwellPromoHeading }
    featuredSeries {
      databaseId title frontendUrl arcwellArtLabel arcwellEditionLabel publishedPostCount
      arcwellPosts(first: 10) { nodes { databaseId title frontendUrl } pageInfo { hasNextPage endCursor } }
    }
    aboutPage { databaseId frontendUrl }
  }
}
```

Page adds `arcwellPresentation { template values { heading body } }` and revision-aware `arcwellHomepage`. User adds `arcwellSpecialty`. MediaItem adds `arcwellCredit { photographer sourceUrl licenseLabel licenseUrl }`. Category/ArcwellTopic add `arcwellImage` and `arcwellPromoHeading`. Post adds `arcwellSeries(first: 10)`. Both Series connections accept forward pagination, `first` 1–50, and `after`; reverse pagination is rejected. Series stores up to 100 unique ordered Posts. Curated public results omit drafts, private/password-protected content and unavailable media even for authenticated queries. Empty selections have deterministic fallbacks; a missing homepage returns null. Use native connections for full archives/search.

Use typed `frontendUrl` instead of rewriting WordPress `uri`. Routes are `/articles/{slug}/`, `/series/{slug}/`, `/topics/{slug}/`, `/category/{slug}/`, `/tags/{slug}/`, `/authors/{nicename}/`, and hierarchical Pages. The designated front Page is `/`. Unpublished entities have no public URL. Reserved Page prefixes are rejected in REST editing. The frontend remains responsible for old-slug redirects, search, pagination, metadata, feeds, sitemaps, forms and interaction state.

## GET /api/arcwell/preview

1. Read `payload` and `signature` from the launch query; call `verifyPreview`. It validates exact HMAC bytes, version, source, origin, identity and five-minute expiry. `frontendUrl` is part of the signed claims; never redirect to an independent request parameter.
2. Retrieve the signed parent through WPGraphQL using server-held `WP_PREVIEW_USERNAME`/`WP_PREVIEW_APP_PASSWORD` and `previewHeaders(claims)`. Use a dedicated least-privilege editorial service account that can edit/read the supported targets; verify other-editor drafts on the deployment. Public requests must never carry this authorization.
3. The standard `X-GraphQL-Preview` supplies parent and image context. `X-Arcwell-Revision` pins the exact validated revision, or `0` for saved content. Request a native node query by database ID; request `Page.arcwellHomepage` for homepage preview, not the published root query. Check GraphQL errors and target existence before granting a session.
4. Enable Next Draft Mode and store `createPreviewSession(...)` in an HttpOnly, Secure production, SameSite=Lax cookie with 30-minute lifetime. Do not log the launch or cookie. Redirect to the signed destination and remove launch parameters. Use `Cache-Control: private, no-store`, a noindex policy and restrictive referrer policy.
5. On subsequent preview renders, call `readPreviewSession(cookie, targetDatabaseId, config)` before any authenticated fetch; Draft Mode alone grants nothing. Forward only the validated preview headers. Do not cache the authenticated response or allow navigation to unrelated drafts.

The editor autosaves before launch. Historical revision selection validates parent identity and uses the revision's stored featured-image snapshot, including explicit image removal (`0`). Revisions created before Arcwell installation have no reliable image snapshot and historical launch is rejected; create a fresh revision or preview current content. Site settings and menus are immediate configuration, not revision documents.

## GET /api/arcwell/preview/exit

Clear both Draft Mode and the application preview cookie. Return to a validated published destination or `/` for an unpublished target. Never accept an unchecked return URL.

## POST /api/arcwell/revalidate

Read the exact raw UTF-8 request body before parsing; enforce a 256 KB limit. Call `receiveWebhook(rawBody, request.headers, config, { runOnce, invalidate })`. Invalid signatures/contracts must return an error; transient cache/store failures should return 5xx so WordPress retries. Return 2xx only after invalidation has completed or an already successful duplicate is recognized.

`runOnce(key, work)` must implement durable atomic cross-process idempotency with leases/recovery. The key is source plus stable event ID. Mark success only after `work` completes; a process crash or invalidation failure must remain retryable. A plain in-memory Set is insufficient in production. `X-Arcwell-Delivery` identifies one attempt and is not the idempotency key. The signature is `sha256=HMAC_SHA256(timestamp + '.' + exactBody)` and permits at most five minutes of age and 30 seconds of clock skew.

`invalidate(event)` maps semantic relationships to the application's actual cache tags/paths. Always invalidate both `before.frontendUrl` and `after.frontendUrl`, plus every supplied typed affected ID and collection. Publication withdrawal/deletion must remove old public output. Private snapshots contain only `{ public: false }`; events contain no content bodies. Supported collection scopes:

| Scope | Frontend coverage |
| --- | --- |
| journal | Article lists, searches and category/topic/tag/author listings |
| homepage | Homepage content and cards |
| series | Series indexes, detail pages and membership displays |
| page-routes | Page rendering and routing |
| navigation | Menus and shared navigation |
| site | Global settings and shared site presentation |
| media-dependent-content | All renderings that may embed or reference changed media |
| taxonomy-dependent-content | Renderings that use taxonomy names, images or relationships |
| author-dependent-content | Renderings that use author identity/presentation |

Never ignore an unfamiliar scope: reject the unsupported contract and surface the delivery failure. For dependency sets exceeding 1,000 IDs, WordPress omits that set and supplies all known collection scopes for broad invalidation; this is not an empty/no-op dependency. Cache invalidation should be repeatable. `arcwell.test` validates transport and signatures without invalidating content.

A deployment acceptance test must cover publish, slug/term changes, withdrawal, deletion, featured-image removal, another editor's draft, historical revisions, preview exit, stale/tampered tokens, repeated delivery and worker recovery. Those require the actual Next.js renderer and cache backend.
