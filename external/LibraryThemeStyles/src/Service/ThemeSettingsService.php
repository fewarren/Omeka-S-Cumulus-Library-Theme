<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;

/**
 * Service for managing theme settings operations
 *
 * This service handles the business logic for applying presets, saving settings,
 * and other theme-related operations. It eliminates code duplication by centralizing
 * all theme settings logic in one place.
 */
class ThemeSettingsService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private ErrorHandler $errorHandler;

    /**
     * Initialize the service and store required dependencies.
     */
    public function __construct(
        ApiManager $api,
        Settings $settings,
        SiteSettings $siteSettings,
        ErrorHandler $errorHandler
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Apply a named preset into theme settings for a site or the global scope.
     *
     * Merges the preset's key/value pairs into both the top-level theme_settings container
     * (handling either a slug-keyed map or a flat structure) and the theme-specific
     * namespaced settings (theme_settings_<slug>), then persists the updated containers.
     *
     * @param string|null $siteSlug Site slug, or null to target global settings
     * @param string $themeKey Theme key used when resolving the theme slug if necessary
     * @param string $preset Preset name (for example: "modern", "traditional")
     * @return array [int $count, array $values] Number of settings applied and the applied preset values
     * @throws \RuntimeException If the preset is unknown or the specified site cannot be resolved
     */
    public function applyPresetToThemeSettings(?string $siteSlug, string $themeKey, string $preset): array
    {
        // Validate preset
        if (!\LibraryThemeStyles\Service\PresetManager::hasPreset($preset)) {
            throw new \RuntimeException('Unknown preset: ' . $preset);
        }
        $values = \LibraryThemeStyles\Service\PresetManager::getPreset($preset);

        // Resolve site and get appropriate settings instance
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        // Get theme slug - use themeKey parameter for theme resolution
        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);

        // Read current theme settings containers
        $container = $settingsInstance->get('theme_settings', []);
        if (!is_array($container)) {
            $container = [];
        }

        $namespacedKey = 'theme_settings_' . $themeSlug;
        $namespaced = $settingsInstance->get($namespacedKey, []);
        if (!is_array($namespaced)) {
            $namespaced = [];
        }

        // Decide if theme_settings is a map keyed by theme slug or a flat array
        $isMap = isset($container[$themeSlug]) && is_array($container[$themeSlug]);
        if ($isMap) {
            $target = $container[$themeSlug];
        } else {
            $target = $container; // flat
        }

        // Merge preset values
        $count = 0;
        foreach ($values as $k => $v) {
            $target[$k] = $v;
            $namespaced[$k] = $v;
            $count++;
        }

        // Persist back
        if ($isMap) {
            $container[$themeSlug] = $target;
            $settingsInstance->set('theme_settings', $container);
        } else {
            $settingsInstance->set('theme_settings', $target);
        }
        $settingsInstance->set($namespacedKey, $namespaced);

        return [$count, $values];
    }

    /**
     * Store the resolved theme's current settings as the saved defaults for a named preset.
     *
     * Resolves the target site and theme, reads the effective theme settings, and writes them
     * into the global settings under the key `LibraryThemeStyles_defaults_<preset>` as JSON.
     *
     * @param string|null $siteSlug Site slug or null to operate on global settings
     * @param string $themeKey Theme key used to help resolve the theme when needed
     * @param string $preset Preset name under which to store the defaults
     * @return array [count, current] `count` is the number of settings saved, `current` is the associative array of the saved settings
     */
    public function saveSettingsAsPresetDefaults(?string $siteSlug, string $themeKey, string $preset): array
    {
        // Resolve site and get appropriate settings instance
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        // Get theme slug - use themeKey parameter for theme resolution
        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);

        // Get current settings with fallback logic
        $current = $this->getCurrentThemeSettings($themeSlug, $settingsInstance);

        if (!is_array($current) || empty($current)) {
            return [0, []];
        }

        // Persist into global settings as JSON (per-preset)
        $defaultsKey = 'LibraryThemeStyles_defaults_' . $preset;
        $this->settings->set($defaultsKey, json_encode($current));

        return [count($current), $current];
    }

    /**
     * Merge stored preset defaults into the target site's theme-specific settings and persist the result.
     *
     * Retrieves the stored defaults for the given preset, writes each default into the namespaced theme settings container (theme_settings_<slug>), saves the updated container, and reports how many keys were written.
     *
     * @param string|null $siteSlug Site slug to target, or null to operate on global settings
     * @param string $preset Preset name whose stored defaults will be applied
     * @return array{0:int,1:string} [count, message] First element is the number of keys written; second element is a descriptive message about the updated settings key and counts
     */
    public function loadStoredDefaultsIntoSettings(?string $siteSlug, string $preset): array
    {
        // Resolve site and get appropriate settings instance
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        $themeSlug = $this->getThemeSlug($site, null, $settingsInstance);
        $key = 'theme_settings_' . $themeSlug;
        $current = $settingsInstance->get($key, []);
        $current = is_array($current) ? $current : [];
        $defaults = $this->getStoredDefaults($preset);

        $count = 0;
        foreach ($defaults as $k => $v) {
            $current[$k] = $v;
            $count++;
        }

        $settingsInstance->set($key, $current);
        return [$count, sprintf('theme=%s key=%s now has %d keys', $themeSlug, $key, count($current))];
    }

    /**
     * Retrieve stored default values for a preset.
     *
     * The provided `$siteSlug` is accepted for API consistency but is not used;
     * stored defaults are global per preset. This returns the decoded defaults
     * previously saved for the named preset.
     *
     * @param string|null $siteSlug Site slug (ignored by this method).
     * @param string $preset Preset name to load defaults from.
     * @return array [int, array] An array where the first element is the number of stored defaults and the second element is the associative defaults array.
     */
    public function loadStoredDefaults(?string $siteSlug, string $preset): array
    {
        $defaults = $this->getStoredDefaults($preset);
        return [count($defaults), $defaults];
    }

    /**
     * Determine the number of theme settings for the resolved theme.
     *
     * @param string $siteSlug Site slug used to resolve a site-specific settings instance.
     * @param string $themeKey Theme key used to resolve the theme slug when the site's theme is not available.
     * @return int The number of theme settings present for the resolved theme.
     */
    public function countThemeSettings(string $siteSlug, string $themeKey): int
    {
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);
        $namespaced = $settingsInstance->get('theme_settings_' . $themeSlug, []);

        if (is_array($namespaced)) {
            return count($namespaced);
        }

        $container = $settingsInstance->get('theme_settings', []);
        if (is_array($container)) {
            if (isset($container[$themeSlug]) && is_array($container[$themeSlug])) {
                return count($container[$themeSlug]);
            }
            return count($container);
        }

        return 0;
    }

    /**
     * Retrieve the value of a single theme setting for a given site and theme.
     *
     * @param string $siteSlug Site slug or empty to target global settings.
     * @param string $themeKey Theme key used to resolve the theme when necessary.
     * @param string $key Setting key to inspect.
     * @return mixed The setting value if present, `null` if not found.
     */
    public function inspectSingleKey(string $siteSlug, string $themeKey, string $key)
    {
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);
        $namespaced = $settingsInstance->get('theme_settings_' . $themeSlug, []);

        if (is_array($namespaced) && array_key_exists($key, $namespaced)) {
            return $namespaced[$key];
        }

        $container = $settingsInstance->get('theme_settings', []);
        if (is_array($container)) {
            if (isset($container[$themeSlug]) && is_array($container[$themeSlug]) && array_key_exists($key, $container[$themeSlug])) {
                return $container[$themeSlug][$key];
            }
            if (array_key_exists($key, $container)) {
                return $container[$key];
            }
        }

        return null;
    }

    /**
     * Produce a concise list of differences between the resolved theme's current settings and a named preset.
     *
     * The result is a comma-separated list (up to 15 entries) of differences formatted as `key:current -> preset`
     * where `current` and `preset` are JSON-encoded values. If the preset is missing or there are no differences,
     * an empty string is returned.
     *
     * @param string $siteSlug Site slug; empty string is treated as the global scope.
     * @param string $themeKey Theme key used to resolve the theme when necessary.
     * @param string $preset Preset name to compare against.
     * @return string A comma-separated list of differences formatted as `key:current -> preset`, or an empty string if none.
     */
    public function diffVsPreset(string $siteSlug, string $themeKey, string $preset): string
    {
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);
        $current = $settingsInstance->get('theme_settings_' . $themeSlug, []);
        $want = \LibraryThemeStyles\Service\PresetManager::hasPreset($preset) ? \LibraryThemeStyles\Service\PresetManager::getPreset($preset) : [];

        $diffs = [];
        foreach ($want as $k => $v) {
            $cv = $current[$k] ?? null;
            if ($cv !== $v) {
                $diffs[] = $k . ':' . json_encode($cv) . ' -> ' . json_encode($v);
            }
        }

        return implode(', ', array_slice($diffs, 0, 15));
    }

    /**
     * Produce a concise summary of the resolved theme's settings and container state.
     *
     * @param string $siteSlug Site slug used to select site-specific settings; an empty value targets global settings.
     * @param string $themeKey Theme key used to resolve the theme slug when the site does not define one.
     * @return string A formatted string describing the namespaced settings key, the number of namespaced keys, the container type and its key count, and up to 15 sample namespaced keys.
     */
    public function inspectThemeSettings(string $siteSlug, string $themeKey): string
    {
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);
        $namespacedKey = 'theme_settings_' . $themeSlug;
        $namespaced = $settingsInstance->get($namespacedKey, []);
        $namespacedCount = is_array($namespaced) ? count($namespaced) : 0;

        $container = $settingsInstance->get('theme_settings', []);
        $containerInfo = 'N/A';
        $containerCount = 0;

        if (is_array($container)) {
            if (isset($container[$themeSlug]) && is_array($container[$themeSlug])) {
                $containerCount = count($container[$themeSlug]);
                $containerInfo = 'map[' . $themeSlug . ']';
            } else {
                $containerCount = count($container);
                $containerInfo = 'flat';
            }
        }

        $sampleKeys = is_array($namespaced) ? implode(', ', array_slice(array_keys($namespaced), 0, 15)) : 'N/A';

        return sprintf(
            'Inspect: %s has %d keys; theme_settings (%s) has %d keys. Sample (namespaced): %s',
            $namespacedKey,
            $namespacedCount,
            $containerInfo,
            $containerCount,
            $sampleKeys
        );
    }

    /**
     * Compare stored preset defaults with the current theme settings and produce a summary report.
     *
     * @param string $siteSlug The site slug to target (empty string refers to global settings).
     * @param string $preset The preset name whose stored defaults will be compared.
     * @return string A formatted summary reporting counts of settings, defaults, missing keys in each, and sample diffs. */
    public function verifyDefaultsVsSettings(string $siteSlug, string $preset): string
    {
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        $themeSlug = $this->getThemeSlug($site, null, $settingsInstance);
        $namespaced = $settingsInstance->get('theme_settings_' . $themeSlug, []);
        $namespaced = is_array($namespaced) ? $namespaced : [];
        $defaults = $this->getStoredDefaults($preset);

        $missingInDefaults = [];
        $missingInSettings = [];
        $diffs = [];

        foreach ($namespaced as $k => $v) {
            if (!array_key_exists($k, $defaults)) {
                $missingInDefaults[] = $k;
            }
        }

        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $namespaced)) {
                $missingInSettings[] = $k;
            } elseif ($namespaced[$k] !== $v) {
                $diffs[] = $k . ':' . json_encode($namespaced[$k]) . ' != ' . json_encode($v);
            }
        }

        return sprintf(
            'Verify: settings=%d, defaults=%d, missingInDefaults=%d, missingInSettings=%d, diffs=%d. Samples: missingInDefaults=[%s]; missingInSettings=[%s]; diffs=[%s]',
            count($namespaced),
            count($defaults),
            count($missingInDefaults),
            count($missingInSettings),
            count($diffs),
            implode(', ', array_slice($missingInDefaults, 0, 10)),
            implode(', ', array_slice($missingInSettings, 0, 10)),
            implode(', ', array_slice($diffs, 0, 10))
        );
    }

    /**
     * Resolve site entity from slug
     *
     * @param string|null $siteSlug Site slug or null
     * @return mixed Site entity or null
     * @throws \RuntimeException If site cannot be found
     */
    private function resolveSite(?string $siteSlug)
    {
        if (!$siteSlug) {
            return null;
        }

        try {
            return $this->api->read('sites', ['slug' => $siteSlug])->getContent();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Site not found: ' . $siteSlug);
        }
    }

    /**
     * Get theme slug from site or use fallback
     *
     * @param mixed $site Site entity or null
     * @param string|null $themeKey Theme key for resolution (now used)
     * @param mixed $settingsInstance Settings instance to use (optional)
     * @return string Theme slug
     */
    private function getThemeSlug($site = null, ?string $themeKey = null, $settingsInstance = null): string
    {
        // First try to get theme from site
        if ($site && method_exists($site, 'theme') && $site->theme()) {
            return (string) $site->theme();
        }

        // If themeKey is provided and looks like a theme slug, use it
        if ($themeKey && $themeKey !== 'LibraryTheme') {
            return strtolower(str_replace(' ', '-', $themeKey));
        }

        // Try to get theme from settings (use provided instance or fall back to site settings)
        try {
            $settings = $settingsInstance ?: $this->siteSettings;
            $slug = $settings->get('theme');
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }

        // Default fallback
        return 'library-theme';
    }

    /**
     * Get current theme settings with fallback logic
     *
     * @param string $themeSlug Theme slug
     * @param mixed $settingsInstance Settings instance to use (site or global)
     * @return array Current theme settings
     */
    private function getCurrentThemeSettings(string $themeSlug, $settingsInstance = null): array
    {
        // Use provided settings instance or fall back to site settings (for backward compatibility)
        $settings = $settingsInstance ?: $this->siteSettings;

        // Prefer namespaced settings; fall back to container (map or flat)
        $namespacedKey = 'theme_settings_' . $themeSlug;
        $current = $settings->get($namespacedKey, []);

        if (!is_array($current) || empty($current)) {
            $container = $settings->get('theme_settings', []);
            if (is_array($container)) {
                if (isset($container[$themeSlug]) && is_array($container[$themeSlug])) {
                    $current = $container[$themeSlug];
                } elseif (!empty($container)) {
                    $current = $container; // flat array variant
                }
            }
        }

        return is_array($current) ? $current : [];
    }

    /**
     * Get stored defaults for a preset
     *
     * @param string $preset Preset name
     * @return array Stored defaults or empty array
     */
    private function getStoredDefaults(string $preset): array
    {
        $raw = $this->settings->get('LibraryThemeStyles_defaults_' . $preset);
        if (!$raw) {
            return [];
        }

        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * Selects the Settings instance appropriate for the given site context.
     *
     * @param mixed $site Site entity or null; when provided selects the site-specific Settings target.
     * @return \LibraryThemeStyles\Service\Settings|\LibraryThemeStyles\Service\SiteSettings The settings instance to use (site-specific when $site is provided, otherwise global Settings).
     */
    private function getSiteSettingsInstance($site)
    {
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
            return $this->siteSettings;
        }

        return $this->settings;
    }
}