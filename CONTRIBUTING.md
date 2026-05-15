# Contributing to GutenBlock Pro

Thanks for taking the time to contribute! This document covers how to file bugs, propose patterns, and submit pull requests.

## Filing issues

- Search [existing issues](https://github.com/hjherbst/gutenblock-pro/issues) first.
- Use the **Bug report** or **Feature request** issue template.
- For bugs, include: WordPress version, PHP version, active theme, plugin version, and steps to reproduce.

## Development setup

```bash
git clone https://github.com/hjherbst/gutenblock-pro.git
cd gutenblock-pro
npm install
npm run start
```

Symlink (or copy) the directory into your `wp-content/plugins/` and activate it.

## Coding standards

- PHP: follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). Prefix functions with `gutenblock_pro_` and use the `gutenblock-pro` text domain for i18n.
- JS/TS: ESLint defaults from `@wordpress/scripts`.
- Patterns: each pattern lives in `patterns/<slug>.php` with a proper header block (`Title`, `Slug`, `Categories: gutenblock`, `Block Types`).
- Keep external dependencies minimal.

## Commit messages

Conventional commits are appreciated:

```
feat(patterns): add pricing-grid-v3
fix(bridge): handle missing manifest gracefully
chore: bump version to 1.x.y
```

## Pull requests

1. Fork and create a topic branch (`feat/...`, `fix/...`).
2. Run `npm run build` and commit the build output if assets change.
3. Reference the related issue in the PR description.
4. Be ready to discuss alternatives – patterns get a *lot* of eyes.

## Licensing of contributions

By submitting a contribution, you agree that it will be licensed under the project's **GPL-2.0-or-later** license (see `LICENSE`).

## Security

Please do **not** open public issues for security problems. Email the maintainer (see `gutenblock-pro.php` plugin header) and we'll coordinate a fix and disclosure.
