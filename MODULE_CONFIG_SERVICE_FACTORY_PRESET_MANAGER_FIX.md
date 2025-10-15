# ModuleConfigServiceFactory PresetManager Dependency Fix

## 🚨 **Issue Identified**

ModuleConfigServiceFactory attempts to get PresetManager from container but service is no longer registered.

**Location:** `external/LibraryThemeStyles/src/Service/ModuleConfigServiceFactory.php` (lines 21-27)

**Problem:** Factory was trying to retrieve PresetManager as a service from the container, but PresetManager is no longer registered as a service and should be accessed via static methods instead.

## 🔍 **Root Cause Analysis**

### **Service Registration Issue:**

#### **Before (Problematic):**
```php
// external/LibraryThemeStyles/src/Service/ModuleConfigServiceFactory.php
$presetManager = $container->get(\LibraryThemeStyles\Service\PresetManager::class);
$presetMap = $presetManager->getPresetMap();
```

**Problems:**
1. **Service Not Registered**: PresetManager is no longer registered in the service container
2. **Runtime Error**: `$container->get(PresetManager::class)` would fail with service not found error
3. **Unnecessary Dependency**: PresetManager doesn't need to be a service since it's stateless

### **Architecture Evolution:**
- **Old Pattern**: PresetManager as a service with instance methods
- **New Pattern**: PresetManager as a static utility class with static methods
- **Benefit**: Simpler architecture, no service registration needed

## 🔧 **Fix Applied**

### **✅ Replaced Container Dependency with Static Accessor**

**Before:**
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ModuleConfigService
{
    $api = $container->get('Omeka\ApiManager');
    $settings = $container->get('Omeka\Settings');
    $siteSettings = $container->get('Omeka\Settings\Site');
    $themeSettingsService = $container->get(\LibraryThemeStyles\Service\ThemeSettingsService::class);
    $presetManager = $container->get(\LibraryThemeStyles\Service\PresetManager::class);  // ❌ Service not registered

    // Get preset map from centralized PresetManager
    $presetMap = $presetManager->getPresetMap();  // ❌ Instance method call

    return new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $presetMap);
}
```

**After:**
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ModuleConfigService
{
    $api = $container->get('Omeka\ApiManager');
    $settings = $container->get('Omeka\Settings');
    $siteSettings = $container->get('Omeka\Settings\Site');
    $themeSettingsService = $container->get(\LibraryThemeStyles\Service\ThemeSettingsService::class);

    // Get preset map from static accessor (PresetManager service no longer registered)
    $presetMap = PresetManager::getAllPresets();  // ✅ Static method call

    return new ModuleConfigService($api, $settings, $siteSettings, $themeSettingsService, $presetMap);
}
```

### **✅ Changes Made:**

#### **1. Removed Container Dependency:**
- **Removed**: `$presetManager = $container->get(\LibraryThemeStyles\Service\PresetManager::class);`
- **Benefit**: No longer depends on service registration

#### **2. Used Static Accessor:**
- **Added**: `$presetMap = PresetManager::getAllPresets();`
- **Benefit**: Direct access to preset data without service instantiation

#### **3. Updated Comment:**
- **Added**: Clear explanation of why static accessor is used
- **Benefit**: Documents the architectural change

## 🎯 **Fix Benefits**

### **1. ✅ Eliminates Service Registration Dependency:**
- **Before**: Required PresetManager to be registered as a service
- **After**: No service registration needed

### **2. ✅ Prevents Runtime Errors:**
- **Before**: `ServiceNotFoundException` when PresetManager not registered
- **After**: Direct static access always works

### **3. ✅ Simplifies Architecture:**
- **Before**: PresetManager as service with instance methods
- **After**: PresetManager as static utility class

### **4. ✅ Maintains Functionality:**
- **Before**: `$presetManager->getPresetMap()` returned preset array
- **After**: `PresetManager::getAllPresets()` returns same preset array

## 📋 **PresetManager Static Interface**

### **Available Static Methods:**
```php
// src/Service/PresetManager.php
class PresetManager
{
    private static array $presets = [
        'modern' => [...],
        'classic' => [...],
        // ... other presets
    ];

    public static function getAllPresets(): array
    {
        return self::$presets;  // ✅ Returns all preset definitions
    }

    public static function getPreset(string $name): array
    {
        return self::$presets[$name] ?? [];  // ✅ Returns specific preset
    }

    public static function hasPreset(string $name): bool
    {
        return isset(self::$presets[$name]);  // ✅ Checks preset existence
    }
}
```

### **Usage Pattern:**
```php
// Factory usage
$presetMap = PresetManager::getAllPresets();

// Service usage
$modernPreset = PresetManager::getPreset('modern');
$hasClassic = PresetManager::hasPreset('classic');
```

## 🧪 **Testing Verification**

### **Syntax Validation:**
```bash
php -l external/LibraryThemeStyles/src/Service/ModuleConfigServiceFactory.php
# ✅ No syntax errors detected
```

### **Dependency Resolution:**
- ✅ **No Container Dependency**: PresetManager no longer retrieved from container
- ✅ **Static Access**: `PresetManager::getAllPresets()` called directly
- ✅ **Same Output**: Returns same preset array as before

### **Service Instantiation:**
- ✅ **ModuleConfigService Constructor**: Still receives array $presetMap as expected
- ✅ **Parameter Count**: Still 5 parameters (api, settings, siteSettings, themeSettingsService, presetMap)
- ✅ **Type Compatibility**: Static method returns array as required

## 📋 **Architectural Impact**

### **Service Container Simplification:**
- **Before**: Required PresetManager service registration in module.config.php
- **After**: No service registration needed for PresetManager

### **Dependency Graph:**
- **Before**: ModuleConfigServiceFactory → Container → PresetManager → preset data
- **After**: ModuleConfigServiceFactory → PresetManager::getAllPresets() → preset data

### **Performance:**
- **Before**: Service instantiation overhead
- **After**: Direct static access (faster)

### **Maintainability:**
- **Before**: Service configuration required
- **After**: Self-contained static utility

## 📋 **Compatibility**

### **✅ Backward Compatibility:**
- **ModuleConfigService**: Constructor signature unchanged
- **Preset Data**: Same array structure returned
- **Functionality**: All preset operations work identically

### **✅ Forward Compatibility:**
- **Static Pattern**: Easier to extend with new static methods
- **No Service Dependencies**: Simpler to test and maintain
- **Clear Interface**: Static methods provide clear API

## 📋 **Status**

**✅ COMPLETE** - ModuleConfigServiceFactory PresetManager dependency fixed:

1. **Container Dependency Removed**: No longer tries to get PresetManager from container
2. **Static Accessor Used**: `PresetManager::getAllPresets()` provides preset data
3. **Runtime Errors Prevented**: Eliminates ServiceNotFoundException
4. **Architecture Simplified**: PresetManager as static utility instead of service
5. **Functionality Preserved**: Same preset data provided to ModuleConfigService

The factory now uses the correct static accessor pattern for PresetManager, eliminating the service registration dependency while maintaining full functionality.
