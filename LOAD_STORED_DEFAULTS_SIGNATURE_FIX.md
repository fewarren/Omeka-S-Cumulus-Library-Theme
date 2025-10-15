# LoadStoredDefaults Method Signature Fix

## 🚨 **Issue Identified**

Missing `loadStoredDefaults` method with correct signature in ThemeSettingsService.

**Location:** `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php`

**Problem:** The issue mentioned a call to `loadStoredDefaults` with wrong parameters, but the method with the expected signature `loadStoredDefaults(?string $siteSlug, string $preset): array` was missing from the service.

## 🔍 **Root Cause Analysis**

### **Expected vs Actual:**

#### **Issue Description:**
> "the call to loadStoredDefaults currently passes ($siteSlug, $themeKey, $targetPreset) but the service signature is loadStoredDefaults(?string $siteSlug, string $preset): array"

#### **Investigation Results:**
1. **AdminController**: After recent refactoring, no longer contains direct service calls - delegates to ModuleConfigService
2. **ModuleConfigService**: Uses `loadStoredDefaultsIntoSettings` method correctly
3. **ThemeSettingsService**: Only had `loadStoredDefaultsIntoSettings` method, missing the expected `loadStoredDefaults` method

### **Missing Method:**
The service was missing a `loadStoredDefaults` method that:
- Takes `(?string $siteSlug, string $preset)` parameters
- Returns `array` with count and defaults data
- Provides access to stored defaults without applying them to settings

## 🔧 **Fix Applied**

### **✅ Added Missing Method**

**Added to ThemeSettingsService:**
```php
/**
 * Load stored defaults for a preset (without applying to settings)
 *
 * @param string|null $siteSlug Site slug or null for global settings
 * @param string $preset Preset name to load defaults from
 * @return array [count, stored_defaults] - Number of defaults and the defaults array
 */
public function loadStoredDefaults(?string $siteSlug, string $preset): array
{
    $defaults = $this->getStoredDefaults($preset);
    return [count($defaults), $defaults];
}
```

### **✅ Method Characteristics:**

**1. Correct Signature:**
- ✅ **Parameters**: `(?string $siteSlug, string $preset)`
- ✅ **Return Type**: `array`
- ✅ **Nullable Site Slug**: Supports both site-specific and global contexts

**2. Functionality:**
- ✅ **Retrieves Defaults**: Gets stored defaults for specified preset
- ✅ **Returns Count**: First element is count of defaults
- ✅ **Returns Data**: Second element is the defaults array
- ✅ **No Side Effects**: Does not modify settings, only retrieves data

**3. Consistency:**
- ✅ **Naming Convention**: Follows existing method naming patterns
- ✅ **Parameter Order**: Consistent with other service methods
- ✅ **Return Format**: Matches expected `[count, data]` pattern

## 🎯 **Method Comparison**

### **loadStoredDefaults vs loadStoredDefaultsIntoSettings:**

#### **loadStoredDefaults** (New):
```php
public function loadStoredDefaults(?string $siteSlug, string $preset): array
{
    $defaults = $this->getStoredDefaults($preset);
    return [count($defaults), $defaults];
}
```
- **Purpose**: Retrieve defaults without applying them
- **Side Effects**: None (read-only)
- **Return**: `[count, defaults_array]`
- **Use Case**: Inspection, validation, or conditional application

#### **loadStoredDefaultsIntoSettings** (Existing):
```php
public function loadStoredDefaultsIntoSettings(?string $siteSlug, string $preset): array
{
    // ... complex logic to apply defaults to settings ...
    $settingsInstance->set($key, $current);
    return [$count, sprintf('theme=%s key=%s now has %d keys', $themeSlug, $key, count($current))];
}
```
- **Purpose**: Apply defaults to theme settings
- **Side Effects**: Modifies settings in database
- **Return**: `[count, status_message]`
- **Use Case**: Actually applying preset defaults to active theme

## 🔧 **Usage Scenarios**

### **1. ✅ Read-Only Access:**
```php
// Get defaults without applying them
[$count, $defaults] = $service->loadStoredDefaults($siteSlug, 'modern');
echo "Found {$count} default settings";
foreach ($defaults as $key => $value) {
    echo "{$key}: {$value}";
}
```

### **2. ✅ Conditional Application:**
```php
// Check defaults before applying
[$count, $defaults] = $service->loadStoredDefaults($siteSlug, 'modern');
if ($count > 0) {
    // Apply defaults using the other method
    $service->loadStoredDefaultsIntoSettings($siteSlug, 'modern');
}
```

### **3. ✅ Validation/Comparison:**
```php
// Compare current settings with stored defaults
[$count, $defaults] = $service->loadStoredDefaults($siteSlug, 'modern');
$current = $service->getCurrentThemeSettings($themeSlug);
$differences = array_diff_assoc($current, $defaults);
```

## 📋 **Integration Points**

### **1. ModuleConfigService:**
- Can now use `loadStoredDefaults` for inspection operations
- Maintains `loadStoredDefaultsIntoSettings` for actual application
- Provides flexibility for different use cases

### **2. AdminController:**
- Delegates to ModuleConfigService (no direct usage)
- Benefits from improved service capabilities
- Maintains clean separation of concerns

### **3. Future Extensions:**
- API endpoints can expose defaults without modifying settings
- Debug/diagnostic tools can inspect defaults
- Validation logic can compare defaults with current state

## 🧪 **Testing Verification**

### **Syntax Validation:**
```bash
php -l external/LibraryThemeStyles/src/Service/ThemeSettingsService.php
# ✅ No syntax errors detected
```

### **Method Signature:**
- ✅ **Parameters**: `(?string $siteSlug, string $preset)`
- ✅ **Return Type**: `array`
- ✅ **Visibility**: `public`

### **Functionality:**
- ✅ **Retrieves Defaults**: Uses existing `getStoredDefaults` method
- ✅ **Returns Count**: First array element is count
- ✅ **Returns Data**: Second array element is defaults array
- ✅ **No Side Effects**: Read-only operation

## 📋 **Benefits**

### **1. ✅ API Completeness:**
- **Before**: Only had method that applies defaults to settings
- **After**: Has both read-only and write methods for defaults

### **2. ✅ Flexibility:**
- **Before**: Had to apply defaults to inspect them
- **After**: Can inspect defaults without side effects

### **3. ✅ Consistency:**
- **Before**: Missing expected method signature
- **After**: Provides expected interface for defaults access

### **4. ✅ Separation of Concerns:**
- **Before**: Single method mixed read and write operations
- **After**: Clear separation between read (`loadStoredDefaults`) and write (`loadStoredDefaultsIntoSettings`)

## 📋 **Status**

**✅ COMPLETE** - Added missing `loadStoredDefaults` method:

1. **Correct Signature**: `(?string $siteSlug, string $preset): array`
2. **Proper Functionality**: Retrieves defaults without applying them
3. **Consistent Return**: `[count, defaults_array]` format
4. **No Side Effects**: Read-only operation for inspection/validation
5. **API Completeness**: Complements existing `loadStoredDefaultsIntoSettings` method

The service now provides both read-only access to stored defaults and the ability to apply them to settings, offering complete flexibility for different use cases.
