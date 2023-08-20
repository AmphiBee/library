<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\HtmlString;
use Pollen\Foundation\Application;

class Vite
{
    /**
     * The path of the ViteJS client.
     *
     * @var ?HtmlString
     */
    protected ?HtmlString $client = null;

    /**
     * Referenced hooks.
     */
    protected array $loadedInHooks = [];

    /**
     * The path to the build directory.
     *
     * @var string
     */
    protected $buildDirectory = 'build';

    /**
     * The name of the manifest file.
     *
     * @var string
     */
    protected $manifestFilename = 'manifest.json';

    /**
     * The cached manifest files.
     *
     * @var array
     */
    protected static $manifests = [];

    /**
     * The path to the "hot" file.
     *
     * @var string|null
     */
    protected $hotFile;

    public function __construct(Application $app)
    {
        $this->client = $app->get(\Illuminate\Foundation\Vite::class)([]);
    }

    /**
     * Generates the HTML markup for the Vite client script tag associated with a given hook.
     *
     * @param  string  $hook The hook to reference not to duplicate the client markup.
     * @return HtmlString The HTML object for the Vite client script tag.
     */
    public function viteClientHtml($hook): HtmlString
    {
        $this->loadInHook($hook);

        return $this->client;
    }

    /**
     * Reference a hook into the loadedInHooks property.
     *
     * @param  string  $hook The hook to Reference.
     */
    public function loadInHook($hook): void
    {
        $this->loadedInHooks[$hook] = true;
    }

    /**
     * Checks if a given hook is referenced in the loadedInHooks property.
     *
     * @param  string  $hook The hook to check.
     * @return bool Returns true if the hook is referenced, false otherwise.
     */
    public function loadedInHook(string $hook): bool
    {
        return isset($this->loadedInHooks[$hook]);
    }

    /**
     * Handle hot asset for the current file.
     *
     * This method retrieves the hot asset path using the `hotAsset` method
     *
     * @return void
     */
    public function retrieveHotAsset(string $path): string
    {
        $hotAsset = $this->hotAsset($path);

        return $hotAsset;
    }

    /**
     * Lookup an asset in the manifest.
     *
     * This method looks up the given asset path in the manifest array.
     * If the asset path exists in the manifest, it modifies the asset path to
     * include the full URL to the asset file.
     *
     * @return void
     */
    public function lookupAssetInManifest(string $path): string
    {
        $manifest = $this->manifest($this->buildDirectory);

        if (isset($manifest[$path])) {
            $path = home_url().'/'.$this->buildDirectory.'/'.$manifest[$path]['file'];
        }

        return $path;
    }

    /**
     * Get the the manifest file for the given build directory.
     *
     * @param  string  $buildDirectory
     * @return array
     *
     * @throws \Illuminate\Foundation\ViteManifestNotFoundException
     */
    public function manifest($buildDirectory)
    {
        $path = $this->manifestPath($buildDirectory);

        if (! isset(static::$manifests[$path])) {
            if (! is_file($path)) {
                throw new ViteManifestNotFoundException("Vite manifest not found at: $path");
            }

            static::$manifests[$path] = json_decode(file_get_contents($path), true);
        }

        return static::$manifests[$path];
    }

    /**
     * Returns the path to the manifest file for a given build directory.
     *
     * @param  string  $buildDirectory The build directory.
     * @return string The path to the manifest file.
     */
    protected function manifestPath($buildDirectory)
    {
        return public_path($buildDirectory.'/'.$this->manifestFilename);
    }

    /**
     * Get the path to a given asset when running in HMR mode.
     *
     * @return string
     */
    public function hotAsset($asset)
    {
        return rtrim(file_get_contents(\Illuminate\Support\Facades\Vite::hotFile())).'/'.$asset;
    }
}
