# Debug Analysis - Deployment Issues

## Issues Identified

### **Issue 1: PDF Download Controls Not Working** ❌
**Problem**: After setting "Hide Download Links" to enabled, PDF viewers still show download/save icons.

**Root Cause Analysis**:
- PDF thumbnails in item pages link to media pages (`$media->url()`)
- Media pages use our PDF viewer with download controls
- BUT: There may be a missing PDF redirect that bypasses our controls
- OR: Browser PDF viewer ignoring `#toolbar=0` parameter

### **Issue 2: Black Admin Banner Appearing** ❌
**Problem**: Unexpected black banner with "Admin" and "Logout" options appearing on public pages.

**Root Cause Analysis**:
- User bar logic in `view/layout/layout.phtml` (lines 658-676)
- Condition: `$this->siteSetting('show_user_bar', 1) && $this->identity()`
- Default `show_user_bar` setting is `1` (enabled)
- If user is logged in, banner appears on ALL pages

## Debug Tracing Added

### **PDF Download Control Debug** ✅
**Location**: `view/omeka/site/media/show.phtml` (lines 62-67)
```html
<!-- DEBUG PDF DOWNLOAD CONTROL: hide_download_links setting='X', hideDownloads=TRUE/FALSE, toolbarParam='#toolbar=0', originalUrl='...', finalPdfUrl='...' -->
```

**Location**: `external/LibraryThemeStyles/src/Media/FileRenderer/PdfRenderer.php` (lines 32-37)
```html
<!-- DEBUG PDF RENDERER: hide_download_links setting='X', hideDownloads=TRUE/FALSE, toolbarParam='#toolbar=0', originalUrl='...', finalPdfUrl='...' -->
```

### **Admin Banner Debug** ✅
**Location**: `view/layout/layout.phtml` (lines 664-668)
```html
<!-- DEBUG USER BAR: show_user_bar setting=TRUE/FALSE, identity=LOGGED_IN/NOT_LOGGED_IN, showUserBar=TRUE/FALSE, userIsAllowed=TRUE/FALSE -->
```

## Expected Debug Output

### **When PDF Download Controls Work**:
```html
<!-- DEBUG PDF DOWNLOAD CONTROL: hide_download_links setting='1', hideDownloads=TRUE, toolbarParam='#toolbar=0', originalUrl='http://site.com/files/original/abc123.pdf', finalPdfUrl='http://site.com/files/original/abc123.pdf#toolbar=0' -->
```

### **When Admin Banner Should NOT Appear**:
```html
<!-- DEBUG USER BAR: show_user_bar setting=TRUE, identity=NOT_LOGGED_IN, showUserBar=FALSE, userIsAllowed=FALSE -->
```

### **When Admin Banner IS Appearing (Problem)**:
```html
<!-- DEBUG USER BAR: show_user_bar setting=TRUE, identity=LOGGED_IN, showUserBar=TRUE, userIsAllowed=TRUE -->
```

## Analysis Plan

### **Step 1: Deploy Debug Version** ✅
```bash
sudo ./DEPLOY.sh
```

### **Step 2: Test PDF Download Controls**
1. Navigate to PDF item page
2. Click PDF thumbnail → Goes to media page
3. Check browser source for debug output
4. Verify `#toolbar=0` parameter in iframe src
5. Test in Chrome/Safari (should hide toolbar) vs Firefox (won't work)

### **Step 3: Test Admin Banner**
1. Visit public pages while logged out → Should see `identity=NOT_LOGGED_IN, showUserBar=FALSE`
2. Visit public pages while logged in → Will see `identity=LOGGED_IN, showUserBar=TRUE` (problem)
3. Check site settings for `show_user_bar` value

## Likely Root Causes

### **PDF Download Issue**:
**Hypothesis 1**: Browser compatibility
- Chrome/Safari/Edge: `#toolbar=0` should work
- Firefox: Uses PDF.js, ignores toolbar parameter
- **Test**: Check which browser user is using

**Hypothesis 2**: PDF redirect missing
- Documentation mentions PDF redirect for single PDF items
- Current item template doesn't have redirect logic
- **Test**: Check if PDF items redirect to file directly (bypassing our controls)

**Hypothesis 3**: Setting not applied
- Theme setting not saved correctly
- Wrong setting key or value
- **Test**: Debug output will show actual setting value

### **Admin Banner Issue**:
**Hypothesis 1**: Site setting misconfigured
- `show_user_bar` setting defaults to `1` (enabled)
- Should be `0` (disabled) for public sites
- **Fix**: Change site setting or modify default

**Hypothesis 2**: User logged in during testing
- Admin user remains logged in across sessions
- Banner appears for any logged-in user
- **Fix**: Log out or modify logic to hide on public pages

## Immediate Fixes to Implement

### **Fix 1: Admin Banner (High Priority)**
```php
// Option A: Disable user bar by default
$showUserBar = $this->siteSetting('show_user_bar', 0) && $this->identity();

// Option B: Hide on public pages
$isAdminArea = strpos($this->serverUrl(true), '/admin') !== false;
$showUserBar = $this->siteSetting('show_user_bar', 1) && $this->identity() && $isAdminArea;
```

### **Fix 2: PDF Download Controls (Medium Priority)**
```php
// Add missing PDF redirect for single PDF items
if (count($itemMedia) === 1 && $primaryMedia->mediaType() === 'application/pdf') {
    $hideDownloads = $this->themeSetting('hide_download_links', '0') === '1';
    $toolbarParam = $hideDownloads ? '#toolbar=0' : '';
    $pdfUrl = $primaryMedia->originalUrl() . $toolbarParam;
    // Redirect to PDF with toolbar control
    echo '<script>window.location.href = "' . $escape($pdfUrl) . '";</script>';
    return;
}
```

## Testing Scenarios

### **PDF Download Control Tests**:
1. **Chrome/Safari**: Should hide download button with `#toolbar=0`
2. **Firefox**: Will still show download button (expected limitation)
3. **Direct PDF access**: Should include toolbar parameter in URL
4. **Media page**: Should show debug output with correct settings

### **Admin Banner Tests**:
1. **Logged out user**: No banner should appear
2. **Logged in user on public pages**: Banner should NOT appear (after fix)
3. **Logged in user on admin pages**: Banner should appear (if needed)
4. **Site setting disabled**: No banner regardless of login status

## Next Steps

1. **Deploy debug version** and gather evidence
2. **Analyze debug output** to confirm root causes
3. **Implement targeted fixes** based on evidence
4. **Test fixes** in multiple browsers and scenarios
5. **Remove debug output** once issues resolved
6. **Document final solutions** for future reference

## Files Modified for Debug

### **Debug Added**:
- ✅ `view/layout/layout.phtml` - Admin banner debug
- ✅ `view/omeka/site/media/show.phtml` - PDF download debug  
- ✅ `external/LibraryThemeStyles/src/Media/FileRenderer/PdfRenderer.php` - PDF renderer debug

### **Ready for Deployment**:
All debug changes are non-breaking and will provide comprehensive evidence to identify and fix both issues.
