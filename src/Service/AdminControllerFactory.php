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
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): AdminController
    {
        $api = $container->get('Omeka\ApiManager');
        $errorHandler = $container->get(ErrorHandler::class);
        $themeSettingsService = $container->get(ThemeSettingsService::class);
        
        return new AdminController($api, $errorHandler, $themeSettingsService);
    }
}
