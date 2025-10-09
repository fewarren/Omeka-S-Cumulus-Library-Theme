<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;
use Omeka\Mvc\Controller\Plugin\Messenger;
use LibraryThemeStyles\Config\ModuleConfig;

/**
 * Service for handling module configuration operations
 * Centralizes business logic for module configuration form handling
 */
class ModuleConfigService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private ThemeSettingsService $themeSettingsService;
    private ErrorHandler $errorHandler;

    /**
     * Construct the service with its required collaborators.
     */
    public function __construct(
        ApiManager $api,
        Settings $settings,
        SiteSettings $siteSettings,
        ThemeSettingsService $themeSettingsService,
        ErrorHandler $errorHandler
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->themeSettingsService = $themeSettingsService;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Dispatches a configuration form submission to the appropriate action handler.
     *
     * Expects $data to contain form fields that control the dispatched action:
     * - 'action': the requested operation (e.g. 'inspect_theme_settings', 'load_defaults_into_settings').
     * - 'target_preset': optional preset name; defaults to ModuleConfig::DEFAULT_PRESET when absent.
     * - 'site': optional site slug to target.
     * - 'debug': optional truthy flag to enable debug behaviour.
     *
     * @param array $data Form submission values (see description for expected keys).
     * @param Messenger $messenger Messenger used to report success, warning, or error messages.
     * @return bool `true` if the submission was handled (an action was processed or an error message was added), `false` otherwise.
     */
    public function handleConfigFormSubmission(array $data, Messenger $messenger): bool
    {
        $action = $data['action'] ?? null;
        $targetPreset = $data['target_preset'] ?? ModuleConfig::DEFAULT_PRESET;
        $siteSlug = $data['site'] ?? null;
        $debug = !empty($data['debug']);

        try {
            return $this->processAction($action, $targetPreset, $siteSlug, $debug, $data, $messenger);
        } catch (\Throwable $e) {
            $errorMessage = $this->errorHandler->handleException($e, 'module_config_form_submission');
            $messenger->addError($errorMessage);
            return true;
        }
    }

    /**
     * Dispatches the requested configuration action to the appropriate handler.
     *
     * Selects and invokes the handler corresponding to $action; if no valid action
     * is provided a warning is added to the messenger and the method returns.
     *
     * @param string|null $action The action identifier (e.g. 'inspect_theme_settings', 'verify_defaults_vs_settings', 'load_stored_defaults', 'inspect_key', 'diff_vs_preset', 'load_defaults_into_settings', 'save_settings_as_defaults').
     * @param string $targetPreset The preset name to operate against (defaults to ModuleConfig::DEFAULT_PRESET when applicable).
     * @param string|null $siteSlug The site slug to scope the operation, or null for the default site.
     * @param bool $debug When true, handlers may include additional debug information in messages.
     * @param array $data Additional form data required by some actions (for example the 'inspect_key' value).
     * @param Messenger $messenger Messenger used to record success, warning, and error messages.
     * @return bool `true` on completion.
     */
    private function processAction(
        ?string $action,
        string $targetPreset,
        ?string $siteSlug,
        bool $debug,
        array $data,
        Messenger $messenger
    ): bool {
        switch ($action) {
            case 'inspect_theme_settings':
                return $this->handleInspectThemeSettings($siteSlug, $messenger);

            case 'verify_defaults_vs_settings':
                return $this->handleVerifyDefaultsVsSettings($siteSlug, $targetPreset, $messenger);

            case 'load_stored_defaults':
                return $this->handleLoadStoredDefaults($siteSlug, $targetPreset, $messenger);

            case 'inspect_key':
                return $this->handleInspectKey($siteSlug, $data, $messenger);

            case 'diff_vs_preset':
                return $this->handleDiffVsPreset($siteSlug, $targetPreset, $messenger);

            case 'load_defaults_into_settings':
                return $this->handleLoadDefaultsIntoSettings($siteSlug, $targetPreset, $debug, $messenger);

            case 'save_settings_as_defaults':
                return $this->handleSaveSettingsAsDefaults($siteSlug, $targetPreset, $debug, $messenger);

            default:
                $messenger->addWarning('No action selected.');
                return true;
        }
    }

    /**
     * Inspect theme settings for a given site and post the result to the messenger.
     *
     * Validates the provided site slug, invokes the theme settings inspection, and adds either
     * a success message with a summary (site, theme, settings count and sample keys) or an
     * error message to the messenger.
     *
     * @param string|null $siteSlug Site slug to inspect; null to target the default site.
     * @return bool `true` when processing and messaging are complete.
     */
    private function handleInspectThemeSettings(?string $siteSlug, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->themeSettingsService->inspectThemeSettings($siteSlug),
            'inspect_theme_settings'
        );

        if ($result['success']) {
            $data = $result['data'];
            $summary = sprintf(
                'Inspect: Site "%s" (theme: %s) has %d theme settings. Sample keys: %s',
                $data['site_slug'] ?? 'default',
                $data['theme_slug'],
                $data['settings_count'],
                implode(', ', array_slice(array_keys($data['settings']), 0, 15))
            );
            $messenger->addSuccess($summary);
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
     * Compare a preset's defaults against a site's theme settings and report the results to the messenger.
     *
     * Validates the provided site slug, performs the comparison between the specified preset and the site's theme
     * settings, and adds either a success message with a summary of matches/differences or an error message to the messenger.
     *
     * @param string|null $siteSlug Site slug to operate on, or null to target the current/default site.
     * @param string $targetPreset Identifier of the preset to compare against.
     * @param Messenger $messenger Messenger instance that receives success or error messages.
     * @return bool `true` when processing is finished.
     */
    private function handleVerifyDefaultsVsSettings(?string $siteSlug, string $targetPreset, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->themeSettingsService->compareWithPreset($siteSlug, $targetPreset),
            'verify_defaults_vs_settings'
        );

        if ($result['success']) {
            $data = $result['data'];
            $report = sprintf(
                'Verify: %d preset keys, %d matches, %d differences. Sample differences: %s',
                $data['total_preset_keys'],
                $data['matches'],
                $data['differences'],
                implode(', ', array_slice(array_keys($data['different_keys']), 0, 10))
            );
            $messenger->addSuccess($report);
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
     * Load stored preset defaults into the theme settings for the given site and report results to the messenger.
     *
     * Validates the provided site slug, attempts to load the stored defaults for the specified preset, and adds
     * a success message with the number of keys loaded or an error message to the supplied Messenger.
     *
     * @param string|null $siteSlug The site slug to operate on, or null for the current/default site.
     * @param string $targetPreset The preset identifier whose stored defaults should be loaded.
     * @param Messenger $messenger Messenger used to report success or error messages to the caller.
     * @return bool `true` after processing (outcomes are reported via the Messenger).
     */
    private function handleLoadStoredDefaults(?string $siteSlug, string $targetPreset, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->themeSettingsService->loadStoredDefaults($siteSlug, $targetPreset),
            'load_stored_defaults'
        );

        if ($result['success']) {
            [$count, $details] = $result['data'];
            $messenger->addSuccess(sprintf('Loaded %d stored default keys into settings.', $count));
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
         * Inspect a single theme setting key for a site and add the result to the messenger.
         *
         * @param string|null $siteSlug Optional site slug to target; when null the current site is used.
         * @param array $data Expects an 'inspect_key' entry containing the setting key to inspect.
         * @return bool Always `true` to indicate the handler completed and messages were added to the messenger.
         */
    private function handleInspectKey(?string $siteSlug, array $data, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $key = trim((string)($data['inspect_key'] ?? ''));
        if ($key === '') {
            $messenger->addError('Provide a setting key to inspect.');
            return true;
        }

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->inspectSingleKey($siteSlug, $key),
            'inspect_single_key'
        );

        if ($result['success']) {
            $value = $result['data'];
            $messenger->addSuccess(sprintf('Inspect key %s: %s', $key, json_encode($value)));
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
     * Build and add a concise summary of differences between a site's current theme settings and a specified preset.
     *
     * @param string|null $siteSlug The site slug to operate on, or null for the default site.
     * @param string $targetPreset The preset identifier to compare against.
     * @return bool Always `true` to indicate processing completed and that a success or error message has been added to the messenger.
     */
    private function handleDiffVsPreset(?string $siteSlug, string $targetPreset, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->themeSettingsService->compareWithPreset($siteSlug, $targetPreset),
            'diff_vs_preset'
        );

        if ($result['success']) {
            $data = $result['data'];
            $differences = array_slice($data['different_keys'], 0, 15);
            $diffStrings = [];
            foreach ($differences as $key => $diff) {
                $diffStrings[] = $key . ':' . json_encode($diff['current']) . ' -> ' . json_encode($diff['preset']);
            }
            $messenger->addSuccess('Diff vs preset (first 15): ' . implode(', ', $diffStrings));
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
     * Load defaults from a preset into the site's LibraryTheme settings and report the result to the provided messenger.
     *
     * @param string|null $siteSlug The site slug to operate on, or null to use the default site.
     * @param string $targetPreset The name of the preset whose defaults will be applied.
     * @param bool $debug When true, include before/after theme settings counts in messenger output for debugging.
     * @param Messenger $messenger Messenger used to report success, debug information, or errors to the caller.
     * @return bool `true` after processing (successes and failures are communicated via the messenger).
     */
    private function handleLoadDefaultsIntoSettings(?string $siteSlug, string $targetPreset, bool $debug, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $before = $debug ? $this->countThemeSettings($siteSlug) : null;

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->themeSettingsService->applyPresetToThemeSettings($siteSlug, ModuleConfig::DEFAULT_THEME_KEY, $targetPreset),
            'load_defaults_into_settings'
        );

        if ($result['success']) {
            [$count, $details] = $result['data'];
            $after = $debug ? $this->countThemeSettings($siteSlug) : null;
            
            $messenger->addSuccess(sprintf(
                'Loaded %d %s preset defaults into LibraryTheme settings for site "%s".',
                $count,
                $targetPreset,
                $siteSlug
            ));
            
            if ($debug && $before !== null && $after !== null) {
                $messenger->addSuccess(sprintf(
                    'Debug: theme_settings count before=%d after=%d',
                    $before,
                    $after
                ));
            }
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
     * Save the current theme settings as the default values for a named preset.
     *
     * Validates the provided site slug, attempts to persist the current settings as defaults for
     * the given preset, and records success or error messages on the provided Messenger.
     *
     * @param string|null $siteSlug Site identifier slug; if null the default site is used.
     * @param string $targetPreset Name of the preset to save defaults into.
     * @param bool $debug When true, includes a truncated sample of stored defaults in messenger.
     * @param Messenger $messenger Messenger used to collect user-facing success or error messages.
     * @return bool `true` after the operation completes and messages have been added to the messenger.
     */
    private function handleSaveSettingsAsDefaults(?string $siteSlug, string $targetPreset, bool $debug, Messenger $messenger): bool
    {
        if ($siteError = $this->errorHandler->validateSiteSlug($siteSlug)) {
            $messenger->addError($siteError);
            return true;
        }

        $result = $this->errorHandler->wrapOperation(
            fn() => $this->themeSettingsService->saveSettingsAsPresetDefaults($siteSlug, ModuleConfig::DEFAULT_THEME_KEY, $targetPreset),
            'save_settings_as_defaults'
        );

        if ($result['success']) {
            [$count, $current] = $result['data'];
            $messenger->addSuccess(sprintf(
                'Saved current LibraryTheme settings as %s preset defaults (%d fields).',
                $targetPreset,
                $count
            ));
            
            if ($debug) {
                $sample = substr(json_encode($current), 0, 300) . '...';
                $messenger->addSuccess('Debug: stored defaults sample: ' . $sample);
            }
        } else {
            $messenger->addError($result['error']);
        }

        return true;
    }

    /**
     * Retrieve the value for a single theme setting key for the given site.
     *
     * @param string|null $siteSlug Site identifier or null to use the default/current site.
     * @param string $key The setting key to inspect.
     * @return mixed|null The setting value if present, `null` otherwise.
     */
    private function inspectSingleKey(?string $siteSlug, string $key)
    {
        $settings = $this->themeSettingsService->inspectThemeSettings($siteSlug);
        return $settings['settings'][$key] ?? null;
    }

    /**
     * Get the number of theme settings for a site.
     *
     * @param string|null $siteSlug Site slug to inspect; may be null.
     * @return int The number of theme settings. 
     */
    private function countThemeSettings(?string $siteSlug): int
    {
        $settings = $this->themeSettingsService->inspectThemeSettings($siteSlug);
        return $settings['settings_count'];
    }
}