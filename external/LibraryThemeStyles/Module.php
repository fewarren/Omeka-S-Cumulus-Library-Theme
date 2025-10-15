<?php declare(strict_types=1);

namespace LibraryThemeStyles;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Model\ViewModel;

class Module extends AbstractModule
{
    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    // Explicitly mark module configurable to ensure Configure link appears
    public function isConfigurable(): bool
    {
        return true;
    }

    // Expose a Configure button in Modules list and render our admin form
    public function getConfigForm(PhpRenderer $renderer)
    {
        // Render a fragment that Omeka wraps in its own form with CSRF token
        $view = new ViewModel();
        $view->setTemplate('library-theme-styles/admin/configure');
        return $renderer->render($view);
    }

    // Handle form submission from the module's Configure page (Omeka signature)
    public function handleConfigForm(AbstractController $controller): bool
    {
        $services = $controller->getEvent()->getApplication()->getServiceManager();
        $moduleConfigService = $services->get(Service\ModuleConfigService::class);
        $messenger = $controller->messenger();

        $data = $controller->params()->fromPost();

        // Delegate all business logic to the service
        return $moduleConfigService->handleConfigFormSubmission($data, $messenger);
    }
}

