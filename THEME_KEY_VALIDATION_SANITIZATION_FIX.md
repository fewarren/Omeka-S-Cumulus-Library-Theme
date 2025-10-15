# Theme Key Validation and Sanitization Fix

**Date:** 2025-10-14  
**Status:** ✅ FIXED AND DEPLOYED

## Issue

**Location:** `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php` (lines 422-448)

**Problem:** Theme key validation and slug generation were too permissive

### Code Review Feedback

> In external/LibraryThemeStyles/src/Service/ThemeSettingsService.php around lines 422-448, the themeKey validation and slug generation are too permissive; replace the simple non-empty string check and the current slug transform with stricter validation and sanitization: trim the input, verify it matches an allowed pattern (e.g. contains only letters, numbers, spaces, hyphens, underscores) or check it against a configured whitelist of allowed theme keys, and if it fails mark $themeKey = null; when creating the slug (line 442) normalize by trimming, converting to lowercase, replacing any sequence of non-alphanumeric characters with a single hyphen, collapsing multiple hyphens, and trimming leading/trailing hyphens before returning to ensure no special characters or repeated separators get through.

## Root Cause

### Previous Validation (Too Permissive)

```php
// Only checked for non-empty string
if ($themeKey !== null && !$this->errorHandler->validateAndLog(
    $themeKey,
    fn($key) => is_string($key) && !empty($key),
    'Invalid theme key provided'
)) {
    $themeKey = null;
}
```

**Problems:**
- Accepted any non-empty string
- No character validation
- Could accept special characters, SQL injection attempts, path traversal, etc.

### Previous Slug Generation (Too Simple)

```php
$themeSlug = strtolower(str_replace(' ', '-', $themeKey));
```

**Problems:**
- Only replaced spaces with hyphens
- Didn't handle other special characters
- Could produce invalid slugs with multiple hyphens
- Didn't trim leading/trailing hyphens
- Could allow dangerous characters through

## Security Risks

### 1. Special Characters

**Before:**
```php
$themeKey = "Library<script>alert('xss')</script>Theme";
$themeSlug = "library<script>alert('xss')</script>theme"; // DANGEROUS
```

**After:**
```php
$themeKey = "Library<script>alert('xss')</script>Theme";
// Validation fails: contains disallowed characters
$themeKey = null; // Falls back to default
```

### 2. Path Traversal

**Before:**
```php
$themeKey = "../../../etc/passwd";
$themeSlug = "../../../etc/passwd"; // DANGEROUS
```

**After:**
```php
$themeKey = "../../../etc/passwd";
// Validation fails: contains disallowed characters (/)
$themeKey = null; // Falls back to default
```

### 3. SQL Injection Attempts

**Before:**
```php
$themeKey = "'; DROP TABLE sites; --";
$themeSlug = "'; drop table sites; --"; // DANGEROUS
```

**After:**
```php
$themeKey = "'; DROP TABLE sites; --";
// Validation fails: contains disallowed characters (', ;)
$themeKey = null; // Falls back to default
```

### 4. Multiple Hyphens/Spaces

**Before:**
```php
$themeKey = "Library   Theme   2024";
$themeSlug = "library   theme   2024"; // Invalid slug with spaces
```

**After:**
```php
$themeKey = "Library   Theme   2024";
// Validation passes (spaces allowed)
$themeSlug = "library-theme-2024"; // Clean slug
```

## Solution Implemented

### 1. Strict Input Validation

**New validation logic:**

```php
if ($themeKey !== null) {
    $themeKey = trim($themeKey);
    
    // Validate: only letters, numbers, spaces, hyphens, underscores allowed
    if (!$this->errorHandler->validateAndLog(
        $themeKey,
        fn($key) => is_string($key) && !empty($key) && preg_match('/^[a-zA-Z0-9\s\-_]+$/', $key),
        'Invalid theme key provided: contains disallowed characters'
    )) {
        $this->errorHandler->logWarning('Theme key rejected due to invalid characters', [
            'themeKey' => $themeKey,
        ]);
        $themeKey = null; // Fall back to default
    }
}
```

**Allowed characters:**
- Letters: `a-z`, `A-Z`
- Numbers: `0-9`
- Spaces: ` `
- Hyphens: `-`
- Underscores: `_`

**Rejected characters:**
- Special characters: `<`, `>`, `&`, `'`, `"`, `;`, etc.
- Path separators: `/`, `\`
- SQL operators: `'`, `"`, `;`, `--`, etc.
- Any other non-alphanumeric characters

### 2. Comprehensive Slug Sanitization

**New `sanitizeThemeSlug()` method:**

```php
private function sanitizeThemeSlug(string $themeKey): string
{
    // Trim whitespace
    $slug = trim($themeKey);
    
    // Convert to lowercase
    $slug = strtolower($slug);
    
    // Replace any sequence of non-alphanumeric characters with a single hyphen
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    
    // Collapse multiple hyphens into single hyphen
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Trim leading and trailing hyphens
    $slug = trim($slug, '-');
    
    $this->errorHandler->logDebug('Theme slug sanitized', [
        'original' => $themeKey,
        'sanitized' => $slug,
    ]);
    
    return $slug;
}
```

**Sanitization steps:**
1. **Trim whitespace** - Remove leading/trailing spaces
2. **Convert to lowercase** - Normalize case
3. **Replace non-alphanumeric sequences** - Convert to single hyphen
4. **Collapse multiple hyphens** - Ensure no repeated separators
5. **Trim hyphens** - Remove leading/trailing hyphens

## Examples

### Valid Inputs

| Input | Validation | Sanitized Slug |
|-------|-----------|----------------|
| `"LibraryTheme"` | ✅ Pass | `"librarytheme"` |
| `"Library Theme"` | ✅ Pass | `"library-theme"` |
| `"Library-Theme"` | ✅ Pass | `"library-theme"` |
| `"Library_Theme"` | ✅ Pass | `"library-theme"` |
| `"Library Theme 2024"` | ✅ Pass | `"library-theme-2024"` |
| `"Library   Theme"` | ✅ Pass | `"library-theme"` |
| `"  Library Theme  "` | ✅ Pass | `"library-theme"` |

### Invalid Inputs (Rejected)

| Input | Validation | Result |
|-------|-----------|--------|
| `"Library<Theme>"` | ❌ Fail | `null` (fallback) |
| `"Library/Theme"` | ❌ Fail | `null` (fallback) |
| `"Library'Theme"` | ❌ Fail | `null` (fallback) |
| `"Library;Theme"` | ❌ Fail | `null` (fallback) |
| `"../Library"` | ❌ Fail | `null` (fallback) |
| `"Library&Theme"` | ❌ Fail | `null` (fallback) |
| `"Library@Theme"` | ❌ Fail | `null` (fallback) |

### Edge Cases

| Input | Validation | Sanitized Slug |
|-------|-----------|----------------|
| `"---Library---"` | ✅ Pass | `"library"` |
| `"Library___Theme"` | ✅ Pass | `"library-theme"` |
| `"Library - - Theme"` | ✅ Pass | `"library-theme"` |
| `"LIBRARY THEME"` | ✅ Pass | `"library-theme"` |

## Security Benefits

### 1. XSS Prevention

**Before:** Could inject script tags
```php
$themeKey = "Theme<script>alert(1)</script>";
// Would be used in HTML/CSS contexts
```

**After:** Rejected at validation
```php
$themeKey = "Theme<script>alert(1)</script>";
// Validation fails, $themeKey = null
```

### 2. Path Traversal Prevention

**Before:** Could access parent directories
```php
$themeKey = "../../etc/passwd";
// Could be used in file paths
```

**After:** Rejected at validation
```php
$themeKey = "../../etc/passwd";
// Validation fails, $themeKey = null
```

### 3. SQL Injection Prevention

**Before:** Could inject SQL
```php
$themeKey = "'; DROP TABLE sites; --";
// Could be used in queries
```

**After:** Rejected at validation
```php
$themeKey = "'; DROP TABLE sites; --";
// Validation fails, $themeKey = null
```

### 4. Clean URL Slugs

**Before:** Could produce invalid URLs
```php
$themeKey = "Library   Theme!!!";
$slug = "library   theme!!!"; // Invalid
```

**After:** Produces clean slugs
```php
$themeKey = "Library   Theme!!!";
// Validation fails (! not allowed)
$themeKey = null; // Falls back
```

## Error Logging

### Validation Failure

```
[LibraryThemeStyles] WARNING: Theme key rejected due to invalid characters | Context: {"themeKey":"Library<Theme>"}
```

### Successful Sanitization

```
[LibraryThemeStyles] DEBUG: Theme slug sanitized | Context: {"original":"Library Theme 2024","sanitized":"library-theme-2024"}
```

### Fallback to Default

```
[LibraryThemeStyles] DEBUG: Theme slug resolved from site | Context: {"themeSlug":"library-theme"}
```

## Validation Pattern

The regex pattern used for validation:

```php
/^[a-zA-Z0-9\s\-_]+$/
```

**Breakdown:**
- `^` - Start of string
- `[a-zA-Z0-9\s\-_]+` - One or more of:
  - `a-zA-Z` - Letters (uppercase and lowercase)
  - `0-9` - Numbers
  - `\s` - Whitespace (spaces, tabs)
  - `\-` - Hyphens
  - `_` - Underscores
- `$` - End of string

## Sanitization Pattern

Two regex patterns used for sanitization:

### 1. Replace Non-Alphanumeric Sequences

```php
preg_replace('/[^a-z0-9]+/', '-', $slug)
```

**Breakdown:**
- `[^a-z0-9]+` - One or more characters that are NOT:
  - `a-z` - Lowercase letters
  - `0-9` - Numbers
- Replaced with: `-` (single hyphen)

### 2. Collapse Multiple Hyphens

```php
preg_replace('/-+/', '-', $slug)
```

**Breakdown:**
- `-+` - One or more consecutive hyphens
- Replaced with: `-` (single hyphen)

## Files Modified

1. ✅ `external/LibraryThemeStyles/src/Service/ThemeSettingsService.php`
   - Updated `getThemeSlug()` method with strict validation
   - Added `sanitizeThemeSlug()` helper method
   - Added comprehensive error logging

## Deployment

✅ Deployed to `/var/www/omeka-s/modules/LibraryThemeStyles`  
✅ Ownership set to `www-data:www-data`  
✅ Apache restarted  
✅ Documentation created: `THEME_KEY_VALIDATION_SANITIZATION_FIX.md`

## Testing

### Manual Testing

1. **Test valid theme keys:**
   - "LibraryTheme" → "librarytheme"
   - "Library Theme" → "library-theme"
   - "Library Theme 2024" → "library-theme-2024"

2. **Test invalid theme keys:**
   - "Library<Theme>" → null (rejected)
   - "Library/Theme" → null (rejected)
   - "Library'Theme" → null (rejected)

3. **Test edge cases:**
   - "---Library---" → "library"
   - "Library   Theme" → "library-theme"
   - "LIBRARY THEME" → "library-theme"

### Log Monitoring

Monitor logs with:
```bash
tail -f /var/log/apache2/error.log | grep LibraryThemeStyles
```

## Best Practices

### Always Validate User Input

```php
// ✅ Correct - Validate before use
if (preg_match('/^[a-zA-Z0-9\s\-_]+$/', $input)) {
    $slug = sanitizeThemeSlug($input);
}

// ❌ Incorrect - Use without validation
$slug = strtolower(str_replace(' ', '-', $input));
```

### Always Sanitize for Context

```php
// ✅ Correct - Sanitize for URL slug
$slug = sanitizeThemeSlug($input);

// ❌ Incorrect - Minimal sanitization
$slug = str_replace(' ', '-', $input);
```

## Conclusion

Theme key validation and slug generation have been significantly hardened with:
- Strict input validation (alphanumeric + spaces/hyphens/underscores only)
- Comprehensive slug sanitization (lowercase, hyphen-separated, no special chars)
- Detailed error logging for security monitoring
- Protection against XSS, path traversal, and SQL injection

**Status:** ✅ COMPLETE

