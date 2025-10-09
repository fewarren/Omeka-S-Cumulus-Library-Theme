<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;
use LibraryThemeStyles\Config\ModuleConfig;

/**
 * Service for managing theme settings operations
 * Handles the business logic for applying presets and saving settings
 */
class ThemeSettingsService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private ErrorHandler $errorHandler;

    /**
     * Construct the ThemeSettingsService with its required dependencies.
     *
     * @param ApiManager $api API manager used to read site entities.
     * @param Settings $settings Global settings storage.
     * @param SiteSettings $siteSettings Site-scoped settings helper.
     * @param ErrorHandler $errorHandler Error handler/validator for theme settings and API errors.
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
     * Apply a named preset into a site's (or global) theme settings.
     *
     * @param string|null $siteSlug Site slug or null to target global settings.
     * @param string $themeKey Expected theme key used to validate the target theme.
     * @param string $preset Preset name to apply.
     * @return array Array with two elements: applied settings count (int) and the resulting settings array.
     * @throws \RuntimeException If the preset is unknown, preset validation fails, or the provided theme key does not match the target theme.
     */
    public function applyPresetToThemeSettings(?string $siteSlug, string $themeKey, string $preset): array
    {
        // Validate preset
        if (!PresetManager::hasPreset($preset)) {
            throw new \RuntimeException(ModuleConfig::getErrorMessage('unknown_preset', $preset));
        }

        $values = PresetManager::getPreset($preset);

        // Validate preset data
        $validationErrors = $this->errorHandler->validateThemeSettings($values);
        if (!empty($validationErrors)) {
            throw new \RuntimeException('Preset validation failed: ' . implode(', ', $validationErrors));
        }

        // Resolve site and settings scope
        $site = $this->resolveSite($siteSlug);
        $siteSettings = $this->getSiteSettingsInstance($site);

        // Get theme slug and validate against expected theme key
        $themeSlug = $this->getThemeSlug($site);
        $this->validateThemeKey($themeKey, $themeSlug);

        $key = ModuleConfig::getThemeSettingsKey($themeSlug);

        // Apply settings
        $current = $siteSettings->get($key, []);
        $current = is_array($current) ? $current : [];

        $count = 0;
        foreach ($values as $k => $v) {
            $current[$k] = $v;
            $count++;
        }
        
        $siteSettings->set($key, $current);

        $this->errorHandler->logSuccess('Applied preset to theme settings', [
            'preset' => $preset,
            'site_slug' => $siteSlug,
            'settings_count' => $count,
        ]);

        return [$count, $current];
    }

    /**
     * Save the current theme settings as stored defaults for a named preset.
     *
     * Resolves the target site (or global), validates the provided theme key against the resolved theme,
     * retrieves the active theme settings, validates them, and persists them as JSON under the preset's defaults key.
     *
     * @param string|null $siteSlug Site slug or null to operate on global settings.
     * @param string $themeKey Expected theme key used to validate the resolved theme before saving.
     * @param string $preset Preset name under which to store the defaults.
     * @return array Array where element 0 is the number of settings saved and element 1 is the stored settings array.
     * @throws \RuntimeException If the provided theme key does not match the resolved theme, if no settings are found, or if validation of the settings fails.
     */
    public function saveSettingsAsPresetDefaults(?string $siteSlug, string $themeKey, string $preset): array
    {
        // Resolve site and settings
        $site = $this->resolveSite($siteSlug);
        $siteSettings = $this->getSiteSettingsInstance($site);

        // Get theme slug and validate against expected theme key
        $themeSlug = $this->getThemeSlug($site);
        $this->validateThemeKey($themeKey, $themeSlug);

        // Get current settings with fallback logic
        $stored = $this->getCurrentThemeSettings($siteSettings, $themeSlug);

        if (!is_array($stored) || empty($stored)) {
            throw new \RuntimeException(ModuleConfig::getErrorMessage('settings_not_found', $siteSlug ?? 'default'));
        }

        // Validate settings before saving
        $validationErrors = $this->errorHandler->validateThemeSettings($stored);
        if (!empty($validationErrors)) {
            throw new \RuntimeException('Settings validation failed: ' . implode(', ', $validationErrors));
        }

        // Persist into global settings as JSON (per-preset)
        $defaultsKey = ModuleConfig::getDefaultsKey($preset);
        $this->settings->set($defaultsKey, json_encode($stored));

        $this->errorHandler->logSuccess('Saved settings as preset defaults', [
            'preset' => $preset,
            'site_slug' => $siteSlug,
            'settings_count' => count($stored),
        ]);

        return [count($stored), $stored];
    }

    /**
     * Restore previously saved default settings for a preset into the theme settings for a site or globally.
     *
     * @param string|null $siteSlug Site slug to target, or `null` to apply to global settings.
     * @param string $preset Name of the preset whose stored defaults should be loaded.
     * @return array Array with two elements: the number of applied settings and the resulting settings array.
     * @throws \RuntimeException If no stored defaults are found for the preset or if stored defaults are in an invalid format.
     */
    public function loadStoredDefaults(?string $siteSlug, string $preset): array
    {
        $defaultsKey = ModuleConfig::getDefaultsKey($preset);
        $storedJson = $this->settings->get($defaultsKey);
        
        if (!$storedJson) {
            throw new \RuntimeException("No stored defaults found for preset: {$preset}");
        }

        $storedDefaults = json_decode($storedJson, true);
        if (!is_array($storedDefaults)) {
            throw new \RuntimeException("Invalid stored defaults format for preset: {$preset}");
        }

        // Apply the stored defaults as if they were a preset
        return $this->applyPresetToThemeSettings($siteSlug, ModuleConfig::DEFAULT_THEME_KEY, $preset);
    }

    /**
     * Retrieve the current theme settings and related metadata for a site or the global scope.
     *
     * @param string|null $siteSlug Optional site slug; pass null to inspect global settings.
     * @return array{
     *     site_slug: string|null,
     *     theme_slug: string,
     *     settings_count: int,
     *     settings: array
     * } Map containing the inspected site slug, resolved theme slug, the number of settings entries, and the settings array.
     */
    public function inspectThemeSettings(?string $siteSlug): array
    {
        $site = $this->resolveSite($siteSlug);
        $siteSettings = $this->getSiteSettingsInstance($site);
        $themeSlug = $this->getThemeSlug($site);
        
        $settings = $this->getCurrentThemeSettings($siteSettings, $themeSlug);
        
        return [
            'site_slug' => $siteSlug,
            'theme_slug' => $themeSlug,
            'settings_count' => count($settings),
            'settings' => $settings,
        ];
    }

    /**
     * Compare the current theme settings for a site (or global settings when null) against a named preset.
     *
     * @param string|null $siteSlug Site slug to inspect, or null to inspect global settings.
     * @param string $preset Preset name to compare against.
     * @return array{
     *   preset: string,
     *   total_preset_keys: int,
     *   matches: int,
     *   differences: int,
     *   matching_keys: array<string,mixed>,
     *   different_keys: array<string,array{current:mixed,preset:mixed}>
     * } Map describing the comparison: the preset name, counts of preset keys, number of matching and differing keys, a map of matching key => current value, and a map of differing key => ['current' => currentValue, 'preset' => presetValue].
     */
    public function compareWithPreset(?string $siteSlug, string $preset): array
    {
        $current = $this->inspectThemeSettings($siteSlug);
        $presetValues = PresetManager::getPreset($preset);
        
        $differences = [];
        $matches = [];
        
        foreach ($presetValues as $key => $presetValue) {
            $currentValue = $current['settings'][$key] ?? null;
            
            if ($currentValue === $presetValue) {
                $matches[$key] = $currentValue;
            } else {
                $differences[$key] = [
                    'current' => $currentValue,
                    'preset' => $presetValue,
                ];
            }
        }
        
        return [
            'preset' => $preset,
            'total_preset_keys' => count($presetValues),
            'matches' => count($matches),
            'differences' => count($differences),
            'matching_keys' => $matches,
            'different_keys' => $differences,
        ];
    }

    /**
     * Retrieve a site entity for a given slug, or return null to indicate global context.
     *
     * @param string|null $siteSlug The site slug to resolve; pass `null` to target the global (no-site) context.
     * @return mixed|null The site entity returned by the API, or `null` if `$siteSlug` is `null`.
     * @throws \RuntimeException If the API call fails (error message produced by the error handler).
     */
    private function resolveSite(?string $siteSlug)
    {
        if (!$siteSlug) {
            return null;
        }

        try {
            return $this->api->read('sites', ['slug' => $siteSlug])->getContent();
        } catch (\Throwable $e) {
            throw new \RuntimeException($this->errorHandler->handleApiError($e, 'read site'));
        }
    }

    /**
     * Return the settings instance for the given site or the global settings when no site is provided.
     *
     * If a site is provided, the SiteSettings instance is prepared for that site by setting its site ID.
     *
     * @param object|null $site Site entity with an id() method, or null to indicate global scope.
     * @return Settings|SiteSettings The settings instance scoped to the site when $site is provided, otherwise the global Settings instance.
     */
    private function getSiteSettingsInstance($site): Settings|SiteSettings
    {
        if ($site) {
            $this->siteSettings->setSiteId($site->id());
            return $this->siteSettings;
        }
        
        return $this->settings;
    }

    /**
     * Determine the theme slug for a site or return the fallback slug.
     *
     * If a site object with a callable theme() method is provided and that method
     * returns a non-empty value, that value is used; otherwise the module's
     * fallback theme slug is returned.
     *
     * @param object|null $site Site entity or null for global context.
     * @return string The theme slug to use.
     */
    private function getThemeSlug($site): string
    {
        return $site && method_exists($site, 'theme') && $site->theme()
            ? (string) $site->theme()
            : ModuleConfig::FALLBACK_THEME_SLUG;
    }

    /**
     * Ensure the provided theme key corresponds to the computed theme slug.
     *
     * Throws a RuntimeException when the key does not match the slug or common slug variations.
     *
     * @param string $themeKey The expected theme key to validate.
     * @param string $themeSlug The computed theme slug for the site.
     * @throws \RuntimeException If the theme key does not match the computed theme slug.
     */
    private function validateThemeKey(string $themeKey, string $themeSlug): void
    {
        // Convert theme key to expected slug format for comparison
        $expectedSlug = strtolower(str_replace(' ', '-', $themeKey));

        // Allow exact match or common variations
        if ($expectedSlug !== $themeSlug &&
            $expectedSlug !== str_replace('-', '', $themeSlug) &&
            $themeKey !== 'LibraryTheme') {
            throw new \RuntimeException(
                "Theme key mismatch: expected '{$themeKey}' (slug: {$expectedSlug}) but computed theme slug is '{$themeSlug}'"
            );
        }
    }

    /**
     * Retrieve the current theme settings for a theme, using namespaced keys with container fallbacks.
     *
     * Attempts to read settings under the theme-specific namespaced key; if absent or not an array,
     * falls back to the shared theme settings container and returns either the theme-specific entry
     * from that container or the container itself when it represents a flat settings map.
     *
     * @param Settings|SiteSettings $siteSettings Settings storage for the target site or global settings.
     * @param string $themeSlug The theme slug to resolve settings for.
     * @return array The resolved theme settings as an associative array, or an empty array if none found.
     */
    private function getCurrentThemeSettings($siteSettings, string $themeSlug): array
    {
        // Prefer namespaced theme settings; fall back to container variants
        $namespacedKey = ModuleConfig::getThemeSettingsKey($themeSlug);
        $stored = $siteSettings->get($namespacedKey, []);
        
        if (!is_array($stored) || empty($stored)) {
            $container = $siteSettings->get(ModuleConfig::THEME_SETTINGS_CONTAINER_KEY, []);
            if (is_array($container)) {
                if (isset($container[$themeSlug]) && is_array($container[$themeSlug])) {
                    $stored = $container[$themeSlug];
                } elseif (!empty($container)) {
                    $stored = $container; // flat array variant
                }
            }
        }
        
        return is_array($stored) ? $stored : [];
    }
}