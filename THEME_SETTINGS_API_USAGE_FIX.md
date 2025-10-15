# Theme Settings Service API Usage Fix

**Date:** 2025-10-14  
**Status:** ✅ FIXED AND DEPLOYED

## Issue

**Location:** `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php` line 368

**Problem:** Incorrect API method usage for site resolution by slug

### Code Review Feedback

> Site resolution by slug uses wrong API; use searchOne()
> 
> ApiManager::read() expects an ID, not criteria. Searching by slug should use searchOne().

## Root Cause

The `resolveSite()` method was using `ApiManager::read()` with search criteria:

```php
return $this->api->read('sites', ['slug' => $siteSlug])->getContent();
```

**Problem:**
- `read()` expects a resource ID (integer), not search criteria (array)
- Passing `['slug' => $siteSlug]` is incorrect usage
- This may work in some cases but is not the proper API pattern

## Solution

Changed to use `ApiManager::searchOne()` which is designed for searching by criteria:

### Before (Incorrect)

```php
private function resolveSite(?string $siteSlug)
{
    if (!$siteSlug) {
        return null;
    }

    try {
        return $this->api->read('sites', ['slug' => $siteSlug])->getContent();
    } catch (\Throwable $e) {
        throw new \RuntimeException('Site not found: ' . $siteSlug);
    }
}
```

### After (Correct)

```php
private function resolveSite(?string $siteSlug)
{
    if (!$siteSlug) {
        return null;
    }

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
}
```

## Key Changes

1. **Use `searchOne()` instead of `read()`**
   - `searchOne()` is designed for searching by criteria
   - Returns first matching result or null

2. **Proper null handling**
   - Check if `$response` is null (no results found)
   - Extract content only if response exists

3. **Improved error handling**
   - Catch exceptions and fall through to error message
   - Throw `RuntimeException` if site not found (either no results or exception)

## API Method Comparison

### `read($resource, $id)`
- **Purpose:** Fetch a single resource by ID
- **Parameters:** 
  - `$resource` (string): Resource type (e.g., 'sites')
  - `$id` (int): Resource ID
- **Returns:** Response object with single resource
- **Example:** `$api->read('sites', 123)`

### `searchOne($resource, $criteria)`
- **Purpose:** Search for resources and return first match
- **Parameters:**
  - `$resource` (string): Resource type (e.g., 'sites')
  - `$criteria` (array): Search criteria (e.g., `['slug' => 'my-site']`)
- **Returns:** Response object with first matching resource, or null if no matches
- **Example:** `$api->searchOne('sites', ['slug' => 'my-site'])`

### `search($resource, $criteria)`
- **Purpose:** Search for resources and return all matches
- **Parameters:**
  - `$resource` (string): Resource type
  - `$criteria` (array): Search criteria
- **Returns:** Response object with array of matching resources
- **Example:** `$api->search('sites', ['slug' => 'my-site'])`

## Impact

**Scope:** Low - Internal method used for site resolution

**Risk:** Minimal - The fix corrects API usage to follow Omeka S conventions

**Testing:** 
- Site resolution by slug should work correctly
- Error handling improved for missing sites
- No functional changes to external behavior

## Files Modified

- `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php` (lines 361-377)

## Deployment

✅ Deployed to `/var/www/omeka-s/modules/LibraryThemeStyles`  
✅ Ownership set to `www-data:www-data`  
✅ Apache restarted

## Related Documentation

- Omeka S API Manager documentation
- LibraryThemeStyles module architecture
- Theme settings scope management

## Verification

To verify the fix works correctly:

1. **Test site resolution by slug:**
   ```php
   $service = $serviceLocator->get('LibraryThemeStyles\ThemeSettingsService');
   // Internal method, but used by public methods that accept site slug
   ```

2. **Test error handling:**
   - Attempt to resolve non-existent site slug
   - Should throw `RuntimeException` with message "Site not found: {slug}"

3. **Test normal operation:**
   - Load/save theme settings with valid site slug
   - Should work without errors

## Best Practices Applied

1. ✅ Use correct API method for the operation
2. ✅ Handle null responses properly
3. ✅ Maintain consistent error handling
4. ✅ Follow Omeka S API conventions
5. ✅ Preserve existing behavior while fixing implementation

## Conclusion

The API usage has been corrected to use `searchOne()` for searching by criteria instead of incorrectly using `read()`. This follows Omeka S API conventions and improves code correctness.

