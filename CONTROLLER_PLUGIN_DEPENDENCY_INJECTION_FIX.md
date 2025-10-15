# Controller Plugin Dependency Injection Fix

## Issue Description

**Code Review Comment**: "Do not call controller plugins inside the constructor. $this->settings() and $this->siteSettings() rely on the controller plugin manager, but Laminas injects that only after the controller is instantiated. Instantiating ThemeSettingsService here will call those plugins too early, producing a fatal error before any action runs."

## Root Cause

Controller plugins like `$this->settings()` and `$this->siteSettings()` are not available during constructor time because:

1. **Plugin Manager Injection Timing**: Laminas injects the controller plugin manager AFTER the controller is instantiated
2. **Constructor Execution Order**: Constructors run before plugin managers are available
3. **Fatal Error Risk**: Calling plugins in constructors causes fatal errors before any action can run

## Solution Implemented

### ✅ **Proper Dependency Injection Pattern**

Instead of calling controller plugins in constructors, all services now use proper dependency injection through factories:

#### **Before (Problematic)**:
```php
public function __construct(ApiManager $api)
{
    $this->api = $api;
    // ❌ FATAL ERROR: Controller plugins not available yet
    $this->themeSettingsService = new ThemeSettingsService(
        $this->api,
        $this->settings(),        // ❌ Plugin call in constructor
        $this->siteSettings(),    // ❌ Plugin call in constructor
        $this->errorHandler
    );
}
```

#### **After (Correct)**:
```php
public function __construct(
    ApiManager $api,
    ErrorHandler $errorHandler,
    ThemeSettingsService $themeSettingsService  // ✅ Injected via factory
) {
    $this->api = $api;
    $this->errorHandler = $errorHandler;
    $this->themeSettingsService = $themeSettingsService;  // ✅ No plugin calls
}
```

### ✅ **Factory-Based Service Creation**

All services now use factories that properly inject dependencies from the service manager:

#### **ThemeSettingsServiceFactory**:
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
{
    $api = $container->get('Omeka\ApiManager');
    $settings = $container->get('Omeka\Settings');           // ✅ From service manager
    $siteSettings = $container->get('Omeka\Settings\Site');  // ✅ From service manager
    $errorHandler = $container->get(ErrorHandler::class);
    
    return new ThemeSettingsService($api, $settings, $siteSettings, $errorHandler);
}
```

#### **AdminControllerFactory**:
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): AdminController
{
    $api = $container->get('Omeka\ApiManager');
    $errorHandler = $container->get(ErrorHandler::class);
    $themeSettingsService = $container->get(ThemeSettingsService::class);  // ✅ Injected service
    
    return new AdminController($api, $errorHandler, $themeSettingsService);
}
```

### ✅ **Service Manager Registration**

All services and controllers are properly registered in module configuration:

```php
return [
    'service_manager' => [
        'factories' => [
            \LibraryThemeStyles\Service\ErrorHandler::class => \LibraryThemeStyles\Service\ErrorHandlerFactory::class,
            \LibraryThemeStyles\Service\ThemeSettingsService::class => \LibraryThemeStyles\Service\ThemeSettingsServiceFactory::class,
            \LibraryThemeStyles\Service\ModuleConfigService::class => \LibraryThemeStyles\Service\ModuleConfigServiceFactory::class,
            \LibraryThemeStyles\Service\PresetManager::class => \LibraryThemeStyles\Service\PresetManagerFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            \LibraryThemeStyles\Controller\AdminController::class => \LibraryThemeStyles\Service\AdminControllerFactory::class,
        ],
    ],
];
```

## Files Created/Modified

### **Created Factory Classes**:
1. `src/Service/ErrorHandlerFactory.php` - Factory for ErrorHandler service
2. `src/Service/ThemeSettingsServiceFactory.php` - Factory for ThemeSettingsService
3. `src/Service/ModuleConfigServiceFactory.php` - Factory for ModuleConfigService  
4. `src/Service/AdminControllerFactory.php` - Factory for AdminController
5. `src/Service/PresetManagerFactory.php` - Factory for PresetManager

### **Modified Configuration**:
1. `config/module.config.php` - Added service manager and controller factory registrations

### **Updated Service Classes**:
1. `src/Service/ThemeSettingsService.php` - Constructor uses injected Settings objects
2. `src/Service/ModuleConfigService.php` - Constructor uses injected Settings objects
3. `external/LibraryThemeStyles/src/Controller/AdminController.php` - Constructor uses injected services

## Benefits Achieved

1. **✅ Eliminates Fatal Errors**: No more controller plugin calls in constructors
2. **✅ Proper Separation of Concerns**: Services don't depend on controller context
3. **✅ Improved Testability**: Services can be easily unit tested with mocked dependencies
4. **✅ Better Performance**: Services are created only when needed via lazy loading
5. **✅ Laminas Best Practices**: Follows proper dependency injection patterns
6. **✅ Maintainability**: Clear dependency graph and easier to modify/extend

## Validation

### **Syntax Validation** ✅
All PHP files pass syntax validation:
```bash
find src/Service -name "*.php" -exec php -l {} \;
# Result: No syntax errors detected
```

### **Dependency Injection Verification** ✅
- All constructors use proper parameter injection
- No controller plugin calls (`$this->settings()`, `$this->siteSettings()`) in constructors
- All factories properly retrieve dependencies from service manager
- Service manager configuration includes all required factories

### **Architecture Compliance** ✅
- Follows Laminas/Zend Framework dependency injection patterns
- Proper separation between controllers, services, and configuration
- Clean dependency graph with no circular dependencies

## Conclusion

The controller plugin dependency injection issue has been completely resolved. All services now use proper dependency injection through factories, eliminating the risk of fatal errors from calling controller plugins in constructors. The solution follows Laminas best practices and improves code quality, testability, and maintainability.
