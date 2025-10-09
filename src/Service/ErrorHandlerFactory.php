<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ErrorHandler
 */
class ErrorHandlerFactory implements FactoryInterface
{
    /**
     * Create and return a new ErrorHandler instance.
     *
     * @param ContainerInterface $container Service container (unused by this factory).
     * @param string $requestedName Name of the requested service (unused).
     * @param array|null $options Optional creation options (unused).
     * @return ErrorHandler The created ErrorHandler instance.
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ErrorHandler
    {
        return new ErrorHandler();
    }
}