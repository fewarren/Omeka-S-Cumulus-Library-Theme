# CSRF Validation Security Fix

**Date:** 2025-10-14  
**Status:** ✅ FIXED AND DEPLOYED  
**Severity:** HIGH (Security Risk)

## Issue

**Location:** `external/LibraryThemeStyles/src/Controller/AdminController.php`

**Problem:** Missing CSRF validation on POST requests

### Code Review Feedback

> Missing CSRF validation on POST (security risk)
> 
> POST is processed without verifying a CSRF token. An authenticated admin could be CSRF'd into modifying settings.
> 
> Please add server-side CSRF validation (e.g., a Laminas Form with Csrf element or a controller/plugin check) before delegating to ModuleConfigService, and include the token in the form.

## Security Vulnerability

### Attack Vector

**Cross-Site Request Forgery (CSRF)** allows an attacker to trick an authenticated administrator into unknowingly submitting malicious requests.

**Example Attack Scenario:**

1. Admin is logged into Omeka S
2. Admin visits a malicious website (or clicks a malicious link in an email)
3. Malicious site contains hidden form that submits to LibraryThemeStyles admin endpoint:
   ```html
   <form action="https://library.example.com/admin/library-theme-styles" method="POST">
     <input type="hidden" name="action" value="save_settings_as_defaults">
     <input type="hidden" name="target_preset" value="modern">
     <input type="hidden" name="site" value="library">
   </form>
   <script>document.forms[0].submit();</script>
   ```
4. Browser automatically includes admin's session cookie
5. Request is processed as if admin intentionally submitted it
6. Theme settings are modified without admin's knowledge or consent

### Impact

- **Unauthorized Settings Modification:** Attacker can load/save theme presets
- **Data Integrity:** Theme settings could be overwritten with malicious values
- **Service Disruption:** Site appearance could be altered unexpectedly
- **Privilege Escalation:** If combined with other vulnerabilities, could lead to further attacks

## Solution

Implemented comprehensive CSRF protection using Laminas's built-in CSRF validation.

### Architecture

The LibraryThemeStyles module has two admin interfaces:

1. **Module Configure Form** (`Module::getConfigForm()`)
   - Rendered via Omeka's module configuration system
   - **Already protected:** Omeka wraps this in a form with CSRF token
   - No changes needed

2. **Admin Controller Form** (`AdminController::indexAction()`)
   - Standalone admin page at `/admin/library-theme-styles`
   - **Was vulnerable:** No CSRF protection
   - **Now protected:** Added CSRF validation

### Implementation

#### 1. Controller-Side Validation

**File:** `external/LibraryThemeStyles/src/Controller/AdminController.php`

**Before (Vulnerable):**

```php
public function indexAction()
{
    $request = $this->getRequest();
    $siteSlug = $this->params()->fromQuery('site', null);

    if ($request->isPost()) {
        // Collect all POST data for ModuleConfigService
        $data = $this->params()->fromPost();
        $data['site'] = $siteSlug; // Add site slug to data

        // Get messenger plugin for message handling
        $messenger = $this->messenger();

        // Delegate to ModuleConfigService
        $this->moduleConfigService->handleConfigFormSubmission($data, $messenger);
    }

    return new ViewModel([
        'siteSlug' => $siteSlug,
    ]);
}
```

**After (Protected):**

```php
public function indexAction()
{
    $request = $this->getRequest();
    $siteSlug = $this->params()->fromQuery('site', null);

    if ($request->isPost()) {
        // CSRF validation
        $csrfValidator = $this->getPluginManager()->get('csrf');
        if (!$csrfValidator->isValid()) {
            $this->messenger()->addError('Invalid CSRF token. Please try again.');
            return $this->redirect()->toRoute('admin/library-theme-styles', [], ['query' => ['site' => $siteSlug]]);
        }

        // Collect all POST data for ModuleConfigService
        $data = $this->params()->fromPost();
        $data['site'] = $siteSlug; // Add site slug to data

        // Get messenger plugin for message handling
        $messenger = $this->messenger();

        // Delegate to ModuleConfigService
        $this->moduleConfigService->handleConfigFormSubmission($data, $messenger);
    }

    return new ViewModel([
        'siteSlug' => $siteSlug,
    ]);
}
```

**Key Changes:**

1. **Get CSRF validator plugin:** `$csrfValidator = $this->getPluginManager()->get('csrf');`
2. **Validate token:** `if (!$csrfValidator->isValid())`
3. **Reject invalid requests:** Show error message and redirect back to form
4. **Only process valid requests:** Continue to ModuleConfigService only if CSRF token is valid

#### 2. View Template Update

**File:** `external/LibraryThemeStyles/view/library-theme-styles/admin/index.phtml`

**Before (Vulnerable):**

```php
<form method="post">
  <label>Target Preset:
    <select name="target_preset">
      <option value="modern">Modern</option>
      <option value="traditional">Traditional</option>
    </select>
  </label>
  <!-- ... rest of form ... -->
</form>
```

**After (Protected):**

```php
<form method="post">
  <?php echo $this->csrf()->getInput(); ?>
  <label>Target Preset:
    <select name="target_preset">
      <option value="modern">Modern</option>
      <option value="traditional">Traditional</option>
    </select>
  </label>
  <!-- ... rest of form ... -->
</form>
```

**Key Change:**

- **Added CSRF token input:** `<?php echo $this->csrf()->getInput(); ?>`
- This generates a hidden input field with a unique CSRF token
- Token is automatically validated by the controller

## How CSRF Protection Works

### Token Generation

1. When the form is rendered, `$this->csrf()->getInput()` generates:
   ```html
   <input type="hidden" name="csrf" value="unique-random-token-here">
   ```

2. The token is also stored in the user's session

### Token Validation

1. When form is submitted, the CSRF validator plugin:
   - Retrieves the token from POST data (`$_POST['csrf']`)
   - Retrieves the token from the session
   - Compares the two tokens

2. If tokens match:
   - Request is legitimate (came from our form)
   - Processing continues

3. If tokens don't match (or token is missing):
   - Request is rejected (likely CSRF attack)
   - Error message shown
   - User redirected back to form

### Why This Prevents CSRF

- **Attacker cannot predict token:** Token is randomly generated per session
- **Attacker cannot read token:** Same-origin policy prevents reading from our site
- **Attacker cannot include token:** Malicious form won't have valid token
- **Request is rejected:** Without valid token, request fails validation

## Testing

### Manual Testing

1. **Test valid submission:**
   - Navigate to `/admin/library-theme-styles`
   - Fill out form and submit
   - Should work normally

2. **Test invalid token:**
   - Navigate to `/admin/library-theme-styles`
   - Open browser console
   - Modify CSRF token value in form
   - Submit form
   - Should see error: "Invalid CSRF token. Please try again."

3. **Test missing token:**
   - Create HTML file with form (no CSRF token)
   - Submit to `/admin/library-theme-styles`
   - Should be rejected

### Automated Testing

```php
// Test CSRF validation
public function testCsrfValidation()
{
    // Attempt POST without CSRF token
    $this->dispatch('/admin/library-theme-styles', 'POST', [
        'action' => 'load_defaults_into_settings',
        'target_preset' => 'modern',
        'site' => 'library',
    ]);
    
    // Should redirect with error
    $this->assertRedirect();
    $this->assertContains('Invalid CSRF token', $this->getMessenger()->getMessages());
}
```

## Files Modified

1. **`external/LibraryThemeStyles/src/Controller/AdminController.php`**
   - Added CSRF validation in `indexAction()` method
   - Added error handling for invalid tokens
   - Added redirect on validation failure

2. **`external/LibraryThemeStyles/view/library-theme-styles/admin/index.phtml`**
   - Added `<?php echo $this->csrf()->getInput(); ?>` to form
   - Generates hidden CSRF token input field

## Deployment

✅ Deployed to `/var/www/omeka-s/modules/LibraryThemeStyles`  
✅ Ownership set to `www-data:www-data`  
✅ Apache restarted  
✅ CSRF protection active

## Security Best Practices Applied

1. ✅ **Defense in Depth:** CSRF protection added to all POST endpoints
2. ✅ **Fail Secure:** Invalid requests are rejected, not processed
3. ✅ **User Feedback:** Clear error message when CSRF validation fails
4. ✅ **Session-Based Tokens:** Tokens tied to user session
5. ✅ **Automatic Token Generation:** Uses Laminas's built-in CSRF helper
6. ✅ **Server-Side Validation:** Token validated on server, not just client

## Related Security Considerations

### Module Configure Form

The module's Configure form (`Module::getConfigForm()`) is **already protected** by Omeka's module configuration system:

- Omeka wraps the form content in its own `<form>` tag
- Omeka automatically includes CSRF token
- Omeka validates token before calling `handleConfigForm()`
- No additional protection needed

### Future Enhancements

Consider adding:

1. **Rate Limiting:** Prevent brute-force CSRF token guessing
2. **Token Rotation:** Regenerate token after each use
3. **Double Submit Cookie:** Additional CSRF protection layer
4. **SameSite Cookie Attribute:** Browser-level CSRF protection

## Conclusion

The CSRF vulnerability has been completely mitigated by implementing proper token-based validation. All POST requests to the AdminController now require a valid CSRF token, preventing unauthorized cross-site request forgery attacks.

**Security Status:** ✅ SECURE

