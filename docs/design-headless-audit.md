# Arcwell design / headless WordPress feasibility audit

Date: September 14, 2026. Baseline: `d2acc99` (committed before this audit).

## Verdict

Every visual component in the prototype is feasible with the specified WordPress + WPGraphQL + Next.js architecture. No visual redesign is required merely because the site is headless. However, none of the current pages is connected to WordPress. Working fixture interactions are not working CMS integrations.

The principal distinction is who supplies content and behavior: WordPress stores editorial data; WPGraphQL exposes that data; Next.js renders the design and handles public interactions. A native WordPress field does not automatically produce a Next.js component or carry its theme styling with it.

This audit covers all 11 HTML pages, shared CSS, JavaScript interactions, fixture data, the generator, and the project spec. It is a source and prototype-browser review, not a deployed CMS integration test. Technical references were checked against official documentation; implementation must still pin and verify the actual installed versions.

## Complete element inventory

Classification: **Native data** means native WordPress content with a frontend implementation; **Custom integration** means additional fields, schema, selection rules, or server behavior; **Frontend** means presentation or browser interaction; **Review only** means an intentional prototype artifact.

| Page / element | Classification | Production implementation and limits |
| --- | --- | --- |
| Shared logo, favicon, colors, fonts, type scale, rules | Frontend | Preserve SVG, Google Fonts, and CSS in Next.js. Branding can remain version-controlled; editors do not need to edit layout CSS. |
| Header and footer navigation | Native data + frontend | Use registered WordPress menu locations, expose their menu items, resolve internal URLs to the public domain. Current links are hardcoded. |
| Mobile menu, search icon, hover effects, responsive layouts | Frontend | Small client components and CSS. No CMS plugin needed for these behaviors. |
| Masthead tagline, establishment year, issue label | Custom integration if editable | Store in site configuration. `Issue 01` is currently decorative text, not an issue-management feature. |
| Homepage headline and introduction | Custom integration | Homepage configuration fields or constrained content blocks, with frontend-owned layout. |
| Cover story | Native post + custom selection | Editor selects a post ID. Title, excerpt, author, image, and terms come from that post. Unpublished/missing selections require fallback or hiding. |
| Cover image overlay sentence | Custom integration | Separate editorial caption/teaser field; do not confuse promotional overlay copy with image alt text or photo credit. |
| Editor's picks and display order | Custom integration | Ordered post-ID selection exposed through the core plugin. Preserve editorial ordering, exclude unpublished content. |
| Fresh/latest stories | Native data | Query published posts ordered by publication date; define whether earlier homepage selections are excluded. Current selection is an array slice. |
| Featured topic panel | Custom integration | Selected topic plus term description, media ID, and optional promotional heading. |
| Featured series promotion | Custom integration + frontend | Select Series entity; expose title, teaser, ordered entries, and optional art label. CSS orbit illustration remains a reusable frontend component. |
| Homepage manifesto / About call to action | Custom integration if editable | Structured homepage fields with a resolved Page link. |
| Journal cards, titles, excerpts, dates, images | Native data | WPGraphQL post connections and featured media rendered server-side. |
| Category filtering | Native data + frontend | Query by taxonomy ID/slug. Do not download the entire publication and filter it in the browser. |
| Archive introduction, count, end-of-list | Native/custom + frontend | Term description or archive configuration; derive count from a defined count query and end-of-list from connection pagination. |
| Topics landing page and category/topic detail | Native taxonomy primitives + custom Topic taxonomy | Register Topic separately as specified. Add term imagery metadata. Current topics page only links to category filters. |
| Article title, standfirst, body, date, byline | Native data | Post title, excerpt, content, date, author, and featured image. Native single authorship matches the current design. |
| Article reading time | Custom derived value | Compute from actual article content under a consistent rule; optional editorial override. Current values are fixed fixtures. |
| Article table of contents | Custom derived frontend behavior | Parse rendered headings, assign stable unique anchors, and omit when there are no relevant headings. |
| Article typography, drop cap, quotes, image layout | Frontend | Style supported Gutenberg output. Editor content must not be allowed to break the reading layout. |
| Author name, biography, initials, author archive | Native data + frontend | Query public author data and published posts, derive initials. Specialty label is additional profile metadata. Avoid exposing private account fields. |
| Contributor directory link | Frontend + data | Current “Our contributors” link opens one author. Either relabel it or build a contributor index. |
| Related stories | Custom selection logic | Related topic/category query with deterministic order, excluding the current post; optionally allow editorial overrides. |
| “Part of this series” article link | Custom relationship | Render only when a real series relationship exists. |
| Copy story link | Frontend | Copy the canonical public URL; provide clipboard failure feedback. No WordPress functionality needed. |
| Ordered Series page, chapter numbers, start link | Custom integration | Series custom post type, ordered post relationships, publication filtering, and aggregate duration. |
| Series decorative “Observe” artwork | Frontend | CSS/SVG supported fully. If editors change the word, constrain length and test wrapping. |
| Search input, submit, result cards, empty state | Native search + frontend | Server-side WPGraphQL search with pagination. Matching author names and taxonomy labels needs extra query logic. |
| About page image, text, values, sections | Native Page + controlled composition | Supported blocks/patterns or a fixed template with structured fields; unrestricted page-building is outside v1. |
| Photo credits page | Native Page or custom media metadata | A manually maintained Page is sufficient initially. An automatically generated asset-credit directory needs media fields and collection logic. |
| 404 presentation and recovery links | Frontend/server | Next.js missing-content handling with verified HTTP behavior. The static demo redirect does not establish a production 404 response. |
| Embed preview button | Review only | Only changes status text. A real embed needs supported provider handling, responsive markup, and any chosen consent behavior. |
| Block library and 404-preview footer links | Review only | Keep in review/documentation, remove from ordinary public navigation. |
| Prototype notice and global noindex | Review only | Preserve on staging; replace with appropriate production robots/SEO rules at launch. |

Native posts and filtering are documented by [WPGraphQL](https://www.wpgraphql.com/docs/posts-and-pages). Its [menus documentation](https://www.wpgraphql.com/docs/menus) explains public menu-location visibility. [Custom taxonomies](https://www.wpgraphql.com/docs/custom-taxonomies) and [custom post types](https://www.wpgraphql.com/docs/custom-post-types) need explicit GraphQL exposure. [Media](https://www.wpgraphql.com/docs/media) and [users](https://www.wpgraphql.com/docs/users) supply relevant native content, subject to access rules.

## Gutenberg compatibility audit

The current block library is handcrafted HTML, not captured WordPress output. It establishes a visual target but does not prove compatibility with real block markup, attributes, or nested variations.

| Block group | Feasible? | Required work |
| --- | --- | --- |
| Paragraph, heading, list, quote, separator | Yes | Style actual core markup; constrain color/type variations; generate valid heading anchors. |
| Image and gallery | Yes | Use actual media dimensions, alt text, captions, aspect ratios, and responsive sizing. Gallery lightbox behavior is not shown or promised. |
| Code | Yes | Escaped code text and horizontal scrolling. Syntax highlighting is optional additional frontend work. |
| Table | Yes | Preserve header semantics and captions, support narrow-screen overflow. |
| Buttons | Yes | Render safe links and approved styles. A button does not create form, payment, or subscription functionality. |
| Columns and group | Yes, controlled | Map actual nested markup and approved layout options; stack predictably on mobile. Arbitrary nesting is not demonstrated. |
| Cover | Yes, controlled | Respect selected media, overlay, focal point, and accessible text contrast. The sample currently hardcodes a background image in CSS. |
| Embed | Conditional | WordPress can store/render supported embeds; the headless frontend must handle provider markup/scripts and any consent UI explicitly. The demo is not a player. |
| Third-party blocks, shortcodes, plugin widgets | Not automatically | Assess individually for server-rendered output, required scripts/styles, authentication, and API support. Universal compatibility remains excluded. |

WordPress documents the [core blocks](https://wordpress.org/documentation/article/blocks-list/) and [Embed block](https://wordpress.org/documentation/article/embed-block/). Their availability in the editor does not establish identical behavior in an independent frontend.

## Findings requiring correction or an explicit implementation decision

### High priority — resolve before translating the design into production

1. **Topics currently mean categories.** In `HTML/build.py`, `topics.html` is generated from Design/Culture/Nature/Ideas and links to `archive.html?category=...`. The spec distinguishes broad categories from Topic entities. Keep those four as categories; model subjects such as Architecture, Objects & rituals, and Future of living as Topics, with dedicated topic URLs and multi-topic assignment. Treat this as a recommended mapping, not an already implemented model.
2. **Search promises more than standard WordPress search.** `main.js` searches title, description, category, section, and author, but not full article bodies. Default WordPress search targets title, excerpt, and content. Implement ordinary article search plus separate taxonomy/author filters for v1, or explicitly scope enhanced matching; do not claim the present fixture search is equivalent. [WordPress search fields](https://developer.wordpress.org/reference/classes/wp_query/)
3. **Series membership is false for two sample stories.** Every article shows “Part of: The art of paying attention,” while `series.html` lists only stories 0, 1, 2, and 4. Cities and Objects with stories are not in that list. Use a real relationship and conditionally show the link.
4. **Related stories can recommend the current article.** The related cards are always stories 1–3 regardless of the selected article. Exclude the current post and define a relevance/fallback rule.
5. **No real CMS-backed template has been validated.** The template generator and JavaScript contain fixed content. Translate them into server-rendered routes; query by stable identities; test real Gutenberg content and GraphQL schema before considering the design integration-ready.
6. **Missing publishing states.** No draft banner, exit-preview control, expired/unauthorized preview screen, or CMS-unavailable state exists. Add focused designs for these spec-required workflows. A 404 is not the fallback for a failed CMS request.
7. **No pagination or realistic content-volume state.** Archives, author pages, and search only demonstrate six fixtures. Add connection-based pagination and empty archives. Show the “caught up” message only after the final page.

### Medium priority — resolve during component implementation

8. **Fresh stories need a documented ordering rule.** Homepage latest uses `stories[3:]`, which omits the newest entries. This is valid only if intentionally showing the newest stories not already featured. Define de-duplication and refill behavior for sparse content.
9. **Author labels do not change with authors.** The specialty remains “Design & everyday life” for Jamie and Sam. Populate it from a profile field or remove it. Unknown author/category parameters currently fall back silently; production routes need a deliberate missing-content policy.
10. **Navigation labels imply missing indexes.** “Our contributors” opens Alex; “Series” opens one collection. Relabel single-destination links or add indexes when multiple contributors/series are intended. The homepage “Editor's picks” link opens the entire archive, not a picks collection; relabel or implement a selection view.
11. **Counts, read times, issue labels, and decorative metadata are static.** Derive article/series values; make issue metadata editorial configuration if retained. A static issue number should not suggest a complete edition-management system.
12. **Media assumptions need production treatment.** The shared image helper emits 960×640 dimensions even for portrait files. Use actual metadata, focal-point-aware crops, responsive delivery, and missing-image fallbacks. These JPGs range up to approximately 1.6 MB; do not carry their full bytes into every card request.
13. **Real content extremes are untested.** Test long titles/names, multiple terms, absent excerpts, missing images, empty curation, and long series titles. Layouts currently depend on short fixed strings and deliberate line breaks.
14. **Accessibility review remains partial.** Previous/browser checks confirm basic geometry and interaction, not WCAG conformance. Metadata is often 9–11 px: enlarge where practical for comfortable reading. The series-art container is `aria-hidden`, including the homepage story count; expose meaningful metadata outside decorative art. Check zoom/reflow, contrast across overlays, focus order, and real blocks.
15. **Production SEO is absent by design.** Every page has the same description and noindex; there is no canonical/schema/sitemap/feed implementation. Bind WordPress SEO configuration to server output, preserve staging noindex, and generate public-domain URLs.
16. **Missing-page demo is only a visual state.** JavaScript redirects to a static file. Implement and test actual HTTP handling, including direct requests. Next.js streaming behavior can affect status semantics; validate before streaming where a true missing-content status is required. [Next.js notFound](https://nextjs.org/docs/app/api-reference/functions/not-found)
17. **Fixture HTML rendering is not a CMS security model.** Current `innerHTML` templates consume trusted local strings. Do not substitute arbitrary CMS data into those templates. Use React escaping for plain fields and an explicit allowed markup/URL policy for rich content and embeds.

## Minimal content model needed to preserve this design

These are proposed implementation choices, not additional installed plugins.

| Entity | Native fields | Additional fields / relationships |
| --- | --- | --- |
| Post | Title, slug, excerpt, content, date, author, featured image, categories, tags | Topic terms; Series relation via ordered series membership; optional reading-time override and related-story overrides |
| Page | Title, URI, content, featured image | Approved template choice or constrained section fields for About |
| Topic | Registered WordPress taxonomy with name, slug, description | Featured media ID, optional promo heading |
| Category | Native category name, slug, description | Optional media ID for category cards |
| Series | Custom post type with title, slug, excerpt/content | Ordered post IDs, short display title/art label, sequence number; derive entry count and duration |
| Author | Public display name, slug, biography | Editorial specialty; optional local portrait if desired |
| Media | Source URL, actual dimensions, alt text, caption | Photographer/source/license metadata if credits are generated automatically |
| Homepage | Configuration attached to designated front Page | Intro, hero post ID, hero overlay, ordered picks, featured Topic ID, featured Series ID, manifesto |
| Site configuration | Site title/description; registered menus | Tagline, issue label, founding year if editorially managed |

Prefer a single source of truth for Series membership: ordered post IDs on Series, with a computed reverse relation on articles. Establish whether one article can appear in multiple series before building the editor UI. Expose custom fields with the Headless Publishing Core plugin; ACF is an optional implementation aid, not required by this design.

## Publishing and cache implications

The design introduces dependencies beyond article body text:

- Homepage configuration changes invalidate the homepage even when no article changes.
- Author/profile and taxonomy changes refresh cards and article bylines/labels that embed those fields.
- Series membership/order changes refresh its page, article membership links, homepage promotion, count, and aggregate duration.
- Post publication/unpublication affects selection fallbacks, latest stories, related stories, archives, search, sitemap, and feed.
- Media and caption edits refresh all dependent article/card/credit views.
- Menu/site configuration edits refresh affected shared layouts.

Authenticated preview remains custom integration work, even though the underlying editor revisions/autosaves are native. Current [WPGraphQL preview documentation](https://www.wpgraphql.com/docs/previews) requires authentication and edit capability and describes version-dependent preview behavior. Verify custom-field preview support against the pinned version.

The spec's cache snippets are conceptual. Current [Next.js revalidateTag documentation](https://nextjs.org/docs/app/api-reference/functions/revalidateTag) distinguishes stale-while-revalidate from immediate expiry. Define the acceptable publishing delay; unpublishing must not accidentally keep serving withdrawn content under the normal stale-content policy. Test the selected strategy with the chosen deployment platform.

## What is not automatically provided

- Exact frontend styling from WordPress themes or the block editor.
- A universal renderer for arbitrary block plugins and PHP widgets.
- Real media playback from the prototype's embed-state button.
- Author/taxonomy-aware full-text search from the standard search argument alone.
- Secure preview, signed webhooks, cache invalidation, public SEO, and CMS outage handling from an HTML design.
- Complete issue management from “Issue 01,” or a contributor/series directory from one detail page.

These are either additional implementation work or excluded scope, not reasons to abandon the headless architecture. Commerce, memberships, saved stories, comments, and newsletter subscriptions are not present in this prototype and remain outside the v1 scope.

## Recommended next work

1. Correct topic/category semantics, series membership, related-story selection, and navigation labels in the prototype.
2. Add pagination, empty collections, preview/exit/error states, and content-stress fixtures.
3. Freeze the CMS field and relationship contract against the approved design.
4. Prove one real Post end-to-end: Gutenberg content → WPGraphQL → matching Next.js article → secure preview → publish/revalidation.
5. Implement the remaining templates against that contract, then test CMS-driven variants and production behavior.

The audit intentionally preserves the committed prototype for review; findings are recorded rather than silently changing the approved baseline.

## Re-audit browser results

Re-ran the complete prototype check after the baseline commit: all 11 pages at 1440, 768, 390, and 320 px. No document overflow, broken images, or JavaScript page errors were reported. Menu open/Escape-close, fixture search and empty results, Culture filtering, Jamie Chen's archive, article switching, and unknown-article recovery passed. These results validate the existing demo behavior; they do not negate the semantic and integration findings above.
