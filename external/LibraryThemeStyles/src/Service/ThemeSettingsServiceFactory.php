<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ThemeSettingsService
 */
class ThemeSettingsServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $errorHandler = $container->get(\LibraryThemeStyles\Service\ErrorHandler::class);

        return new ThemeSettingsService($api, $settings, $siteSettings, $errorHandler);
    }
}
