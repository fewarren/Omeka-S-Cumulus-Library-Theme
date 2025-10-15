# PresetManager Duplication Fix

## Overview

Resolved service registration conflict where both main and external modules were registering the same `PresetManager` service, causing potential configuration conflicts and service resolution issues.

## Issue Identified

**Problem:** Duplicate service registrations

**Locations:**
- **Main Module**: `config/module.config.php` line 8
- **External Module**: `external/LibraryThemeStyles/config/module.config.php` line 14

**Conflict:**
```php
// Both modules registering the same service
PresetManager::class => Service\PresetManagerFactory::class
```

**Impact:**
- Service container conflicts
- Unpredictable service resolution
- Last-loaded module overwrites the first
- Potential runtime errors

## Analysis

### **Duplicate Files Found:**

#### **1. PresetManagerFactory (Identical)**
- `src/Service/PresetManagerFactory.php` (18 lines)
- `external/LibraryThemeStyles/src/Service/PresetManagerFactory.php` (18 lines)
- **Status**: Completely identical implementations

#### **2. PresetManager (Different Implementations)**
- **Main**: `src/Service/PresetManager.php` (255 lines)
  - Uses static array `$presets` 
  - Methods: `getAllPresets()`, `getPreset()`, `hasPreset()`
  - More comprehensive implementation
- **External**: `external/LibraryThemeStyles/src/Service/PresetManager.php` (104 lines)
  - Uses instance method `getPresetMap()`
  - Simpler inline array structure
  - Less functionality

#### **3. Service Registrations (Conflicting)**
```php
// Main module config
PresetManager::class => Service\PresetManagerFactory::class

// External module config (DUPLICATE)
PresetManager::class => Service\PresetManagerFactory::class
```

## Solution Applied

### **Strategy: Remove External Duplicates**

**Rationale:**
1. **Main implementation is more comprehensive** (255 vs 104 lines)
2. **Main has better architecture** (static methods, type hints)
3. **External appears to be outdated copy**
4. **Avoid breaking main module functionality**

### **Files Removed:**

#### **1. ✅ External PresetManagerFactory**
- **Removed**: `external/LibraryThemeStyles/src/Service/PresetManagerFactory.php`
- **Reason**: Identical to main factory, causing conflict

#### **2. ✅ External PresetManager**
- **Removed**: `external/LibraryThemeStyles/src/Service/PresetManager.php`
- **Reason**: Outdated implementation, main version is superior

### **Configuration Updated:**

#### **3. ✅ External Module Config**
- **File**: `external/LibraryThemeStyles/config/module.config.php`

**Before:**
```php
use LibraryThemeStyles\Service\ModuleConfigService;
use LibraryThemeStyles\Service\ThemeSettingsService;
use LibraryThemeStyles\Service\PresetManager;  // ❌ REMOVED

return [
    'service_manager' => [
        'factories' => [
            ModuleConfigService::class => Service\ModuleConfigServiceFactory::class,
            ThemeSettingsService::class => Service\ThemeSettingsServiceFactory::class,
            PresetManager::class => Service\PresetManagerFactory::class,  // ❌ REMOVED
        ],
    ],
```

**After:**
```php
use LibraryThemeStyles\Service\ModuleConfigService;
use LibraryThemeStyles\Service\ThemeSettingsService;
// PresetManager import removed

return [
    'service_manager' => [
        'factories' => [
            ModuleConfigService::class => Service\ModuleConfigServiceFactory::class,
            ThemeSettingsService::class => Service\ThemeSettingsServiceFactory::class,
            // PresetManager registration removed
        ],
    ],
```

## Verification

### **✅ Main Module Preserved:**
- **PresetManager**: `src/Service/PresetManager.php` (255 lines) - ✅ Intact
- **PresetManagerFactory**: `src/Service/PresetManagerFactory.php` (18 lines) - ✅ Intact
- **Service Registration**: `config/module.config.php` line 8 - ✅ Active

### **✅ External Module Cleaned:**
- **PresetManager**: ❌ Removed (was duplicate)
- **PresetManagerFactory**: ❌ Removed (was duplicate)
- **Service Registration**: ❌ Removed (was conflicting)
- **Import Statement**: ❌ Removed (no longer needed)

### **✅ Service Resolution:**
```php
// Now only one registration exists:
// config/module.config.php
PresetManager::class => \LibraryThemeStyles\Service\PresetManagerFactory::class
```

## Benefits

### **1. Eliminated Service Conflicts**
- **Before**: Two modules registering same service
- **After**: Single, authoritative service registration

### **2. Consistent Implementation**
- **Before**: Two different PresetManager implementations
- **After**: Single, comprehensive implementation (255 lines)

### **3. Cleaner Architecture**
- **Before**: Duplicate factories and services
- **After**: DRY principle followed, no duplication

### **4. Predictable Behavior**
- **Before**: Service resolution dependent on module load order
- **After**: Deterministic service resolution

## Technical Details

### **Main PresetManager Features (Preserved):**
```php
class PresetManager
{
    private static array $presets = [...];
    
    public static function getAllPresets(): array
    public static function getPreset(string $name): array
    public static function hasPreset(string $name): bool
    public static function getPresetNames(): array
}
```

### **Service Container Resolution:**
```php
// Container now resolves to single implementation
$presetManager = $container->get(PresetManager::class);
// Returns: src/Service/PresetManager instance
```

## Testing

- ✅ **PHP Syntax**: External module config validated
- ✅ **Service Registration**: Only main module registers PresetManager
- ✅ **No Conflicts**: Single service registration exists
- ✅ **Functionality**: Main PresetManager fully functional

## Files Modified

### **Removed:**
- ❌ `external/LibraryThemeStyles/src/Service/PresetManagerFactory.php`
- ❌ `external/LibraryThemeStyles/src/Service/PresetManager.php`

### **Updated:**
- ✅ `external/LibraryThemeStyles/config/module.config.php` - Removed duplicate registration

### **Preserved:**
- ✅ `src/Service/PresetManager.php` - Main implementation
- ✅ `src/Service/PresetManagerFactory.php` - Main factory
- ✅ `config/module.config.php` - Main service registration

## Deployment

The fixed configuration is ready for deployment via:

```bash
sudo ./DEPLOY.sh
```

**Date:** 2025-10-08  
**Status:** COMPLETE ✅  
**Issue Type:** Service Registration Conflict  
**Severity:** High (Configuration Conflict)
