<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for PresetManager
 */
class PresetManagerFactory implements FactoryInterface
{
    /**
     * Create and return a new PresetManager instance.
     *
     * @return PresetManager The created PresetManager instance.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): PresetManager
    {
        return new PresetManager();
    }
}