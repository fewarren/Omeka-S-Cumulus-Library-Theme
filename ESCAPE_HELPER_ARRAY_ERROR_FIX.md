# Escape Helper Array Error Fix

## Issue Description

**Error**: `Laminas\View\Exception\InvalidArgumentException: Array provided to Escape helper, but flags do not allow recursion`

**Context**: Error occurs when viewing items that have multiple subject values (dcterms:subject), specifically when the escape helper tries to process Value objects.

**Location**: `/var/www/omeka-s/themes/LibraryTheme/view/omeka/site/item/show.phtml(214)`

## Root Cause Analysis

### **Technical Problem**
The escape helper (`$escape()`) expects a string value, but was receiving an Omeka Value object (which is essentially an array/object structure) when processing multiple subject values.

### **Problematic Code Pattern**
```php
// Line 44: Getting multiple values (returns array of Value objects)
$subject = $item->values('dcterms:subject', ['lang' => $valueLang]);

// Line 214: Trying to escape a Value object directly
<?php foreach ($subject as $subjectValue): ?>
<span class="subject-tag"><?= $escape($subjectValue) ?></span>  // ❌ BROKEN
<?php endforeach; ?>
```

### **Data Type Issue**
- **`$item->value()`** (singular) - Returns a single `ValueRepresentation` object
- **`$item->values()`** (plural) - Returns an array of `ValueRepresentation` objects
- **`$escape()`** - Expects a string, not an object/array

### **Value Object Structure**
Omeka `ValueRepresentation` objects contain:
- Raw value data
- Language information  
- Resource references
- HTML formatting
- Metadata properties

## Impact

### **User Experience Issues**
1. **Fatal Error**: Users encounter error when viewing items with multiple subjects
2. **Broken Item Display**: Subject tags section fails to render
3. **Navigation Interruption**: Prevents users from viewing item details
4. **Metadata Loss**: Subject information becomes inaccessible

### **Affected Functionality**
- Item detail pages with multiple subject values
- Subject tag display in metadata sections
- Items with dcterms:subject properties

## Solution Implemented

### **Root Fix Applied** ✅

**File**: `view/omeka/site/item/show.phtml`
**Line**: 214

```php
// Before (BROKEN)
<span class="subject-tag"><?= $escape($subjectValue) ?></span>

// After (FIXED)
<span class="subject-tag"><?= $escape($subjectValue->value()) ?></span>
```

### **Technical Solution**
The fix converts the Value object to a string using the `->value()` method before passing it to the escape helper:

1. **`$subjectValue`** - Omeka ValueRepresentation object
2. **`$subjectValue->value()`** - Extracts the raw string value
3. **`$escape($subjectValue->value())`** - Safely escapes the string

### **Alternative Methods**
Other valid approaches for Value objects:
```php
// Raw string value (used in fix)
$escape($subjectValue->value())

// HTML representation (for links, formatting)
$subjectValue->asHtml()

// Display value (handles complex formatting)
$subjectValue->displayValue()
```

## Validation

### **Error Resolution** ✅
- **Primary Error**: Fixed the exact line (214) mentioned in the stack trace
- **Subject Tags**: Multiple subject values now display correctly
- **Item Viewing**: No more fatal errors when viewing items with subjects

### **Data Type Consistency** ✅
- **String Conversion**: Value objects properly converted to strings
- **Escape Safety**: All subject values safely escaped for HTML output
- **Metadata Integrity**: Subject information displays correctly

### **Pattern Verification** ✅
Confirmed that other similar code in the theme follows correct patterns:
- Single values (`$item->value()`) work correctly with `$escape()`
- Multiple values now properly extract string content before escaping
- Consistent handling across all metadata fields

## Technical Details

### **Omeka Value API**
```php
// Single value methods
$item->value('property')           // Returns ValueRepresentation or null
$item->displayTitle()              // Returns string
$item->displayDescription()        // Returns string

// Multiple value methods  
$item->values('property')          // Returns array of ValueRepresentation objects
$item->displayValues()             // Returns formatted HTML string
```

### **Value Object Methods**
```php
$valueObject->value()              // Raw string value
$valueObject->asHtml()             // HTML representation
$valueObject->displayValue()       // Formatted display value
$valueObject->lang()               // Language code
$valueObject->type()               // Value type (literal, resource, uri)
```

### **Escape Helper Requirements**
```php
$escape($string)                   // ✅ Accepts strings
$escape($valueObject)              // ❌ Rejects objects/arrays
$escape($valueObject->value())     // ✅ Accepts extracted string
```

## Benefits Achieved

1. **✅ Error Elimination**: No more fatal errors when viewing items with multiple subjects
2. **✅ Proper Subject Display**: Subject tags render correctly with proper escaping
3. **✅ Data Safety**: All subject values safely escaped for HTML output
4. **✅ Consistent Pattern**: Follows Omeka best practices for Value object handling
5. **✅ Maintainability**: Clear pattern for handling multiple values in templates
6. **✅ User Experience**: Seamless viewing of item metadata and subject information

## Prevention

### **Best Practice Established**
When working with Omeka Value objects in templates:

```php
// ✅ RECOMMENDED PATTERNS

// For single values
$singleValue = $item->value('property');
if ($singleValue) {
    echo $escape($singleValue);  // Works directly
}

// For multiple values
$multipleValues = $item->values('property');
foreach ($multipleValues as $value) {
    echo $escape($value->value());  // Extract string first
}

// For complex display
echo $item->displayValues();  // Let Omeka handle formatting
```

### **Code Review Checklist**
- [ ] All `$item->values()` results properly extract strings before escaping
- [ ] Value objects use `->value()` method when passed to `$escape()`
- [ ] Multiple value loops handle Value objects correctly
- [ ] Subject and other metadata displays work without errors

## Conclusion

The escape helper array error has been completely resolved by properly extracting string values from Omeka Value objects before passing them to the escape helper. The fix ensures that subject tags display correctly while maintaining proper HTML escaping for security. This establishes a clear pattern for handling multiple metadata values throughout the theme.
