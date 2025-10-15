# PDF Item Navigation Error Fix

## Issue Description

**Error**: `Laminas\Router\Exception\InvalidArgumentException: Missing parameter "site-slug"`

**Context**: Error occurs when viewing embedded media PDF items in Omeka S, specifically when the breadcrumb navigation tries to generate URLs.

**Location**: `/var/www/omeka-s/themes/LibraryTheme/view/omeka/site/item/show.phtml(58)`

## Root Cause Analysis

### **Technical Problem**
The `site/resource` route in Omeka S requires a `site-slug` parameter. When calling the URL helper without the `true` parameter, the current site context is not automatically included, causing the router to fail.

### **Problematic Code Pattern**
```php
// ❌ BROKEN: Missing site context
$this->url('site/resource', ['controller' => 'item', 'action' => 'browse'])

// ✅ CORRECT: Includes site context  
$this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true)
```

### **URL Helper Behavior**
- **Without `true`**: `$this->url('route', $params)` - Generates relative URL, may not include site context
- **With `true`**: `$this->url('route', $params, true)` - Generates absolute URL, includes current site context

### **Route Requirements**
The `site/resource` route pattern includes `{site-slug}` as a required parameter. Without the site context, the router cannot assemble the URL and throws the "Missing parameter 'site-slug'" exception.

## Impact

### **User Experience Issues**
1. **Fatal Error**: Users encounter error when viewing PDF items
2. **Broken Navigation**: Breadcrumb "Items" link fails to work
3. **Navigation Interruption**: Prevents users from navigating back to item browse page
4. **Accessibility**: Breaks keyboard navigation and screen reader functionality

### **Affected Functionality**
- PDF item viewing pages
- Breadcrumb navigation
- Homepage navigation links
- Collection browsing links

## Solution Implemented

### **Files Fixed**

#### **1. Primary Error Location** ✅
**File**: `view/omeka/site/item/show.phtml`
**Line**: 58
```php
// Before (BROKEN)
<li><a href="<?= $this->url('site/resource', ['controller' => 'item', 'action' => 'browse']) ?>"><?= $translate('Items') ?></a></li>

// After (FIXED)
<li><a href="<?= $this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true) ?>"><?= $translate('Items') ?></a></li>
```

#### **2. Homepage Navigation Links** ✅
**File**: `view/omeka/site/index-backup.phtml`
**Lines**: 77, 79, 139, 188
```php
// Before (BROKEN)
$url('site/resource', ['controller' => 'item', 'action' => 'browse'])
$url('site/resource', ['controller' => 'item-set', 'action' => 'browse'])

// After (FIXED)  
$url('site/resource', ['controller' => 'item', 'action' => 'browse'], true)
$url('site/resource', ['controller' => 'item-set', 'action' => 'browse'], true)
```

#### **3. Reference Page Navigation** ✅
**File**: `view/omeka/site/index-reference.phtml`
**Lines**: 76, 77
```php
// Before (BROKEN)
$url('site/resource', ['controller' => 'item', 'action' => 'browse'])
$url('site/resource', ['controller' => 'item-set', 'action' => 'browse'])

// After (FIXED)
$url('site/resource', ['controller' => 'item', 'action' => 'browse'], true)
$url('site/resource', ['controller' => 'item-set', 'action' => 'browse'], true)
```

### **Pattern Applied**
Added the `true` parameter to all `site/resource` URL calls to ensure proper site context inclusion:

```php
// Standard fix pattern
$this->url('site/resource', $params, true)  // ✅ Includes site context
```

## Validation

### **Error Resolution** ✅
- **Primary Error**: Fixed the exact line (58) mentioned in the stack trace
- **Breadcrumb Navigation**: "Items" link now works correctly
- **PDF Item Viewing**: No more fatal errors when viewing PDF items

### **Comprehensive Fix** ✅
- **All Instances**: Fixed all similar issues across multiple template files
- **Consistent Pattern**: Applied the same fix pattern throughout the theme
- **Future-Proof**: Prevents similar issues in other navigation contexts

### **Testing Scenarios**
1. **PDF Item Viewing**: ✅ No more "Missing parameter 'site-slug'" errors
2. **Breadcrumb Navigation**: ✅ "Items" link works correctly
3. **Homepage Links**: ✅ Browse buttons work correctly
4. **Collection Navigation**: ✅ Collection links work correctly

## Technical Details

### **Omeka S Route Structure**
```
site/resource/{site-slug}/resource/{controller}/{action}
```

### **URL Helper Parameters**
```php
$this->url($route, $params, $options, $reuseMatchedParams)
```
- `$route`: Route name (e.g., 'site/resource')
- `$params`: Route parameters (e.g., ['controller' => 'item'])
- `$options`: URL options - `true` forces absolute URL with site context
- `$reuseMatchedParams`: Whether to reuse matched parameters

### **Site Context Inclusion**
When `$options = true`:
- Forces absolute URL generation
- Includes current site slug automatically
- Ensures all required route parameters are present
- Prevents "Missing parameter" exceptions

## Benefits Achieved

1. **✅ Error Elimination**: No more fatal errors when viewing PDF items
2. **✅ Improved Navigation**: All breadcrumb and navigation links work correctly
3. **✅ Better User Experience**: Seamless navigation throughout the site
4. **✅ Accessibility**: Proper keyboard and screen reader navigation
5. **✅ Consistency**: Uniform URL generation pattern across all templates
6. **✅ Maintainability**: Clear pattern for future URL generation

## Prevention

### **Best Practice Established**
Always use the `true` parameter when generating URLs for `site/resource` routes:

```php
// ✅ RECOMMENDED PATTERN
$this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true)
```

### **Code Review Checklist**
- [ ] All `site/resource` URLs include the `true` parameter
- [ ] Breadcrumb navigation links are properly contextualized
- [ ] Homepage navigation links include site context
- [ ] Collection and item browse links work correctly

## Conclusion

The PDF item navigation error has been completely resolved by fixing the missing site context in URL generation. The fix ensures that all navigation links properly include the required `site-slug` parameter, eliminating fatal errors and providing a seamless user experience when viewing PDF items and navigating throughout the site.
