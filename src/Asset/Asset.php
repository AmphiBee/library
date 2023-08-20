<?php

declare(strict_types=1);

/**
 * Class Asset
 *
 * The Asset class is responsible for handling assets such as stylesheets and scripts in WordPress.
 */

namespace Pollen\Asset;

use Illuminate\Support\Facades\Vite;
use Pollen\Support\Facades\Action;
use Pollen\Support\Facades\Filter;

/**
 * Class Asset
 *
 * The Asset class represents an asset (style or script) to be enqueued in WordPress.
 */
class Asset
{
    /**
     * @var string The file path to be used.
     */
    protected string $handle = '';

    /**
     * @var string The path to be set
     */
    protected string $path = '';

    /**
     * @var string - The type of resource (e.g. 'style', 'script').
     */
    protected string $type = 'style';

    /**
     * @var array An array of dependencies to be set for style or script.
     */
    protected array $dependencies = [];

    protected \Pollen\Asset\Vite $vite;

    protected $requireViteClient = false;

    /**
     * Determines whether ViteJS should be used or not.
     *
     * @var bool Flag indicating whether Vite should be used
     */
    protected bool $useVite = false;

    /**
     * @var null|int The version number to be set. Set to null if no version provided.
     */
    protected ?string $version = null;

    /**
     * @var string The media type for which the CSS file is intended. Example: 'all', 'print', 'screen', etc.
     */
    protected string $media = 'all';

    /**
     * @var bool Whether to load the script in the footer or not
     */
    protected bool $loadInFooter = false;

    /**
     * @var string The strategy to be used for loading the data. Can be null.
     */
    protected ?string $loadStrategy = null;

    /**
     * @var mixed|null The inline content to be associated with the asset.
     */
    protected ?string $inlineContent = null;

    /**
     * @var null|int The position of the inline scripts (before or after).
     */
    protected ?string $inlinePosition = null;

    /**
     * @var array An array of hooks in which the asset should be enqueued.
     */
    protected array $hooks = [];

    /**
     * Constructor for the Asset class
     *
     * @param  string  $handle The handle for the style or script.
     * @param  string  $path The path for the style or script.
     * @return self
     */
    public function __construct(string $handle, string $path)
    {
        $this->handle = $handle;
        $this->path = $path;
        $this->type = $this->determineFileType($path);
        $this->vite = app('vite');

        return $this;
    }

    /**
     * Set an array of dependencies for the styule or script.
     *
     * @param  array  $dependencies An array of dependencies for the style or script.
     */
    public function dependencies(array $dependencies): self
    {
        $this->dependencies = $dependencies;

        return $this;
    }

    /**
     * Enable Vite integration for this asset.
     *
     * This method enables Vite integration for the asset by setting the `useVite` property to true.
     *
     * If Vite is running in hot mode, it calls the `handleHotAssets` method to handle hot asset reloading.
     *
     * It then calls the `lookupAssetInManifest` method to look up the asset in the Vite manifest file.
     *
     * @return $this
     */
    public function useVite()
    {
        $this->useVite = true;

        $this->path = Vite::isRunningHot()
            ? $this->vite->retrieveHotAsset($this->path)
            : $this->vite->lookupAssetInManifest($this->path);

        return $this;
    }

    /**
     * Set the version for the asset.
     *
     * @param  string  $version The version for the asset.
     */
    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Sets the media attribute for the stylesheet.
     *
     * @param  string  $media The media attribute value.
     */
    public function media(string $media): self
    {
        $this->media = $media;

        return $this;
    }

    /**
     * Determines whether the script should be loaded in the footer or not.
     *
     * @return self The current instance of the Asset class.
     */
    public function loadInFooter(): self
    {
        $this->loadInFooter = true;

        return $this;
    }

    /**
     * Sets the load strategy for the asset (defer, async)
     *
     * @param  string  $strategy The load strategy for the asset.
     */
    public function loadStrategy(string $strategy): self
    {
        $this->loadStrategy = $strategy;

        return $this;
    }

    /**
     * Set the type of the asset.
     *
     * @param  string  $type The type of the asset.
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Sets up the hook for enqueuing assets in the frontend of the website.
     *
     * @return self The current instance.
     */
    public function toFrontend(): self
    {
        $this->hook[] = 'wp_enqueue_scripts';

        return $this;
    }

    /**
     * Sets up the hook for enqueuing assets in the backend of WordPress.
     *
     * @return $this The current instance of the class.
     */
    public function toBackend(): self
    {
        $this->hook[] = 'admin_enqueue_scripts';

        return $this;
    }

    /**
     * Sets up the hook for enqueuing assets in the login screen.
     *
     * @return self Returns an instance of the current object.
     */
    public function toLoginScreen(): self
    {
        $this->hook[] = 'login_enqueue_scripts';

        return $this;
    }

    /**
     * Sets up the hook for enqueuing assets in the admin customizer.
     *
     * @return self Returns the current instance of the class.
     */
    public function toCustomizer(): self
    {
        $this->hook[] = 'customize_preview_init';

        return $this;
    }

    /**
     * Sets up the hook for enqueuing assets in the block editor.
     *
     * @return self Returns an instance of the current object.
     */
    public function toEditor(): self
    {
        $this->hook[] = 'enqueue_block_editor_assets';

        return $this;
    }

    /**
     * Localizes data to be passed to a script.
     *
     * @param  string  $objectName The name of the object to attach the data to.
     * @param  array  $data The data to be localized.
     * @return self Returns an instance of the current object.
     */
    public function localize(string $objectName, array $data): self
    {
        if ($this->type === 'script') {
            wp_localize_script($this->handle, $objectName, $data);
        }

        return $this;
    }

    /**
     * Sets the content and position for inline content for style or script.
     *
     * @param  string  $content The content to be inserted inline.
     * @param  string  $position The position where the content should be inserted (before or after), defaults to 'after'.
     * @return self Returns an instance of the current object.
     */
    public function inline(string $content, string $position = 'after'): self
    {
        $this->inlineContent = $content;
        $this->inlinePosition = $position;

        return $this;
    }

    /**
     * It is responsible for registering the enqueueStyleOrScript() method to the
     * specified hooks.
     *
     * If the `hook` property is empty, it defaults to using the 'wp_enqueue_scripts' hook.
     *
     * @return void
     */
    public function __destruct()
    {
        if (empty($this->hook)) {
            $this->hook[] = 'wp_enqueue_scripts';
        }

        foreach ($this->hook as $hook) {
            $this->maybeLoadViteClient($hook);
            Action::add($hook, [$this, 'enqueueStyleOrScript'], 99);
        }
    }

    /**
     * Determine if the Vite client needs to be loaded in the specified hook.
     *
     * @param  string  $hook The hook to check for Vite client loading.
     * @return bool True if the Vite client needs to be loaded in the specified hook, false otherwise.
     */
    protected function needToLoadViteClient(string $hook): bool
    {
        return $this->useVite && Vite::isRunningHot() && ! $this->vite->loadedInHook($hook);
    }

    /**
     * Load Vite client if necessary.
     *
     * This method checks if the Vite client needs to be loaded for a given hook.
     * If it does, it loads the Vite client using the Vite instance and echos the script tag.
     *
     * @param  string  $hook The hook name.
     */
    protected function maybeLoadViteClient(string $hook): void
    {
        Action::add($hook, function () use ($hook) {
            if ($this->needToLoadViteClient($hook)) {
                echo $this->vite->viteClientHtml($hook)->toHtml();
            }
        }, 1);
    }

    /**
     * Enqueues a style or script based on their type.
     *
     * @return void
     */
    public function enqueueStyleOrScript()
    {
        match ($this->type) {
            'style' => $this->enqueueStyle(),
            'script' => $this->enqueueScript()
        };
    }

    /**
     * Enqueue a script file in WordPress.
     *
     *  This method enqueues a script file using WordPress' wp_enqueue_script function.
     *  It takes the handle, path, dependencies, version, and type of load parameters of the style file.
     *
     *  If the `inlineContent` property is set, it uses WordPress' wp_add_inline_script function
     *  to add inline script to the enqueued script file.
     *
     * @return void
     */
    protected function enqueueScript()
    {
        wp_enqueue_script($this->handle, $this->path, $this->dependencies, $this->version, $this->loadInFooter);

        if ($this->useVite) {
            // Update script tag with module attribute.
            Filter::add('script_loader_tag', function ($tag, $handle, $src) {
                if ($handle !== $this->handle) {
                    return $tag;
                }

                // Change the script tag by adding type="module" and return it.
                $tag = '<script type="module" crossorigin src="'.esc_url($src).'"></script>';

                return $tag;
            }, 10, 3);
        }

        if ($this->loadStrategy) {
            wp_script_add_data($this->handle, 'defer', true);
        }
        if ($this->inlineContent) {
            wp_add_inline_script($this->handle, $this->inlineContent, $this->inlinePosition);
        }
    }

    /**
     * Enqueue a style file in WordPress.
     *
     * This method enqueues a style file using WordPress' wp_enqueue_style function.
     * It takes the handle, path, dependencies, version, and media parameters of the style file.
     *
     * If the `inlineContent` property is set, it uses WordPress' wp_add_inline_style function
     * to add inline styles to the enqueued style file.
     *
     * @return void
     */
    protected function enqueueStyle()
    {
        wp_enqueue_style($this->handle, $this->path, $this->dependencies, $this->version, $this->media);

        if ($this->inlineContent) {
            wp_add_inline_style($this->handle, $this->inlineContent);
        }
    }

    /**
     * Determine the file type based on the file extension.
     *
     * This method takes a file path as a parameter and determines the file type
     * based on the file extension. It returns the corresponding file type as a string.
     *
     * @param  string  $path The path of the file.
     * @return string The file type. Possible values are 'style', 'script', or 'style' if the extension is unknown.
     */
    protected function determineFileType($path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'css' => 'style',
            'js' => 'script',
            default => 'style', // Default to 'style'
        };
    }
}
