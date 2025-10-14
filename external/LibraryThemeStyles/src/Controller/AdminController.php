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

    /**
     * Create an AdminController configured with the service responsible for handling module configuration.
     *
     * @param ModuleConfigService $moduleConfigService Service that processes and persists module configuration form submissions.
     */
    public function __construct(ModuleConfigService $moduleConfigService)
    {
        $this->moduleConfigService = $moduleConfigService;
    }

    /**
     * Render the admin configuration page and handle configuration form submissions.
     *
     * When the request is a POST, collects POST data, augments it with the current
     * site slug, and delegates processing to the ModuleConfigService for handling
     * the submission and user messaging.
     *
     * @return ViewModel The view model containing the `siteSlug` variable for the view.
     */
    public function indexAction()
    {
        $request = $this->getRequest();
        $siteSlug = $this->params()->fromQuery('site', null);

        if ($request->isPost()) {
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
