# Contributing

Thanks for considering contributing to WPAssets.

## Issues

- Search existing issues before opening a new one.
- Include the PHP version, WordPress version, and relevant theme context (Sage 9, Sage 10, classic theme).
- Paste relevant error messages and manifest.json snippets.

## Pull Requests

1. Fork the repository.
2. Create a feature branch from `main`.
3. Keep changes focused — one PR per feature or fix.
4. Update the readme if adding or changing public API.
5. Add an entry to `changelog.md` following the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.
6. Bump the `VERSION` constant in `src/WPAssets.php` and the version in `composer.json` if releasing.

## Code style

- Follow PSR-12.
- Use type declarations where possible.
- Keep methods `protected` unless they need to be public API.
- Document all public and protected methods with PHPDoc.

## Testing

The package is currently tested manually against real WordPress themes. Include test notes in your PR description if possible when submitting changes.
