<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for PresetManager
 */
class PresetManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): PresetManager
    {
        return new PresetManager();
    }
}
