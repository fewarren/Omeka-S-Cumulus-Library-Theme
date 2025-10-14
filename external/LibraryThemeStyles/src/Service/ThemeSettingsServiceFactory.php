<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ThemeSettingsService
 */
class ThemeSettingsServiceFactory implements FactoryInterface
{
    /**
     * Create a ThemeSettingsService instance using dependencies from the container.
     *
     * @param ContainerInterface $container The dependency injection container.
     * @param string $requestedName The requested service name.
     * @param array|null $options Optional factory options.
     * @return ThemeSettingsService The configured ThemeSettingsService instance.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $errorHandler = $container->get(\LibraryThemeStyles\Service\ErrorHandler::class);

        return new ThemeSettingsService($api, $settings, $siteSettings, $errorHandler);
    }
}