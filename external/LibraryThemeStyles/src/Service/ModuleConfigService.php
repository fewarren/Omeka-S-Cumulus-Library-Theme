<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;
use Laminas\Mvc\Controller\Plugin\Messenger;

/**
 * Service for handling module configuration form operations
 * 
 * This service extracts all business logic from Module::handleConfigForm()
 * and provides a clean, testable interface for configuration operations.
 */
class ModuleConfigService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private ThemeSettingsService $themeSettingsService;
    private array $presetMap;

    /**
     * Construct the ModuleConfigService and inject required dependencies.
     *
     * @param ApiManager $api Manager for external API interactions used by the service.
     * @param Settings $settings Global settings storage.
     * @param SiteSettings $siteSettings Site-scoped settings storage.
     * @param ThemeSettingsService $themeSettingsService Service responsible for theme-related operations.
     * @param array $presetMap Mapping of preset identifiers to their configuration metadata.
     */
    public function __construct(
        ApiManager $api,
        Settings $settings,
        SiteSettings $siteSettings,
        ThemeSettingsService $themeSettingsService,
        array $presetMap
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->themeSettingsService = $themeSettingsService;
        $this->presetMap = $presetMap;
    }

    /**
     * Process module configuration form submission and dispatch the requested action.
     *
     * Expects an associative $data array with the following keys:
     * - 'action' (string|null): the action to perform.
     * - 'target_preset' (string): preset name to target; defaults to 'modern' if absent.
     * - 'site' (string|null): site slug to operate on.
     * - 'debug' (mixed): truthy value enables debug behavior.
     *
     * @param array $data Form submission values (see description for expected keys).
     * @param Messenger $messenger Messenger used to report messages to the user.
     * @return bool `true` if the action was handled (including when an error was reported), `false` otherwise.
     */
    public function handleConfigFormSubmission(array $data, Messenger $messenger): bool
    {
        $action = $data['action'] ?? null;
        $targetPreset = $data['target_preset'] ?? 'modern';
        $siteSlug = $data['site'] ?? null;
        $debug = !empty($data['debug']);
        $themeKey = 'LibraryTheme';

        try {
            return $this->processAction($action, $siteSlug, $targetPreset, $themeKey, $debug, $data, $messenger);
        } catch (\Throwable $e) {
            error_log('[LibraryThemeStyles] ERROR: ' . $e->getMessage());
            $messenger->addError('Error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Dispatches a configuration action to the corresponding handler.
     *
     * @param string|null $action The action key selected from the configuration form.
     * @param string|null $siteSlug Optional site identifier to scope the operation.
     * @param string $targetPreset Target preset name to apply or compare against.
     * @param string $themeKey Theme identifier used for theme-specific operations.
     * @param bool $debug When true, handlers may include additional debug messages.
     * @param array $data Raw form data; used by actions that require extra input (e.g. `inspect_key`).
     * @param Messenger $messenger Messenger service used by handlers to report results to the user.
     * @return bool `true` if the dispatcher completed handling the action.
    private function processAction(
        ?string $action,
        ?string $siteSlug,
        string $targetPreset,
        string $themeKey,
        bool $debug,
        array $data,
        Messenger $messenger
    ): bool {
        switch ($action) {
            case 'inspect_theme_settings':
                return $this->handleInspectThemeSettings($siteSlug, $themeKey, $messenger);

            case 'verify_defaults_vs_settings':
                return $this->handleVerifyDefaultsVsSettings($siteSlug, $targetPreset, $messenger);

            case 'load_stored_defaults':
                return $this->handleLoadStoredDefaults($siteSlug, $targetPreset, $messenger);

            case 'inspect_key':
                return $this->handleInspectKey($siteSlug, $themeKey, $data, $messenger);

            case 'diff_vs_preset':
                return $this->handleDiffVsPreset($siteSlug, $themeKey, $targetPreset, $messenger);

            case 'load_defaults_into_settings':
                return $this->handleLoadDefaultsIntoSettings($siteSlug, $themeKey, $targetPreset, $debug, $messenger);

            case 'save_settings_as_defaults':
                return $this->handleSaveSettingsAsDefaults($siteSlug, $themeKey, $targetPreset, $debug, $messenger);

            default:
                $messenger->addWarning('No action selected.');
                return true;
        }
    }

    /**
     * Inspect current theme settings for a site and report the result via the messenger.
     *
     * If the provided site slug is missing or invalid the function records an error to the messenger.
     *
     * @param string|null $siteSlug Site slug for the target site.
     * @param string $themeKey Theme identifier to inspect.
     * @return bool `true` to indicate the action was handled.
     */
    private function handleInspectThemeSettings(?string $siteSlug, string $themeKey, Messenger $messenger): bool
    {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Provide a Site Slug to inspect current theme settings.')) {
            return true;
        }

        $summary = $this->inspectThemeSettings($siteSlug, $themeKey);
        $messenger->addSuccess($summary);
        return true;
    }

    /**
         * Verify stored preset defaults against current theme settings and report the result.
         *
         * @param string|null $siteSlug The site slug to scope the verification; required for a valid operation.
         * @param string $targetPreset The preset identifier whose stored defaults will be compared against site settings.
         * @return bool `true` when processing is finished.
         */
    private function handleVerifyDefaultsVsSettings(?string $siteSlug, string $targetPreset, Messenger $messenger): bool
    {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Provide a Site Slug.')) {
            return true;
        }

        $report = $this->verifyDefaultsVsSettings($siteSlug, $targetPreset);
        $messenger->addSuccess($report);
        return true;
    }

    /**
         * Load stored defaults for a preset into the site's theme settings.
         *
         * Attempts to apply stored default keys for the given preset into the settings for the provided site slug.
         * If the site slug is not provided, an error is added to the messenger and no changes are made.
         *
         * @param string|null $siteSlug The site slug to target; may be null (validation will report an error).
         * @param string $targetPreset The preset identifier whose stored defaults should be loaded.
         * @param Messenger $messenger Messenger used to report success or validation errors to the user.
         * @return bool Always returns `true` to indicate the action handler completed.
         */
    private function handleLoadStoredDefaults(?string $siteSlug, string $targetPreset, Messenger $messenger): bool
    {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Provide a Site Slug.')) {
            return true;
        }

        [$count, $msg] = $this->loadStoredDefaultsIntoSettings($siteSlug, $targetPreset);
        $messenger->addSuccess(sprintf('Loaded %d stored default keys into settings. %s', $count, $msg));
        return true;
    }

    /**
         * Inspect a single theme setting key and post result messages to the messenger.
         *
         * Validates the site slug and the presence of `inspect_key` in `$data`, then retrieves
         * the setting value and adds a success message containing the key and its JSON-encoded value.
         *
         * @param string|null $siteSlug The site slug to operate on.
         * @param string $themeKey The theme identifier to inspect.
         * @param array $data Form data containing an `inspect_key` entry with the setting key to inspect.
         * @param Messenger $messenger Messenger used to report errors or success messages to the user.
         * @return bool `true` to indicate the request was processed.
         */
    private function handleInspectKey(?string $siteSlug, string $themeKey, array $data, Messenger $messenger): bool
    {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Provide a Site Slug.')) {
            return true;
        }

        $key = trim((string)($data['inspect_key'] ?? ''));
        if ($key === '') {
            $messenger->addError('Provide a setting key to inspect.');
            return true;
        }

        $value = $this->inspectSingleKey($siteSlug, $themeKey, $key);
        $messenger->addSuccess(sprintf('Inspect key %s: %s', $key, json_encode($value)));
        return true;
    }

    /**
     * Process the "diff vs preset" action: validate the site, compute the diff of theme settings against a preset, and add a success message with a short diff sample.
     *
     * @param string|null $siteSlug The site slug to operate on; must be provided.
     * @param string $themeKey The theme identifier to inspect.
     * @param string $targetPreset The preset name to compare against.
     * @param Messenger $messenger Messenger service used to record user-facing messages.
     * @return bool `true` always.
     */
    private function handleDiffVsPreset(?string $siteSlug, string $themeKey, string $targetPreset, Messenger $messenger): bool
    {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Provide a Site Slug.')) {
            return true;
        }

        $target = $this->diffVsPreset($siteSlug, $themeKey, $targetPreset);
        $messenger->addSuccess('Diff vs preset (first 15): ' . $target);
        return true;
    }

    /**
         * Load a preset's stored defaults into the LibraryTheme settings for a site.
         *
         * Validates the site slug, applies the named preset to the theme settings, and adds success messages to the provided Messenger.
         * When `$debug` is true, records and reports the count of theme settings before and after applying the preset.
         *
         * @param string|null $siteSlug Site slug to which defaults should be applied.
         * @param string $themeKey Theme settings key (e.g. `LibraryTheme`).
         * @param string $targetPreset Name of the preset whose stored defaults will be applied.
         * @param bool $debug When true, include debug output showing counts before and after applying the preset.
         * @param Messenger $messenger Messenger used to record success or error messages.
         * @return bool `true` always (handler indicates the request was processed and messages were added).
         */
    private function handleLoadDefaultsIntoSettings(
        ?string $siteSlug,
        string $themeKey,
        string $targetPreset,
        bool $debug,
        Messenger $messenger
    ): bool {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Please provide a Site Slug to load defaults into LibraryTheme settings.')) {
            return true;
        }

        $before = $debug ? $this->countThemeSettings($siteSlug, $themeKey) : null;
        [$count] = $this->applyPresetToThemeSettings($siteSlug, $themeKey, $targetPreset);
        $after = $debug ? $this->countThemeSettings($siteSlug, $themeKey) : null;

        $messenger->addSuccess(sprintf('Loaded %d %s preset defaults into LibraryTheme settings for site "%s".', $count, $targetPreset, $siteSlug));
        
        if ($debug) {
            $messenger->addSuccess(sprintf('Debug: theme_settings_%s count before=%d after=%d', $themeKey, $before, $after));
        }
        
        return true;
    }

    /**
         * Save the current theme settings as the defaults for a named preset for a site.
         *
         * If the site slug is missing, records an error message and returns. On success, saves the
         * current settings into the specified preset, adds a success message with the number of
         * stored fields, and — when `$debug` is true — adds a debug message containing a JSON
         * sample of the stored defaults.
         *
         * @param string|null $siteSlug The site identifier; must be provided to perform the save.
         * @param string $themeKey The theme key to operate on (e.g. 'LibraryTheme').
         * @param string $targetPreset The preset name to store the defaults under.
         * @param bool $debug When true, adds a debug message with a sample of the saved defaults.
         * @return bool `true` after processing (returns early after adding an error if the site slug is invalid).
         */
    private function handleSaveSettingsAsDefaults(
        ?string $siteSlug,
        string $themeKey,
        string $targetPreset,
        bool $debug,
        Messenger $messenger
    ): bool {
        if (!$this->validateSiteSlug($siteSlug, $messenger, 'Please provide a Site Slug to save current settings as preset defaults.')) {
            return true;
        }

        [$count, $current] = $this->saveSettingsAsPresetDefaults($siteSlug, $themeKey, $targetPreset);
        $messenger->addSuccess(sprintf('Saved current LibraryTheme settings as %s preset defaults (%d fields).', $targetPreset, $count));
        
        if ($debug) {
            $messenger->addSuccess('Debug: stored defaults sample: ' . substr(json_encode($current), 0, 300) . '...');
        }
        
        return true;
    }

    /**
     * Validate a site slug and report a user-facing error when it is missing.
     *
     * @param string|null $siteSlug The site slug to validate.
     * @param Messenger $messenger Messenger used to add an error message if validation fails.
     * @param string $errorMessage Error message to add when $siteSlug is empty.
     * @return bool `true` if $siteSlug is present, `false` otherwise.
     */
    private function validateSiteSlug(?string $siteSlug, Messenger $messenger, string $errorMessage): bool
    {
        if (!$siteSlug) {
            $messenger->addError($errorMessage);
            return false;
        }
        return true;
    }

    /**
     * Apply a named preset to theme settings for a given site and theme.
     *
     * @param string|null $siteSlug Site identifier (slug); pass null to target the default/global site.
     * @param string $themeKey The theme key to which the preset will be applied.
     * @param string $preset The preset name to apply.
     * @return array An associative array containing details about the applied preset (for example counts and messages).
     */
    private function applyPresetToThemeSettings(?string $siteSlug, string $themeKey, string $preset): array
    {
        return $this->themeSettingsService->applyPresetToThemeSettings($siteSlug, $themeKey, $preset);
    }

    /**
     * Save current theme settings as the defaults for a named preset.
     *
     * @param string|null $siteSlug The site slug to scope the settings, or `null` for global/default scope.
     * @param string $themeKey The theme identifier whose settings will be saved.
     * @param string $preset The preset name under which to store the defaults.
     * @return array An associative array with details about the saved defaults (for example, keys like `count` and `snapshot`).
     */
    private function saveSettingsAsPresetDefaults(?string $siteSlug, string $themeKey, string $preset): array
    {
        return $this->themeSettingsService->saveSettingsAsPresetDefaults($siteSlug, $themeKey, $preset);
    }

    /**
     * Get the number of theme settings stored for the given site and theme.
     *
     * @param string $siteSlug The site slug that scopes the settings.
     * @param string $themeKey The theme key identifying which theme's settings to count.
     * @return int The count of theme settings for the specified site and theme.
     */
    private function countThemeSettings(string $siteSlug, string $themeKey): int
    {
        return $this->themeSettingsService->countThemeSettings($siteSlug, $themeKey);
    }

    /**
         * Inspect a single theme setting key for a given site and theme.
         *
         * @param string $siteSlug The site slug to scope the inspection.
         * @param string $themeKey The theme identifier to inspect against.
         * @param string $key The setting key to inspect.
         * @return mixed The inspection result for the specified setting key.*/
    private function inspectSingleKey(string $siteSlug, string $themeKey, string $key)
    {
        return $this->themeSettingsService->inspectSingleKey($siteSlug, $themeKey, $key);
    }

    /**
         * Produce a textual diff between the current theme settings for a site and a named preset.
         *
         * @param string $siteSlug The site slug to scope the theme settings.
         * @param string $themeKey The theme identifier.
         * @param string $preset The preset name to compare against.
         * @return string A textual diff describing differences between the current theme settings and the preset.
         */
    private function diffVsPreset(string $siteSlug, string $themeKey, string $preset): string
    {
        return $this->themeSettingsService->diffVsPreset($siteSlug, $themeKey, $preset);
    }

    /**
         * Produce a textual inspection of a theme's settings for a given site.
         *
         * @param string $siteSlug The site identifier (slug) to inspect.
         * @param string $themeKey The theme key whose settings should be inspected.
         * @return string The inspection output as a string.
         */
    private function inspectThemeSettings(string $siteSlug, string $themeKey): string
    {
        return $this->themeSettingsService->inspectThemeSettings($siteSlug, $themeKey);
    }

    /**
     * Retrieve stored defaults for a given preset from persistent settings.
     *
     * Reads the JSON-encoded value stored under the key `LibraryThemeStyles_defaults_<preset>` and
     * returns it as an associative array. If the setting is missing or the value is not a valid JSON
     * object/array, an empty array is returned.
     *
     * @param string $preset The preset identifier whose stored defaults to retrieve.
     * @return array The decoded defaults as an associative array, or an empty array if none exist or decoding fails.
     */
    private function getStoredDefaults(string $preset): array
    {
        $raw = $this->settings->get('LibraryThemeStyles_defaults_' . $preset);
        if (!$raw) return [];
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * Compare stored preset defaults with the current theme settings and produce a report.
     *
     * @param string $siteSlug The site identifier (slug) whose settings to compare.
     * @param string $preset The preset name whose stored defaults will be compared.
     * @return string A human-readable summary describing differences between the preset defaults and the site's current settings.
     */
    private function verifyDefaultsVsSettings(string $siteSlug, string $preset): string
    {
        return $this->themeSettingsService->verifyDefaultsVsSettings($siteSlug, $preset);
    }

    /**
     * Apply stored preset defaults to the specified site's theme settings.
     *
     * @param string $siteSlug The site identifier (site slug) to apply defaults for.
     * @param string $preset The preset name whose stored defaults will be loaded.
     * @return array An associative array with operation details (for example, the number of settings applied and any informational messages).
     */
    private function loadStoredDefaultsIntoSettings(string $siteSlug, string $preset): array
    {
        return $this->themeSettingsService->loadStoredDefaultsIntoSettings($siteSlug, $preset);
    }
}