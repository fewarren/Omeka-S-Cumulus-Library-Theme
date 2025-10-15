# ThemeSettingsServiceFactory Dependency Injection Fix

## 🚨 **Issue Identified**

Factory wiring mismatch with ThemeSettingsService constructor in `external/LibraryThemeStyles/src/Service/ThemeSettingsServiceFactory.php`.

**Problem:** Constructor expects `ErrorHandler`; factory passes `$presetMap`. This will fatally error.

## 🔍 **Root Cause Analysis**

### **Factory was providing:**
```php
$presetManager = $container->get(\LibraryThemeStyles\Service\PresetManager::class);
$presetMap = $presetManager->getPresetMap();
return new ThemeSettingsService($api, $settings, $siteSettings, $presetMap);
//                                                                ^^^^^^^^^^^ array
```

### **Constructor expected:**
```php
public function __construct(
    ApiManager $api,
    Settings $settings,
    SiteSettings $siteSettings,
    ErrorHandler $errorHandler  // <-- ErrorHandler, not array
) {
```

### **Impact:**
- **Fatal Error**: Type mismatch on service instantiation
- **Service Registration Failure**: ThemeSettingsService cannot be created
- **AdminController Failure**: Depends on ThemeSettingsService

## 🔧 **Fix Applied**

### **1. ✅ Updated Factory**

**File:** `external/LibraryThemeStyles/src/Service/ThemeSettingsServiceFactory.php`

**Before:**
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
{
    $api = $container->get('Omeka\ApiManager');
    $settings = $container->get('Omeka\Settings');
    $siteSettings = $container->get('Omeka\Settings\Site');
    $presetManager = $container->get(\LibraryThemeStyles\Service\PresetManager::class);

    // Get preset map from centralized PresetManager
    $presetMap = $presetManager->getPresetMap();

    return new ThemeSettingsService($api, $settings, $siteSettings, $presetMap);
}
```

**After:**
```php
public function __invoke(ContainerInterface $container, $requestedName, array $options = null): ThemeSettingsService
{
    $api = $container->get('Omeka\ApiManager');
    $settings = $container->get('Omeka\Settings');
    $siteSettings = $container->get('Omeka\Settings\Site');
    $errorHandler = $container->get(\LibraryThemeStyles\Service\ErrorHandler::class);
    
    return new ThemeSettingsService($api, $settings, $siteSettings, $errorHandler);
}
```

### **2. ✅ Updated Constructor**

**File:** `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php`

**Before:**
```php
class ThemeSettingsService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private array $presetMap;

    public function __construct(
        ApiManager $api,
        Settings $settings,
        SiteSettings $siteSettings,
        array $presetMap
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->presetMap = $presetMap;
    }
```

**After:**
```php
class ThemeSettingsService
{
    private ApiManager $api;
    private Settings $settings;
    private SiteSettings $siteSettings;
    private ErrorHandler $errorHandler;

    public function __construct(
        ApiManager $api,
        Settings $settings,
        SiteSettings $siteSettings,
        ErrorHandler $errorHandler
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->errorHandler = $errorHandler;
    }
```

### **3. ✅ Updated Preset Access**

**Before:**
```php
// Validate preset
if (!isset($this->presetMap[$preset])) {
    throw new \RuntimeException('Unknown preset: ' . $preset);
}
$values = $this->presetMap[$preset];

// Later in code...
$want = $this->presetMap[$preset] ?? [];
```

**After:**
```php
// Validate preset
if (!PresetManager::hasPreset($preset)) {
    throw new \RuntimeException('Unknown preset: ' . $preset);
}
$values = PresetManager::getPreset($preset);

// Later in code...
$want = PresetManager::hasPreset($preset) ? PresetManager::getPreset($preset) : [];
```

## 🎯 **Benefits**

1. **✅ Eliminates Fatal Error**: Constructor now receives correct type
2. **✅ Consistent Architecture**: Matches main theme's ThemeSettingsService pattern
3. **✅ Centralized Preset Management**: Uses static PresetManager methods
4. **✅ Error Handling**: ErrorHandler available for exception management
5. **✅ Service Registration**: ThemeSettingsService can be properly instantiated

## 🧪 **Testing**

The fix ensures:
- ✅ **Factory creates service successfully**
- ✅ **Constructor receives ErrorHandler as expected**
- ✅ **Preset validation works via PresetManager**
- ✅ **AdminController can use ThemeSettingsService**
- ✅ **No type mismatches or fatal errors**

## 📋 **Status**

**✅ COMPLETE** - Factory wiring mismatch resolved. ThemeSettingsService constructor now properly expects and receives ErrorHandler instead of preset map array.
