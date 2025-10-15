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

        if ($request->isPost()) {
            // CSRF validation
            $csrfValidator = $this->getPluginManager()->get('csrf');
            if (!$csrfValidator->isValid()) {
                $this->messenger()->addError('Invalid CSRF token. Please try again.');
                return $this->redirect()->toRoute('admin/library-theme-styles', [], ['query' => ['site' => $siteSlug]]);
            }

            // Collect all POST data for ModuleConfigService
            $data = $this->params()->fromPost();
            $data['site'] = $siteSlug; // Add site slug to data

            // Get messenger plugin for message handling
            $messenger = $this->messenger();

            // Delegate to ModuleConfigService
            $this->moduleConfigService->handleConfigFormSubmission($data, $messenger);
        }

        return new ViewModel([
            'siteSlug' => $siteSlug,
        ]);
    }

}

