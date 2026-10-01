# Blankless - Development Guide

## Project Overview
WordPress plugin that pre-populates the block editor with a configurable block pattern per post type. Pure PHP (no JS build step). Database-stored patterns take priority over file-registered patterns.

Approved on WordPress.org (September 2026): https://wordpress.org/plugins/blankless/

## Repository Structure
```
├── blankless.php   # Main plugin file (settings UI + default_content filter)
├── uninstall.php             # Removes plugin option on delete
├── readme.txt                # WordPress.org plugin readme
├── README.md                 # GitHub-facing documentation
├── languages/                # Translation files (POT); regenerate with `wp i18n make-pot . languages/blankless.pot --slug=blankless --exclude=.github,.claude,.wordpress-org-src`
├── build.sh                  # Builds build/blankless/ + .zip from .distignore; checks Version == Stable tag
├── deploy.sh                 # Manual SVN deploy fallback (`./deploy.sh <svn-checkout>`), uses build.sh
├── .distignore               # Excluded from the WP.org package; must list /.git (10up deploy action)
├── .wordpress-org/           # WP.org assets (banners, icons, screenshots); synced to SVN assets/
│   └── blueprints/blueprint.json  # Playground blueprint (installs from WordPress.org); WP.org Live Preview reads it from SVN assets/blueprints/
├── .wordpress-org-src/       # HTML sources + render script for the drawn banners and screenshot 1
└── .github/workflows/
    ├── plugin-check.yml      # CI: build, then Plugin Check the package, on push/PR to main
    ├── deploy.yml            # CD: GitHub release published -> version check -> SVN trunk + tag, zip attached to release
    └── wporg-assets.yml      # readme.txt / .wordpress-org changes -> SVN; idle until repo variable WPORG_SVN_READY=true
```

Release tooling mirrors `enable-navigation-icons` (already published on WP.org under the `bmx269` account).

## Development Standards
- Follow WordPress Coding Standards (WPCS) for PHP.
- PHP 8.0+ minimum, WordPress 6.5+ minimum.
- `declare(strict_types=1);` and namespace `Blankless` for all PHP. Prefix every global name (options, setting groups, admin page slug, HTML ids) with `blankless` (`blankless_` for options and setting groups, `blankless-` for the page slug, CSS classes and HTML ids). WordPress.org review rejected an earlier generic prefix, the name "Pattern Primer" (it clashes with adactio's existing Pattern Primer tool) and "Pattern Kickoff" (too generic). "Blankless" was approved. A company prefix (Affinity Bridge, Small Robot) is not wanted.
- All user-facing strings must use `__()` / `esc_html__()` / `esc_attr__()` with text domain `blankless`.
- Sanitize all input (`sanitize_*`), escape all output (`esc_*`); pass HTML through `wp_kses_post()` where appropriate.
- Capability checks (`current_user_can( 'manage_options' )`) on all admin actions.
- Prefer root-cause fixes over surface workarounds.

## Key Architecture
- Single option `blankless` stores `[ post_type => [ 'slug' => string, 'pattern_id' => int ] ]`. `sanitize_settings()` links the slug to a published `wp_block` ID at save time; 0 means code patterns only.
- `default_content` filter resolves the saved slug to block markup at new-post time, only when the incoming content is empty.
- Resolution order: (1) the linked `wp_block` by ID, read live so edits and renames apply, (2) full registered pattern name, (3) registered pattern slug suffix. Never look up `wp_block` by slug at post-creation time: any Author can publish one, which would let them replace an administrator's default.
- Settings page lists every public post type (minus `attachment` and `wp_block`) with a live status badge per row.

## Versioning & Releases
- Version must be synced in: `blankless.php` (plugin header), `readme.txt` (`Stable tag`), and `LICENSE` (year).
- `Tested up to` in `readme.txt` should match the latest WP release verified against.
- Update `Changelog` sections in both `README.md` and `readme.txt` for every release.
- `build.sh` is the only place the package is assembled. Never hand-list files elsewhere; change `.distignore` instead.

### One-time setup (after WordPress.org approval)
1. WordPress.org → profile → Account & Security: set an SVN password for `bmx269`.
2. GitHub repo → Settings → Secrets and variables → Actions: add secrets `SVN_USERNAME` (`bmx269`) and `SVN_PASSWORD`, and variable `WPORG_SVN_READY` = `true`.
3. First deploy: publish GitHub release `1.0.0` (or `v1.0.0`). The approval email's SVN repo is empty until then.

### Each release
1. Bump the version in the plugin header and `readme.txt` Stable tag; update changelogs, Upgrade Notice and `Tested up to`.
2. Regenerate the POT, run `./build.sh`, and test the zip.
3. Merge to `main`, then publish a GitHub release whose tag matches the version. The deploy workflow refuses a mismatched tag.

Readme-only or asset-only changes (e.g. bumping `Tested up to`) go live on push to `main` via `wporg-assets.yml`, provided the Stable tag is already released.

## Attribution
Plugin authored and maintained by Trent Stromkins (WordPress.org and GitHub: `bmx269`). Do not reference Affinity Bridge anywhere in the plugin: WordPress.org review rejected it as a trademark the submitting account does not own. Licensed GPL-2.0-or-later.
