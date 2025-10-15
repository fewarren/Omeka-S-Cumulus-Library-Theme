<?php declare(strict_types=1);

namespace LibraryThemeStyles;

use LibraryThemeStyles\Service\ModuleConfigService;
use LibraryThemeStyles\Service\ThemeSettingsService;

return [
    'service_manager' => [
        'factories' => [
            ModuleConfigService::class => Service\ModuleConfigServiceFactory::class,
            ThemeSettingsService::class => Service\ThemeSettingsServiceFactory::class,
            \LibraryThemeStyles\Service\ErrorHandler::class => function ($sm) {
                return new \LibraryThemeStyles\Service\ErrorHandler();
            },
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\AdminController::class => function ($sm) {
                return new Controller\AdminController(
                    $sm->get(\LibraryThemeStyles\Service\ModuleConfigService::class)
                );
            },
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'library-theme-styles' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/library-theme-styles',
                            'defaults' => [
                                '__NAMESPACE__' => 'LibraryThemeStyles\Controller',
                                'controller' => 'Admin',
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Library Theme Styles',
                'route' => 'admin/library-theme-styles',
                'resource' => 'LibraryThemeStyles\Controller\Admin',
                'privilege' => 'index',
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'file_renderers' => [
        'invokables' => [
            'pdf' => Media\FileRenderer\PdfRenderer::class,
        ],
        'aliases' => [
            'application/pdf' => 'pdf',
        ],
    ],
];
