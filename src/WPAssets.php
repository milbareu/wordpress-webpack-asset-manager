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
    const VERSION = '1.1.0';

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
     * Detection is based on three heuristics (any one is sufficient):
     *  1. The `App\Sage` class is loaded (registered by Sage 9's ServiceProvider).
     *  2. A `config/theme.php` file exists inside the active theme directory
     *     (Sage 9 ships this file; Sage 10+ does not).
     *  3. A `resources/views` directory exists inside the active theme (Blade
     *     template directory introduced in Sage 9).
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

        // First, check if the asset exists in the manifest with the full path
        if (!isset($manifest[$assetName])) {
            // If not found, try without the path (normalize)
            $assetName = self::normalizeAssetName($assetName);

            // Check again if the normalized asset exists in the manifest
            if (!isset($manifest[$assetName])) {
                return new \WP_Error('asset_file_missing', "Asset '$assetName' not found in the manifest.");
            }
        }

        // Construct the asset's path (either URL or filesystem path)
        $assetPath = self::getBaseUrl() . $manifest[$assetName];
        $filePath = self::getBaseDir() . $manifest[$assetName];

        // If requested, return the content of the asset
        if ($getContents) {
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
        self::enqueueJsFiles($assets['js'] ?? [], $namespace, $normalizedEntry);
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
     * @param string $namespace The namespace prefix for the assets.
     * @param string $entry The normalized entry name.
     * @return void
     * @throws Exception
     */
    protected static function enqueueJsFiles(array $jsFiles, string $namespace, string $entry): void
    {
        $dependencies = self::getAssetDependencies($entry);

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
     * @return array
     * @throws Exception
     */
    public static function getAssetDependencies(string $entry): array
    {
        // Normalize the entry name
        $entry = self::normalizeAssetName($entry, true);

        $manifest = self::getManifestContent();

        // Try to get PHP asset from a bundle entrypoint
        $phpFilePath = self::getPhpFileFromBundle($manifest, $entry);

        // If no PHP asset is found in the bundle, check if it's a single asset
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
