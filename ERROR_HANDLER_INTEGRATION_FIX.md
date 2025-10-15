# ErrorHandler Integration Fix

**Date:** 2025-10-14  
**Status:** ✅ FIXED AND DEPLOYED

## Issue

**Location:** `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php`

**Problem:** ErrorHandler was declared and injected but never actually used

### Code Review Feedback

> In external/LibraryThemeStyles/src/Service/ThemeSettingsServiceFactory.php around lines 18 to 20, the ThemeSettingsService is constructed without passing the ErrorHandler; update the factory to call the new constructor signature by passing $errorHandler as the fourth argument: retrieve $errorHandler from the container (already fetched on the previous line) and include it in the ThemeSettingsService instantiation; additionally ensure ThemeSettingsService class constructor is updated to accept ErrorHandler and assign it to the private property and add appropriate validation/logging usages of $this->errorHandler within that class.

## Root Cause

The factory and constructor were already correct:
- Factory was fetching ErrorHandler from container
- Factory was passing it to constructor
- Constructor was accepting and assigning it

**However**, the ErrorHandler class didn't exist and the property was never actually used in the service methods.

## Solution

### 1. Created ErrorHandler Class

**File:** `external/LibraryThemeStyles/src/Service/ErrorHandler.php`

Implemented a comprehensive error handling service with:

```php
class ErrorHandler
{
    // Log error messages
    public function logError(string $message, array $context = []): void
    
    // Log warning messages
    public function logWarning(string $message, array $context = []): void
    
    // Log info messages
    public function logInfo(string $message, array $context = []): void
    
    // Log debug messages
    public function logDebug(string $message, array $context = []): void
    
    // Handle exceptions with context
    public function handleException(\Throwable $e, string $context = ''): void
    
    // Validate values and log errors
    public function validateAndLog($value, callable $validator, string $errorMessage): bool
}
```

**Features:**
- Structured logging with context data
- Multiple log levels (error, warning, info, debug)
- Exception handling with stack traces
- Validation with automatic error logging
- Consistent log format: `[LibraryThemeStyles] LEVEL: message | Context: {...}`

### 2. Integrated ErrorHandler into ThemeSettingsService

Added error handling and logging throughout the service:

#### A. Preset Validation

**Before:**
```php
if (!\LibraryThemeStyles\Service\PresetManager::hasPreset($preset)) {
    throw new \RuntimeException('Unknown preset: ' . $preset);
}
```

**After:**
```php
if (!\LibraryThemeStyles\Service\PresetManager::hasPreset($preset)) {
    $this->errorHandler->logError('Unknown preset requested', [
        'preset' => $preset,
        'siteSlug' => $siteSlug,
        'themeKey' => $themeKey,
    ]);
    throw new \RuntimeException('Unknown preset: ' . $preset);
}

$this->errorHandler->logInfo('Applying preset to theme settings', [
    'preset' => $preset,
    'siteSlug' => $siteSlug,
    'themeKey' => $themeKey,
]);
```

#### B. Site Resolution

**Before:**
```php
try {
    $response = $this->api->searchOne('sites', ['slug' => $siteSlug]);
    $site = $response ? $response->getContent() : null;
    if ($site) {
        return $site;
    }
} catch (\Throwable $e) {
    // fall through
}
throw new \RuntimeException('Site not found: ' . $siteSlug);
```

**After:**
```php
try {
    $response = $this->api->searchOne('sites', ['slug' => $siteSlug]);
    $site = $response ? $response->getContent() : null;
    if ($site) {
        $this->errorHandler->logDebug('Site resolved successfully', [
            'siteSlug' => $siteSlug,
            'siteId' => $site->id(),
        ]);
        return $site;
    }
} catch (\Throwable $e) {
    $this->errorHandler->handleException($e, 'Error resolving site');
}

$this->errorHandler->logError('Site not found', ['siteSlug' => $siteSlug]);
throw new \RuntimeException('Site not found: ' . $siteSlug);
```

#### C. Settings Save Operations

**Before:**
```php
if (!is_array($current) || empty($current)) {
    return [0, []];
}

$defaultsKey = 'LibraryThemeStyles_defaults_' . $preset;
$this->settings->set($defaultsKey, json_encode($current));

return [count($current), $current];
```

**After:**
```php
$this->errorHandler->logInfo('Saving current settings as defaults', [
    'siteSlug' => $siteSlug,
    'themeKey' => $themeKey,
    'preset' => $preset,
]);

if (!is_array($current) || empty($current)) {
    $this->errorHandler->logWarning('No settings found to save as defaults', [
        'siteSlug' => $siteSlug,
        'themeSlug' => $themeSlug,
    ]);
    return [0, []];
}

$defaultsKey = 'LibraryThemeStyles_defaults_' . $preset;
$this->settings->set($defaultsKey, json_encode($current));

$this->errorHandler->logInfo('Settings saved as defaults successfully', [
    'preset' => $preset,
    'count' => count($current),
]);

return [count($current), $current];
```

#### D. Theme Key Validation

**Before:**
```php
private function getThemeSlug($site = null, ?string $themeKey = null, $settingsInstance = null): string
{
    // First try to get theme from site
    if ($site && method_exists($site, 'theme') && $site->theme()) {
        return (string) $site->theme();
    }
    
    // If themeKey is provided and looks like a theme slug, use it
    if ($themeKey && $themeKey !== 'LibraryTheme') {
        return strtolower(str_replace(' ', '-', $themeKey));
    }
```

**After:**
```php
private function getThemeSlug($site = null, ?string $themeKey = null, $settingsInstance = null): string
{
    // Validate themeKey if provided
    if ($themeKey !== null && !$this->errorHandler->validateAndLog(
        $themeKey,
        fn($key) => is_string($key) && !empty($key),
        'Invalid theme key provided'
    )) {
        $themeKey = null; // Fall back to default
    }
    
    // First try to get theme from site
    if ($site && method_exists($site, 'theme') && $site->theme()) {
        $themeSlug = (string) $site->theme();
        $this->errorHandler->logDebug('Theme slug resolved from site', [
            'themeSlug' => $themeSlug,
        ]);
        return $themeSlug;
    }
    
    // If themeKey is provided and looks like a theme slug, use it
    if ($themeKey && $themeKey !== 'LibraryTheme') {
        $themeSlug = strtolower(str_replace(' ', '-', $themeKey));
        $this->errorHandler->logDebug('Theme slug resolved from themeKey', [
            'themeKey' => $themeKey,
            'themeSlug' => $themeSlug,
        ]);
        return $themeSlug;
    }
```

## Benefits

### 1. Improved Debugging

**Before:** Silent failures or generic error messages

**After:** Detailed logs with context:
```
[LibraryThemeStyles] INFO: Applying preset to theme settings | Context: {"preset":"modern","siteSlug":"library","themeKey":"LibraryTheme"}
[LibraryThemeStyles] DEBUG: Site resolved successfully | Context: {"siteSlug":"library","siteId":1}
[LibraryThemeStyles] DEBUG: Theme slug resolved from site | Context: {"themeSlug":"library-theme"}
[LibraryThemeStyles] INFO: Settings saved as defaults successfully | Context: {"preset":"modern","count":45}
```

### 2. Better Error Tracking

**Before:** Exceptions thrown without logging

**After:** Exceptions logged with full context before throwing:
```
[LibraryThemeStyles] ERROR: Unknown preset requested | Context: {"preset":"invalid","siteSlug":"library","themeKey":"LibraryTheme"}
[LibraryThemeStyles] ERROR: Site not found | Context: {"siteSlug":"nonexistent"}
```

### 3. Validation with Logging

**Before:** No validation of input parameters

**After:** Automatic validation with error logging:
```php
$this->errorHandler->validateAndLog(
    $themeKey,
    fn($key) => is_string($key) && !empty($key),
    'Invalid theme key provided'
);
```

### 4. Exception Context

**Before:** Exception messages only

**After:** Full exception details logged:
```
[LibraryThemeStyles] ERROR: Error resolving site: Connection timeout | Context: {
    "exception":"Doctrine\\DBAL\\Exception\\ConnectionException",
    "file":"/var/www/omeka-s/modules/LibraryThemeStyles/src/Service/ThemeSettingsService.php",
    "line":395,
    "trace":"..."
}
```

## Files Modified

1. **`external/LibraryThemeStyles/src/Service/ErrorHandler.php`** (NEW)
   - Created comprehensive error handling service
   - Multiple log levels (error, warning, info, debug)
   - Exception handling with context
   - Validation with automatic logging

2. **`external/LibraryThemeStyles/src/Service/ThemeSettingsService.php`**
   - Added error logging to `applyPresetToThemeSettings()`
   - Added exception handling to `resolveSite()`
   - Added logging to `saveCurrentSettingsAsDefaults()`
   - Added validation to `getThemeSlug()`

3. **`external/LibraryThemeStyles/src/Service/ThemeSettingsServiceFactory.php`**
   - Already correct (no changes needed)

## Log Output Examples

### Successful Operation
```
[LibraryThemeStyles] INFO: Applying preset to theme settings | Context: {"preset":"modern","siteSlug":"library","themeKey":"LibraryTheme"}
[LibraryThemeStyles] DEBUG: Site resolved successfully | Context: {"siteSlug":"library","siteId":1}
[LibraryThemeStyles] DEBUG: Theme slug resolved from site | Context: {"themeSlug":"library-theme"}
```

### Error Scenario
```
[LibraryThemeStyles] ERROR: Unknown preset requested | Context: {"preset":"invalid","siteSlug":"library","themeKey":"LibraryTheme"}
```

### Warning Scenario
```
[LibraryThemeStyles] WARNING: No settings found to save as defaults | Context: {"siteSlug":"library","themeSlug":"library-theme"}
```

## Deployment

✅ Created ErrorHandler class  
✅ Integrated ErrorHandler into ThemeSettingsService  
✅ Deployed to `/var/www/omeka-s/modules/LibraryThemeStyles`  
✅ Ownership set to `www-data:www-data`  
✅ Apache restarted

## Testing

### Manual Testing

1. **Test successful preset application:**
   - Apply Modern preset to a site
   - Check logs for INFO messages

2. **Test error handling:**
   - Try to apply invalid preset
   - Check logs for ERROR messages with context

3. **Test site resolution:**
   - Use valid site slug
   - Check logs for DEBUG messages
   - Use invalid site slug
   - Check logs for ERROR messages

### Log Monitoring

Monitor logs with:
```bash
tail -f /var/log/apache2/error.log | grep LibraryThemeStyles
```

## Conclusion

The ErrorHandler has been successfully created and integrated into ThemeSettingsService. All critical operations now include proper error logging, validation, and exception handling with detailed context information.

**Status:** ✅ COMPLETE

