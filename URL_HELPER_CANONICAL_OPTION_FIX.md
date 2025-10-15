# URL Helper Canonical Option Fix

**Date:** 2025-10-14  
**Status:** ✅ FIXED AND DEPLOYED

## Issue

**Location:** Multiple view template files

**Problem:** `url()` helper calls using boolean `true` as third parameter instead of options array

### Code Review Feedback

> In view/omeka/site/index-reference.phtml around lines 76 to 77, the url() helper calls should pass the canonical option as an array; replace the third argument true with ['force_canonical' => true] for both links so the helper receives the options array form.

## Root Cause

The `url()` view helper in Laminas/Zend Framework expects options to be passed as an associative array, not as a boolean value. While passing `true` as the third parameter may work in some cases due to backward compatibility, it's not the correct API usage.

### Incorrect Usage

```php
$this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true)
```

### Correct Usage

```php
$this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['force_canonical' => true])
```

## URL Helper Signature

```php
url(
    string $name,              // Route name
    array $params = [],        // Route parameters
    array $options = [],       // Options (force_canonical, query, etc.)
    bool $reuseMatchedParams = false
)
```

**Options array can include:**
- `force_canonical` - Force absolute URL with scheme and host
- `query` - Query string parameters
- `fragment` - URL fragment (hash)
- `reuse_matched_params` - Reuse matched route parameters

## Files Fixed

### 1. view/search/contact-us.phtml (Line 38)

**Before:**
```php
$consent
    ->setLabel('<a href="' . $this->url('site/page', ['page-slug' => 'avis'], true) . '" target="_blank">He llegit i accepto les condicions d'ús de les imatges</a>')
```

**After:**
```php
$consent
    ->setLabel('<a href="' . $this->url('site/page', ['page-slug' => 'avis'], ['force_canonical' => true]) . '" target="_blank">He llegit i accepto les condicions d'ús de les imatges</a>')
```

### 2. view/omeka/site/item/browse.phtml (Line 154)

**Before:**
```php
<?php echo $this->hyperlink($translate('Advanced search'), $this->url('site/resource', ['controller' => 'item', 'action' => 'search'], ['query' => $query], true), ['class' => 'advanced-search']); ?>
```

**After:**
```php
<?php echo $this->hyperlink($translate('Advanced search'), $this->url('site/resource', ['controller' => 'item', 'action' => 'search'], ['query' => $query, 'force_canonical' => true]), ['class' => 'advanced-search']); ?>
```

**Note:** This case was more complex because the options array already had `['query' => $query]`, so we added `'force_canonical' => true` to the existing array.

### 3. view/omeka/site/index-modern-style.phtml (Line 61)

**Before:**
```php
<form class="search-form" action="<?php echo $url('site/resource', ['controller' => 'item', 'action' => 'browse'], true); ?>" method="get">
```

**After:**
```php
<form class="search-form" action="<?php echo $url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['force_canonical' => true]); ?>" method="get">
```

### 4. view/omeka/site/item/show.phtml (Lines 64-65)

**Before:**
```php
<li><a href="<?= $this->url('site', [], true) ?>"><?= $translate('Home') ?></a></li>
<li><a href="<?= $this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], true) ?>"><?= $translate('Items') ?></a></li>
```

**After:**
```php
<li><a href="<?= $this->url('site', [], ['force_canonical' => true]) ?>"><?= $translate('Home') ?></a></li>
<li><a href="<?= $this->url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['force_canonical' => true]) ?>"><?= $translate('Items') ?></a></li>
```

### 5. view/common/search-form.phtml (Line 6)

**Before:**
```php
case 'cross-site':
    $searchAction = $this->url('site/cross-site-search', ['action' => 'results'], true);
    break;
```

**After:**
```php
case 'cross-site':
    $searchAction = $this->url('site/cross-site-search', ['action' => 'results'], ['force_canonical' => true]);
    break;
```

## Note on index-reference.phtml

The file mentioned in the code review (`view/omeka/site/index-reference.phtml`) was already correct:

```php
<li><a href="<?php echo $url('site/resource', ['controller' => 'item', 'action' => 'browse'], ['force_canonical' => true]); ?>"><?php echo $translate('All Items'); ?></a></li>
<li><a href="<?php echo $url('site/resource', ['controller' => 'item-set', 'action' => 'browse'], ['force_canonical' => true]); ?>"><?php echo $translate('Collections'); ?></a></li>
```

This suggests the file may have been fixed previously, or the code review was based on an older version.

## Why This Matters

### 1. API Correctness

Using the proper array format ensures compatibility with the Laminas/Zend Framework URL helper API.

### 2. Future Compatibility

The boolean shorthand may be deprecated in future versions of the framework.

### 3. Extensibility

The array format allows adding additional options:

```php
$this->url('site/resource', ['controller' => 'item'], [
    'force_canonical' => true,
    'query' => ['sort' => 'title'],
    'fragment' => 'results'
])
```

### 4. Code Clarity

The array format is more explicit about what the parameter does:

```php
// Less clear
$this->url('site', [], true)

// More clear
$this->url('site', [], ['force_canonical' => true])
```

## Testing

### Manual Testing

1. **Test canonical URLs:**
   - Navigate to item browse page
   - Check that "Advanced search" link is absolute URL
   - Verify breadcrumb links are absolute URLs

2. **Test search forms:**
   - Submit search form
   - Verify action URL is correct

3. **Test contact form:**
   - View contact form
   - Check that consent link is absolute URL

### Verification

All URLs should generate absolute URLs with scheme and host when `force_canonical` is true:

```
https://library.example.com/s/library/item
```

Instead of relative URLs:

```
/s/library/item
```

## Files Modified

1. ✅ `view/search/contact-us.phtml` - Fixed consent link
2. ✅ `view/omeka/site/item/browse.phtml` - Fixed advanced search link
3. ✅ `view/omeka/site/index-modern-style.phtml` - Fixed search form action
4. ✅ `view/omeka/site/item/show.phtml` - Fixed breadcrumb links (2 instances)
5. ✅ `view/common/search-form.phtml` - Fixed cross-site search action

## Deployment

✅ Deployed via `DEPLOY.sh`  
✅ Files synced to `/var/www/omeka-s/themes/LibraryTheme`  
✅ Apache restarted

## Best Practices

### Always Use Array Format for Options

```php
// ✅ Correct
$this->url('route', $params, ['force_canonical' => true])

// ❌ Incorrect
$this->url('route', $params, true)
```

### Combining Multiple Options

```php
$this->url('site/resource', ['controller' => 'item'], [
    'force_canonical' => true,
    'query' => ['page' => 2, 'sort' => 'title'],
    'fragment' => 'results'
])
```

### When to Use force_canonical

Use `force_canonical => true` when:
- Generating URLs for external use (emails, RSS feeds)
- Creating shareable links
- Building canonical URLs for SEO
- Cross-site navigation

Don't use it for:
- Internal site navigation (relative URLs are fine)
- AJAX endpoints
- Form actions within the same site

## Conclusion

All instances of `url()` helper calls with boolean `true` as the third parameter have been updated to use the proper array format `['force_canonical' => true]`. This ensures API correctness, future compatibility, and code clarity.

**Status:** ✅ COMPLETE

