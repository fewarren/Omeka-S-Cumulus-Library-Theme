<?php declare(strict_types=1);

namespace LibraryThemeStyles\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Manager as ApiManager;
use LibraryThemeStyles\Service\ErrorHandler;
use LibraryThemeStyles\Service\ThemeSettingsService;

/**
 * Admin controller for LibraryThemeStyles module
 *
 * Delegates all business logic to ModuleConfigService for consistency
 * with the main module configuration form handling.
 */
class AdminController extends AbstractActionController
{
    private ApiManager $api;
    private ErrorHandler $errorHandler;
    private ThemeSettingsService $themeSettingsService;

    public function __construct(
        ApiManager $api,
        ErrorHandler $errorHandler,
        ThemeSettingsService $themeSettingsService
    ) {
        $this->api = $api;
        $this->errorHandler = $errorHandler;
        $this->themeSettingsService = $themeSettingsService;
    }

    public function indexAction()
    {
        $request = $this->getRequest();
        $siteSlug = $this->params()->fromQuery('site', null);

        $message = null;
        $error = null;

        try {
            if ($request->isPost()) {
                $action = $this->params()->fromPost('action');
                $targetPreset = $this->params()->fromPost('target_preset', 'modern');
                $themeKey = 'LibraryTheme';

                // Handle form submission using injected services
                $result = $this->handleFormAction($action, $siteSlug, $targetPreset, $themeKey);

                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['error'];
                }
            }
        } catch (\Throwable $e) {
            $error = $this->errorHandler->handleException($e, 'AdminController form submission');
        }

        return new ViewModel([
            'message' => $message,
            'error' => $error,
            'siteSlug' => $siteSlug,
        ]);
    }

    /**
     * Handle form action using injected services
     */
    private function handleFormAction(string $action, ?string $siteSlug, string $targetPreset, string $themeKey): array
    {
        try {
            switch ($action) {
                case 'apply_preset':
                    $result = $this->themeSettingsService->applyPresetToThemeSettings($siteSlug, $themeKey, $targetPreset);
                    return [
                        'success' => true,
                        'message' => "Applied {$targetPreset} preset: {$result[0]} settings updated."
                    ];

                case 'save_defaults':
                    $result = $this->themeSettingsService->saveSettingsAsPresetDefaults($siteSlug, $themeKey, $targetPreset);
                    return [
                        'success' => true,
                        'message' => "Saved {$result[0]} settings as {$targetPreset} defaults."
                    ];

                case 'load_defaults':
                    $result = $this->themeSettingsService->loadStoredDefaults($siteSlug, $themeKey, $targetPreset);
                    return [
                        'success' => true,
                        'message' => "Loaded {$result[0]} default settings for {$targetPreset}."
                    ];

                default:
                    return [
                        'success' => false,
                        'error' => "Unknown action: {$action}"
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $this->errorHandler->handleException($e, "Action: {$action}")
            ];
        }
    }
}

