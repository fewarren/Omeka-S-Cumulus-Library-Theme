# AdminControllerFactory Dependency Injection Fix

## Overview

Fixed dependency mismatch in `src/Service/AdminControllerFactory.php` where the factory was building AdminController with three dependencies (`$api`, `$errorHandler`, `$themeSettingsService`) while the AdminController constructor expected only `ModuleConfigService`.

## Issue Identified

**Problem:** Factory-Constructor parameter mismatch

**Factory was providing:**
```php
new AdminController($api, $errorHandler, $themeSettingsService);
//                  ^^^^ ^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^^^^^^ 3 dependencies
```

**Constructor expected:**
```php
public function __construct(ModuleConfigService $moduleConfigService)
//                          ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ 1 dependency
```

**Impact:**
- Type mismatch: Wrong number and types of constructor arguments
- Runtime errors when instantiating AdminController
- Violation of dependency injection principles

## Fix Applied

### 1. ✅ **Created AdminController with Correct Constructor**

**Created:** `src/Controller/AdminController.php`

**Constructor signature:**
```php
class AdminController extends AbstractActionController
{
    private ModuleConfigService $moduleConfigService;

    public function __construct(ModuleConfigService $moduleConfigService)
    {
        $this->moduleConfigService = $moduleConfigService;
    }
```

**Business logic delegation:**
```php
public function indexAction()
{
    // ... collect form data ...
    
    if ($request->isPost()) {
        $data = [
            'action' => $this->params()->fromPost('action'),
            'target_preset' => $this->params()->fromPost('target_preset', 'modern'),
            'site' => $siteSlug,
            'debug' => false,
        ];

        // Delegate to ModuleConfigService for consistent handling
        $messenger = $this->messenger();
        $success = $this->moduleConfigService->handleConfigFormSubmission($data, $messenger);
        
        // Extract messages from messenger...
    }
}
```

### 2. ✅ **Updated AdminControllerFactory**

**Simplified dependency injection:**
```php
class AdminControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): AdminController
    {
        $moduleConfigService = $container->get(ModuleConfigService::class);
        
        return new AdminController($moduleConfigService);
    }
}
```

**Removed unnecessary dependencies:**
- ❌ `$api = $container->get('Omeka\ApiManager');`
- ❌ `$errorHandler = $container->get(ErrorHandler::class);`
- ❌ `$themeSettingsService = $container->get(ThemeSettingsService::class);`

### 3. ✅ **Updated Imports**

**AdminController imports:**
```php
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use LibraryThemeStyles\Service\ModuleConfigService;  // ✅ Only needed import
```

**AdminControllerFactory imports:**
```php
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LibraryThemeStyles\Controller\AdminController;   // ✅ Simplified imports
```

## Architecture Benefits

### **Before (Problematic):**
```
AdminControllerFactory
├── ApiManager (direct injection)
├── ErrorHandler (direct injection)  
├── ThemeSettingsService (direct injection)
└── AdminController
    ├── Duplicated business logic
    ├── Direct service calls
    └── Inconsistent error handling
```

### **After (Clean):**
```
AdminControllerFactory
└── ModuleConfigService (single injection)
    └── AdminController
        ├── Delegates to ModuleConfigService
        ├── Consistent business logic
        └── Unified error handling
```

## Key Improvements

1. **Single Responsibility**: AdminController focuses on HTTP concerns, delegates business logic
2. **Consistency**: Uses same ModuleConfigService as main module configuration
3. **Maintainability**: Business logic centralized in one service
4. **Type Safety**: Constructor signature matches factory arguments
5. **Dependency Reduction**: Controller only depends on what it actually needs

## Verification

### **Constructor Match:**
- ✅ **Factory provides**: `ModuleConfigService $moduleConfigService`
- ✅ **Constructor expects**: `ModuleConfigService $moduleConfigService`
- ✅ **Parameter count**: 1 argument matches 1 parameter

### **Dependency Chain:**
- ✅ **Container** → `ModuleConfigService::class`
- ✅ **ModuleConfigService** → Contains all business logic dependencies
- ✅ **AdminController** → Delegates to ModuleConfigService

### **Business Logic:**
- ✅ **Form handling**: Delegated to `$moduleConfigService->handleConfigFormSubmission()`
- ✅ **Error handling**: Handled by ModuleConfigService internally
- ✅ **Message extraction**: From Laminas Messenger plugin

## Testing

- ✅ **PHP Syntax**: Both files pass `php -l` validation
- ✅ **Type Safety**: Constructor signature matches factory arguments
- ✅ **Import Resolution**: All use statements are correct

## Files Modified

- ✅ `src/Controller/AdminController.php` - **CREATED** with correct constructor
- ✅ `src/Service/AdminControllerFactory.php` - Updated to provide single dependency
- ✅ `ADMIN_CONTROLLER_FACTORY_FIX.md` - Documentation

## Deployment

The fixed files are ready for deployment via:

```bash
sudo ./DEPLOY.sh
```

**Date:** 2025-10-08  
**Status:** COMPLETE ✅  
**Issue Type:** Dependency Injection Fix  
**Severity:** High (Runtime Error Prevention)
