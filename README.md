# GutenBlock Pro

**Professional WordPress block patterns and Full Site Editor (FSE) building blocks for the [GutenBlock](https://gutenblock.com) SaaS – and any standalone WordPress site.**

GutenBlock Pro is the companion WordPress plugin to the [GutenBlock SaaS website builder](https://gutenblock.com). It ships a curated library of conversion‑focused Gutenberg block patterns (hero, services, pricing, testimonials, FAQ, CTA, footer, …), a bridge for importing AI‑generated websites from the GutenBlock dashboard, and conditional CSS/JS loading so your site stays fast.

Use it as:

- a **drop‑in pattern library** for hand‑built WordPress sites, or
- the **target plugin for the GutenBlock SaaS export** (one‑click import of a complete, AI‑generated site).

---

## Features

- 50+ FSE‑native patterns covering hero, features, services, pricing, testimonials, team, FAQ, contact, CTA, blog and footer sections.
- Custom blocks (accordion, animated tokens, …) with conditional asset loading – no bloat on pages that don't use them.
- **GutenBlock SaaS bridge**: paste a provisioning token, get a complete, content‑filled WordPress site (pages, menus, options) provisioned automatically.
- Pattern‑slug based page assembly via authenticated REST endpoints (`X-API-Key`).
- Optional sync of typography/colors with the GutenBlock SaaS customizer.
- Block‑gap and full‑site‑editing compatible (`should_load_separate_core_block_assets`).
- Self‑hosted updates via [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) – releases are pulled directly from GitHub.
- Translation‑ready (`gutenblock-pro` text domain, `languages/` directory).

## Requirements

- WordPress **6.0+** (block themes / FSE recommended)
- PHP **7.4+**
- A block‑based theme (e.g. Twenty Twenty‑Four, GutenTheme)

## Installation

### Option A – Latest release (recommended)

1. Download the latest `gutenblock-pro.zip` from the [Releases page](https://github.com/hjherbst/gutenblock-pro/releases).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, activate.
3. (Optional) Connect to the [GutenBlock SaaS](https://gutenblock.com) under **Settings → GutenBlock Pro** by pasting your provisioning token.

### Option B – From source (development)

```bash
git clone https://github.com/hjherbst/gutenblock-pro.git
cd gutenblock-pro
npm install
npm run build
```

Then symlink or copy the folder into `wp-content/plugins/`.

## Usage

### Insert patterns

Open the Gutenberg editor → **block inserter → Patterns** → category **GutenBlock**. Drop any pattern into a page or template, then style with the FSE.

### Import a complete site from GutenBlock SaaS

1. Build a website on [gutenblock.com](https://gutenblock.com) (free preview).
2. In the SaaS dashboard, open your site → **Deliver** → copy the provisioning token.
3. In WordPress: **Settings → GutenBlock Pro → Connect**, paste the token, click **Import**.

The plugin pulls the manifest from the SaaS, creates pages, sets the front page, syncs menus and (optionally) the customizer.

## Development

```bash
npm install
npm run start    # watch builds for block assets
npm run build    # production build
```

Pattern PHP files live under `patterns/`, custom blocks under `blocks/`, plugin code under `inc/`.

### Releases

Versioning is **SemVer** via Git tags `vX.Y.Z`. To cut a release:

1. Bump the version in `gutenblock-pro.php` (plugin header **and** `GUTENBLOCK_PRO_VERSION`).
2. Update `CHANGELOG.md`.
3. Commit, tag, push:
   ```bash
   git commit -am "Release 1.x.y"
   git tag v1.x.y
   git push --follow-tags
   ```
4. The GitHub Action in `.github/workflows/release.yml` builds and attaches `gutenblock-pro.zip` to the release.

## Related projects

- **GutenBlock SaaS** – the AI website builder that emits sites for this plugin: [gutenblock.com](https://gutenblock.com)
- **plugin-update-checker** – self‑hosted updates: [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker)

## Contributing

Bug reports, pattern contributions and pull requests are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) and open an issue first for larger changes.

## License

GutenBlock Pro is licensed under the **GNU General Public License v2.0 or later** – see [LICENSE](LICENSE).

> WordPress and Gutenberg are trademarks of the WordPress Foundation. GutenBlock Pro is an independent project and is not affiliated with or endorsed by the WordPress Foundation.
