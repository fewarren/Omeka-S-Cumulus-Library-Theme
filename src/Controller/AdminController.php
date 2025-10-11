<?php declare(strict_types=1);

namespace LibraryThemeStyles\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use LibraryThemeStyles\Service\ModuleConfigService;

/**
 * Admin controller for LibraryThemeStyles module
 *
 * Delegates all business logic to ModuleConfigService for consistency
 * with the main module configuration form handling.
 */
class AdminController extends AbstractActionController
{
    private ModuleConfigService $moduleConfigService;

    public function __construct(ModuleConfigService $moduleConfigService)
    {
        $this->moduleConfigService = $moduleConfigService;
    }

    public function indexAction()
    {
        $request = $this->getRequest();
        $siteSlug = $this->params()->fromQuery('site', null);

        $message = null;
        $error = null;

        try {
            if ($request->isPost()) {
                // Collect form data
                $data = [
                    'action' => $this->params()->fromPost('action'),
                    'target_preset' => $this->params()->fromPost('target_preset', 'modern'),
                    'site' => $siteSlug,
                    'debug' => false, // Admin interface doesn't need debug mode
                ];

                // Delegate to ModuleConfigService for consistent handling
                $messenger = $this->messenger();
                $this->moduleConfigService->handleConfigFormSubmission($data, $messenger);

                // Extract messages from messenger
                $messages = $messenger->getMessages();
                if (!empty($messages['success'])) {
                    $message = implode(' ', $messages['success']);
                }
                if (!empty($messages['error'])) {
                    $error = implode(' ', $messages['error']);
                }
            }
        } catch (\Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }

        return new ViewModel([
            'message' => $message,
            'error' => $error,
            'siteSlug' => $siteSlug,
        ]);
    }
}
