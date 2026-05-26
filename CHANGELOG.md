# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The full per-release notes (with build artifacts) live on the
[GitHub Releases page](https://github.com/hjherbst/gutenblock-pro/releases).

## [1.30.0] – 2026-05-26

- Pattern preview: hard inline-CSS image constraint in the iframe HTML so pictures stay within their pattern even before `wp-block-library.css` finishes loading. Fixes "some images render larger than the pattern" / "second open looks better" symptom across the section modal and admin patterns overview. The static preview cache is segmented per plugin version, so the fix takes effect on first open after the update.
- Site Takeover (Mode A): the bundled `gutentheme` is now wiped and re-copied on every import instead of being merged in place, so stale files from a previous version can no longer linger. Theme cache is invalidated so WP picks up the fresh files immediately. Notice copy updated to "sauber neu installiert".
- Font import hardening: existing `settings.typography.fontFamilies` entries without a `slug` are now dropped before upserting. Fixes the `Undefined array key "slug" in class-wp-theme-json.php` warnings that surfaced on imports against a `wp_global_styles` post inherited from a custom theme.

## [1.29.1] – 2026-05-26

- Shape import: Button border-radius now lands at `styles.blocks.core/button.border.radius` (was `elements.button.border.radius`) so the value shows up in the FSE Site Editor under "Stile → Blöcke → Button → Rand → Radius" — the spot users intuitively look for. Element-level entries from 1.29.0 imports are cleaned up on the next import. Image radius stays at `blocks.core/image.border.radius`.

## [1.29.0] – 2026-05-26

- Font import: Provisioning now upserts the two SaaS-picked font families (heading + body) into the user `wp_global_styles` with the full 9 weights × normal + italic `fontFace[]` list (Google Fonts CDN URLs). The FSE Typography panel shows only the SaaS picks, each with all variants selectable. Other theme-declared families stay intact (merge by slug, never wholesale-replace).
- Shape import: The SaaS shape pick (none/subtle/medium/strong) now writes FSE-conformant `border.radius` for `elements.button` and `blocks.core/image` into user `wp_global_styles`. Visible and editable under "Site Editor → Styles → Buttons / Images → Border" instead of relying on theme-baked CSS overrides.

## [1.28.0] – 2026-05-26

- Import fix: strip `metadata.patternName` from imported sections so they land as inline-editable blocks (no forced pattern-edit mode).
- New patterns: `process-v4`, `text-columns-v2`..`text-columns-v5`.
- Pattern refresh: `carousel-v1`, `hero-v5`. `header-v2` removed.

## [1.24.0] – 2026-05-12

- Pattern refresh; `services-v6` removed.

## [1.23.1] – 2026-05-11

- `patterns`: rewrite plugin-asset URLs to local `plugins_url()`, sync FSE updates.

## [1.23.0] – 2026-05-11

- Import dashboard rework.
- Optional SaaS style sync (typography/colors from GutenBlock SaaS customizer).
- Heading weight handling improvements.

## [1.22.4] – 2026-05-08

- Remove unused version endpoints; bump version; pattern processing updates.

## [1.22.3] – 2026-05-08

- `bridge`: fix block gaps in SaaS preview via
  `should_load_separate_core_block_assets` and `HTTP_ACCEPT` handling.

## [1.22.2] – earlier

- Clean up pattern assets and button links.

## [1.22.1] – earlier

- Remove legacy MU-bridge during update.

## [1.22.0] and earlier

- Initial public iterations. See Git tags `v1.11.7` … `v1.22.0` and the
  [Releases page](https://github.com/hjherbst/gutenblock-pro/releases) for details.

[1.24.0]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.24.0
[1.23.1]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.23.1
[1.23.0]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.23.0
[1.22.4]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.22.4
[1.22.3]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.22.3
[1.22.2]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.22.2
[1.22.1]: https://github.com/hjherbst/gutenblock-pro/releases/tag/v1.22.1
