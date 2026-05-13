# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.9]

### Added
- Added `WPAssets::isSage9()` to detect Sage 9-style themes using multiple heuristics.
- Added support for overriding Sage 9 detection through the `wpassets_is_sage9` filter.
- Added global caching of Sage 9 detection through the `WPASSETS_IS_SAGE9` constant.
- Added changelog tracking for future project updates.

### Changed
- Updated base URL and base directory resolution to account for Sage 9 theme structures.
- Refreshed `readme.md` to document the Sage 9 behavior, available filters, and the `isSage9()` helper.

## [1.1.0]

### Added
- Initial public API for loading Webpack assets from `manifest.json`.
- Bundle enqueueing support for CSS, JS, and PHP assets.
- Dependency loading via generated `.asset.php` files.
- Output directory customization through the `wpassets_output_dir` filter.
- Child-theme-aware asset and manifest lookup by default.
