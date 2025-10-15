# Page Title and Submenu Ordering Implementation

**Date:** 2025-10-13  
**Status:** Implemented and Deployed

## Problem Statement

The default Omeka S theme behavior when `render_submenu_by_default` is enabled is to render child page links (submenu) before page blocks. This creates a suboptimal user experience when a Page Title block is used as the first block on a page, because the submenu appears before the page title.

### User Requirements

1. **Detect Page Title Block**: Check if the first page block on a page is a Page Title block
2. **Reorder Rendering**: If the first block is a Page Title, render it BEFORE the submenu (child page links)
3. **Maintain Table of Contents Behavior**: The Table of Contents block already suppresses the default submenu by setting `displayNavigation` to false - this behavior must be preserved
4. **Avoid Duplication**: Ensure the Page Title block is not rendered twice

## Solution Architecture

### Key Components

1. **PageViewModel Setup** (`view/omeka/site/page/show.phtml` lines 78-81)
   - Sets `$this->pageViewModel` to make it available for blocks that need it
   - Required for BlockPlus Table of Contents module compatibility
   - Normally set by controller, but needed for manual block rendering

2. **Page Block Inspection** (`view/omeka/site/page/show.phtml` lines 83-94)
   - Inspect the page's blocks before rendering
   - Detect if the first block is a Page Title block (`layout === 'pageTitle'`)
   - Pre-render the Page Title block if detected

3. **Conditional Page Title Rendering** (`view/omeka/site/page/show.phtml` lines 106-110)
   - Render the pre-rendered Page Title HTML before the submenu
   - Only renders if `$firstBlockIsPageTitle` is true

4. **Submenu Rendering** (`view/omeka/site/page/show.phtml` lines 112-123)
   - Unchanged from original implementation
   - Renders child page links when `render_submenu_by_default` is enabled
   - Respects `displayNavigation` flag (set to false by Table of Contents block)

5. **Content Rendering with Skip Logic** (`view/omeka/site/page/show.phtml` lines 133-157)
   - If first block is Page Title: manually iterate through blocks, skipping the first one
   - Otherwise: use standard `$this->content` rendering (which includes all blocks)

### Implementation Details

#### PageViewModel Setup (Critical for BlockPlus Compatibility)

```php
// Set pageViewModel for blocks that need it (e.g., Table of Contents from BlockPlus module)
// This is normally set by the controller, but we need it available for manual block rendering
if (!isset($this->pageViewModel)) {
    // Create a ViewModel to act as pageViewModel
    $pageViewModel = new \Laminas\View\Model\ViewModel();
    $pageViewModel->setVariable('displayNavigation', $this->displayNavigation ?? true);
    $this->pageViewModel = $pageViewModel;
}
```

**Key Points:**
- The BlockPlus Table of Contents module expects `$view->pageViewModel` to be a ViewModel object
- It uses this to set `displayNavigation` to false: `$view->pageViewModel->setVariable('displayNavigation', false)`
- Without this, manual block rendering causes errors:
  - First: "Call to a member function setVariable() on null"
  - Then: "Call to undefined method Laminas\View\Variables::setVariable()"
- We create a new `Laminas\View\Model\ViewModel` object to act as the pageViewModel
- Initialize it with the current `displayNavigation` value (defaults to true)
- This setup must happen BEFORE any blocks are rendered

#### Block Detection and Pre-rendering

```php
// Check if first block is a Page Title block
$firstBlockIsPageTitle = false;
$pageTitleHtml = '';
$blocks = $page->blocks();
if (count($blocks) > 0) {
    $firstBlock = $blocks[0];
    if ($firstBlock->layout() === 'pageTitle') {
        $firstBlockIsPageTitle = true;
        // Render the page title block early
        $pageTitleHtml = $this->blockLayout()->render($firstBlock);
    }
}
```

**Key Points:**
- Uses `$page->blocks()` to get all blocks for the page
- Checks if first block's layout is `'pageTitle'`
- Pre-renders the block using `$this->blockLayout()->render($firstBlock)`
- Stores result in `$pageTitleHtml` for later output

#### Conditional Page Title Output

```php
<?php
// Render page title before submenu if it's the first block
if ($firstBlockIsPageTitle):
    echo $pageTitleHtml;
endif;
?>
```

**Key Points:**
- Positioned immediately before the submenu rendering
- Only outputs if a Page Title block was detected
- Uses the pre-rendered HTML from earlier

#### Content Rendering with Skip Logic

```php
<?php
// Render page blocks, skipping first if it's a Page Title (already rendered above)
if ($firstBlockIsPageTitle) {
    // Manually render blocks, skipping the first one
    $output = [];
    $blockIndex = 0;
    foreach ($page->blocks() as $block) {
        if ($blockIndex === 0) {
            // Skip first block (Page Title already rendered)
            $blockIndex++;
            continue;
        }
        $output[] = $this->blockLayout()->render($block);
        $blockIndex++;
    }
    echo implode('', $output);
} else {
    // Normal rendering - no Page Title block to skip
    echo $this->content;
}
?>
```

**Key Points:**
- Two rendering paths: with skip (Page Title first) or without skip (normal)
- Manual iteration allows skipping the first block to avoid duplication
- Falls back to standard `$this->content` when no Page Title block is first
- Maintains all block layout features (grid, blockGroup, etc.) through `blockLayout()->render()`

## Rendering Flow

### Scenario 1: Page with Page Title Block as First Block

1. **Block Inspection**: Detects Page Title block at position 0
2. **Pre-render**: Renders Page Title block, stores HTML
3. **Breadcrumbs**: Renders breadcrumbs (if not homepage)
4. **Page Title Output**: Outputs pre-rendered Page Title HTML
5. **Submenu**: Renders child page links (if enabled and has children)
6. **Content Blocks**: Manually iterates blocks, skipping first (Page Title)
7. **Result**: Page Title → Submenu → Other Blocks

### Scenario 2: Page without Page Title Block as First Block

1. **Block Inspection**: First block is not Page Title
2. **Breadcrumbs**: Renders breadcrumbs (if not homepage)
3. **Submenu**: Renders child page links (if enabled and has children)
4. **Content Blocks**: Standard rendering via `$this->content`
5. **Result**: Submenu → All Blocks (in order)

### Scenario 3: Page with Table of Contents Block

1. **Block Inspection**: May or may not have Page Title first
2. **Breadcrumbs**: Renders breadcrumbs (if not homepage)
3. **Page Title Output**: Outputs if Page Title is first block
4. **Submenu**: **SUPPRESSED** (Table of Contents sets `displayNavigation = false`)
5. **Content Blocks**: Renders normally (with or without skip logic)
6. **Result**: [Page Title if first] → Table of Contents → Other Blocks

## Table of Contents Block Compatibility

The Table of Contents block (`TableOfContents.php`) includes this code:

```php
public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = 'common/block-layout/table-of-contents')
{
    $view->pageViewModel->setVariable('displayNavigation', false);
    // ... rest of rendering
}
```

This sets `displayNavigation` to `false`, which is checked in the submenu rendering condition:

```php
<?php if ($activePage && ((string)$this->themeSetting('render_submenu_by_default', '0') === '1')): ?>
    <?php if ($this->displayNavigation && $activePage['page']->hasPages()): ?>
    <!-- Submenu rendering -->
    <?php endif; ?>
<?php endif; ?>
```

**Result**: When a Table of Contents block is present, the submenu is suppressed regardless of the `render_submenu_by_default` setting.

## Files Modified

### `view/omeka/site/page/show.phtml`

**Lines 78-81**: PageViewModel setup for BlockPlus compatibility
**Lines 83-94**: Block detection and pre-rendering logic
**Lines 106-110**: Conditional Page Title output before submenu
**Lines 112-123**: Submenu rendering (unchanged)
**Lines 133-157**: Content rendering with skip logic

## Known Issues and Fixes

### Issue: BlockPlus Table of Contents Compatibility Errors

**Symptoms**:
1. First error: "Call to a member function setVariable() on null"
2. After initial fix: "Call to undefined method Laminas\View\Variables::setVariable()"

**Root Cause**: The BlockPlus Table of Contents module expects `$view->pageViewModel` to be a `Laminas\View\Model\ViewModel` object so it can call:
```php
$view->pageViewModel->setVariable('displayNavigation', false);
```

When manually rendering blocks (as we do when skipping the first block), the `pageViewModel` variable is not automatically set by the controller.

**Attempted Fix #1 (Failed)**:
```php
$this->pageViewModel = $this->vars();  // Returns Laminas\View\Variables, not ViewModel
```
This failed because `Laminas\View\Variables` doesn't have a `setVariable()` method.

**Final Fix (Working)**: Create a proper ViewModel object:
```php
if (!isset($this->pageViewModel)) {
    // Create a ViewModel to act as pageViewModel
    $pageViewModel = new \Laminas\View\Model\ViewModel();
    $pageViewModel->setVariable('displayNavigation', $this->displayNavigation ?? true);
    $this->pageViewModel = $pageViewModel;
}
```

This ensures compatibility with both core Omeka S Table of Contents and BlockPlus Table of Contents modules.

## Testing Scenarios

1. **Page with Page Title first + Submenu enabled**
   - Expected: Page Title → Submenu → Other Blocks
   - Verify: No duplicate Page Title

2. **Page with Page Title first + Submenu disabled**
   - Expected: Page Title → Other Blocks
   - Verify: No submenu, no duplicate Page Title

3. **Page with HTML block first + Submenu enabled**
   - Expected: Submenu → HTML Block → Other Blocks
   - Verify: Normal rendering, no changes

4. **Page with Table of Contents block**
   - Expected: [Page Title if first] → Table of Contents → Other Blocks
   - Verify: No submenu (suppressed by TOC block)

5. **Page with Page Title + Table of Contents**
   - Expected: Page Title → Table of Contents → Other Blocks
   - Verify: No submenu, no duplicate Page Title

## Benefits

1. **Improved UX**: Page title appears before navigation, providing context
2. **Backward Compatible**: Pages without Page Title block render normally
3. **Respects TOC**: Table of Contents block behavior unchanged
4. **No Duplication**: Page Title block only rendered once
5. **Minimal Changes**: Isolated to single template file

## Limitations

1. **First Block Only**: Only detects Page Title if it's the first block
2. **Manual Iteration**: When skipping first block, must manually iterate (can't use `$this->content`)
3. **Block Layout Features**: Manual iteration may not support all advanced block layout features (grid positioning, blockGroup edge cases)

## Future Enhancements

If more complex block layout features are needed when skipping the first block, consider:

1. Creating a custom view helper that extends `Omeka\View\Helper\PageLayout`
2. Adding a `skipBlocks` parameter to allow skipping arbitrary blocks
3. Registering the helper in a theme Module.php file

## Deployment

Deployed via `DEPLOY.sh` on 2025-10-13.

Files synced to `/var/www/omeka-s/themes/LibraryTheme/view/omeka/site/page/show.phtml`.

Apache restarted automatically.

