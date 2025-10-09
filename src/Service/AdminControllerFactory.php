<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LibraryThemeStyles\Controller\AdminController;

/**
 * Factory for AdminController
 * 
 * Properly injects dependencies without calling controller plugins in constructor
 */
class AdminControllerFactory implements FactoryInterface
{
    /**
     * Create an AdminController populated with its required dependencies from the container.
     *
     * @param ContainerInterface $container The service container used to retrieve dependencies.
     * @param string $requestedName The requested service name.
     * @param array|null $options Optional creation options (not used).
     * @return AdminController The constructed controller configured with API manager, error handler, and theme settings service.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): AdminController
    {
        $api = $container->get('Omeka\ApiManager');
        $errorHandler = $container->get(ErrorHandler::class);
        $themeSettingsService = $container->get(ThemeSettingsService::class);
        
        return new AdminController($api, $errorHandler, $themeSettingsService);
    }
}