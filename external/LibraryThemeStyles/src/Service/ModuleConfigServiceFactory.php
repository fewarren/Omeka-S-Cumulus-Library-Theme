<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ModuleConfigService
 */
class ModuleConfigServiceFactory implements FactoryInterface
{
    /**
     * Create and configure a ModuleConfigService with dependencies resolved from the container.
     *
     * @param ContainerInterface $container Service container used to retrieve dependencies.
     * @param string $requestedName Requested service name.
     * @param array|null $options Optional factory options.
     * @return ModuleConfigService A ModuleConfigService instance configured with API, settings, theme settings service, and preset map.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ModuleConfigService
    {
        $api = $container->get('Omeka\ApiManager');
        $settings = $container->get('Omeka\Settings');
        $siteSettings = $container->get('Omeka\Settings\Site');
        $themeSettingsService = $container->get(\LibraryThemeStyles\Service\ThemeSettingsService::class);
        $presetManager = $container->get(\LibraryThemeStyles\Service\PresetManager::class);

        // Get preset map from centralized PresetManager
        $presetMap = $presetManager->getPresetMap();

        return new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $presetMap);
    }
}