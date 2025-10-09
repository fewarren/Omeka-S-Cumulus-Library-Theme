<?php declare(strict_types=1);

namespace LibraryThemeStyles;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Model\ViewModel;

class Module extends AbstractModule
{
    /**
     * Load and return the module configuration.
     *
     * @return array The module's configuration array as defined in config/module.config.php.
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Indicates the module exposes a configuration user interface.
     *
     * @return bool `true` if the module should be marked configurable and a Configure link shown, `false` otherwise.
     */
    public function isConfigurable(): bool
    {
        return true;
    }

    /**
     * Render the module's admin configuration form as an HTML fragment.
     *
     * @param PhpRenderer $renderer The view renderer used to render the configuration template.
     * @return string The rendered HTML fragment for the Configure page.
     */
    public function getConfigForm(PhpRenderer $renderer)
    {
        // Render a fragment that Omeka wraps in its own form with CSRF token
        $view = new ViewModel();
        $view->setTemplate('library-theme-styles/admin/configure');
        return $renderer->render($view);
    }

    /**
     * Process POST data from the module's Configure page.
     *
     * Collects form input from the controller request and returns whether the submission
     * was handled successfully.
     *
     * @param AbstractController $controller The controller handling the request.
     * @return bool `true` if the configuration submission was processed successfully, `false` otherwise.
     */
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
