<?php

namespace MB\WPAssets;

use Exception;

/**
 * AssetManager class to handle Webpack manifest and entry points.
 */
class WPAssets
{
    /**
     * Version of the AssetManager module.
     */
    const VERSION = '1.2.1';

    /**
     * Base directory for public assets.
     *
     * @var string
     */
    protected static string $outputDir = 'public';

    /**
     * Get the output directory, allowing themes to override via filter 'wpassets_output_dir'.
     *
     * @return string
     */
    protected static function getOutputDir(): string
    {
        return apply_filters('wpassets_output_dir', self::$outputDir);
    }

    /**
     * Load the manifest.json file content.
     * Automatically loads from child theme if active, otherwise from parent theme.
     *
     * @return array
     * @throws Exception
     */
    protected static function getManifestContent(): array
    {
        $manifestPath = self::getBaseDir() . '/manifest.json';

        if (!file_exists($manifestPath)) {
            wp_die($manifestPath, 'Manifest file is missing.');
        }

        $manifest = file_get_contents($manifestPath);
        return json_decode($manifest, true);
    }

    /**
     * Get the version of the AssetManager module.
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Determine whether the currently active theme is Sage 9.
     *
     * Detection is based on three heuristics (any one is sufficient),
     * AND the theme must not be detected as Sage 10+:
     *  1. The `App\Sage` class is loaded (registered by Sage 9's ServiceProvider).
     *  2. A `config/theme.php` file exists inside the active theme directory
     *     (Sage 9 ships this file; Sage 10+ does not).
     *  3. A `resources/views` directory exists inside the active theme (Blade
     *     template directory introduced in Sage 9).
     *
     * Sage 10+ is excluded by checking for the presence of
     * `Roots\Acorn\Sage\SageServiceProvider` (introduced in Sage 10 / Acorn).
     *
     * Themes or plugins can override the result via the `wpassets_is_sage9` filter:
     *
     *   add_filter('wpassets_is_sage9', '__return_true');
     *   add_filter('wpassets_is_sage9', '__return_false');
     *
     * A `WPASSETS_IS_SAGE9` constant (bool) is also defined on the first call so
     * the value is available globally without calling this method every time.
     *
     * @return bool
     */
    public static function isSage9(): bool
    {
        if (defined('WPASSETS_IS_SAGE9')) {
            return WPASSETS_IS_SAGE9;
        }

        // Sage 10+ uses Acorn — if its service provider exists this is not Sage 9
        if (class_exists('Roots\\Acorn\\Sage\\SageServiceProvider')) {
            $result = false;
            define('WPASSETS_IS_SAGE9', $result);
            return $result;
        }

        $themeDir = function_exists('get_stylesheet_directory') ? get_stylesheet_directory() : '';

        $detected =
            class_exists('App\\Sage') ||
            ($themeDir && file_exists($themeDir . '/config/theme.php')) ||
            ($themeDir && is_dir($themeDir . '/resources/views'));

        $result = (bool)apply_filters('wpassets_is_sage9', $detected);

        define('WPASSETS_IS_SAGE9', $result);

        return $result;
    }

    /**
     * Normalize the asset name by removing prefixes (scripts/, styles/) and file extensions (.js, .css).
     *
     * @param string $entry The entry name (e.g., 'scripts/main.js', 'styles/main.css')
     * @return string The normalized asset name (e.g., 'main')
     */
    protected static function normalizeAssetName(string $entry, bool $stripExtension = false): string
    {
        // Remove 'scripts/' or 'styles/' prefixes
        if (str_starts_with($entry, 'scripts/')) {
            $entry = str_replace('scripts/', '', $entry);
        } elseif (str_starts_with($entry, 'styles/')) {
            $entry = str_replace('styles/', '', $entry);
        }

        if ($stripExtension) {
            // Remove '.js' or '.css' extension
            $entry = str_replace(['.js', '.css'], '', $entry);
        }

        return $entry;
    }

    /**
     * For a .asset.php asset name (e.g. 'scripts/editor.asset.php'), derive the
     * hashed path from the corresponding JS entry in the manifest.
     *
     * @param array $manifest
     * @param string $assetName
     * @return string|null The manifest key (e.g. 'scripts/editor.abc123.asset.php')
     */
    protected static function resolveAssetPhpFromJs(array $manifest, string $assetName): ?string
    {
        // scripts/editor.asset.php -> scripts/editor.js
        $jsName = preg_replace('/\.asset\.php$/', '.js', $assetName);

        // Try as-is
        $jsValue = $manifest[$jsName] ?? null;

        if (!$jsValue) {
            // Try normalized (e.g., 'main.js')
            $normalizedJs = self::normalizeAssetName($jsName);
            $jsValue = $manifest[$normalizedJs] ?? null;
        }

        if (!$jsValue && isset($manifest['/'. $jsName])) {
            $jsValue = $manifest['/' . $jsName];
        }

        if ($jsValue) {
            $jsValue = ltrim($jsValue, '/');
            // scripts/editor.abc123.js -> scripts/editor.abc123.asset.php
            $phpKey = preg_replace('/\.js$/', '.asset.php', $jsValue);

            if (isset($manifest[$phpKey])) {
                return $phpKey;
            }
        }

        return null;
    }

    /**
     * Get the URL or contents of a single asset.
     *
     * @param string $assetName The name of the asset (e.g., 'main.css', 'main.js', 'scripts/main.js').
     * @param bool $getContents Whether to return the content (true) or URL (false).
     * @return string|null|\WP_Error Returns the URL or contents of the asset, or null if not found.
     * @throws Exception
     */
    public static function getAsset(string $assetName, bool $getContents = false): string|null|\WP_Error
    {
        $manifest = self::getManifestContent();

        // Resolve the actual manifest key
        $resolvedKey = null;

        // 1. Try direct lookup
        if (isset($manifest[$assetName])) {
            $resolvedKey = $assetName;
        }

        // 2. Try normalized name
        if (!$resolvedKey) {
            $normalized = self::normalizeAssetName($assetName);
            if (isset($manifest[$normalized])) {
                $resolvedKey = $normalized;
            }
        }

        // 3. For .asset.php files, derive from matching JS entry
        if (!$resolvedKey && str_ends_with($assetName, '.asset.php')) {
            $derived = self::resolveAssetPhpFromJs($manifest, $assetName);
            if ($derived) {
                $resolvedKey = $derived;
            }
        }

        if (!$resolvedKey) {
            return new \WP_Error('asset_file_missing', "Asset '$assetName' not found in the manifest.");
        }

        $manifestValue = $manifest[$resolvedKey];

        // Construct the asset's path (either URL or filesystem path)
        $assetPath = self::getBaseUrl() . $manifestValue;
        $filePath = self::getBaseDir() . $manifestValue;

        // If requested, return the content of the asset
        if ($getContents) {
            if (file_exists($filePath)) {
                return file_get_contents($filePath);
            }

            // Fallback: try without the leading slash in $filePath
            $filePath = self::getBaseDir() . '/' . ltrim($manifestValue, '/');
            if (file_exists($filePath)) {
                return file_get_contents($filePath);
            }

            return new \WP_Error('asset_file_missing', "Asset file '$filePath' not found.");
        }

        // Otherwise, return the URL of the asset
        return $assetPath;
    }

    /**
     * Enqueue a script and style bundle from the entrypoints defined in manifest.json.
     *
     * @param string $entry The name of the entry (e.g., 'main', 'editor').
     * @param string $namespace The namespace prefix for the assets (e.g., 'wpa').
     * @return void
     * @throws Exception
     */
    public static function enqueueBundle(string $entry, string $namespace = 'wpa'): void
    {
        // Normalize the entry name
        $normalizedEntry = self::normalizeAssetName($entry, true);

        // Load manifest.json
        $manifest = self::getManifestContent();

        // Check if the entry exists in the manifest
        if (!isset($manifest['entrypoints'][$normalizedEntry])) {
            wp_die("Entry point '$entry' does not exist in manifest.json.");
        }

        $assets = $manifest['entrypoints'][$normalizedEntry]['assets'];

        // Enqueue all types of assets
        self::enqueueCssFiles($assets['css'] ?? [], $namespace, $normalizedEntry);
        self::enqueueJsFiles($assets['js'] ?? [], $manifest, $namespace, $normalizedEntry);
        self::includePhpFiles($assets['php'] ?? []);
    }

    /**
     * Enqueue CSS files for a given entry.
     *
     * @param array $cssFiles List of CSS file paths.
     * @param string $namespace The namespace prefix for the assets.
     * @param string $entry The normalized entry name.
     * @return void
     * @throws Exception
     */
    protected static function enqueueCssFiles(array $cssFiles, string $namespace, string $entry): void
    {
        foreach ($cssFiles as $css) {
            wp_enqueue_style("$namespace/$entry-style", self::getBaseUrl() . $css, [], null);
        }
    }

    /**
     * Enqueue JS files with their dependencies for a given entry.
     *
     * @param array $jsFiles List of JS file paths.
     * @param array $manifest The manifest content.
     * @param string $namespace The namespace prefix for the assets.
     * @param string $entry The normalized entry name.
     * @return void
     * @throws Exception
     */
    protected static function enqueueJsFiles(array $jsFiles, array $manifest, string $namespace, string $entry): void
    {
        $dependencies = self::getAssetDependencies($entry, $manifest);

        foreach ($jsFiles as $js) {
            wp_enqueue_script("$namespace/$entry-script", self::getBaseUrl() . $js, $dependencies['dependencies'], $dependencies['version'], true);
        }
    }

    /**
     * Include PHP files for a given entry.
     *
     * @param array $phpFiles List of PHP file paths.
     * @return void
     * @throws Exception
     */
    protected static function includePhpFiles(array $phpFiles): void
    {
        foreach ($phpFiles as $phpFile) {
            $filePath = self::getBaseDir() . $phpFile;

            if (file_exists($filePath)) {
                include_once $filePath;
            }
        }
    }

    /**
     * Get the dependencies from the corresponding .asset.php file.
     *
     * @param string $entry The entry name (e.g., 'main', 'editor') or a single file (e.g., 'main.js').
     * @param array|null $manifest Optional pre-loaded manifest content.
     * @return array
     * @throws Exception
     */
    public static function getAssetDependencies(string $entry, ?array $manifest = null): array
    {
        // Normalize the entry name
        $entry = self::normalizeAssetName($entry, true);

        $manifest = $manifest ?? self::getManifestContent();

        // Try to get PHP asset from a bundle entrypoint
        $phpFilePath = self::getPhpFileFromBundle($manifest, $entry);

        // If not found, derive from the entry's JS file in entrypoints
        if (!$phpFilePath) {
            $phpFilePath = self::getPhpFileFromEntryJs($manifest, $entry);
        }

        // If still not found, check if it's a single asset
        if (!$phpFilePath) {
            $phpFilePath = self::getPhpFileFromSingleAsset($entry);
        }

        // If the PHP file exists, include and return the dependencies
        if ($phpFilePath && file_exists($phpFilePath)) {
            return include $phpFilePath;
        }

        // Return an empty structure if no dependencies are found
        return [
            'dependencies' => [],
            'version' => null,
        ];
    }

    /**
     * Derive the .asset.php file path from the entry's JS output in entrypoints.
     *
     * @param array $manifest The manifest content
     * @param string $entry The normalized entry name (e.g., 'main', 'editor')
     * @return string|null Returns the full PHP file path or null if not found
     * @throws Exception
     */
    protected static function getPhpFileFromEntryJs(array $manifest, string $entry): ?string
    {
        $jsFiles = $manifest['entrypoints'][$entry]['assets']['js'] ?? [];

        if (empty($jsFiles)) {
            return null;
        }

        // Take the first JS file and resolve its hashed path through the manifest
        $firstJs = ltrim($jsFiles[0], '/');
        $resolvedJs = $manifest[$firstJs] ?? null;

        if ($resolvedJs) {
            $resolvedJs = ltrim($resolvedJs, '/');
        } else {
            $resolvedJs = $firstJs;
        }

        $assetPhpKey = preg_replace('/\.js$/', '.asset.php', $resolvedJs);

        // Look up in manifest flat keys
        if (isset($manifest[$assetPhpKey])) {
            $fullPath = self::getBaseDir() . '/' . ltrim($manifest[$assetPhpKey], '/');
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Try direct filesystem path
        $fullPath = self::getBaseDir() . '/' . $assetPhpKey;
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        return null;
    }

    /**
     * Check if the PHP asset file is part of a bundle entrypoint.
     *
     * @param array $manifest The manifest content
     * @param string $entry The entry name (e.g., 'main', 'editor')
     * @return string|null Returns the full PHP file path or null if not found
     * @throws Exception
     */
    protected static function getPhpFileFromBundle(array $manifest, string $entry): ?string
    {
        // Ensure the entry and php assets exist in the manifest
        if (isset($manifest['entrypoints'][$entry]['assets']['php']) && is_array($manifest['entrypoints'][$entry]['assets']['php'])) {
            $phpFile = $manifest['entrypoints'][$entry]['assets']['php'][0] ?? null;

            // Return the full path if the PHP file exists
            if ($phpFile = self::getBaseDir() . $phpFile) {
                return file_exists($phpFile) ? $phpFile : null;
            }
        }

        return null;
    }

    /**
     * Check if the PHP asset file is a standalone asset.
     *
     * @param string $entry The entry name (e.g., 'main.js')
     * @return string|null Returns the PHP file path or null if not found
     * @throws Exception
     */
    protected static function getPhpFileFromSingleAsset(string $entry): ?string
    {
        $assetPath = self::getBaseDir() . '/' . $entry . '.asset.php';
        return file_exists($assetPath) ? $assetPath : null;
    }

    /**
     * Get the base URL of the public directory.
     * Automatically uses child theme if active, otherwise parent theme.
     * For Sage 9 themes the output directory suffix is omitted because Sage 9
     * serves compiled assets directly from the theme root (resources/).
     *
     * @return string
     * @throws Exception
     */
    protected static function getBaseUrl(): string
    {
        $useParentTheme = (bool)apply_filters('wpassets_use_parent_theme_manifest', false);

        $dirFunction = $useParentTheme ? 'get_template_directory_uri' : 'get_stylesheet_directory_uri';

        if (!function_exists($dirFunction)) {
            throw new Exception("$dirFunction() function is not available.");
        }

        $dir = $dirFunction();

        if (self::isSage9()) {
            $dir = dirname($dir);
        }

        return $dir . '/' . self::getOutputDir();
    }

    /**
     * Get the base directory of the public directory (server-side path).
     * Automatically uses child theme if active, otherwise parent theme.
     * For Sage 9 themes the output directory suffix is omitted because Sage 9
     * serves compiled assets directly from the theme root (resources/).
     *
     * @return string
     * @throws Exception
     */
    protected static function getBaseDir(): string
    {
        $useParentTheme = (bool)apply_filters('wpassets_use_parent_theme_manifest', false);

        $dirFunction = $useParentTheme ? 'get_template_directory' : 'get_stylesheet_directory';

        if (!function_exists($dirFunction)) {
            throw new Exception("$dirFunction() function is not available.");
        }

        $dir = $dirFunction();

        if (self::isSage9()) {
            $dir = dirname($dir);
        }

        return $dir . '/' . self::getOutputDir();
    }
}
