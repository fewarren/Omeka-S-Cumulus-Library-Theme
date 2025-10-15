# Breadcrumb "Include Current Page" Setting Fix

## 🚨 **Issue Identified**

The "Include Current Page in Breadcrumbs" theme setting was not working because the breadcrumb templates were not checking this setting. The current page was always being displayed in breadcrumbs regardless of the setting value.

## 🔍 **Root Cause Analysis**

### **Problem**
Two breadcrumb implementations in the theme were ignoring the `breadcrumbs_include_current` theme setting:

1. **Foundation Breadcrumbs** (`view/common/foundation-breadcrumbs.phtml`)
   - Used by pages and general navigation
   - Always showed active page with `class="active"`

2. **Custom Item Breadcrumbs** (`view/omeka/site/item/show.phtml`)
   - Used specifically for item detail pages
   - Always showed current item with `class="current"`

### **Setting Configuration**
The setting was properly defined in `config/theme.ini`:
```ini
elements.breadcrumbs_include_current.name = "breadcrumbs_include_current"
elements.breadcrumbs_include_current.type = "Select"
elements.breadcrumbs_include_current.options.label = "Include Current Page in Breadcrumbs"
elements.breadcrumbs_include_current.options.value_options.1 = "Show current page"
elements.breadcrumbs_include_current.options.value_options.0 = "Hide current page"
elements.breadcrumbs_include_current.attributes.value = "1"
```

But the templates were not reading this setting.

## ✅ **Fix Applied**

### **1. Fixed Foundation Breadcrumbs Template**

**File**: `view/common/foundation-breadcrumbs.phtml`

**Before**:
```php
<?php foreach($this->pages as $page): ?>
    <?php if( ! $page->isActive()): ?>
        <li><a href="<?php echo $page->getHref() ?>"><?php echo $page->getLabel() ?></a></li>
    <?php else: ?>
        <li class="active">
            <?php echo $page->getLabel() ?>
        </li>
    <?php endif ?>
<?php endforeach ?>
```

**After**:
```php
<?php 
// Check theme setting for including current page in breadcrumbs
$includeCurrent = (string)($this->themeSetting('breadcrumbs_include_current', '1') ?? '1') === '1';
?>
<?php foreach($this->pages as $page): ?>
    <?php if( ! $page->isActive()): ?>
        <li><a href="<?php echo $page->getHref() ?>"><?php echo $page->getLabel() ?></a></li>
    <?php elseif($includeCurrent): ?>
        <!-- Show current page only if setting is enabled -->
        <li class="active">
            <?php echo $page->getLabel() ?>
        </li>
    <?php endif ?>
<?php endforeach ?>
```

### **2. Fixed Item Show Breadcrumbs**

**File**: `view/omeka/site/item/show.phtml`

**Before**:
```php
<nav class="breadcrumbs" aria-label="<?= $translate('Breadcrumb') ?>">
    <ul>
        <li><a href="<?= $this->url('site', [], true) ?>"><?= $translate('Home') ?></a></li>
        <li><a href="<?= $this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true) ?>"><?= $translate('Items') ?></a></li>
        <!-- ... item sets ... -->
        <li class="current"><?= $escape($itemTitle) ?></li>
    </ul>
</nav>
```

**After**:
```php
<?php 
// Check theme setting for including current page in breadcrumbs
$includeCurrent = (string)($this->themeSetting('breadcrumbs_include_current', '1') ?? '1') === '1';
?>
<nav class="breadcrumbs" aria-label="<?= $translate('Breadcrumb') ?>">
    <ul>
        <li><a href="<?= $this->url('site', [], true) ?>"><?= $translate('Home') ?></a></li>
        <li><a href="<?= $this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true) ?>"><?= $translate('Items') ?></a></li>
        <!-- ... item sets ... -->
        <?php if ($includeCurrent): ?>
        <li class="current"><?= $escape($itemTitle) ?></li>
        <?php endif; ?>
    </ul>
</nav>
```

## 🧪 **Testing Instructions**

### **Step 1: Test Current Setting State**
1. Go to **Admin → Appearance → Themes**
2. Click **Configure** on Library Theme
3. Scroll to **Breadcrumbs** section
4. Check current value of "Include Current Page in Breadcrumbs"

### **Step 2: Test with Setting Enabled**
1. Set "Include Current Page in Breadcrumbs" to **"Show current page"**
2. Click **Save**
3. Visit any page or item on your site
4. **Expected**: Breadcrumbs should show the current page as the last item

### **Step 3: Test with Setting Disabled**
1. Set "Include Current Page in Breadcrumbs" to **"Hide current page"**
2. Click **Save**
3. Visit the same pages/items
4. **Expected**: Breadcrumbs should NOT show the current page (end with parent page)

### **Step 4: Test Different Page Types**
Test both setting states on:
- **Site pages** (uses foundation-breadcrumbs.phtml)
- **Item detail pages** (uses custom breadcrumbs in item/show.phtml)
- **Collection pages**
- **Browse pages**

## 🎯 **Expected Behavior**

### **When "Show current page" (value = '1')**
```
Home > Items > Collection Name > Current Item Title
Home > About > Current Page Title
```

### **When "Hide current page" (value = '0')**
```
Home > Items > Collection Name
Home > About
```

## 📋 **Files Modified**

- ✅ `view/common/foundation-breadcrumbs.phtml` - Added setting check for foundation breadcrumbs
- ✅ `view/omeka/site/item/show.phtml` - Added setting check for item breadcrumbs
- ✅ Deployed to production via `DEPLOY.sh`

## 🚀 **Deployment Status**

✅ **Successfully deployed** - Breadcrumb setting fix is now live

## 🔧 **Technical Details**

### **Setting Check Logic**
```php
$includeCurrent = (string)($this->themeSetting('breadcrumbs_include_current', '1') ?? '1') === '1';
```

- **Default value**: '1' (show current page)
- **Type casting**: Ensures string comparison for reliability
- **Null handling**: Uses '1' as fallback if setting is not set
- **Boolean logic**: Only shows current page when setting equals '1'

### **Conditional Rendering**
- **Foundation breadcrumbs**: Uses `elseif($includeCurrent)` to conditionally show active page
- **Item breadcrumbs**: Uses `if ($includeCurrent)` to conditionally show current item
- **Maintains accessibility**: Preserves aria-label and proper navigation structure

## 🎉 **Benefits**

1. **Setting Functionality Restored**: The breadcrumb setting now works as intended
2. **User Control**: Users can choose whether to show/hide current page in breadcrumbs
3. **Consistent Behavior**: Both breadcrumb implementations now respect the setting
4. **Backward Compatibility**: Default behavior (show current page) is preserved
5. **Clean Navigation**: Option to have cleaner breadcrumbs without redundant current page

## ⚡ **Next Steps**

1. **Test immediately** - The fix is deployed and ready
2. **Toggle the setting** - Try both "Show" and "Hide" options
3. **Verify on different page types** - Test pages, items, collections
4. **Report results** - Confirm the setting now controls breadcrumb behavior correctly

The breadcrumb "Include Current Page" setting should now work properly across all page types in the Library Theme!
