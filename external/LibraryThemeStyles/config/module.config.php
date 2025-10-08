<?php declare(strict_types=1);

namespace LibraryThemeStyles;

use LibraryThemeStyles\Service\ModuleConfigService;
use LibraryThemeStyles\Service\ThemeSettingsService;
use LibraryThemeStyles\Service\PresetManager;

return [
    'service_manager' => [
        'factories' => [
            ModuleConfigService::class => Service\ModuleConfigServiceFactory::class,
            ThemeSettingsService::class => Service\ThemeSettingsServiceFactory::class,
            PresetManager::class => Service\PresetManagerFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\AdminController::class => Service\ControllerFactory::class,
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
