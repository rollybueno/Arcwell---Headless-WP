# Prototype validation

Checked September 14, 2026 using headless Google Chrome with Playwright. These checks concern the static prototype only.

- All 11 pages visited at 1440, 768, 390, and 320 pixel viewport widths.
- No document-level horizontal overflow, broken images, or JavaScript page errors detected.
- Exactly one H1 on every page.
- Local HTML asset and link targets exist.
- Google Fonts loaded in the browser test.
- Mobile menu opens and closes using Escape.
- Searching “nature” returns two fixture stories.
- An unmatched query produces the empty state; HTML-like query input remains text.
- Culture filter returns two stories.
- Jamie Chen's contributor page returns two stories.
- Article query parameters select the corresponding story.
- Unknown article identifiers redirect to the 404 design.
- Desktop and mobile homepage screenshots visually reviewed; adjusted the decorative series wordmark to fit narrow layouts.

This is not a full accessibility audit or a production publishing test. WordPress integration, authenticated previews, webhook handling, real search data, SEO, and deployment validation belong to the later implementation phases.
