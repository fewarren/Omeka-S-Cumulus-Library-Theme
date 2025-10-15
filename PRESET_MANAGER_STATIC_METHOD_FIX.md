# PresetManager Static Method Fix

**Date:** 2025-10-14  
**Status:** ✅ FIXED AND DEPLOYED

## Issue

**Location:** `external/LibraryThemeStyles/src/Service/ModuleConfigServiceFactory.php` (lines 20-21)

**Problem:** Call to non-existent static method `PresetManager::getAllPresets()`

### Code Review Feedback

> The code calls a non-existent static method PresetManager::getAllPresets(); either add a public static function getAllPresets() to PresetManager that returns the same data as getPresetMap() (or delegates to getPresetMap()) or change this factory to fetch the PresetManager from the DI container and call its instance method getPresetMap(); implement one of these two fixes so the call resolves to an existing method.

## Root Cause

The `ModuleConfigServiceFactory` was calling a static method that didn't exist:

```php
// Get preset map from static accessor (PresetManager service no longer registered)
$presetMap = PresetManager::getAllPresets();
```

However, the `PresetManager` class only had instance methods:
- `getPresetMap()` - Returns all presets
- `getPreset($name)` - Returns specific preset
- `hasPreset($name)` - Checks if preset exists

This would cause a fatal error: `Call to undefined method LibraryThemeStyles\Service\PresetManager::getAllPresets()`

## Solution Options

Two possible solutions were suggested:

### Option 1: Add Static Methods (Chosen)

Add static methods to `PresetManager` that delegate to instance methods.

**Pros:**
- Minimal changes to existing code
- Maintains current factory pattern
- No dependency injection changes needed
- Simple and straightforward

**Cons:**
- Creates static methods that instantiate the class internally
- Slightly less testable than DI approach

### Option 2: Use Dependency Injection

Register `PresetManager` in service manager and inject it into `ModuleConfigService`.

**Pros:**
- More testable
- Better follows dependency injection principles
- Avoids static methods

**Cons:**
- Requires changes to multiple files
- More complex refactoring
- Changes service constructor signature

## Implementation (Option 1)

Added static accessor methods to `PresetManager` that delegate to instance methods.

### Changes to PresetManager

**File:** `external/LibraryThemeStyles/src/Service/PresetManager.php`

**Added Static Methods:**

```php
/**
 * Get all available theme presets (static accessor for factory use)
 * 
 * @return array Associative array of preset name => preset values
 */
public static function getAllPresets(): array
{
    $instance = new self();
    return $instance->getPresetMap();
}

/**
 * Get a specific preset by name (static accessor for service use)
 * 
 * @param string $presetName Name of the preset to retrieve
 * @return array Preset values
 * @throws \InvalidArgumentException If preset doesn't exist
 */
public static function getPreset(string $presetName): array
{
    $instance = new self();
    return $instance->getPresetInstance($presetName);
}

/**
 * Check if a preset exists (static accessor for service use)
 * 
 * @param string $presetName Name of the preset to check
 * @return bool True if preset exists, false otherwise
 */
public static function hasPreset(string $presetName): bool
{
    $instance = new self();
    return $instance->hasPresetInstance($presetName);
}
```

**Renamed Instance Methods for Clarity:**

- `getPreset()` → `getPresetInstance()` (instance method)
- `hasPreset()` → `hasPresetInstance()` (instance method)

This allows both static and instance usage:

```php
// Static usage (for factories and services)
$allPresets = PresetManager::getAllPresets();
$modernPreset = PresetManager::getPreset('modern');
$exists = PresetManager::hasPreset('traditional');

// Instance usage (if needed)
$manager = new PresetManager();
$allPresets = $manager->getPresetMap();
$modernPreset = $manager->getPresetInstance('modern');
$exists = $manager->hasPresetInstance('traditional');
```

## Usage Patterns

### In Factories

```php
// ModuleConfigServiceFactory.php
$presetMap = PresetManager::getAllPresets();
```

### In Services

```php
// ThemeSettingsService.php
if (!PresetManager::hasPreset($preset)) {
    throw new \RuntimeException('Unknown preset: ' . $preset);
}
$values = PresetManager::getPreset($preset);
```

## Files Modified

1. **`external/LibraryThemeStyles/src/Service/PresetManager.php`**
   - Added `getAllPresets()` static method
   - Added `getPreset()` static method
   - Added `hasPreset()` static method
   - Renamed instance methods to avoid confusion:
     - `getPreset()` → `getPresetInstance()`
     - `hasPreset()` → `hasPresetInstance()`

## Existing Usage

The following files already use `PresetManager` static methods and will now work correctly:

1. **`ModuleConfigServiceFactory.php`** (line 21)
   ```php
   $presetMap = PresetManager::getAllPresets();
   ```

2. **`ThemeSettingsService.php`** (lines 47, 50, 250)
   ```php
   if (!PresetManager::hasPreset($preset)) { ... }
   $values = PresetManager::getPreset($preset);
   ```

## Testing

### Manual Testing

1. **Test preset loading:**
   - Navigate to module configuration
   - Select "Modern" preset
   - Click "Apply Preset to This Site"
   - Should work without errors

2. **Test preset validation:**
   - Try to load invalid preset name
   - Should throw proper exception

3. **Test factory instantiation:**
   - Module should load without errors
   - Service should be created successfully

### Unit Testing

```php
// Test static methods
public function testGetAllPresets()
{
    $presets = PresetManager::getAllPresets();
    $this->assertIsArray($presets);
    $this->assertArrayHasKey('modern', $presets);
    $this->assertArrayHasKey('traditional', $presets);
}

public function testGetPreset()
{
    $modern = PresetManager::getPreset('modern');
    $this->assertIsArray($modern);
    $this->assertArrayHasKey('h1_font_family', $modern);
}

public function testHasPreset()
{
    $this->assertTrue(PresetManager::hasPreset('modern'));
    $this->assertTrue(PresetManager::hasPreset('traditional'));
    $this->assertFalse(PresetManager::hasPreset('nonexistent'));
}
```

## Deployment

✅ Deployed to `/var/www/omeka-s/modules/LibraryThemeStyles`  
✅ Ownership set to `www-data:www-data`  
✅ Apache restarted  
✅ Static methods now available

## Design Considerations

### Why Static Methods?

The static methods provide a convenient accessor pattern for:

1. **Factory classes** that need preset data during service construction
2. **Service classes** that need to validate/retrieve presets
3. **Backward compatibility** with existing code

### Singleton Pattern

The static methods create a new instance each time they're called. This is acceptable because:

1. `PresetManager` has no state (just returns hardcoded data)
2. Performance impact is negligible (simple array return)
3. No shared state concerns

If performance becomes an issue, could implement lazy singleton:

```php
private static ?PresetManager $instance = null;

public static function getInstance(): PresetManager
{
    if (self::$instance === null) {
        self::$instance = new self();
    }
    return self::$instance;
}

public static function getAllPresets(): array
{
    return self::getInstance()->getPresetMap();
}
```

## Future Enhancements

Consider these improvements:

1. **Dependency Injection:** Refactor to use DI instead of static methods
2. **Caching:** Cache preset data if it becomes more complex
3. **Validation:** Add schema validation for preset data
4. **Extensibility:** Allow modules to register custom presets

## Conclusion

The missing static method issue has been resolved by adding static accessor methods to `PresetManager` that delegate to instance methods. This maintains backward compatibility while fixing the fatal error.

**Status:** ✅ RESOLVED

