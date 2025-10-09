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
     * Creates a PresetManager instance.
     *
     * @param string|mixed $requestedName The requested service name or alias.
     * @param array|null $options Optional factory options.
     * @return PresetManager The created PresetManager instance.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): PresetManager
    {
        return new PresetManager();
    }
}