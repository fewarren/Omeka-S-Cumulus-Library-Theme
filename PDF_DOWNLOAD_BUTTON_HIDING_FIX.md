# PDF Download Button Hiding - Comprehensive Fix

## Problem Analysis

The local Firefox-specific PDF viewer based on PDF.js was still showing the Save/Download icon despite attempts to hide it. After deep analysis, several root causes were identified:

### Root Causes Identified

1. **Incorrect Button Selectors**: The original code used `#download` but the actual button ID is `#downloadButton`
2. **Missing Secondary Toolbar**: The secondary toolbar contains `#secondaryDownload` which wasn't being targeted
3. **Incomplete Localization Handling**: PDF.js uses `data-l10n-id="pdfjs-save-button"` for localized buttons
4. **Timing Issues**: PDF.js renders buttons asynchronously, requiring multiple hiding attempts
5. **Insufficient CSS Specificity**: The injected CSS wasn't comprehensive enough
6. **Missing DOM Removal Fallback**: Hidden buttons could still be re-shown by PDF.js

## Comprehensive Fix Applied

### 1. ✅ **Corrected Button Selectors**

**Before:**
```javascript
const idSelectors = ['#download', '#secondaryDownload', '#print', '#secondaryPrint'];
```

**After:**
```javascript
// Primary download/save buttons (correct IDs from viewer.html)
const downloadSelectors = [
  '#downloadButton',        // Main toolbar download button
  '#secondaryDownload',     // Secondary toolbar download button
  'button[data-l10n-id="pdfjs-save-button"]',  // Localized save buttons
  'button[data-l10n-id*="save"]',              // Any save-related l10n
  'button[data-l10n-id*="download"]'           // Any download-related l10n
];
```

### 2. ✅ **Enhanced CSS Injection**

**Added comprehensive CSS rules:**
```css
#downloadButton { display: none !important; visibility: hidden !important; opacity: 0 !important; }
#secondaryDownload { display: none !important; visibility: hidden !important; opacity: 0 !important; }
button[data-l10n-id*="save"] { display: none !important; }
button[data-l10n-id*="download"] { display: none !important; }
button[data-l10n-id*="print"] { display: none !important; }
```

### 3. ✅ **Aggressive Timing Strategy**

**Before:**
```javascript
setTimeout(hideButtonsInIframe, 100);
setTimeout(hideButtonsInIframe, 500);
setTimeout(hideButtonsInIframe, 1500);
```

**After:**
```javascript
// Multiple attempts with increasing delays
const delays = [50, 100, 200, 500, 1000, 1500, 2000, 3000];
delays.forEach((delay, index) => {
  setTimeout(() => hideButtonsInIframe(), delay);
});

// Continuous monitoring for the first 10 seconds
const continuousCheck = setInterval(() => {
  hideButtonsInIframe();
}, 500);
```

### 4. ✅ **Enhanced Mutation Observer**

**Added intelligent mutation detection:**
```javascript
const mo = new MutationObserver((mutations) => {
  let shouldHide = false;
  mutations.forEach(mutation => {
    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) { // Element node
          const isTargetButton = allSelectors.some(sel => {
            try { return node.matches && node.matches(sel); } catch(_) { return false; }
          });
          if (isTargetButton) shouldHide = true;
        }
      });
    }
  });
  if (shouldHide) hideNodes();
});
```

### 5. ✅ **Nuclear Option - DOM Removal**

**Added fallback DOM removal:**
```javascript
// Nuclear option: Remove buttons from DOM entirely
const removeButtons = () => {
  allSelectors.forEach(sel => {
    try { 
      const elements = doc.querySelectorAll(sel);
      elements.forEach(el => el.remove()); 
    } catch(_) {}
  });
};

// After a delay, check if any buttons are still visible and remove them
setTimeout(() => {
  const stillVisible = allSelectors.some(sel => {
    try {
      const elements = doc.querySelectorAll(sel);
      return Array.from(elements).some(el => el.offsetWidth > 0 && el.offsetHeight > 0);
    } catch(_) { return false; }
  });
  
  if (stillVisible) {
    removeButtons();
  }
}, 1000);
```

### 6. ✅ **Comprehensive Debug Tracing**

**Added detailed logging:**
```javascript
// Enhanced hiding function with comprehensive debug output
const hideNodes = () => {
  let hiddenCount = 0;
  const foundButtons = [];
  
  allSelectors.forEach(sel => {
    try { 
      const elements = doc.querySelectorAll(sel);
      elements.forEach(el => { 
        foundButtons.push({
          selector: sel,
          id: el.id || 'no-id',
          className: el.className || 'no-class',
          tagName: el.tagName,
          l10nId: el.getAttribute('data-l10n-id') || 'no-l10n',
          visible: el.offsetWidth > 0 && el.offsetHeight > 0
        });
        
        // Apply multiple hiding techniques
        el.style.setProperty('display', 'none', 'important'); 
        el.setAttribute('disabled', 'true'); 
        el.setAttribute('aria-hidden', 'true');
        el.style.setProperty('visibility', 'hidden', 'important');
        el.style.setProperty('opacity', '0', 'important');
        el.style.setProperty('pointer-events', 'none', 'important');
        hiddenCount++;
      }); 
    } catch(_) {}
  });
  
  console.log(`[PDF Viewer] Button hiding attempt: found ${foundButtons.length} buttons, hidden ${hiddenCount}`);
  if (foundButtons.length > 0) {
    console.table(foundButtons);
  }
};
```

## Testing Tools

### Debug Test Page
Created `asset/pdf-viewer-debug-test.html` for comprehensive testing:

- **Browser Detection**: Identifies Firefox, Chrome, Safari
- **Real-time Debug Logging**: Captures all console output
- **Iframe Inspection**: Analyzes button visibility in real-time
- **Test PDF Loading**: Uses the included PDF.js test document
- **Button Analysis**: Shows detailed information about found buttons

### Usage Instructions

1. **Open the debug test page:**
   ```
   http://your-site.com/themes/LibraryTheme/asset/pdf-viewer-debug-test.html
   ```

2. **Load a test PDF** and monitor the debug output

3. **Use "Inspect Iframe Content"** to check button visibility

4. **Monitor console output** for detailed hiding attempts

## Expected Results

After applying these fixes, the PDF viewer should:

1. ✅ **Hide all download/save buttons** in the main toolbar
2. ✅ **Hide all download/save buttons** in the secondary toolbar  
3. ✅ **Prevent keyboard shortcuts** (Ctrl+S, Ctrl+P)
4. ✅ **Work across all PDF.js rendering phases**
5. ✅ **Provide detailed debug information** for troubleshooting
6. ✅ **Gracefully handle edge cases** with DOM removal fallback

## Files Modified

- ✅ `asset/pdf-custom-viewer.html` - Main fix implementation
- ✅ `asset/pdf-viewer-debug-test.html` - Debug testing tool (new)

## Deployment

Run the deployment script to apply changes:
```bash
sudo ./DEPLOY.sh
```

## Verification Steps

1. **Enable the custom PDF viewer** in theme settings:
   - Set "Hide Download Links" to "Enabled"
   - Set "Use Custom PDF Viewer" to "Enabled"

2. **Test in Firefox** with a PDF item

3. **Check debug output** in browser console

4. **Verify no download buttons** are visible in the PDF viewer

5. **Test keyboard shortcuts** are disabled (Ctrl+S should not work)

## Troubleshooting

If download buttons are still visible:

1. **Check browser console** for debug output
2. **Use the debug test page** to analyze button detection
3. **Verify PDF.js assets** are properly installed under `asset/pdfjs/`
4. **Clear browser cache** and reload the page
5. **Check theme settings** are properly configured

The comprehensive fix addresses all known causes of the download button visibility issue and provides extensive debugging tools for any remaining edge cases.
