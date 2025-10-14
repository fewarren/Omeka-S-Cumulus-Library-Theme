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
     * Create and return a ModuleConfigService configured from the service container.
     *
     * Retrieves required dependencies and a preset map, then constructs the ModuleConfigService.
     *
     * @param \Psr\Container\ContainerInterface $container The service container.
     * @param string $requestedName The name of the service being requested.
     * @param array|null $options Optional factory options.
     * @return \LibraryThemeStyles\Service\ModuleConfigService The configured ModuleConfigService instance.
     */
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