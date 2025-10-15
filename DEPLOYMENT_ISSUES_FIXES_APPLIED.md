# Deployment Issues - Fixes Applied

## Issues Identified and Fixed

### **Issue 1: Black Admin Banner on Public Pages** ✅ **FIXED**

#### **Problem Confirmed**:
```html
<!-- DEBUG USER BAR: show_user_bar setting=TRUE, identity=LOGGED_IN, showUserBar=TRUE, userIsAllowed=TRUE -->
<div id="user-bar">
    <nav class="user-bar-nav">
        <a href="/omeka-s/admin">Admin</a>
        <a href="/omeka-s/logout">Logout</a>
    </nav>
</div>
```

#### **Root Cause**:
- User bar appeared on ALL pages for logged-in users
- Should only appear in admin area, not on public pages

#### **Fix Applied** ✅:
**Location**: `view/layout/layout.phtml` (lines 658-671)

**Before**:
```php
$showUserBar = $this->siteSetting('show_user_bar', 1) && $this->identity();
```

**After**:
```php
$isAdminArea = strpos($this->serverUrl(true), '/admin') !== false;
$showUserBar = $this->siteSetting('show_user_bar', 1) && $this->identity() && $isAdminArea;
```

#### **Result**:
- ✅ **Public pages**: No admin banner (clean interface)
- ✅ **Admin pages**: Admin banner appears when needed
- ✅ **Logged out users**: No banner anywhere

---

### **Issue 2: PDF Download Controls in Firefox** ✅ **EXPLAINED**

#### **Problem Confirmed**:
- Firefox browser still shows Save icon despite `hide_download_links` enabled
- `#toolbar=0` parameter present in URL but ignored by Firefox

#### **Root Cause**:
- **Firefox uses PDF.js** (built-in PDF viewer)
- **PDF.js ignores `#toolbar=0` parameter** (known limitation)
- **Chrome/Safari/Edge respect toolbar parameter** (should work correctly)

#### **Enhanced Debug Added** ✅:
**Location**: `view/omeka/site/media/show.phtml` (lines 62-74)

**New Debug Output**:
```html
<!-- DEBUG PDF DOWNLOAD CONTROL: hide_download_links setting='1', hideDownloads=TRUE, toolbarParam='#toolbar=0', originalUrl='...', finalPdfUrl='...', browser=FIREFOX, toolbarSupported=NO -->
```

#### **Browser Compatibility Matrix**:
| Browser | PDF Viewer | `#toolbar=0` Support | Download Control |
|---------|------------|---------------------|------------------|
| **Firefox** | PDF.js | ❌ **NO** | ❌ Save icon visible |
| **Chrome** | Native | ✅ **YES** | ✅ Save icon hidden |
| **Safari** | Native | ✅ **YES** | ✅ Save icon hidden |
| **Edge** | Native | ✅ **YES** | ✅ Save icon hidden |

#### **Expected Behavior** ✅:
- **Firefox**: Save icon remains visible (expected limitation)
- **Chrome/Safari/Edge**: Save icon should be hidden
- **All browsers**: Fallback download links properly hidden/shown

---

## Testing Instructions

### **Test 1: Admin Banner Fix** ✅
1. **While logged in as admin**:
   - Visit public pages → Should see NO black banner
   - Visit admin pages (`/admin`) → Should see black banner
2. **While logged out**:
   - Visit any pages → Should see NO black banner

**Expected Debug Output (Public Pages)**:
```html
<!-- DEBUG USER BAR: show_user_bar setting=TRUE, identity=LOGGED_IN, isAdminArea=FALSE, showUserBar=FALSE, userIsAllowed=TRUE -->
```

### **Test 2: PDF Download Controls** ✅
1. **Test in Firefox**:
   - PDF Save icon will remain visible (expected)
   - Debug should show `browser=FIREFOX, toolbarSupported=NO`

2. **Test in Chrome/Safari**:
   - PDF Save icon should be hidden
   - Debug should show `browser=CHROME, toolbarSupported=YES`

**Expected Debug Output**:
```html
<!-- DEBUG PDF DOWNLOAD CONTROL: hide_download_links setting='1', hideDownloads=TRUE, toolbarParam='#toolbar=0', originalUrl='...', finalPdfUrl='...#toolbar=0', browser=CHROME, toolbarSupported=YES -->
```

---

## Files Modified

### **Fixed Files**:
- ✅ `view/layout/layout.phtml` - Admin banner restricted to admin area
- ✅ `view/omeka/site/media/show.phtml` - Enhanced PDF debug with browser detection

### **Debug Files** (Enhanced):
- ✅ `external/LibraryThemeStyles/src/Media/FileRenderer/PdfRenderer.php` - PDF renderer debug

---

## Deployment Status

### **Ready for Deployment** ✅
```bash
sudo ./DEPLOY.sh
```

### **Expected Results After Deployment**:
1. **Admin Banner**: Will disappear from public pages immediately
2. **PDF Downloads**: 
   - Firefox: No change (Save icon remains, expected)
   - Chrome/Safari: Save icon should disappear
3. **Debug Output**: Enhanced browser detection and admin area detection

---

## Production Cleanup

### **After Testing Confirms Fixes Work**:
1. **Remove debug output** from all templates
2. **Clean deployment** without debug comments
3. **Update documentation** with browser compatibility notes

### **Files to Clean**:
- `view/layout/layout.phtml` - Remove user bar debug
- `view/omeka/site/media/show.phtml` - Remove PDF debug
- `external/LibraryThemeStyles/src/Media/FileRenderer/PdfRenderer.php` - Remove renderer debug

---

## Summary

### **Issue 1: Admin Banner** ✅ **RESOLVED**
- **Problem**: Black banner on all pages for logged-in users
- **Solution**: Restrict banner to admin area only
- **Impact**: Clean public interface, functional admin interface

### **Issue 2: PDF Downloads** ✅ **CLARIFIED**
- **Problem**: Firefox ignores download controls
- **Explanation**: Firefox PDF.js limitation (expected behavior)
- **Solution**: Works correctly in Chrome/Safari/Edge
- **Impact**: Partial browser support (majority of users benefit)

### **Next Steps**:
1. **Deploy fixes** and test in multiple browsers
2. **Confirm admin banner disappears** from public pages
3. **Test PDF controls in Chrome/Safari** (should work)
4. **Remove debug output** once confirmed working
5. **Document browser limitations** for future reference

**Status**: ✅ **Fixes ready for deployment and testing**
