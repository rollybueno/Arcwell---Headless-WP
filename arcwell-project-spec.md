# Arcwell

> A production-oriented headless WordPress publishing platform using WPGraphQL and Next.js, built to demonstrate the complete editorial lifecycle from editing and secure preview through publishing, selective cache revalidation, SEO, and frontend delivery.

## Project Identity

**Recommended project name:** Arcwell  
**Repository slug:** `arcwell`  
**Project type:** Headless WordPress / full-stack publishing architecture  
**Primary focus:** Editorial publishing, API architecture, frontend delivery, caching, preview workflows, and deployment

### Short description

Arcwell is a headless editorial publishing platform that keeps WordPress as the content management system while using Next.js as the public presentation layer. It demonstrates how a production-style decoupled WordPress architecture can handle WPGraphQL content delivery, Gutenberg content, secure draft previews, signed publishing webhooks, selective cache revalidation, SEO, media, testing, and independent deployments.

### One-line description

A production-style headless WordPress publishing platform powered by WPGraphQL and Next.js.

### Tagline

**WordPress for editors. Next.js for the frontend. A publishing workflow designed for both.**

### GitHub About

Modern headless publishing platform using WordPress, WPGraphQL, Next.js, secure previews, signed webhooks, selective revalidation, and SEO.

### Suggested GitHub topics

`wordpress` `headless-wordpress` `wpgraphql` `nextjs` `react` `typescript` `graphql` `gutenberg` `headless-cms` `decoupled-wordpress` `webhooks` `seo` `docker`

---

# 1. Project Overview

Arcwell explores WordPress as an application backend rather than as a traditional theme-rendered CMS.

WordPress owns:

- content
- editorial state
- revisions and autosaves
- users and capabilities
- media
- taxonomies
- Gutenberg editing
- SEO configuration

Next.js owns:

- public routing
- page rendering
- presentation
- caching
- CDN delivery
- public SEO output
- sitemap and RSS
- error handling
- frontend deployment

WPGraphQL is the primary read API between the two systems.

The project is intentionally more than a basic "WordPress API + React blog." Its main engineering focus is the complete publishing lifecycle:

```text
Edit
  ↓
Preview
  ↓
Publish
  ↓
Signed webhook
  ↓
Selective cache revalidation
  ↓
Updated frontend
  ↓
Correct SEO and canonical output
```

---

# 2. Why This Project Exists

A basic headless WordPress demo proves that WordPress content can be fetched from JavaScript. It does not prove that the architecture works well for editors or in production.

Arcwell is intended to answer the harder questions:

- How does an editor securely preview an unpublished article?
- How does the frontend know when content has changed?
- Which cached pages should be invalidated after an update?
- How are related archives refreshed without purging the entire site?
- How are WordPress URLs mapped to frontend URLs?
- Where does SEO configuration live?
- How are canonical URLs, sitemaps, RSS, and structured data generated?
- What happens if WordPress is temporarily unavailable?
- How are WordPress and the frontend deployed independently?
- Which responsibilities belong to WordPress and which belong to Next.js?
- What limitations does a headless architecture introduce?

The project therefore focuses on architectural decisions rather than simply adding technologies.

---

# 3. Goals

The project should demonstrate:

1. WordPress as a clean editorial backend.
2. WPGraphQL as a structured content API.
3. Next.js App Router as an independent frontend.
4. Server-rendered and cached content delivery.
5. Secure draft and autosave previews.
6. Signed WordPress-to-Next.js webhooks.
7. Dependency-aware cache invalidation.
8. Gutenberg content support.
9. SEO parity with a traditional WordPress site.
10. Correct canonical frontend URLs.
11. Independent CMS and frontend deployments.
12. Reproducible local development.
13. Automated quality checks and testing.
14. Clear documentation of trade-offs and limitations.

---

# 4. Non-Goals

Version 1 is not intended to become:

- an e-commerce platform
- a membership system
- a visual page builder
- a universal Gutenberg renderer
- a replacement WordPress admin
- a SaaS application
- a multi-tenant CMS
- a frontend authentication system
- a commenting platform
- a universal WordPress plugin compatibility layer

Keeping these concerns outside the initial scope protects the architectural focus.

---

# 5. Target Users

## 5.1 Editors and publishers

Primary editorial users include:

- publishers
- magazines
- newsrooms
- company content teams
- documentation teams
- agencies
- organizations already using WordPress

Their expected workflow remains familiar:

```text
Create article
      ↓
Edit in Gutenberg
      ↓
Preview
      ↓
Review
      ↓
Schedule or publish
      ↓
Public frontend updates
```

Editors should not need to understand GraphQL, Next.js, cache tags, or deployment infrastructure.

## 5.2 Developers

The secondary audience is developers evaluating or maintaining the architecture.

The project should demonstrate:

- PHP and WordPress internals
- custom WordPress plugin development
- content modeling
- GraphQL
- API contracts
- React
- Next.js
- TypeScript
- server-side rendering
- cache architecture
- security
- CI/CD
- integration testing

---

# 6. Core Architecture Principle

> WordPress owns content and editorial state. Next.js owns presentation, routing, delivery, caching, and public SEO output.

The systems remain independently deployable.

```text
┌─────────────────────────────────────────────────────────────┐
│                         EDITORS                             │
│                                                             │
│                  WordPress Admin                            │
│                  cms.example.com                            │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Save / Publish / Update
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                       WORDPRESS                             │
│                                                             │
│  WordPress Core                                             │
│  ├── Posts / Pages                                          │
│  ├── Authors                                                │
│  ├── Media                                                  │
│  ├── Categories / Topics                                    │
│  ├── Series                                                 │
│  ├── Revisions / Autosaves                                  │
│  └── Editorial permissions                                  │
│                                                             │
│  Arcwell Core Plugin                                        │
│  ├── Content model                                          │
│  ├── GraphQL extensions                                     │
│  ├── Preview URL handling                                   │
│  ├── Webhook dispatcher                                     │
│  ├── Revalidation payload builder                           │
│  └── Frontend URL mapping                                   │
│                                                             │
│  WPGraphQL                                                  │
│  └── /graphql                                               │
│                                                             │
│  SEO plugin                                                 │
│  └── SEO metadata / schema                                  │
└─────────────┬───────────────────────────────┬───────────────┘
              │                               │
       GraphQL queries                   Signed webhook
              │                               │
              ▼                               ▼
┌─────────────────────────────────────────────────────────────┐
│                       NEXT.JS                               │
│                  www.example.com                            │
│                                                             │
│  App Router                                                 │
│                                                             │
│  Data Layer                                                 │
│  ├── GraphQL client                                         │
│  ├── Query fragments                                        │
│  ├── Generated TypeScript types                             │
│  └── Cache-tag assignment                                   │
│                                                             │
│  Application                                                │
│  ├── Article routes                                         │
│  ├── Archives                                               │
│  ├── Author pages                                           │
│  ├── Topic pages                                            │
│  ├── Series pages                                           │
│  └── Search                                                 │
│                                                             │
│  Route Handlers                                             │
│  ├── /api/arcwell/preview                                   │
│  ├── /api/arcwell/preview/exit                              │
│  └── /api/arcwell/revalidate                                │
│                                                             │
│  Cache                                                      │
│  ├── post:{id}                                              │
│  ├── author:{id}                                            │
│  ├── taxonomy:{id}                                          │
│  ├── series:{id}                                            │
│  └── collection:*                                           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
                  CDN / Edge
                       │
                       ▼
                    VISITOR
```

---

# 6.1 Headless Architecture Target

Arcwell targets a **fully decoupled headless CMS architecture for editorial publishing**.

This means WordPress is used as the editorial backend and system of record, while the public website is rendered and delivered entirely by Next.js.

```text
WordPress
Editorial CMS only
      │
      │ WPGraphQL / REST
      ▼
Next.js
Public application
      │
      ▼
Visitors
```

There is no traditional WordPress theme responsible for the public website.

## Architectural classification

### Fully decoupled / true headless

Arcwell is **fully decoupled** at the application level:

```text
CMS
WordPress

Presentation
Next.js

Communication
API
```

WordPress and the public frontend:

- run as separate applications
- can use separate domains
- can deploy independently
- communicate through APIs and signed webhooks
- do not depend on PHP theme templates for public rendering

This is different from a progressively decoupled WordPress site, where WordPress still renders the main page shell and JavaScript enhances only selected areas.

Arcwell does **not** target that model.

## Rendering model

Although the CMS architecture is fully headless, the frontend rendering strategy is **hybrid**.

Different content uses different delivery modes:

### Cached / static-oriented

Best for:

- published articles
- pages
- author archives
- topic archives
- category archives
- series pages
- homepage sections

These pages can be generated or cached by Next.js and refreshed through selective revalidation.

### Dynamic / request-time

Best for:

- draft previews
- autosave previews
- search
- user-specific or uncached utilities
- CMS health-dependent tools

So the overall model is:

```text
CMS architecture
Fully headless / fully decoupled

Frontend rendering
Hybrid
├── cached/static for published editorial content
└── dynamic for preview and interactive queries
```

## Primary use case

Arcwell specifically targets **content-heavy editorial and publishing websites**, such as:

- magazines
- news and media sites
- company publications
- technical publications
- editorial blogs
- documentation-style publications
- content marketing platforms
- multi-author publications

It is not initially designed as a generic headless application framework.

## Why this headless model

This model is appropriate when an organization wants to keep WordPress because of its mature editorial experience while allowing frontend engineering to evolve independently.

It provides:

- familiar WordPress authoring
- Gutenberg editing
- revisions and editorial state
- WordPress user permissions
- structured API access
- independent frontend deployments
- modern React/Next.js development
- strong CDN caching
- selective revalidation
- frontend-controlled performance
- frontend-controlled SEO output

## What Arcwell is not

Arcwell is not:

### Progressively decoupled WordPress

```text
WordPress theme
      +
React components
```

WordPress does not render the public application shell.

### Traditional WordPress with a JavaScript theme

The public frontend is an independent Next.js application, not a theme bundled into WordPress.

### Static-site export only

Arcwell is not limited to build-time static generation. It supports dynamic preview, search, selective cache invalidation, and request-time behavior.

### Headless commerce

WooCommerce, checkout, cart state, inventory, and customer accounts are outside the initial architecture.

### Generic multi-CMS frontend

Version 1 deliberately treats WordPress as the system of record instead of abstracting multiple CMS products behind a universal content layer.

## Short architecture description

For portfolio and README use:

> Arcwell is a fully decoupled headless publishing platform that uses WordPress as the editorial CMS and Next.js as the independent public application. Published content uses a cache-first hybrid rendering strategy, while previews and search remain dynamic.


# 7. Recommended Technology Stack

| Area | Technology |
| --- | --- |
| CMS | WordPress |
| CMS language | PHP |
| Content API | WPGraphQL |
| Auxiliary API | WordPress REST API |
| Editor | Gutenberg |
| SEO configuration | Yoast SEO or equivalent |
| Frontend | Next.js App Router |
| Frontend language | TypeScript |
| UI | React |
| GraphQL typing | GraphQL Code Generator |
| Styling | CSS Modules, Tailwind, or project CSS |
| WordPress tests | PHPUnit |
| Frontend unit tests | Vitest |
| Component tests | React Testing Library |
| E2E tests | Playwright |
| Local environment | Docker / Docker Compose |
| CI | GitHub Actions |
| Frontend hosting | Vercel initially |
| CMS hosting | Standard PHP/MySQL WordPress host |

---

# 8. Repository Structure

A monorepo keeps the architecture visible without committing WordPress core.

```text
arcwell/
│
├── HTML/
│   ├── README.md
│   ├── index.html
│   ├── article.html
│   ├── archive.html
│   ├── topics.html
│   ├── author.html
│   ├── series.html
│   ├── search.html
│   ├── page.html
│   ├── blocks.html
│   ├── credits.html
│   ├── 404.html
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── apps/
│   └── web/
│       ├── app/
│       ├── components/
│       ├── graphql/
│       ├── lib/
│       ├── types/
│       └── tests/
│
├── wordpress/
│   └── wp-content/
│       ├── mu-plugins/
│       │   └── headless-loader.php
│       │
│       └── plugins/
│           └── arcwell-core/
│               ├── src/
│               │   ├── Content/
│               │   ├── GraphQL/
│               │   ├── Preview/
│               │   ├── Revalidation/
│               │   ├── REST/
│               │   └── URLs/
│               ├── tests/
│               └── arcwell-core.php
│
├── packages/
│   └── graphql/
│       ├── fragments/
│       ├── queries/
│       └── generated/
│
├── docker/
│
├── docs/
│   ├── architecture.md
│   ├── caching.md
│   ├── preview.md
│   ├── seo.md
│   └── deployment.md
│
├── compose.yaml
├── package.json
└── README.md
```

A WordPress theme is optional. If one is present, its purpose should be limited to editor styles or CMS fallback behavior rather than public rendering.

---

# 9. WordPress Responsibilities

WordPress is the system of record.

It owns:

```text
Content
Editorial state
Revisions
Autosaves
Users
Capabilities
Media
Taxonomies
Permalinks
SEO configuration
```

It does not own:

```text
Public rendering
Public React components
Frontend navigation state
Page delivery
Public cache behavior
CDN behavior
Frontend routing
```

---

# 10. Arcwell Core Plugin

The implementation contract is [Arcwell Core WordPress Plugin Specification](plugin/arcwell-core-plugin-spec.md). It defines the precise editorial fields, GraphQL extensions, preview claims, webhook payload, permissions, and release gates. Architecture examples below are illustrative; use that shared versioned contract when implementing PHP and TypeScript.

A custom WordPress plugin acts as the integration layer.

Suggested plugin name:

**Arcwell Core**

Responsibilities:

```text
Arcwell Core

├── Content
│   ├── Register Series
│   ├── Register Topic taxonomy
│   └── Register metadata
│
├── GraphQL
│   ├── Additional fields
│   ├── Homepage configuration
│   └── URL helpers
│
├── Preview
│   ├── Replace preview URL
│   └── Generate signed preview context
│
├── Revalidation
│   ├── Detect content changes
│   ├── Determine dependencies
│   ├── Build webhook payload
│   └── Send webhook
│
├── URLs
│   ├── CMS URL → frontend URL
│   └── Headless redirects
│
└── REST
    └── Health/status endpoint
```

The plugin keeps integration behavior out of the active WordPress theme.

---

# 11. Content Model

Version 1 should favor native WordPress primitives.

## Native entities

- Posts
- Pages
- Authors
- Categories
- Tags
- Media

## Additional editorial entities

### Topics

A topic taxonomy can represent long-running editorial subjects independent of a broad category.

Example:

```text
Category: Design
Topic: Architecture
```

### Series

A Series entity groups articles into an ordered editorial collection.

Example:

```text
Series: Building Modern WordPress Applications

1. Why Go Headless?
2. Modeling Content
3. Building the GraphQL Layer
4. Preview Architecture
5. Cache Revalidation
```

Avoid using custom fields where native posts, taxonomies, users, or metadata already model the relationship correctly.

---

# 12. Gutenberg Strategy

Gutenberg remains the primary article editor.

Version 1 supports a controlled set of common core blocks, such as:

- Paragraph
- Heading
- Image
- Gallery
- List
- Quote
- Code
- Table
- Buttons
- Embed
- Separator
- Columns
- Group
- Cover

The platform does not promise universal compatibility with every block plugin.

Unsupported blocks should either:

1. fall back to safe serialized HTML when appropriate, or
2. be flagged as unsupported during editorial QA.

A future editor warning can display:

```text
⚠ This block is not supported by the headless frontend.
```

This limitation is intentional.

---

# 13. WPGraphQL Architecture

WPGraphQL is the primary content read API.

```text
Next.js
   │
   │ POST /graphql
   ▼
WPGraphQL
   │
   ├── Posts
   ├── Pages
   ├── Taxonomies
   ├── Users
   ├── Media
   └── Custom schema fields
```

For ordinary page rendering, GraphQL queries should happen server-side.

Preferred:

```text
Browser
    ↓
Next.js Server Component
    ↓
WPGraphQL
```

Avoid making the browser the primary GraphQL client for public content.

Benefits include:

- centralized caching
- credentials remain server-side
- consistent errors
- controlled query construction
- reduced frontend coupling to the CMS endpoint

---

# 14. GraphQL Organization

Reusable fragments should describe common entity shapes.

```graphql
fragment FeaturedImage on MediaItem {
  databaseId
  sourceUrl
  altText
  mediaDetails {
    width
    height
  }
}

fragment AuthorSummary on User {
  databaseId
  name
  slug
}

fragment ArticleCard on Post {
  databaseId
  slug
  uri
  title
  excerpt
  date

  featuredImage {
    node {
      ...FeaturedImage
    }
  }
}
```

Primary queries:

```text
GetHomepage
GetArticleByURI
GetArticleArchive
GetAuthor
GetTopic
GetSeries
GetSearchResults
GetSitemapEntries
```

---

# 15. GraphQL Type Generation

Do not manually duplicate GraphQL response types.

```text
WordPress GraphQL schema
          ↓
GraphQL Code Generator
          ↓
Generated TypeScript types
          ↓
Next.js
```

Example generated location:

```text
packages/graphql/generated/graphql.ts
```

This also creates a useful build contract:

```text
WordPress schema changes
        ↓
GraphQL codegen
        ↓
TypeScript/build validation
        ↓
Breaking change discovered before deployment
```

---

# 16. Frontend Routes

Initial public routes:

```text
/
/articles/
/articles/[slug]/
/topics/
/topics/[slug]/
/series/
/series/[slug]/
/authors/
/authors/[slug]/
/category/[slug]/
/tags/[slug]/
/search/
/about/
```

The architecture should allow route structure to differ from WordPress permalink structure.

A centralized URL resolver handles the mapping.

---

# 17. Homepage Curation

The homepage should not merely display the ten latest posts.

WordPress should provide editorial configuration for:

```text
Hero story
Editor's picks
Latest stories
Featured topic
Featured series
```

Example CMS configuration:

```text
Homepage

Hero Article
[ Select article ]

Editor's Picks
[ Article 1 ]
[ Article 2 ]
[ Article 3 ]

Featured Topic
[ WordPress ]

Featured Series
[ Modern Publishing ]
```

Next.js consumes this configuration and owns visual rendering. Store the structured homepage configuration as revisionable metadata on the designated front Page, not ordinary options. The plugin spec defines copy fields, ordered selections, publication filtering, and empty-selection fallbacks. Site-wide settings and menus remain separate, saved-immediate configuration.

---

# 18. Public Request Lifecycle

A normal article request:

```text
GET /articles/headless-wordpress/
        │
        ▼
Next.js Router
        │
        ▼
Article Server Component
        │
        ▼
getArticle()
        │
        ▼
GraphQL request
        │
        ▼
WPGraphQL
        │
        ▼
WordPress
        │
        ▼
GraphQL response
        │
        ▼
Next.js cached data
        │
        ▼
React Server Component
        │
        ▼
HTML
        │
        ▼
CDN
        │
        ▼
Visitor
```

Subsequent requests may be fulfilled from cached data without contacting WordPress.

```text
Visitor
   ↓
Next.js / CDN
   ↓
Cached content
```

---

# 19. Cache Architecture

Cache invalidation should be modeled around content dependencies, not only URL paths.

An article can depend on:

```text
Article #123
├── Author #8
├── Topic #14
├── Category #5
└── Series #3
```

Conceptual tags:

```text
post:123
author:8
topic:14
category:5
series:3
```

Collection-level tags:

```text
posts
homepage
latest-posts
author:8:posts
topic:14:posts
category:5:posts
series:3:posts
```

This allows related surfaces to be refreshed without purging unrelated pages.

---

# 20. Selective Revalidation

Selective revalidation is one of the project's key engineering features.

A content update should not cause:

```text
PURGE EVERYTHING
```

Instead WordPress sends enough information for the frontend to determine affected dependencies.

The canonical versioned event contract is defined in the plugin specification, section 13. Include a stable event ID, environment-specific source ID, typed entity identity, previous/current public URLs and relationships, and the union of affected public dependencies. Capturing both old and new state is required for slug changes, author/topic reassignment, and deletion. The plugin describes semantic dependencies; Next.js maps them to cache tags and paths.

Possible invalidations:

```text
post:123
posts
author:8
author:8:posts
topic:14
topic:14:posts
category:5
category:5:posts
series:3
series:3:posts
```

`homepage` is invalidated only when the changed entity participates in homepage content.

---

# 21. Tag vs Path Revalidation

Use cache tags as the primary dependency mechanism.

Conceptually:

```text
Content changed
      ↓
Invalidate data tags
```

Path revalidation remains useful when a specific route has additional output dependencies.

```text
revalidateTag("post:123")
revalidateTag("author:8")
revalidateTag("topic:14")

revalidatePath("/articles/headless-wordpress/")
```

General distinction:

```text
Cache tag
    ↓
Shared DATA dependency

Path
    ↓
Specific ROUTE output
```

---

# 22. Revalidation Rules

## Post updated

Invalidate:

```text
post:{id}
posts
author:{id}:posts
topic:{id}:posts
category:{id}:posts
series:{id}:posts
```

Conditionally invalidate:

```text
homepage
```

## Post published

Invalidate all update dependencies plus:

```text
latest-posts
sitemap:posts
rss
```

## Post deleted or unpublished

Invalidate:

```text
post:{id}
posts
archives
related taxonomy collections
sitemap
rss
```

The former public article URL should return a correct not-found response or an explicitly chosen `410 Gone` strategy.

## Author updated

Invalidate:

```text
author:{id}
author:{id}:posts
```

If author data is rendered directly on article pages, article dependencies for that author must also be refreshed.

## Taxonomy updated

Example:

```text
topic:14
topic:14:posts
```

Affected article caches may also need invalidation when the taxonomy label or metadata appears directly in article output.

---

# 23. Signed Webhook Architecture

WordPress sends revalidation events to:

```text
POST https://www.example.com/api/arcwell/revalidate
```

Do not use an unauthenticated endpoint or a secret in a query string.

Recommended headers:

```http
X-Arcwell-Timestamp: 1789344000
X-Arcwell-Signature: sha256=<signature>
```

Signature concept:

```text
HMAC_SHA256(
    timestamp + "." + raw_request_body,
    ARCWELL_WEBHOOK_SECRET
)
```

Next.js validates:

```text
signature valid?
timestamp recent?
event supported?
payload structurally valid?
```

before invalidating caches.

This protects against:

- forged requests
- modified payloads
- simple replay attacks

---

# 24. Webhook Reliability

WordPress content saves should not wait synchronously for frontend revalidation.

Preferred flow:

```text
WordPress saves content
        ↓
Schedule webhook work
        ↓
Return editor response
        ↓
Send frontend revalidation
```

A lightweight first implementation can use WordPress scheduled events or an equivalent queue mechanism.

Possible webhook log:

```text
Event                     Status
content.updated:123       success
content.updated:455       failed
content.updated:891       retrying
```

Example retry policy:

```text
Attempt 1
↓ 30 seconds
Attempt 2
↓ 2 minutes
Attempt 3
↓ 10 minutes
Attempt 4
↓
Final failure
```

For client delivery, bounded retries, durable event storage, failure diagnostics, and a reliable scheduled worker are required in v1. WP-Cron must be driven by host/system scheduling or an equivalent worker so CMS traffic is not required for delivery. See the plugin specification for the queue and retry contract.

---

# 25. Preview Architecture

Preview must support drafts, revisions, and autosaves without exposing unpublished content publicly.

Use Next.js Draft Mode.

Editor flow:

```text
WordPress editor
      │
      │ Preview
      ▼
Next.js /api/arcwell/preview
      │
      ├── Verify signed request
      ├── Determine content ID
      ├── Enable Draft Mode
      └── Resolve trusted frontend URL
              │
              ▼
/articles/article-slug/
              │
              ▼
Draft mode enabled?
              │
              ├── no → normal cached GraphQL
              │
              └── yes
                    ↓
              authenticated WPGraphQL
                    ↓
               preview context
                    ↓
            draft/autosave content
```

Preview traffic bypasses the normal public cache.

---

# 26. Preview Authentication

Unpublished content requires an authenticated WordPress request.

Use a dedicated server-to-server integration account with minimal capabilities.

Example:

```text
next-preview
```

Server-side environment values:

```text
WP_PREVIEW_USERNAME
WP_PREVIEW_APP_PASSWORD
```

These values must never use browser-exposed environment prefixes.

Normal published content remains publicly queryable through WPGraphQL.

Preview-only credentials are used only from the Next.js server.

---

# 27. Preview Request Signing

The preview URL generated by WordPress should contain signed, short-lived context.

Example conceptual token data:

```text
content ID
timestamp
expiry
signature
```

Next.js verifies:

```text
signature and token version
expiry and issue time
content identifier and preview mode
expected source and frontend audience
```

before enabling Draft Mode. Draft Mode alone does not authorize unpublished access; maintain a separate expiring preview session scoped to the signed content and mode, and clear both on exit.

The redirect destination should be derived from trusted CMS data rather than blindly accepting a user-supplied redirect URL.

---

# 28. Exit Preview

Provide:

```text
/api/arcwell/preview/exit
```

The route disables Draft Mode and clears the scoped preview session. It redirects to the canonical public page when published, or to a safe published destination for an unpublished draft.

Preview requests should never populate or reuse normal public content cache entries.

---

# 29. SEO Ownership

SEO responsibilities are deliberately split.

WordPress owns editorial configuration:

```text
SEO title
Meta description
Canonical override
Robots directives
Open Graph configuration
Schema-related editorial data
```

Next.js owns public HTML output:

```text
<title>
<meta name="description">
<link rel="canonical">
Open Graph tags
social metadata
JSON-LD
```

---

# 30. SEO Integration

A WordPress SEO plugin such as Yoast can remain the editor-facing SEO interface.

Possible flow:

```text
Editor
  ↓
SEO plugin
  ↓
WordPress
  ↓
SEO API / GraphQL fields
  ↓
Next.js
  ↓
generateMetadata()
```

It is acceptable for the project to use:

```text
WPGraphQL → content
REST API  → computed SEO document
```

when that is the cleaner integration.

GraphQL does not need to be forced into every API concern.

---

# 31. Structured Data

JSON-LD should be rendered server-side.

Potential entities include:

```text
Article
WebPage
BreadcrumbList
Person
Organization
```

If the SEO plugin already produces a coherent schema graph, reuse it rather than emitting a second competing graph.

---

# 32. Canonical URL Ownership

Public URLs belong to the frontend domain.

A single resolver should provide canonical frontend URLs.

```text
WordPress entity
      ↓
Frontend URL resolver
      ↓
https://www.example.com/...
```

Do not blindly output a WordPress `post.link` pointing to the CMS domain.

The same resolver should be reused by:

- SEO canonical tags
- preview redirects
- webhooks
- sitemap
- RSS
- GraphQL helper fields
- WordPress headless redirects

---

# 33. Sitemap

The public frontend generates:

```text
/sitemap.xml
```

from WordPress content.

For larger datasets, sitemap indexes can be split:

```text
/sitemap.xml
/sitemap/posts/0.xml
/sitemap/pages/0.xml
/sitemap/topics/0.xml
```

All entries use public frontend canonical URLs.

---

# 34. RSS

The public frontend also owns:

```text
/feed.xml
```

WordPress supplies content data while Next.js supplies public canonical URLs.

---

# 35. WordPress Frontend Behavior

The CMS domain remains accessible where required.

Allowed:

```text
cms.example.com/wp-admin/
cms.example.com/wp-login.php
cms.example.com/graphql
cms.example.com/wp-json/
cms.example.com/wp-content/uploads/
```

Normal WordPress content URLs should redirect to the public frontend.

```text
cms.example.com/my-article/
          ↓
www.example.com/articles/my-article/
```

Redirect behavior must not interfere with administration, APIs, media, cron, or authentication.

---

# 36. Media Architecture

Version 1 keeps WordPress as the media origin.

```text
WordPress Media Library
        ↓
cms.example.com/wp-content/uploads/
        ↓
Next.js Image
        ↓
Visitor
```

GraphQL media data should include:

```text
sourceUrl
altText
caption
width
height
mimeType
```

Image alt text comes from WordPress media metadata and must not be inferred from filenames.

---

# 37. Search

Initial search remains simple.

```text
/search?q=wordpress
       ↓
Next.js
       ↓
WPGraphQL
       ↓
WordPress search
```

Search is request-time or conservatively cached.

External search services such as Algolia, Typesense, Meilisearch, or Elasticsearch are outside the initial scope.

---

# 38. Error Handling

Different failure classes should produce different behavior.

## Missing content

```text
WPGraphQL → null
Next.js → notFound()
HTTP 404
```

## CMS unavailable while cache exists

```text
Serve cached content
```

## CMS unavailable and no usable cache exists

Return an appropriate temporary service error rather than incorrectly treating the page as missing.

Conceptually:

```text
CONTENT NOT FOUND
≠
CMS UNAVAILABLE
≠
GRAPHQL ERROR
```

---

# 39. Network Timeouts and Retries

CMS requests should have explicit timeouts.

Example policy:

```text
Normal GraphQL read
→ approximately 5-second timeout
→ retry once where safe

Preview GraphQL
→ no aggressive retry

Webhook delivery
→ asynchronous retry

Authentication failures
→ do not repeatedly retry
```

The exact thresholds should be configurable per environment.

---

# 40. Environment Strategy

Use separate local, staging, and production environments.

```text
LOCAL
CMS:      cms.localhost
Frontend: localhost:3000

STAGING
CMS:      cms.staging.example.com
Frontend: staging.example.com

PRODUCTION
CMS:      cms.example.com
Frontend: www.example.com
```

Each environment receives unique values for:

```text
ARCWELL_FRONTEND_URL
ARCWELL_PREVIEW_SECRET
ARCWELL_WEBHOOK_SECRET
ARCWELL_SOURCE_ID
WP_PREVIEW_USERNAME
WP_PREVIEW_APP_PASSWORD
```

Staging WordPress must never invalidate production frontend caches.

---

# 41. Deployment Architecture

## WordPress

Deploy WordPress to a normal persistent PHP/MySQL environment.

The deployed project-specific code contains:

```text
Custom plugin
MU plugin
Composer dependencies where applicable
Environment configuration
```

WordPress core does not need to be committed directly to the repository.

Persistent media storage is required.

## Next.js

Initial recommendation:

```text
GitHub
   ↓
Vercel
   ↓
Next.js frontend
```

Pull requests can create preview deployments.

```text
Pull Request
      ↓
Preview deployment

main
      ↓
Production deployment
```

Suggested environment variables:

```text
WORDPRESS_URL
WORDPRESS_GRAPHQL_URL

WP_PREVIEW_USERNAME
WP_PREVIEW_APP_PASSWORD

ARCWELL_PREVIEW_SECRET
ARCWELL_WEBHOOK_SECRET
ARCWELL_SOURCE_ID

NEXT_PUBLIC_SITE_URL
```

Only values intentionally exposed to browser JavaScript should use `NEXT_PUBLIC_`.

---

# 42. Self-Hosting Compatibility

The application should remain deployable outside Vercel.

Possible topology:

```text
Docker
   ↓
Node.js
   ↓
Reverse proxy
```

A multi-instance self-hosted deployment adds another architectural requirement:

```text
Instance A cache
Instance B cache
Instance C cache
        ↓
Shared/coordinated invalidation required
```

The project should document that distributed cache coordination becomes the deployer's responsibility when scaling independent frontend instances.

---

# 43. CI Pipeline

GitHub Actions should validate both stacks.

```text
                  Pull Request
                       │
       ┌───────────────┴─────────────────┐
       ▼                                 ▼

WordPress                           Next.js
   │                                   │
composer validate                   npm ci
PHPCS                               ESLint
PHPStan                             TypeScript
PHPUnit                             Vitest
                                    GraphQL codegen
                                    next build
                                    Playwright
```

Merge only after all required checks pass.

---

# 44. Contract Testing

GraphQL schema compatibility should be part of validation.

```text
WordPress WPGraphQL schema
            ↓
GraphQL code generation
            ↓
Compile project queries
            ↓
Build
```

This detects breaking CMS schema changes before the frontend reaches production.

---

# 45. End-to-End Publishing Test

A valuable integration test:

```text
Create fixture article
      ↓
Publish
      ↓
WordPress webhook
      ↓
Next.js revalidation
      ↓
Request public article
      ↓
Expect HTTP 200
      ↓
Expect published title
```

Then test updates:

```text
Change title
     ↓
Webhook
     ↓
Selective revalidation
     ↓
Request article
     ↓
Expect updated title
```

This validates the system rather than testing each component in isolation.

---

# 46. Security Boundaries

Document four trust zones.

```text
PUBLIC
Visitors
    │
    ▼
Next.js

────────────────────────

PUBLIC READ API
WPGraphQL
published content only

────────────────────────

TRUSTED SERVER-TO-SERVER
Next.js → authenticated preview request
WordPress → signed revalidation webhook

────────────────────────

PRIVATE
WordPress admin
editor credentials
preview credentials
webhook secrets
```

Never expose:

- WordPress Application Passwords
- webhook secrets
- preview signing secrets
- editor credentials

to public browser JavaScript.

---

# 47. Local Development

The target developer experience should be simple:

```bash
git clone <repository>
cd arcwell
docker compose up -d
npm install
npm run dev
```

Docker services can include:

```text
wordpress
mysql
```

The Next.js frontend can run locally through Node during active development or be containerized as an optional profile.

---

# 48. MVP Scope

Version 1 includes:

- WordPress CMS
- WPGraphQL
- custom Arcwell Core plugin
- Next.js App Router frontend
- TypeScript
- posts
- pages
- authors
- categories
- tags/topics
- article series
- Gutenberg content
- curated homepage
- article archives
- taxonomy archives
- author archives
- search
- draft/autosave preview
- signed publishing webhook
- selective cache revalidation
- responsive frontend
- accessibility fundamentals
- SEO metadata
- canonical URLs
- structured data
- sitemap
- RSS
- WordPress media delivery
- Docker local environment
- automated tests
- GitHub Actions
- deployment documentation

---

# 49. Explicit Limitations

## No WooCommerce

Commerce introduces unrelated requirements:

```text
cart state
inventory
checkout
payments
accounts
tax
shipping
```

It remains outside the publishing-focused architecture.

## No frontend user accounts

Version 1 does not include:

```text
registration
login
saved articles
subscriptions
member profiles
```

WordPress editor authentication remains separate.

## No arbitrary WordPress plugin compatibility

Plugins that depend on PHP template rendering do not automatically work on the Next.js frontend.

Examples include:

- frontend shortcode plugins
- page builders
- PHP-rendered form plugins
- theme-template extensions

## Controlled Gutenberg compatibility

Only an explicit block set is supported.

Universal third-party block rendering is not promised.

## No exact Gutenberg visual parity

Editor styles can approximate typography and spacing, but the WordPress editing canvas does not need to reproduce the public Next.js design pixel-for-pixel.

## No visual page builder

The architecture deliberately avoids turning block/page-builder markup into a generic React component renderer.

## No external search platform in v1

WordPress search is sufficient to demonstrate the architecture.

## No distributed cache implementation in v1

The architecture documents multi-instance requirements but the portfolio implementation can initially rely on a deployment platform with coordinated cache behavior.

---

# 50. Success Criteria

The project is successful when all of the following work:

1. An editor creates a post in WordPress.
2. The public frontend can render the published post through WPGraphQL.
3. The editor can preview unpublished changes securely.
4. Publishing triggers a signed webhook.
5. The frontend invalidates only relevant data/routes.
6. Updated content appears without a complete site rebuild.
7. Related author/topic/series pages refresh correctly.
8. Canonical URLs point to the frontend, never the CMS.
9. SEO metadata is rendered server-side.
10. Sitemap and RSS use frontend URLs.
11. WordPress and Next.js can deploy independently.
12. A WordPress outage does not automatically remove already cached public content.
13. CI tests both PHP and TypeScript code.
14. The architecture and its limitations are documented.

---

# 51. Portfolio Positioning

## Portfolio title

**Arcwell — Modern Headless Publishing Platform**

## Short portfolio description

Arcwell is a production-oriented headless publishing platform that uses WordPress for editorial workflows and Next.js for the public frontend. The project focuses on WPGraphQL content delivery, secure previews, signed webhooks, selective cache revalidation, SEO, Gutenberg compatibility, and independent deployment.

## Longer portfolio description

Arcwell explores what WordPress looks like when it is treated as an editorial backend rather than the public rendering layer.

WordPress manages content, users, revisions, media, Gutenberg, taxonomies, and SEO configuration. A Next.js frontend independently handles routing, server rendering, caching, SEO output, sitemaps, feeds, and public delivery through WPGraphQL.

The project goes beyond simply fetching WordPress posts into React. It implements the publishing lifecycle that a real editorial system needs: secure draft previews, authenticated preview queries, signed publish webhooks, dependency-aware cache invalidation, frontend URL mapping, canonical SEO output, resilient error handling, and independent deployment.

## Skills demonstrated

```text
WordPress
PHP
Plugin Development
WordPress APIs
WPGraphQL
GraphQL
Next.js
React
TypeScript
Gutenberg
REST API
Webhooks
Caching
SEO
Docker
PHPUnit
Vitest
Playwright
GitHub Actions
CI/CD
System Design
```

---

# 52. README Opening Copy

```text
# Arcwell

Arcwell is a production-oriented headless publishing platform built with WordPress, WPGraphQL, Next.js, React, and TypeScript.

The project focuses on the parts of headless WordPress that simple API demos usually leave out: secure editorial previews, signed publishing webhooks, selective cache revalidation, Gutenberg content, SEO ownership, canonical URL mapping, failure handling, testing, and independent CMS/frontend deployments.

WordPress remains the editorial system of record. Next.js owns the public web experience.
```

---

# 53. Architecture Decision Summary

| Concern | Decision |
| --- | --- |
| CMS | WordPress |
| Editorial editor | Gutenberg |
| Content API | WPGraphQL |
| Auxiliary APIs | WordPress REST |
| Frontend | Next.js App Router |
| Language | TypeScript |
| Rendering | React Server Components / server rendering |
| Published content | Cached |
| Search | Dynamic |
| Preview | Next.js Draft Mode |
| Preview CMS access | Authenticated server-to-server WPGraphQL |
| Cache invalidation | Tags first, paths where required |
| CMS → frontend events | Signed webhook |
| Webhook signing | HMAC + timestamp |
| SEO configuration | WordPress SEO plugin |
| SEO rendering | Next.js |
| Sitemap | Next.js |
| RSS | Next.js |
| Media origin | WordPress |
| Frontend deployment | Vercel initially |
| CMS deployment | Separate WordPress hosting |
| Local development | Docker |
| CI | GitHub Actions |

---

# 54. Recommended Implementation Order

## Phase 0 — Design and HTML Prototyping

Start with the client-facing design before building the WordPress integration or Next.js application.

- confirm brand assets, audience, navigation, content hierarchy, and representative editorial content
- define typography, colors, spacing, layout widths, and reusable component styles
- prototype the curated homepage first, including hero story, editor's picks, latest stories, featured topic, and featured series
- build linked, responsive HTML/CSS prototypes in the root `HTML/` folder
- cover article, archive, author, series, search, and standard page templates; reuse the archive template for category and topic views
- include a block showcase for the supported Gutenberg set, plus empty search results and a not-found state
- demonstrate navigation, mobile menu, and other review-critical interactions with lightweight JavaScript and fixture content
- review keyboard access, focus visibility, heading hierarchy, contrast, and mobile layouts
- collect client feedback, revise the prototype, and record approval of the design and supported templates

The prototype uses static fixture content. Search, previews, and publishing behavior shown during design review are demonstrations; their real CMS-backed behavior is implemented and validated in later phases.

**Deliverables:** linked HTML prototype, shared styles and assets, template inventory, and a record of client feedback and design approval.

**Acceptance gate:** the client approves the visual direction, responsive layouts, navigation, and representative content templates before application implementation begins.

The approved prototype becomes the visual reference for Next.js components and Gutenberg presentation. Keep `HTML/` as a review artifact separate from the production application in `apps/web/`.

### Initial design prototype — September 2026

The independently designed prototype is available at `HTML/index.html`. Its editorial direction uses warm ivory, dark ink, vermilion accents, expressive typography, and photography across design, culture, nature, and ideas. The forward-looking 2027 direction is a creative interpretation, not a fixed design standard.

Google Fonts supplies Manrope and Newsreader. Photography is downloaded locally from direct StockSnap.io `.jpg` URLs; `HTML/ASSETS.md` records sources. The requested `stocknap.io` is interpreted as StockSnap.io.

Eleven linked pages cover the homepage, journal, topics, article, author, series, search, about page, block library, credits, and missing-page state. Interactions use fictional local content. See `HTML/README.md` for review instructions and `HTML/VALIDATION.md` for checks. Client design approval remains pending.

## Phase 1 — Foundation

- create repository and Docker environment
- install WordPress
- install/configure WPGraphQL
- create Next.js application
- establish GraphQL code generation
- implement frontend URL resolver
- render posts/pages

## Phase 2 — Editorial Content

- translate the approved HTML prototype into reusable Next.js components and responsive page templates
- add topics
- add series
- build article/archive routes
- implement homepage curation
- implement Gutenberg rendering strategy
- implement media handling

## Phase 3 — Preview

- customize WordPress preview URLs
- implement signed preview requests
- configure Draft Mode
- implement authenticated preview GraphQL
- add preview-exit flow

## Phase 4 — Publishing Pipeline

- detect publish/update/delete events
- build webhook payload
- sign webhook requests
- implement `/api/arcwell/revalidate`
- add cache tags
- implement dependency-aware invalidation

## Phase 5 — SEO

- connect SEO configuration
- implement metadata
- implement canonical URLs
- implement structured data
- generate sitemap
- generate RSS

## Phase 6 — Reliability and Testing

- error boundaries
- CMS timeout behavior
- webhook logging/retries
- PHP unit tests
- frontend tests
- Playwright tests
- publishing E2E test

## Phase 7 — Deployment

- staging environment
- production WordPress
- production frontend
- environment isolation
- GitHub Actions
- deployment documentation

---

# 55. Future Enhancements

Possible post-MVP extensions:

- multilingual publishing
- WPML or Polylang integration
- external search service
- GraphQL persisted queries
- richer webhook queue
- webhook observability dashboard
- distributed cache adapter
- content federation
- newsletter output
- mobile application consuming the same API
- visual block compatibility report
- editorial preview toolbar
- content release scheduling
- redirect management
- frontend performance budgets

These should remain separate from the initial MVP unless they directly support the portfolio story.

---

# 56. Product Identity Rationale

**Arcwell** is intended to read as a real platform or company product rather than as a technical demo name.

The name deliberately avoids:

- `WP`
- `WordPress`
- `headless`
- `decoupled`
- framework names such as Next.js

Those terms belong in the technical positioning, documentation, and portfolio metadata rather than in the product name itself.

This gives Arcwell room to be presented as a production platform:

```text
Arcwell
Modern publishing infrastructure for editorial teams.
```

while its technical implementation can be described precisely as:

```text
Fully decoupled headless WordPress
+
WPGraphQL
+
Next.js hybrid delivery
```

Suggested product hierarchy:

```text
Arcwell
├── Arcwell Core
│   WordPress integration plugin
│
├── Arcwell Web
│   Next.js public frontend
│
└── Arcwell Platform
    Architecture, publishing workflow, preview,
    revalidation, SEO, and deployment
```

Suggested internal package names:

```text
arcwell-core
arcwell-web
arcwell-graphql
arcwell-preview
```

The product name should remain independent of the underlying CMS and frontend framework so the architecture can evolve without requiring a rebrand.
