# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.2.1]

### Fixed
- `getPhpFileFromEntryJs()` now resolves JS paths through the manifest before deriving `.asset.php` keys, fixing content-hashed asset resolution.
- `resolveAssetPhpFromJs()` no longer returns a manifest key that doesn't exist, preventing an undefined array key access.
- Version bumped to `1.2.1`.

## [1.2.0]

### Added
- Hashed `.asset.php` resolution: automatically derives the hashed path from the entry's JS output in `entrypoints` (supports `[contenthash]` in Webpack output filenames).
- `getPhpFileFromEntryJs()` — new resolution step that finds `.asset.php` files via the manifest entrypoint's JS assets.
- `resolveAssetPhpFromJs()` — helpers that derive `.asset.php` manifest keys from matching JS entries.
- `getAsset()` now resolves `.asset.php` assets by finding the corresponding JS entry, even when hashes are present.

### Changed
- `getAssetDependencies()` now accepts an optional `?array $manifest` parameter to avoid re-reading the manifest file.
- `enqueueJsFiles()` accepts a `$manifest` parameter (internal, `protected` — no breaking change to callers).
- `enqueueBundle()` passes the loaded manifest through to `enqueueJsFiles()` for efficiency.
- **`isSage9()` now excludes Sage 10+ themes** — the `resources/views` heuristic previously false-positived on Sage 10. Added early return when `Roots\Acorn\Sage\SageServiceProvider` is present (Sage 10+ indicator).
- Version bumped to `1.2.0`.

## [1.1.0]

### Added
- Initial public API for loading Webpack assets from `manifest.json`.
- Bundle enqueueing support for CSS, JS, and PHP assets.
- Dependency loading via generated `.asset.php` files.
- Output directory customization through the `wpassets_output_dir` filter.
- Child-theme-aware asset and manifest lookup by default.

## [1.0.9]

### Added
- Added `WPAssets::isSage9()` to detect Sage 9-style themes using multiple heuristics.
- Added support for overriding Sage 9 detection through the `wpassets_is_sage9` filter.
- Added global caching of Sage 9 detection through the `WPASSETS_IS_SAGE9` constant.
- Added changelog tracking for future project updates.

### Changed
- Updated base URL and base directory resolution to account for Sage 9 theme structures.
- Refreshed `readme.md` to document the Sage 9 behavior, available filters, and the `isSage9()` helper.
