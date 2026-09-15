=== Arcwell Core ===
Contributors: arcwell
Requires at least: 6.4
Requires PHP: 8.2
Tested up to: 7.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial data, scoped previews, and durable publishing events for the Arcwell headless frontend.

== Description ==
Requires WPGraphQL 2.22.3 or later. Adds Series, Topics, Gutenberg presentation controls,
media credits, typed GraphQL extensions, signed previews, a publishing outbox, and diagnostics.
No Composer dependencies are needed in production. See README.md for configuration and handoff.

== Installation ==
1. Install and activate WPGraphQL 2.22.3 or later.
2. Upload this ZIP using Plugins > Add New > Upload Plugin, then activate.
3. Open Settings > Arcwell and complete the connection form; generate and copy the security keys.
4. Select a static homepage and open Settings > Arcwell.
5. Configure a system cron worker and integrate the frontend contract.

== Changelog ==
= 0.1.2 =
Guided connection form with secure generation, copy/reveal controls and protected hosting overrides.
= 0.1.2 =
Improved settings dashboard and embedded configuration documentation.
= 0.1.0 =
Initial WordPress companion plugin implementation.
