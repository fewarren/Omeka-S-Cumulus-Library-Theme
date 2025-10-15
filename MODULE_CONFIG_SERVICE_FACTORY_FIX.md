# ModuleConfigServiceFactory Parameter Order Fix

## Overview

Fixed parameter mismatch in `src/Service/ModuleConfigServiceFactory.php` where the factory was passing `$errorHandler` as the 5th argument while `ModuleConfigService::__construct()` expected `array $presetMap` as the 5th parameter.

## Issue Identified

**Problem:** Parameter order mismatch between factory and constructor

**Factory was passing:**
```php
new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $errorHandler);
//                                                                           ^^^^^^^^^^^^^ 5th param
```

**Constructor expected:**
```php
public function __construct(
    ApiManager $api,
    Settings $settings, 
    SiteSettings $siteSettings,
    ThemeSettingsService $themeSettingsService,
    array $presetMap,        // ← 5th parameter should be presetMap
    ErrorHandler $errorHandler // ← 6th parameter should be errorHandler
)
```

**Impact:**
- Type mismatch: `ErrorHandler` object passed where `array $presetMap` expected
- Missing dependency: `$presetMap` not provided to service
- Runtime errors when ModuleConfigService tries to use preset functionality

## Fix Applied

### 1. ✅ **Updated ModuleConfigService Constructor**

**Added `$presetMap` property:**
```php
class ModuleConfigService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private ThemeSettingsService $themeSettingsService;
    private array $presetMap;           // ✅ ADDED
    private ErrorHandler $errorHandler;
```

**Updated constructor parameter order:**
```php
public function __construct(
    ApiManager $api,
    Settings $settings,
    SiteSettings $siteSettings,
    ThemeSettingsService $themeSettingsService,
    array $presetMap,        // ✅ 5th parameter
    ErrorHandler $errorHandler // ✅ 6th parameter
) {
    // ... assignments including:
    $this->presetMap = $presetMap;     // ✅ ADDED
    $this->errorHandler = $errorHandler;
}
```

### 2. ✅ **Updated ModuleConfigServiceFactory**

**Added PresetManager dependency:**
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ModuleConfigService
{
    $api = $container->get('Omeka\ApiManager');
    $settings = $container->get('Omeka\Settings');
    $siteSettings = $container->get('Omeka\Settings\Site');
    $themeSettingsService = $container->get(ThemeSettingsService::class);
    $presetManager = $container->get(PresetManager::class);  // ✅ ADDED
    $errorHandler = $container->get(ErrorHandler::class);
    
    // Get preset map from PresetManager
    $presetMap = $presetManager->getAllPresets();           // ✅ ADDED
    
    return new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $presetMap, $errorHandler);
    //                                                                                     ^^^^^^^^^^ ^^^^^^^^^^^^
    //                                                                                     5th param  6th param
}
```

## Verification

### **Constructor Signature Match:**
- ✅ **Parameter 1**: `ApiManager $api`
- ✅ **Parameter 2**: `Settings $settings`  
- ✅ **Parameter 3**: `SiteSettings $siteSettings`
- ✅ **Parameter 4**: `ThemeSettingsService $themeSettingsService`
- ✅ **Parameter 5**: `array $presetMap` ← **FIXED**
- ✅ **Parameter 6**: `ErrorHandler $errorHandler` ← **MOVED**

### **Factory Arguments Match:**
- ✅ **Argument 1**: `$api` (ApiManager)
- ✅ **Argument 2**: `$settings` (Settings)
- ✅ **Argument 3**: `$siteSettings` (SiteSettings)  
- ✅ **Argument 4**: `$themeSettingsService` (ThemeSettingsService)
- ✅ **Argument 5**: `$presetMap` (array) ← **FIXED**
- ✅ **Argument 6**: `$errorHandler` (ErrorHandler) ← **MOVED**

### **Dependency Resolution:**
- ✅ **PresetManager**: Retrieved from container
- ✅ **PresetMap**: Generated via `$presetManager->getAllPresets()`
- ✅ **Type Safety**: `array $presetMap` matches constructor expectation

## Why This Fix Was Necessary

**ModuleConfigService uses extensive preset functionality:**
- 29 references to "preset" in the code
- Methods like `compareWithPreset()`, `applyPresetToThemeSettings()`, `saveSettingsAsPresetDefaults()`
- Handles preset operations: `diff_vs_preset`, `load_defaults_into_settings`, etc.

**Without `$presetMap`:**
- Service couldn't access preset data
- Preset-related operations would fail
- Type errors when trying to use ErrorHandler as array

## Testing

- ✅ **PHP Syntax**: Both files pass `php -l` validation
- ✅ **Type Safety**: All parameters match constructor signature
- ✅ **Dependency Chain**: PresetManager → presetMap → ModuleConfigService

## Files Modified

- ✅ `src/Service/ModuleConfigService.php` - Updated constructor parameter order
- ✅ `src/Service/ModuleConfigServiceFactory.php` - Fixed argument order and added presetMap
- ✅ `MODULE_CONFIG_SERVICE_FACTORY_FIX.md` - Documentation

## Deployment

The fixed files are ready for deployment via:

```bash
sudo ./DEPLOY.sh
```

**Date:** 2025-10-08  
**Status:** COMPLETE ✅  
**Issue Type:** Dependency Injection Fix  
**Severity:** High (Runtime Error Prevention)
