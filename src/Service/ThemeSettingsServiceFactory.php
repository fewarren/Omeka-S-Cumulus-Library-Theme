<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ThemeSettingsService
 * 
 * Properly injects Settings and SiteSettings from service manager
 * instead of calling controller plugins
 */
class ThemeSettingsServiceFactory implements FactoryInterface
{
    /**
     * Create a ThemeSettingsService with its required dependencies resolved from the container.
     *
     * Retrieves the API manager, global settings, site settings, and an error handler from the service container
     * and injects them into the ThemeSettingsService constructor.
     *
     * @param ContainerInterface $container Service container used to resolve dependencies.
     * @param string $requestedName The requested service name (unused by this factory).
     * @param array|null $options Optional factory options (unused).
     * @return ThemeSettingsService A ThemeSettingsService configured with the API manager, global settings, site settings, and an error handler.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $errorHandler = $container->get(ErrorHandler::class);
        
        return new ThemeSettingsService($api, $settings, $siteSettings, $errorHandler);
    }
}