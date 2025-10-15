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
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $errorHandler = $container->get(ErrorHandler::class);
        
        return new ThemeSettingsService($api, $settings, $siteSettings, $errorHandler);
    }
}
