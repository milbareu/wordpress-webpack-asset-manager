# WPAssets - WordPress Asset Manager

## Introduction

WPAssets is a PHP class designed to manage assets in WordPress. It integrates with Webpack's manifest.json files,
automatically enqueuing CSS, JS, and handling PHP dependencies (
with [Dependency Extraction Webpack Plugin](https://www.npmjs.com/package/@wordpress/dependency-extraction-webpack-plugin))
in a structured and scalable way.
This streamlines asset management in WordPress themes and plugins.

## Features

- Automatic Asset Enqueuing: Easily enqueue JavaScript, CSS, and PHP files defined in Webpack's manifest.json.
- Bundle-Based Asset Management: Manage your assets by bundling them into logical entry points.
- Namespace Support: Add a custom namespace prefix to your assets for better organization.
- PHP Dependency Support: Automatically includes PHP files as part of the asset dependencies.
- Child Theme / Parent Theme Awareness: Loads assets from the active stylesheet by default, with an option to force parent-theme manifests.
- Sage 9 Detection: Automatically detects Sage 9-style theme structures and adjusts asset base paths accordingly.
- WordPress Filter Hooks: Override the output directory and Sage detection logic without modifying the library.

## Install via Composer

You can install WPAssets via Composer. Run the following command in your WordPress, theme or plugin directory:

```bash
composer require milbareu/wordpress-webpack-asset-manager
```

Then import the class where you want to use it:

```php
use MB\WPAssets\WPAssets;
```

## 1. Enqueueing Assets in WordPress

To enqueue a bundle (which includes CSS, JS, and optionally PHP files), use the WPAssets::enqueueBundle() function.
This function pulls the required assets from the Webpack-generated manifest.json file.

```php
// Example: Enqueue the 'main' bundle with the namespace 'mytheme'
WPAssets::enqueueBundle('main', 'mytheme');

// Example: Enqueue the 'editor' bundle with the default namespace
WPAssets::enqueueBundle('editor');
```

## 2. Example Setup in functions.php

Here’s an example of how to use WPAssets in a WordPress theme to manage assets:

```php
<?php

use MB\WPAssets\WPAssets;

// Register the theme assets
add_action('wp_enqueue_scripts', function () {
    WPAssets::enqueueBundle('main', 'mytheme');
}, 100);

// Register assets for the block editor
add_action('enqueue_block_editor_assets', function () {
    WPAssets::enqueueBundle('editor');
}, 100);
```

### 2.1 Example Webpack Configuration

The example/ folder contains sample Webpack configuration files that you can adapt for your project. These
configurations support both production and development environments, allowing you to build optimized assets for each
scenario.

### 2.2 Environment-Based Build

Make sure your Webpack configuration supports environment-based builds. For example, you can define separate
configurations for development and production environments. These will generate different asset versions optimized for
each use case.

In your Webpack configuration files (see the example/ folder):

- **Development**: Generates source maps and unminified assets.
- **Production**: Minifies assets and removes unnecessary comments.

## 3. Webpack Manifest Structure

Your Webpack configuration should output a manifest.json with an entrypoints object. Below is a sample structure for
manifest.json:

```json
{
  "entrypoints": {
    "main": {
      "assets": {
        "css": [
          "/styles/main.css"
        ],
        "js": [
          "/scripts/main.js"
        ],
        "php": [
          "/scripts/main.asset.php"
        ]
      }
    },
    "editor": {
      "assets": {
        "css": [
          "/styles/editor.css"
        ],
        "js": [
          "/scripts/editor.js"
        ],
        "php": [
          "/scripts/editor.asset.php"
        ]
      }
    }
  }
}
```

## 4. Function Reference

`getVersion(): string`

Returns the current library version.

Example:

```php
$version = WPAssets::getVersion();
```

`enqueueBundle(string $entry, string $namespace = 'wpa')`
Enqueues the CSS, JS, and PHP files for a specified entry point.

- $entry: The name of the entry point (e.g., 'main', 'editor').
- $namespace (optional): A prefix for the asset handles (default is 'wpa').

Example:

```php
WPAssets::enqueueBundle('main', 'mytheme');
```

`getAsset(string $assetName, bool $getContents = false): ?string`

Retrieves the URL or content of a single asset based on its name.

- $assetName: The name of the asset (e.g., 'main.css', 'main.js').
- $getContents: Whether to return the content of the file (true) or the URL (false).

Example:

```php
$mainCssUrl = WPAssets::getAsset('main.css'); // Get URL of main.css
```

`getAssetDependencies(string $entry): array`

Retrieves the asset dependencies from the corresponding .asset.php file for a given entry.

- $entry: The entry name (e.g., 'main.js').

Example:

```php
$deps = WPAssets::getAssetDependencies('main.js');
```

`isSage9(): bool`

Detects whether the currently active theme appears to be a Sage 9 theme.

Detection uses the first matching heuristic:

- the `App\Sage` class exists,
- `config/theme.php` exists in the active theme,
- `resources/views` exists in the active theme.

The detected value is cached in the global `WPASSETS_IS_SAGE9` constant on first use and can be overridden with the `wpassets_is_sage9` filter.

Example:

```php
if (WPAssets::isSage9()) {
    // Load additional Sage 9-specific integrations.
}
```

## 5. Advanced Usage

To include specific PHP files from your Webpack configuration, ensure they are listed in the php key under the
corresponding entry point in manifest.json. These files will be automatically included using include_once when the
bundle is enqueued.

Example:

```json
{
  "php": [
    "/scripts/main.asset.php"
  ]
}
```

### 5.1 Sage 9 theme support

When `WPAssets::isSage9()` returns `true`, the library adjusts the base URL and base directory resolution to match Sage 9-style theme structures before appending the configured output directory.

If the automatic detection is not correct for your project, you can override it:

```php
add_filter('wpassets_is_sage9', '__return_true');
// or
add_filter('wpassets_is_sage9', '__return_false');
```

This is especially useful for custom Sage-based themes or migration scenarios where the directory structure does not fully match the default heuristics.

### 5.2 Force loading assets from the parent theme

By default, WPAssets reads manifests and assets from the active stylesheet directory (child theme if one is active). To always use the parent theme instead:

```php
add_filter('wpassets_use_parent_theme_manifest', '__return_true');
```

## 6. Overriding the Output Directory

The default output directory for assets is `public`. You can customize this by adding a `wpassets_output_dir` filter in your theme or plugin. For example:

```php
add_filter('wpassets_output_dir', function($dir) {
    // Override the default output directory 'public'
    return 'my-custom-assets';
});
```

WPAssets will then use your specified directory when generating URLs and file paths via `getBaseUrl()` and `getBaseDir()`.

## 7. Available WordPress filters

WPAssets exposes a few filters so you can adapt it to your theme structure:

- `wpassets_output_dir`: Changes the asset output directory. Default: `public`.
- `wpassets_is_sage9`: Overrides automatic Sage 9 detection.
- `wpassets_use_parent_theme_manifest`: Forces lookup to use the parent theme instead of the active stylesheet directory.

## Contribution

Feel free to contribute by submitting issues or pull requests. Your contributions help improve this project and make it
more useful for the community.

## License

This project is licensed under the MIT License.

## Disclaimer

This Webpack configuration has been tailored to meet my specific needs and preferences. While it does what I need for my
projects, please note that I am not an expert in Webpack, and this setup may not be suitable for all projects or
environments.

Feel free to use it, modify it, or adapt it to your own needs, but please do so at your own risk. I cannot guarantee
that it will work perfectly for every situation, so always ensure you test it thoroughly in your own development and
production environments.
