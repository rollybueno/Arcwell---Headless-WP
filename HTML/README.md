# Arcwell design prototype

A complete static editorial website, designed independently for client review. Start at [index.html](index.html).

## View locally

Open `HTML/index.html` directly in a browser, or serve the folder from the repository root:

```bash
python3 -m http.server 8088 --directory HTML
```

Visit http://localhost:8088. Google Fonts requires an internet connection; all photographs, styles, scripts, and fixture content are local. Serif and sans-serif fallback fonts are provided.

## Design direction

A contemporary independent journal about design, culture, nature, and ideas. Warm ivory, dark ink, vermilion accents, fine rules, asymmetric editorial layouts, expressive type, and restrained hover motion create an inviting reading experience. The oversized sans-serif opening contrasts with Newsreader's literary headlines and reading typography.

“2027” is interpreted as a forward-looking creative direction, not an established trend standard. Current reference: [Figma's design trends](https://www.figma.com/resource-library/web-design-trends/) informed the expressive typography and editorial composition. The implementation prioritizes readable content and usable navigation.

- Google Fonts: [Manrope](https://fonts.google.com/specimen/Manrope) and [Newsreader](https://fonts.google.com/specimen/Newsreader).
- Photography: StockSnap.io, interpreted from the requested `stocknap.io`. All six files were downloaded from direct `.jpg` URLs. See [ASSETS.md](ASSETS.md) for provenance.
- CSS design tokens are defined at the beginning of `assets/css/styles.css`.
- Mobile navigation, visible focus, skip link, semantic landmarks, reduced-motion support, and responsive layouts are included.

## Review pages

| Page | Demonstrates |
| --- | --- |
| [Homepage](index.html) | Cover story, editor's picks, latest stories, featured topic and series |
| [Journal](archive.html) | Six stories with working category filters |
| [Topics](topics.html) | Four editorial subject areas linking to filtered archives |
| [Article](article.html) | Long-form reading, contents links, byline, related stories, copy link |
| [Author](author.html) | Contributor profile and filtered stories; bylines select other contributors |
| [Series](series.html) | Ordered four-story collection |
| [Search](search.html) | Search fixture titles, descriptions, topics, and contributors |
| [Empty search](search.html?q=no-matching-story) | No-results state and recovery |
| [About](page.html) | Standard editorial page and publication identity |
| [Block library](blocks.html) | Paragraph, heading, image, gallery, list, quote, code, table, button, separator, columns, group, cover, and simulated embed |
| [404](404.html) | Missing-page design and recovery links |
| [Credits](credits.html) | Photographer attribution and source links |

## Prototype boundaries

Editorial names and stories are fictional review content. Six article variants use the `story` query parameter. Search and filtering operate on local fixtures. No WordPress, publishing, preview authentication, newsletter service, or production cache is connected. The embed is an explicitly labeled state demonstration, not an external player. A static `404.html` does not configure hosting HTTP status behavior. Pages are marked `noindex,nofollow` for review environments.

Client design approval remains pending. The HTML prototype is a visual reference for later Next.js implementation, not evidence that production requirements have passed.

## Maintenance

The pages contain complete HTML and share CSS and lightweight JavaScript. `build.py` keeps the common shell and templates consistent:

```bash
python3 HTML/build.py
```

Edit page templates in `build.py`, styling in `assets/css/styles.css`, and interactions in `assets/js/main.js`. Generated `assets/js/stories.js` holds shared fixture data. Regenerating replaces HTML pages; avoid editing generated pages directly.
