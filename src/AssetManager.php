<?php

namespace MB\WPAssets;

use Exception;

/**
 * AssetManager class to handle Webpack manifest and asset URLs.
 */
class AssetManager
{
    /**
     * Version of the AssetManager module.
     */
    const VERSION = '1.0.2';
    /**
     * Path to the Webpack-generated manifest.json file.
     *
     * @var string
     */
    protected static $manifestPath = '';

    /**
     * Cache the manifest content to avoid reading the file multiple times.
     *
     * @var array|null
     */
    protected static $manifest = null;

    /**
     * Base directory for public assets.
     *
     * @var string
     */
    protected $outputDir = 'public';

    /**
     * Name of the manifest file.
     *
     * @var string
     */
    protected $manifestFile = 'manifest.json';

    /**
     * Constructor to optionally set the output directory and manifest file.
     *
     * @param string $outputDir
     * @param string|null $manifestFile
     * @throws Exception
     */
    public function __construct(string $outputDir = 'public', ?string $manifestFile = null)
    {
        $this->outputDir = $outputDir;

        // Set a custom manifest file if provided, otherwise use default 'manifest.json'
        if ($manifestFile) {
            $this->manifestFile = $manifestFile;
        }

        // Automatically set the manifest path to the output directory + manifest file.
        self::$manifestPath = self::getBaseUrl() . '/' . $this->manifestFile;
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
     * Set a custom manifest file path manually.
     *
     * @param string $path
     * @return void
     */
    public static function setManifestPath(string $path)
    {
        self::$manifestPath = $path;
    }

    /**
     * Get the asset URL from the manifest.
     *
     * @param string $asset The name of the asset (e.g., 'scripts/app.js').
     * @return string The full URL to the asset.
     * @throws Exception
     */
    public function asset(string $asset): string
    {
        if (!self::$manifestPath) {
            throw new Exception('Manifest path is not set.');
        }

        // Load and cache the manifest if not already loaded
        if (self::$manifest === null) {
            self::$manifest = file_exists(self::$manifestPath)
                ? json_decode(file_get_contents(self::$manifestPath), true)
                : [];
        }

        // If the asset exists in the manifest, return its hashed path
        if (isset(self::$manifest[$asset])) {
            return $this->getBaseUrl() . '/' . self::$manifest[$asset];
        }

        // Fallback: return the original asset path if not in the manifest
        return $this->getBaseUrl() . '/' . $asset;
    }

    /**
     * Enqueue a script and style bundle with its dependencies.
     *
     * @param string $entry The name of the asset entry (e.g., 'app' or 'editor').
     * @param string $namespace
     * @return void
     * @throws Exception
     */
    public function enqueueBundle(string $entry, string $namespace = ''): void
    {
        if (!$entry) {
            return;
        }

        if ($namespace) {
            $entry = "$namespace/$entry";
        }

        // Get the asset dependencies from the *.asset.php file
        $dependencies = $this->getAssetDependencies("scripts/{$entry}.asset.php");

        // Check if wp_enqueue functions are available
        if (!function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script')) {
            throw new Exception('Required WordPress functions are not available.');
        }

        // Enqueue the CSS file
        if ($this->assetExists("styles/{$entry}.css")) {
            wp_enqueue_style($entry, $this->asset("styles/{$entry}.css"), [], $dependencies['version']);
        }

        // Enqueue the JS file with its dependencies
        if ($this->assetExists("scripts/{$entry}.js")) {
            wp_enqueue_script($entry, $this->asset("scripts/{$entry}.js"), $dependencies['dependencies'], $dependencies['version'], true);
        }
    }

    /**
     * Get the dependencies from the WordPress dependency extraction plugin.
     *
     * @param string $assetFile The path to the *.asset.php file (e.g., 'scripts/app.asset.php').
     * @return array Returns an array with 'dependencies' and 'version'.
     */
    protected function getAssetDependencies(string $assetFile): array
    {
        $assetPath = $this->getBaseUrl() . '/' . $assetFile;

        // Default structure for dependencies and version.
        $dependencies = [
            'dependencies' => [],
            'version' => null,
        ];

        // Check if the asset file exists and include it.
        if (file_exists($assetPath)) {
            $dependencies = include $assetPath;
        }

        return $dependencies;
    }

    /**
     * Check if the asset exists in the manifest.
     *
     * @param string $asset The asset path (e.g., 'styles/app.css').
     * @return bool
     * @throws Exception
     */
    protected function assetExists(string $asset): bool
    {
        if (!self::$manifestPath) {
            throw new Exception('Manifest path is not set.');
        }

        // Ensure the manifest is loaded
        if (self::$manifest === null) {
            self::$manifest = file_exists(self::$manifestPath)
                ? json_decode(file_get_contents(self::$manifestPath), true)
                : [];
        }

        return isset(self::$manifest[$asset]);
    }

    /**
     * Get the base URL of the public directory.
     *
     * @return string
     * @throws Exception
     */
    protected function getBaseUrl(): string
    {
        if (!function_exists('get_template_directory_uri')) {
            throw new Exception('get_template_directory_uri() function is not available.');
        }

        return get_template_directory_uri() . '/' . $this->outputDir;
    }
}
