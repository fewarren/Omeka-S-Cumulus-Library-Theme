# LoadStoredDefaults Logic Fix

## 🚨 **Issue Identified**

`loadStoredDefaults` method in `src/Service/ThemeSettingsService.php` ignores decoded defaults and applies wrong values.

**Problem:** The method decodes stored defaults from JSON but then re-applies the original preset instead of using the actual stored defaults.

## 🔍 **Root Cause Analysis**

### **Problematic Logic Flow:**

```php
public function loadStoredDefaults(?string $siteSlug, string $preset): array
{
    $defaultsKey = ModuleConfig::getDefaultsKey($preset);
    $storedJson = $this->settings->get($defaultsKey);
    
    // 1. ✅ Correctly retrieves stored JSON
    if (!$storedJson) {
        throw new \RuntimeException("No stored defaults found for preset: {$preset}");
    }

    // 2. ✅ Correctly decodes stored defaults
    $storedDefaults = json_decode($storedJson, true);
    if (!is_array($storedDefaults)) {
        throw new \RuntimeException("Invalid stored defaults format for preset: {$preset}");
    }

    // 3. ❌ IGNORES $storedDefaults and re-applies original preset!
    return $this->applyPresetToThemeSettings($siteSlug, ModuleConfig::DEFAULT_THEME_KEY, $preset);
    //     ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ This applies PRESET values, not STORED values!
}
```

### **Impact:**
- **Wrong Values Applied**: Original preset values instead of customized stored defaults
- **Data Loss**: User's customized settings (stored as defaults) are ignored
- **Misleading Behavior**: Method name suggests loading stored defaults but actually loads preset
- **Functional Regression**: Defeats the purpose of storing/loading custom defaults

## 🔧 **Fix Applied**

### **Before:**
```php
// Apply the stored defaults as if they were a preset
return $this->applyPresetToThemeSettings($siteSlug, ModuleConfig::DEFAULT_THEME_KEY, $preset);
```

### **After:**
```php
// Apply the stored defaults directly to the target context
$site = $this->resolveSite($siteSlug);
$siteSettings = $this->getSiteSettingsInstance($site);
$themeSlug = $this->getThemeSlug($site);
$key = ModuleConfig::getThemeSettingsKey($themeSlug);
$current = $siteSettings->get($key, []);
$current = is_array($current) ? $current : [];
$merged = array_merge($current, $storedDefaults);
$siteSettings->set($key, $merged);
$this->errorHandler->logSuccess('Loaded stored defaults into theme settings', [
    'preset' => $preset,
    'site_slug' => $siteSlug,
    'settings_count' => count($storedDefaults),
]);
return [count($storedDefaults), $merged];
```

## 🎯 **Fix Benefits**

### **1. ✅ Correct Data Usage**
- **Before:** Ignores `$storedDefaults` variable completely
- **After:** Actually uses the decoded `$storedDefaults` data

### **2. ✅ Proper Functionality**
- **Before:** Re-applies original preset (wrong behavior)
- **After:** Applies stored custom defaults (correct behavior)

### **3. ✅ Data Preservation**
- **Before:** Overwrites current settings with preset values
- **After:** Merges stored defaults with current settings

### **4. ✅ Consistent Architecture**
- **Before:** Delegates to `applyPresetToThemeSettings` (wrong method)
- **After:** Directly manipulates settings (appropriate for stored defaults)

### **5. ✅ Enhanced Logging**
- **Before:** No specific logging for this operation
- **After:** Logs success with context (preset, site, count)

## 🧪 **Testing Scenarios**

### **Scenario 1: Basic Load Operation**
```php
// 1. Store some custom defaults
$service->storeCurrentAsDefaults('site1', 'custom');

// 2. Load stored defaults
$result = $service->loadStoredDefaults('site1', 'custom');

// ✅ Should apply the STORED values, not original preset values
```

### **Scenario 2: Merge Behavior**
```php
// Current settings: ['color' => 'blue', 'size' => 'large']
// Stored defaults: ['color' => 'red', 'font' => 'arial']
// Expected result: ['color' => 'red', 'size' => 'large', 'font' => 'arial']
```

### **Scenario 3: Error Handling**
```php
// Should still throw appropriate exceptions for:
// - Missing stored defaults
// - Invalid JSON format
// - Site resolution failures
```

## 📋 **Implementation Details**

### **Key Changes:**
1. **Direct Settings Manipulation**: Instead of delegating to preset application
2. **Merge Strategy**: `array_merge($current, $storedDefaults)` preserves existing + adds stored
3. **Proper Site Resolution**: Uses same pattern as other methods
4. **Enhanced Logging**: Records operation success with context
5. **Return Format**: `[count, merged_settings]` consistent with other methods

### **Method Signature Unchanged:**
```php
public function loadStoredDefaults(?string $siteSlug, string $preset): array
```

### **Dependencies Used:**
- `$this->resolveSite()` - Site resolution
- `$this->getSiteSettingsInstance()` - Settings instance
- `$this->getThemeSlug()` - Theme identification
- `ModuleConfig::getThemeSettingsKey()` - Settings key generation
- `$this->errorHandler->logSuccess()` - Success logging

## 📋 **Status**

**✅ COMPLETE** - The `loadStoredDefaults` method now correctly applies the actual stored defaults instead of re-applying the original preset values. The decoded `$storedDefaults` variable is properly used, and the method behavior matches its intended purpose.
