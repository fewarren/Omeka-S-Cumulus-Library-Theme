# Download Control Feasibility Analysis

## Executive Summary

**Recommendation**: ✅ **IMPLEMENT** with clear limitations disclosure

A global download control setting is **feasible and safe** to implement at the theme level, but with important limitations that must be clearly communicated to users.

## Feasibility Assessment

### ✅ **What CAN Be Controlled**

#### **1. PDF Viewer Toolbar (Partial)**
- **Method**: Add `#toolbar=0` parameter to PDF iframe URLs
- **Effect**: Hides download button in Chrome, Safari, Edge
- **Limitation**: Firefox ignores this parameter (uses PDF.js)
- **Implementation**: Simple URL modification

#### **2. Fallback Download Links**
- **Method**: Conditional rendering of download links in templates
- **Effect**: Removes explicit "download" prompts from viewers
- **Scope**: All PDF viewers, audio players, video players
- **Implementation**: Theme setting + template conditionals

#### **3. User Experience Improvement**
- **Effect**: Reduces casual downloading, encourages viewing
- **Benefit**: Cleaner interface for viewing-focused sites
- **Use Case**: Educational institutions, reading rooms, preview systems

### ❌ **What CANNOT Be Controlled**

#### **1. Direct File Access**
- **Issue**: Omeka S `originalUrl()` provides direct file URLs
- **Reality**: Files remain accessible via direct URL
- **Limitation**: Theme cannot override Omeka S file serving
- **Bypass**: Right-click → "Save as", browser developer tools

#### **2. True Security**
- **Issue**: Browser-level controls are cosmetic only
- **Reality**: Determined users can always access files
- **Limitation**: No server-side access control at theme level
- **Alternative**: Use Omeka S Access module for real restrictions

#### **3. Core System Behavior**
- **Issue**: Cannot modify Omeka S media serving architecture
- **Scope**: Theme operates at presentation layer only
- **Limitation**: Cannot implement true download prevention

## Technical Implementation Plan

### **1. Global Theme Setting** ✅
```ini
; Download Control
elements.hide_download_links.name = "hide_download_links"
elements.hide_download_links.type = "Laminas\Form\Element\Select"
elements.hide_download_links.options.label = "Hide Download Links"
elements.hide_download_links.options.info = "Hide download buttons and links in media viewers. Note: This is a user experience feature only - files remain accessible via direct URLs."
elements.hide_download_links.options.element_group = "media_controls"
elements.hide_download_links.options.order = 10
elements.hide_download_links.options.value_options.0 = "Show Download Links (Default)"
elements.hide_download_links.options.value_options.1 = "Hide Download Links"
elements.hide_download_links.attributes.value = "0"
```

### **2. PDF Viewer Enhancement** ✅
```php
// In PDF renderer and templates
$hideDownloads = $this->themeSetting('hide_download_links', '0') === '1';
$toolbarParam = $hideDownloads ? '#toolbar=0' : '';
$pdfUrl = $media->originalUrl() . $toolbarParam;

// Conditional fallback link
if (!$hideDownloads) {
    echo '<a href="' . $pdfUrl . '">Download PDF</a>';
}
```

### **3. Template Updates** ✅
- **PDF Viewer**: Add toolbar control, conditional download links
- **Audio Player**: Hide download attributes if setting enabled
- **Video Player**: Remove download options if setting enabled
- **Fallback Messages**: Conditional download link display

### **4. User Communication** ✅
- **Setting Description**: Clear explanation of limitations
- **Admin Interface**: Warning about security implications
- **Documentation**: Honest assessment of what this controls

## Browser Compatibility

### **PDF Toolbar Control**
| Browser | `#toolbar=0` Support | Download Button Hidden |
|---------|---------------------|------------------------|
| Chrome  | ✅ Yes              | ✅ Yes                 |
| Safari  | ✅ Yes              | ✅ Yes                 |
| Edge    | ✅ Yes              | ✅ Yes                 |
| Firefox | ❌ No (PDF.js)      | ❌ No                  |

### **Fallback Link Control**
| Media Type | Control Method | Effectiveness |
|------------|---------------|---------------|
| PDF        | Template conditional | ✅ Complete |
| Audio      | Player attributes | ✅ Complete |
| Video      | Player attributes | ✅ Complete |
| Images     | Lightbox only | ✅ Complete |

## Security Considerations

### **✅ Safe Implementation**
1. **No Security Claims**: Clear messaging that this is UX-only
2. **No Core Modifications**: Theme-level only, respects Omeka S architecture
3. **Graceful Degradation**: Works with/without setting enabled
4. **Honest Limitations**: Documentation explains what this doesn't do

### **⚠️ Important Disclaimers**
1. **Not True Security**: Files remain accessible via direct URLs
2. **Bypass Methods**: Right-click, developer tools, direct URL access
3. **Browser Dependent**: Toolbar hiding varies by browser
4. **Educational Use**: Best for discouraging casual downloads, not preventing access

## Use Cases

### **✅ Appropriate Uses**
- **Educational Institutions**: Encourage reading over downloading
- **Preview Systems**: Focus on viewing experience
- **Reading Rooms**: Reduce download prompts for browsing
- **Clean Interface**: Remove clutter from media viewers

### **❌ Inappropriate Uses**
- **Copyright Protection**: Cannot prevent determined access
- **Sensitive Documents**: Use Omeka S Access module instead
- **True Security**: Requires server-side access controls
- **Legal Compliance**: May not meet regulatory requirements

## Implementation Recommendation

### **✅ PROCEED** with Implementation

**Rationale**:
1. **User Experience Value**: Improves interface for viewing-focused sites
2. **Safe Implementation**: No security claims, honest limitations
3. **Omeka S Best Practices**: Theme-level presentation control only
4. **Clear Communication**: Users understand what this does/doesn't do

**Implementation Approach**:
1. Add global theme setting with clear limitations disclosure
2. Implement PDF toolbar control (`#toolbar=0`)
3. Add conditional download link rendering
4. Update all media viewers (PDF, audio, video)
5. Document limitations clearly in admin interface

**Timeline**: Can be implemented safely as part of PDF viewer enhancement

## Alternative Solutions

### **For True Download Control**
1. **Omeka S Access Module**: Server-side file access restrictions
2. **Watermarked Previews**: Generate preview-only versions
3. **External Viewers**: Use services like Google Docs Viewer
4. **Authentication Systems**: Require login for file access

### **For Enhanced UX**
1. **PDF.js Integration**: Custom viewer with disabled download
2. **Streaming Players**: Audio/video without download options
3. **Image Protection**: Disable right-click, overlay protection
4. **Custom Viewers**: Build specialized media viewers

## Conclusion

The proposed download control feature is **feasible, safe, and valuable** as a user experience enhancement. While it cannot provide true security, it offers legitimate benefits for institutions focused on viewing rather than downloading. The key is honest communication about limitations and appropriate use cases.

**Next Steps**: Proceed with implementation using the outlined approach, ensuring clear documentation of capabilities and limitations.
