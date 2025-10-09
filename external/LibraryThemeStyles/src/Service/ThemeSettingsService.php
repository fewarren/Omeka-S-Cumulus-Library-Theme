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
    private array $presetMap;

    /**
     * Create the ThemeSettingsService with required Omeka services and preset definitions.
     *
     * @param ApiManager $api Omeka API manager used for site lookups.
     * @param Settings $settings Global settings storage.
     * @param SiteSettings $siteSettings Per-site settings manager (used to set the target site context).
     * @param array $presetMap Associative map of preset names to their setting arrays.
    public function __construct(
        ApiManager $api,
        Settings $settings,
        SiteSettings $siteSettings,
        array $presetMap
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->presetMap = $presetMap;
    }

    /**
     * Apply preset values to theme settings for a specific site
     * 
     * @param string|null $siteSlug Site slug or null for global settings
     * @param string $themeKey Theme key (used for theme resolution if needed)
     * @param string $preset Preset name (modern, traditional)
     * @return array [count, values] - Number of settings applied and the preset values
     * @throws \RuntimeException If preset is unknown or site cannot be resolved
     */
    public function applyPresetToThemeSettings(?string $siteSlug, string $themeKey, string $preset): array
    {
        // Validate preset
        if (!isset($this->presetMap[$preset])) {
            throw new \RuntimeException('Unknown preset: ' . $preset);
        }
        $values = $this->presetMap[$preset];

        // Resolve site and set target
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        // Get theme slug - use themeKey parameter for theme resolution
        $themeSlug = $this->getThemeSlug($site, $themeKey);

        // Read current theme settings containers
        $container = $this->siteSettings->get('theme_settings', []);
        if (!is_array($container)) { 
            $container = []; 
        }
        
        $namespacedKey = 'theme_settings_' . $themeSlug;
        $namespaced = $this->siteSettings->get($namespacedKey, []);
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
            $this->siteSettings->set('theme_settings', $container);
        } else {
            $this->siteSettings->set('theme_settings', $target);
        }
        $this->siteSettings->set($namespacedKey, $namespaced);

        return [$count, $values];
    }

    /**
     * Store current theme settings as defaults associated with a preset name.
     *
     * @param string|null $siteSlug Site slug or null to use global settings
     * @param string $themeKey Theme key used to resolve the theme when needed
     * @param string $preset Preset name under which defaults will be saved
     * @return array An array where element 0 is the number of settings saved and element 1 is the saved settings array
     */
    public function saveSettingsAsPresetDefaults(?string $siteSlug, string $themeKey, string $preset): array
    {
        // Resolve site and set target
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        // Get theme slug - use themeKey parameter for theme resolution
        $themeSlug = $this->getThemeSlug($site, $themeKey);

        // Get current settings with fallback logic
        $current = $this->getCurrentThemeSettings($themeSlug);

        if (!is_array($current) || empty($current)) {
            return [0, []];
        }

        // Persist into global settings as JSON (per-preset)
        $defaultsKey = 'LibraryThemeStyles_defaults_' . $preset;
        $this->settings->set($defaultsKey, json_encode($current));
        
        return [count($current), $current];
    }

    /**
     * Merge stored preset defaults into the theme settings for the given site or global context.
     *
     * @param string|null $siteSlug Site slug, or null to operate on global settings
     * @param string $preset Preset name from which to load stored defaults
     * @return array{int,string} [number of settings loaded, status message with theme and key counts]
     */
    public function loadStoredDefaultsIntoSettings(?string $siteSlug, string $preset): array
    {
        // Resolve site and set target
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        $themeSlug = $this->getThemeSlug($site);
        $key = 'theme_settings_' . $themeSlug;
        $current = $this->siteSettings->get($key, []);
        $current = is_array($current) ? $current : [];
        $defaults = $this->getStoredDefaults($preset);

        $count = 0;
        foreach ($defaults as $k => $v) {
            $current[$k] = $v;
            $count++;
        }
        
        $this->siteSettings->set($key, $current);
        return [$count, sprintf('theme=%s key=%s now has %d keys', $themeSlug, $key, count($current))];
    }

    /**
     * Count theme settings for a site
     * 
     * @param string $siteSlug Site slug
     * @param string $themeKey Theme key (used for theme resolution if needed)
     * @return int Number of theme settings
     */
    public function countThemeSettings(string $siteSlug, string $themeKey): int
    {
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        $themeSlug = $this->getThemeSlug($site, $themeKey);
        $namespaced = $this->siteSettings->get('theme_settings_' . $themeSlug, []);
        
        if (is_array($namespaced)) {
            return count($namespaced);
        }
        
        $container = $this->siteSettings->get('theme_settings', []);
        if (is_array($container)) {
            if (isset($container[$themeSlug]) && is_array($container[$themeSlug])) {
                return count($container[$themeSlug]);
            }
            return count($container);
        }
        
        return 0;
    }

    /**
     * Retrieve the value for a single theme setting key for a given site and theme.
     *
     * @param string $siteSlug Site slug used to scope the lookup (use an empty string for global settings).
     * @param string $themeKey Theme key used to resolve the theme slug when needed.
     * @param string $key The individual setting key to inspect.
     * @return mixed The setting value if found, `null` otherwise.
     */
    public function inspectSingleKey(string $siteSlug, string $themeKey, string $key)
    {
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        $themeSlug = $this->getThemeSlug($site, $themeKey);
        $namespaced = $this->siteSettings->get('theme_settings_' . $themeSlug, []);
        
        if (is_array($namespaced) && array_key_exists($key, $namespaced)) {
            return $namespaced[$key];
        }
        
        $container = $this->siteSettings->get('theme_settings', []);
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
         * List differences between the current theme settings and a named preset.
         *
         * Differences are formatted as `key:current -> preset` and the result is limited to the first 15 entries.
         *
         * @param string $siteSlug Site slug used to select site-specific settings (empty or null-like value targets global settings).
         * @param string $themeKey Theme key used to resolve the theme slug when necessary.
         * @param string $preset Preset name to compare against.
         * @return string A comma-separated string of up to 15 differences in the form `key:current -> preset`; empty string if there are no differences.
         */
    public function diffVsPreset(string $siteSlug, string $themeKey, string $preset): string
    {
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        $themeSlug = $this->getThemeSlug($site, $themeKey);
        $current = $this->siteSettings->get('theme_settings_' . $themeSlug, []);
        $want = $this->presetMap[$preset] ?? [];
        
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
         * Produce a concise human-readable summary of a theme's stored settings for a site.
         *
         * Returns a one-line inspection that reports the namespaced settings key, the count
         * of namespaced keys, the structure and count of the global `theme_settings`
         * container (either `map[<theme>]` or `flat`), and a sample list of up to 15
         * namespaced keys.
         *
         * @param string $siteSlug Site slug to inspect; if empty or null-like the global scope is used.
         * @param string $themeKey Theme key used to resolve the theme slug when site data does not provide it.
         * @return string A formatted inspection summary, e.g.
         *                "Inspect: theme_settings_library-theme has 5 keys; theme_settings (map[library-theme]) has 7 keys. Sample (namespaced): color, font, ..."
         */
    public function inspectThemeSettings(string $siteSlug, string $themeKey): string
    {
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        $themeSlug = $this->getThemeSlug($site, $themeKey);
        $namespacedKey = 'theme_settings_' . $themeSlug;
        $namespaced = $this->siteSettings->get($namespacedKey, []);
        $namespacedCount = is_array($namespaced) ? count($namespaced) : 0;

        $container = $this->siteSettings->get('theme_settings', []);
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
         * Compare stored preset defaults with the current theme's namespaced settings and report differences.
         *
         * @param string $siteSlug Site slug (empty string targets global settings)
         * @param string $preset Preset name whose stored defaults will be compared
         * @return string A formatted summary containing counts and sample keys for missing and differing entries
         */
    public function verifyDefaultsVsSettings(string $siteSlug, string $preset): string
    {
        $site = $this->resolveSite($siteSlug);
        if ($site) {
            $this->siteSettings->setTargetId($site->id());
        }

        $themeSlug = $this->getThemeSlug($site);
        $namespaced = $this->siteSettings->get('theme_settings_' . $themeSlug, []);
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
     * Resolves the site entity for the given slug.
     *
     * @param string|null $siteSlug The site slug, or null to indicate no site.
     * @return mixed The site entity if found, or null when $siteSlug is null.
     * @throws \RuntimeException If no site matches the provided slug.
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
         * Resolve the theme slug to use for the given context.
         *
         * @param mixed $site Site entity or null; used if it exposes a theme() value.
         * @param string|null $themeKey Optional theme key to consider when site does not provide a theme.
         * @return string The resolved theme slug (falls back to 'library-theme' when none found).
         */
    private function getThemeSlug($site = null, ?string $themeKey = null): string
    {
        // First try to get theme from site
        if ($site && method_exists($site, 'theme') && $site->theme()) {
            return (string) $site->theme();
        }
        
        // If themeKey is provided and looks like a theme slug, use it
        if ($themeKey && $themeKey !== 'LibraryTheme') {
            return strtolower(str_replace(' ', '-', $themeKey));
        }
        
        // Try to get theme from site settings
        try {
            $slug = $this->siteSettings->get('theme');
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
         * Retrieve the current settings for a theme, preferring the per-theme namespaced
         * settings and falling back to the shared `theme_settings` container (either
         * a map keyed by theme slug or a flat settings array).
         *
         * @param string $themeSlug Theme slug
         * @return array Associative array of theme settings (empty array if none found)
         */
    private function getCurrentThemeSettings(string $themeSlug): array
    {
        // Prefer namespaced settings; fall back to container (map or flat)
        $namespacedKey = 'theme_settings_' . $themeSlug;
        $current = $this->siteSettings->get($namespacedKey, []);
        
        if (!is_array($current) || empty($current)) {
            $container = $this->siteSettings->get('theme_settings', []);
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
         * Retrieve stored defaults for a preset.
         *
         * Loads JSON-encoded defaults from the global setting key `LibraryThemeStyles_defaults_<preset>`
         * and returns them as an associative array.
         *
         * @param string $preset Preset name.
         * @return array Associative array of stored defaults, or an empty array if none or invalid.
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
}