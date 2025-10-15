<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ModuleConfigService
 * 
 * Properly injects all dependencies from service manager
 */
class ModuleConfigServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ModuleConfigService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $themeSettingsService = $container->get(ThemeSettingsService::class);
        $presetManager = $container->get(PresetManager::class);
        $errorHandler = $container->get(ErrorHandler::class);

        // Get preset map from PresetManager
        $presetMap = $presetManager->getAllPresets();

        return new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $presetMap, $errorHandler);
    }
}
