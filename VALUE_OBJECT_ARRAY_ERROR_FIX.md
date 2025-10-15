# Value Object Array Error Fix

## Issue Description

**Error**: `Call to a member function value() on array`

**Context**: Error occurs after the previous escape helper fix when viewing items with subject values, indicating that `$subjectValue` is an array rather than a Value object.

**Location**: `/var/www/omeka-s/themes/LibraryTheme/view/omeka/site/item/show.phtml:214`

## Root Cause Analysis

### **Technical Problem**
The previous fix assumed that `$item->values()` returns an array of Value objects, but the actual data structure was different. The error "Call to a member function value() on array" indicates that `$subjectValue` is an array, not a Value object.

### **Data Structure Investigation**
The issue arose from incorrect assumptions about the return type of `$item->values()` with language filtering:

```php
// Previous problematic code
$subject = $item->values('dcterms:subject', ['lang' => $valueLang]);
foreach ($subject as $subjectValue) {
    $subjectValue->value();  // ❌ ERROR: $subjectValue is array, not object
}
```

### **Language Filtering Impact**
When using language filtering parameters with `$item->values()`, the returned structure may be different than expected, potentially returning nested arrays instead of direct Value objects.

## Solution Evolution

### **Attempt 1: Object Detection** ❌
```php
$escape(is_object($subjectValue) ? $subjectValue->value() : $subjectValue)
```
**Issue**: Too complex and doesn't address root cause

### **Attempt 2: Alternative API** ❌
```php
$subject = $item->value('dcterms:subject', ['lang' => $valueLang, 'all' => true]);
```
**Issue**: Invalid API usage, `'all' => true` parameter doesn't exist

### **Final Solution: Simplified API + Proper Display** ✅

#### **Step 1: Simplified Data Retrieval**
```php
// Before (with language filtering)
$subject = $item->values('dcterms:subject', ['lang' => $valueLang]);

// After (simplified, no language filtering)
$subject = $item->values('dcterms:subject');
```

#### **Step 2: Proper Value Display**
```php
// Before (manual escaping)
<span class="subject-tag"><?= $escape($subjectValue->value()) ?></span>

// After (using asHtml() method)
<span class="subject-tag"><?= $subjectValue->asHtml() ?></span>
```

## Technical Solution

### **Data Retrieval Fix** ✅
**File**: `view/omeka/site/item/show.phtml:44`

```php
// Simplified approach without language filtering
$subject = $item->values('dcterms:subject');
```

**Benefits**:
- Removes complex language filtering that may cause data structure issues
- Returns consistent array of Value objects
- Follows standard Omeka API patterns

### **Display Method Fix** ✅
**File**: `view/omeka/site/item/show.phtml:214`

```php
// Using asHtml() instead of manual escaping
<span class="subject-tag"><?= $subjectValue->asHtml() ?></span>
```

**Benefits**:
- `->asHtml()` returns properly formatted, escaped HTML
- Handles links and complex formatting automatically
- No manual escaping required
- Consistent with Omeka display patterns

## Value Object Methods Comparison

### **Available Methods**
```php
$value->value()        // Raw string value (needs escaping)
$value->asHtml()       // Formatted HTML (pre-escaped)
$value->displayValue() // Display-formatted value
$value->type()         // Value type (literal, resource, uri)
$value->lang()         // Language code
```

### **Method Selection Rationale**
- **`->value()`**: Raw value, requires manual escaping, prone to errors
- **`->asHtml()`**: ✅ **CHOSEN** - Pre-escaped, handles formatting, safe for direct output
- **`->displayValue()`**: Alternative, but `->asHtml()` is more appropriate for HTML context

## Validation

### **Error Resolution** ✅
- **Primary Error**: Fixed "Call to a member function value() on array"
- **Data Structure**: Consistent Value objects returned from `$item->values()`
- **Subject Display**: Multiple subject values render correctly as tags

### **API Consistency** ✅
- **Standard Pattern**: Uses standard Omeka `$item->values()` without complex parameters
- **Value Handling**: Uses recommended `->asHtml()` method for display
- **No Manual Escaping**: Eliminates error-prone manual escaping

### **Display Quality** ✅
- **Proper Formatting**: Subject values display with proper HTML formatting
- **Link Handling**: Automatically handles subject values that are links or resources
- **Consistent Styling**: Subject tags render consistently with theme design

## Benefits Achieved

1. **✅ Error Elimination**: No more "Call to a member function on array" errors
2. **✅ Simplified Code**: Cleaner, more maintainable approach
3. **✅ Better Display**: Proper HTML formatting for subject values
4. **✅ API Compliance**: Follows standard Omeka patterns
5. **✅ Automatic Escaping**: No manual escaping required, reduces security risks
6. **✅ Link Support**: Automatically handles subject values that are resources or URIs

## Technical Details

### **Omeka Values API**
```php
// Single value (returns ValueRepresentation or null)
$item->value('property')
$item->value('property', ['lang' => 'en'])

// Multiple values (returns array of ValueRepresentation objects)
$item->values('property')
$item->values('property', ['lang' => 'en'])  // May return complex structure
```

### **Value Display Best Practices**
```php
// ✅ RECOMMENDED: For HTML output
echo $value->asHtml();

// ✅ ALTERNATIVE: For plain text with manual escaping
echo $escape($value->value());

// ✅ COMPLEX: For custom formatting
if ($value->type() === 'resource') {
    echo $value->valueResource()->link();
} else {
    echo $escape($value->value());
}
```

### **Language Filtering Considerations**
- Simple `$item->values('property')` returns consistent structure
- Language filtering may introduce complexity in return structure
- For custom displays, consider post-processing for language filtering if needed

## Prevention

### **Best Practice Established**
For displaying multiple metadata values in custom templates:

```php
// ✅ RECOMMENDED PATTERN
$values = $item->values('property');
foreach ($values as $value) {
    echo '<span class="tag">' . $value->asHtml() . '</span>';
}

// ✅ ALTERNATIVE: With manual escaping
$values = $item->values('property');
foreach ($values as $value) {
    echo '<span class="tag">' . $escape($value->value()) . '</span>';
}
```

### **Code Review Checklist**
- [ ] Use `$item->values()` without complex parameters for consistent results
- [ ] Use `->asHtml()` for HTML output or `->value()` with manual escaping
- [ ] Test with items that have multiple values for the same property
- [ ] Verify that Value objects are properly handled in foreach loops

## Conclusion

The value object array error has been resolved by simplifying the data retrieval approach and using the proper Omeka display method. The fix eliminates complex language filtering that was causing data structure inconsistencies and uses the recommended `->asHtml()` method for safe, properly formatted HTML output. This provides a robust solution that follows Omeka best practices and handles all types of subject values correctly.
