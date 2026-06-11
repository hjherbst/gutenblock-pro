# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The full per-release notes (with build artifacts) live on the
[GitHub Releases page](https://github.com/hjherbst/gutenblock-pro/releases).

## [1.32.3] – 2026-06-11

- Features admin page: fix CSS not loading on clean installs. v1.32.2 dropped the working `wp_add_inline_style( 'wp-admin', … )` path and relied on hook/screen-id checks that failed silently; restore inline CSS on `wp-admin`, detect the page via `$_GET['page']`, and inline the styles from PHP (removed redundant `features-page.css`). Contact-form feature gating (block chooser + settings submenu) moved from the local MU plugin into `class-contact-form.php`.

## [1.32.2] – 2026-06-11

- Contact form: updated `class-contact-form.php`, `contact-form.css`, block editor (`edit.js`, `editor.scss`, `index.js`); new patterns `contact-v1` and `contact-v2`.
- Features page: updated `class-features-page.php`, new `features-page.css`.
- i18n fallback strings updated.
- Patterns: refreshed `about-v4`, `carousel-v1`, `cta-v4`, `hero-v5`, `testimonial-v2`.
- Build artefacts updated.

## [1.32.1] – 2026-06-11

- GutenTheme sync: updated bundled theme from localhost. New JS modules (`motion.js`, `motion-sidebar.js`, `scroll.js`), new CSS (`motion.css`), new font (`google-sans`), updated `functions.php`, `theme.json`, `parts/header.html`, `style.css`; removed `cms-seo-panel.js`.

## [1.30.8] – 2026-06-08

- Auto-update fix: the ~6.8 MB of bundled variable fonts are no longer shipped inside the release ZIP. Since fonts were bundled (1.27.4) the package had grown to ~5.25 MB, and the synchronous download in WordPress' update AJAX call regularly exceeded the server's FastCGI/gateway timeout — surfacing as "Aktualisierung fehlgeschlagen: Der Download ist fehlgeschlagen. Gateway Timeout" plus a follow-on `updatePluginError` JS error. The update ZIP now drops back to <2 MB. The fonts stay in the repo and are fetched on demand during import (`ensure_theme_fonts()`), version-pinned, straight from `raw.githubusercontent.com` into the freshly copied theme — fast and still self-hosted afterwards. Idempotent and non-fatal: missing downloads are logged but never abort the import.

## [1.30.4] – 2026-05-28

- Buttons: fix vertical height and alignment of the custom `is-style-button-arrow-circle` button variant. Changed `display` from `inline-block` to `inline-flex` with vertical centering (`align-items: center`), and removed hardcoded top/bottom padding so it inherits the theme's or global style's default vertical padding. Added `min-height: 46px` to safely contain the embedded 38px circle-arrow inlay.
- Site Editor: disable the restrictive `contentOnly` editing mode for unsynced patterns on WordPress 7.0+. Unsynced patterns now insert as raw, fully editable block structures directly on the canvas, behaving exactly as they did in WordPress 6.x.

## [1.30.3] – 2026-05-27

- Pattern loader: harden `register_single_pattern()` against entries mutated through the `gutenblock_pro_patterns` filter. If a downstream filter drops the `slug`, `content`, or `folder` key — or replaces the whole entry with a non-array — registration now skips/falls back cleanly instead of raising `Undefined array key "slug" in class-pattern-loader.php on line 400`. Base slug is recovered from the tone-suffixed slug (`hero-v1--dark` → `hero-v1`) where needed.

## [1.30.2] – 2026-05-27

- Site Editor: restore the right-hand "Styles" panel (half-filled-circle pinned-item button) that WP 7.0 lost. Direct comparison of `edit-site.js` 6.9.4 vs 7.0 confirmed the regression: 6.9 mounted `<GlobalStylesSidebar />` unconditionally for block themes; 7.0 moved the component to `editor.js` and gated it behind `postType === "wp_template" || renderingMode === "template-locked"`, hiding the panel on every page-/post-/styles-edit route. We re-introduce both the trigger and the panel by registering a public `<PluginSidebar>` that internally renders the WP-native `GlobalStylesUIWrapper` pulled from `wp.editor.privateApis` (acknowledged opt-in to private APIs; whole resolution path is try/catch-guarded and the file documents the WP-version-coupling risk). Skipped on WP < 7.0 because the native button is still there.

## [1.30.1] – 2026-05-26

- Global styles sanitizer: on every import the user `wp_global_styles` post is now scrubbed for malformed preset rows across **all** WP preset paths (`color.palette/gradients/duotone`, `typography.fontFamilies/fontSizes`, `spacing.spacingSizes`, `shadow.presets`). Rows without a string `slug` are dropped. Closes the `Undefined array key "slug" in class-wp-theme-json.php` warning that 1.30.0 still left behind when the palette was the offender (legacy nested-list entry from an older build, not the fontFamilies list).

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
