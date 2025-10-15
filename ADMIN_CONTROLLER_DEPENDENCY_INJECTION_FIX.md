# AdminController Dependency Injection Fix

## Overview

Updated the AdminController in the external LibraryThemeStyles module to expect three dependencies (ApiManager, ErrorHandler, ThemeSettingsService) instead of ModuleConfigService, and replaced the ControllerFactory class with a closure factory in the module configuration to properly inject all required dependencies.

## Issue Identified

**Problem:** Constructor dependency mismatch

**Location:** `external/LibraryThemeStyles/src/Controller/AdminController.php` lines 19-27

**Root Cause:**
- Code review indicated constructor should expect three dependencies
- Current constructor only expected ModuleConfigService
- Factory configuration used ControllerFactory class instead of closure
- Missing ErrorHandler service registration in external module

**Impact:**
- Dependency injection mismatch between constructor and factory
- Controller couldn't access required services directly
- Inconsistent architecture with code review expectations

## Fix Applied

### **1. ✅ Updated AdminController Constructor**

#### **Before (Single Dependency):**
```php
use LibraryThemeStyles\Service\ModuleConfigService;

class AdminController extends AbstractActionController
{
    private ModuleConfigService $moduleConfigService;

    public function __construct(ModuleConfigService $moduleConfigService)
    {
        $this->moduleConfigService = $moduleConfigService;
    }
}
```

#### **After (Three Dependencies):**
```php
use Omeka\Api\Manager as ApiManager;
use LibraryThemeStyles\Service\ErrorHandler;
use LibraryThemeStyles\Service\ThemeSettingsService;

class AdminController extends AbstractActionController
{
    private ApiManager $api;
    private ErrorHandler $errorHandler;
    private ThemeSettingsService $themeSettingsService;

    public function __construct(
        ApiManager $api,
        ErrorHandler $errorHandler,
        ThemeSettingsService $themeSettingsService
    ) {
        $this->api = $api;
        $this->errorHandler = $errorHandler;
        $this->themeSettingsService = $themeSettingsService;
    }
}
```

### **2. ✅ Updated Module Configuration Factory**

#### **Before (ControllerFactory Class):**
```php
'controllers' => [
    'factories' => [
        Controller\AdminController::class => Service\ControllerFactory::class,
    ],
],
```

#### **After (Closure Factory):**
```php
'controllers' => [
    'factories' => [
        Controller\AdminController::class => function ($sm) {
            return new Controller\AdminController(
                $sm->get('Omeka\ApiManager'),
                $sm->get(\LibraryThemeStyles\Service\ErrorHandler::class),
                $sm->get(ThemeSettingsService::class)
            );
        },
    ],
],
```

### **3. ✅ Added ErrorHandler Service Registration**

#### **Service Manager Configuration:**
```php
'service_manager' => [
    'factories' => [
        ModuleConfigService::class => Service\ModuleConfigServiceFactory::class,
        ThemeSettingsService::class => Service\ThemeSettingsServiceFactory::class,
        \LibraryThemeStyles\Service\ErrorHandler::class => function ($sm) {
            return new \LibraryThemeStyles\Service\ErrorHandler();
        },
    ],
],
```

### **4. ✅ Refactored Controller Logic**

#### **Updated indexAction Method:**
```php
public function indexAction()
{
    $request = $this->getRequest();
    $siteSlug = $this->params()->fromQuery('site', null);

    $message = null;
    $error = null;

    try {
        if ($request->isPost()) {
            $action = $this->params()->fromPost('action');
            $targetPreset = $this->params()->fromPost('target_preset', 'modern');
            $themeKey = 'LibraryTheme';

            // Handle form submission using injected services
            $result = $this->handleFormAction($action, $siteSlug, $targetPreset, $themeKey);
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
        }
    } catch (\Throwable $e) {
        $error = $this->errorHandler->handleException($e, 'AdminController form submission');
    }

    return new ViewModel([
        'message' => $message,
        'error' => $error,
        'siteSlug' => $siteSlug,
    ]);
}
```

#### **Added handleFormAction Method:**
```php
private function handleFormAction(string $action, ?string $siteSlug, string $targetPreset, string $themeKey): array
{
    try {
        switch ($action) {
            case 'apply_preset':
                $result = $this->themeSettingsService->applyPresetToThemeSettings($siteSlug, $themeKey, $targetPreset);
                return [
                    'success' => true,
                    'message' => "Applied {$targetPreset} preset: {$result[0]} settings updated."
                ];

            case 'save_defaults':
                $result = $this->themeSettingsService->saveSettingsAsPresetDefaults($siteSlug, $themeKey, $targetPreset);
                return [
                    'success' => true,
                    'message' => "Saved {$result[0]} settings as {$targetPreset} defaults."
                ];

            case 'load_defaults':
                $result = $this->themeSettingsService->loadStoredDefaults($siteSlug, $themeKey, $targetPreset);
                return [
                    'success' => true,
                    'message' => "Loaded {$result[0]} default settings for {$targetPreset}."
                ];

            default:
                return [
                    'success' => false,
                    'error' => "Unknown action: {$action}"
                ];
        }
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'error' => $this->errorHandler->handleException($e, "Action: {$action}")
        ];
    }
}
```

## Dependency Injection Architecture

### **Service Dependencies:**
1. **ApiManager** (`'Omeka\ApiManager'`) - For Omeka API operations
2. **ErrorHandler** (`\LibraryThemeStyles\Service\ErrorHandler::class`) - For error handling and logging
3. **ThemeSettingsService** (`ThemeSettingsService::class`) - For theme settings operations

### **Service Resolution Order:**
```php
// Factory closure parameters match constructor order:
new Controller\AdminController(
    $sm->get('Omeka\ApiManager'),           // 1st parameter
    $sm->get(\LibraryThemeStyles\Service\ErrorHandler::class), // 2nd parameter  
    $sm->get(ThemeSettingsService::class)  // 3rd parameter
);
```

### **Constructor Signature:**
```php
public function __construct(
    ApiManager $api,                    // 1st parameter
    ErrorHandler $errorHandler,         // 2nd parameter
    ThemeSettingsService $themeSettingsService // 3rd parameter
)
```

## Benefits

### **1. Direct Service Access**
- **Before**: Controller delegated everything to ModuleConfigService
- **After**: Controller has direct access to required services

### **2. Improved Error Handling**
- **Before**: Generic exception handling
- **After**: Comprehensive error handling with ErrorHandler service

### **3. Cleaner Architecture**
- **Before**: Single service dependency with delegation
- **After**: Multiple focused service dependencies

### **4. Better Separation of Concerns**
- **Before**: ModuleConfigService handled both module config and admin interface
- **After**: Controller directly uses appropriate services for its needs

## Form Action Handling

### **Supported Actions:**
1. **apply_preset** - Apply preset values to theme settings
2. **save_defaults** - Save current settings as preset defaults
3. **load_defaults** - Load stored defaults back into settings

### **Error Handling:**
- All actions wrapped in try-catch blocks
- ErrorHandler service provides consistent error messages
- User-friendly error reporting with error IDs

## Files Modified

- ✅ `external/LibraryThemeStyles/src/Controller/AdminController.php` - Updated constructor and logic
- ✅ `external/LibraryThemeStyles/config/module.config.php` - Updated factory and added ErrorHandler service

## Verification

- ✅ **PHP Syntax**: Both files pass `php -l` validation
- ✅ **Dependency Injection**: Constructor matches factory parameters
- ✅ **Service Registration**: All required services properly registered
- ✅ **Error Handling**: Comprehensive exception handling implemented

**Date:** 2025-10-08  
**Status:** COMPLETE ✅  
**Issue Type:** Dependency Injection / Architecture  
**Severity:** Medium (Constructor Mismatch)
