<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ModuleConfigService
 */
class ModuleConfigServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ModuleConfigService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $themeSettingsService = $container->get(\LibraryThemeStyles\Service\ThemeSettingsService::class);

        // Get preset map from static accessor (PresetManager service no longer registered)
        $presetMap = PresetManager::getAllPresets();

        return new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $presetMap);
    }
}
