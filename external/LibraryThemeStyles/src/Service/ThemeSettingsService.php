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
        if (!\LibraryThemeStyles\Service\PresetManager::hasPreset($preset)) {
            $this->errorHandler->logError('Unknown preset requested', [
                'preset' => $preset,
                'siteSlug' => $siteSlug,
                'themeKey' => $themeKey,
            ]);
            throw new \RuntimeException('Unknown preset: ' . $preset);
        }

        $this->errorHandler->logInfo('Applying preset to theme settings', [
            'preset' => $preset,
            'siteSlug' => $siteSlug,
            'themeKey' => $themeKey,
        ]);

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
     * Save current theme settings as preset defaults
     *
     * @param string|null $siteSlug Site slug or null for global settings
     * @param string $themeKey Theme key (used for theme resolution if needed)
     * @param string $preset Preset name to save settings under
     * @return array [count, current] - Number of settings saved and the current settings
     */
    public function saveSettingsAsPresetDefaults(?string $siteSlug, string $themeKey, string $preset): array
    {
        $this->errorHandler->logInfo('Saving current settings as defaults', [
            'siteSlug' => $siteSlug,
            'themeKey' => $themeKey,
            'preset' => $preset,
        ]);

        // Resolve site and get appropriate settings instance
        $site = $this->resolveSite($siteSlug);
        $settingsInstance = $this->getSiteSettingsInstance($site);

        // Get theme slug - use themeKey parameter for theme resolution
        $themeSlug = $this->getThemeSlug($site, $themeKey, $settingsInstance);

        // Get current settings with fallback logic
        $current = $this->getCurrentThemeSettings($themeSlug, $settingsInstance);

        if (!is_array($current) || empty($current)) {
            $this->errorHandler->logWarning('No settings found to save as defaults', [
                'siteSlug' => $siteSlug,
                'themeSlug' => $themeSlug,
            ]);
            return [0, []];
        }

        // Persist into global settings as JSON (per-preset)
        $defaultsKey = 'LibraryThemeStyles_defaults_' . $preset;
        $this->settings->set($defaultsKey, json_encode($current));

        $this->errorHandler->logInfo('Settings saved as defaults successfully', [
            'preset' => $preset,
            'count' => count($current),
        ]);

        return [count($current), $current];
    }

    /**
     * Load stored defaults back into site settings
     *
     * @param string|null $siteSlug Site slug or null for global settings
     * @param string $preset Preset name to load defaults from
     * @return array [count, message] - Number of settings loaded and status message
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
     * Load stored defaults for a preset (without applying to settings)
     *
     * @param string|null $siteSlug Site slug or null for global settings
     * @param string $preset Preset name to load defaults from
     * @return array [count, stored_defaults] - Number of defaults and the defaults array
     */
    public function loadStoredDefaults(?string $siteSlug, string $preset): array
    {
        $defaults = $this->getStoredDefaults($preset);
        return [count($defaults), $defaults];
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
     * Inspect a single setting key
     *
     * @param string $siteSlug Site slug
     * @param string $themeKey Theme key (used for theme resolution if needed)
     * @param string $key Setting key to inspect
     * @return mixed Setting value or null if not found
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
     * Compare current settings with a preset
     *
     * @param string $siteSlug Site slug
     * @param string $themeKey Theme key (used for theme resolution if needed)
     * @param string $preset Preset name to compare against
     * @return string Formatted difference string
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
     * Inspect theme settings and return formatted summary
     *
     * @param string $siteSlug Site slug
     * @param string $themeKey Theme key (used for theme resolution if needed)
     * @return string Formatted inspection summary
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
     * Verify stored defaults against current settings
     *
     * @param string $siteSlug Site slug
     * @param string $preset Preset name to verify against
     * @return string Formatted verification report
     */
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
            $response = $this->api->searchOne('sites', ['slug' => $siteSlug]);
            $site = $response ? $response->getContent() : null;
            if ($site) {
                $this->errorHandler->logDebug('Site resolved successfully', [
                    'siteSlug' => $siteSlug,
                    'siteId' => $site->id(),
                ]);
                return $site;
            }
        } catch (\Throwable $e) {
            $this->errorHandler->handleException($e, 'Error resolving site');
        }

        $this->errorHandler->logError('Site not found', ['siteSlug' => $siteSlug]);
        throw new \RuntimeException('Site not found: ' . $siteSlug);
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
        // Validate themeKey if provided with strict pattern matching
        if ($themeKey !== null) {
            $themeKey = trim($themeKey);

            // Validate: only letters, numbers, spaces, hyphens, underscores allowed
            if (!$this->errorHandler->validateAndLog(
                $themeKey,
                fn($key) => is_string($key) && !empty($key) && preg_match('/^[a-zA-Z0-9\s\-_]+$/', $key),
                'Invalid theme key provided: contains disallowed characters'
            )) {
                $this->errorHandler->logWarning('Theme key rejected due to invalid characters', [
                    'themeKey' => $themeKey,
                ]);
                $themeKey = null; // Fall back to default
            }
        }

        // First try to get theme from site
        if ($site && method_exists($site, 'theme') && $site->theme()) {
            $themeSlug = (string) $site->theme();
            $this->errorHandler->logDebug('Theme slug resolved from site', [
                'themeSlug' => $themeSlug,
            ]);
            return $themeSlug;
        }

        // If themeKey is provided and looks like a theme slug, use it
        if ($themeKey && $themeKey !== 'LibraryTheme') {
            $themeSlug = $this->sanitizeThemeSlug($themeKey);
            $this->errorHandler->logDebug('Theme slug resolved from themeKey', [
                'themeKey' => $themeKey,
                'themeSlug' => $themeSlug,
            ]);
            return $themeSlug;
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
     * Sanitize theme key into a valid slug
     *
     * Normalizes input by:
     * - Trimming whitespace
     * - Converting to lowercase
     * - Replacing sequences of non-alphanumeric characters with single hyphen
     * - Collapsing multiple hyphens
     * - Trimming leading/trailing hyphens
     *
     * @param string $themeKey Theme key to sanitize
     * @return string Sanitized theme slug
     */
    private function sanitizeThemeSlug(string $themeKey): string
    {
        // Trim whitespace
        $slug = trim($themeKey);

        // Convert to lowercase
        $slug = strtolower($slug);

        // Replace any sequence of non-alphanumeric characters with a single hyphen
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Collapse multiple hyphens into single hyphen
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim leading and trailing hyphens
        $slug = trim($slug, '-');

        $this->errorHandler->logDebug('Theme slug sanitized', [
            'original' => $themeKey,
            'sanitized' => $slug,
        ]);

        return $slug;
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
     * Get appropriate settings instance (site or global)
     *
     * @param mixed $site Site entity or null
     * @return Settings|SiteSettings Settings instance to use
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
