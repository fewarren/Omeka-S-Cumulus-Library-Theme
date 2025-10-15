# Media Viewer Implementation - Direct Access to Viewers/Players

## Problem Statement

When users clicked on thumbnails for media items (PDF, audio, video, images), the behavior was inconsistent:
- **Video thumbnails**: Worked correctly - opened media page with inline video player (VideoThumbnails module)
- **Image thumbnails**: Worked correctly - opened lightbox with original image
- **PDF thumbnails**: BROKEN - opened media page but showed only thumbnail image with download link, no viewer
- **Audio thumbnails**: BROKEN - opened media page but player may not display correctly

## Root Cause Analysis

### PDF Issue
Omeka S core does NOT include a PDF file renderer. When `$media->render()` is called for a PDF:
1. File renderer tries to find a renderer for `application/pdf` media type → NOT FOUND
2. Tries file extension `pdf` → NOT FOUND  
3. Falls back to `ThumbnailRenderer` → Shows thumbnail image with download link, NOT a viewer

### Audio Issue
Omeka S core HAS an AudioRenderer, but:
- VideoThumbnails module overrides it with custom AudioRenderer
- The renderer should output HTML5 `<audio>` player
- May not be displaying correctly on media show page

### Video & Image (Working)
- **Video**: VideoThumbnails module provides custom VideoRenderer that outputs `<video>` tags
- **Images**: Custom implementation bypasses media show page and opens lightbox directly

## Solution Implemented

### Approach: Template-Level Type Detection and Embedding

Instead of trying to register custom file renderers (which requires complex module infrastructure), we modified the media show template to detect media types and embed appropriate viewers/players directly.

### Modified File: `view/omeka/site/media/show.phtml`

**Lines 36-73**: Smart media rendering logic

```php
<?php $this->trigger('view.show.before'); ?>
<?php
    // Smart media rendering: detect type and embed appropriate viewer/player
    if (isset($media) && method_exists($media, 'mediaType')) {
        $mediaType = (string) $media->mediaType();
        $escape = $this->plugin('escapeHtml');
        $escapeAttr = $this->plugin('escapeHtmlAttr');
        
        // PDF: Embed in iframe for immediate viewing
        if (stripos($mediaType, 'pdf') !== false && $media->hasOriginal()) {
            $pdfUrl = $media->originalUrl();
            $title = $media->displayTitle() ?: $media->filename();
            echo "\n<!-- DEBUG LT media/show.phtml: PDF embedded viewer -->\n";
            echo sprintf(
                '<div class="pdf-viewer-container" style="width: 100%%; height: 800px; margin-bottom: 20px;">
    <iframe src="%s" width="100%%" height="100%%" style="border: 1px solid #ccc;" title="%s">
        <p>%s <a href="%s">%s</a></p>
    </iframe>
</div>',
                $escapeAttr($pdfUrl),
                $escapeAttr($title),
                $escape($this->translate('Your browser does not support embedded PDFs. Please')),
                $escapeAttr($pdfUrl),
                $escape($this->translate('download the PDF to view it'))
            );
        }
        // Audio: Ensure HTML5 audio player renders
        elseif (stripos($mediaType, 'audio/') === 0 && $media->hasOriginal()) {
            echo "\n<!-- DEBUG LT media/show.phtml: Audio player via media->render() -->\n";
            echo $media->render(['controls' => true, 'preload' => 'metadata']);
        }
        // Video and other types: use standard renderer
        else {
            echo "\n<!-- DEBUG LT media/show.phtml: Standard viewer via media->render() -->\n";
            echo $media->render();
        }
    }
?>
```

### How It Works

1. **Type Detection**: Uses `$media->mediaType()` to get MIME type (e.g., 'application/pdf', 'audio/mpeg', 'video/mp4')

2. **PDF Handling**:
   - Detects PDF via `stripos($mediaType, 'pdf')`
   - Embeds PDF in an `<iframe>` pointing to `$media->originalUrl()`
   - Iframe dimensions: 100% width, 800px height
   - Fallback message for browsers that don't support embedded PDFs

3. **Audio Handling**:
   - Detects audio via `stripos($mediaType, 'audio/')`
   - Calls `$media->render()` with explicit options: `['controls' => true, 'preload' => 'metadata']`
   - This ensures the AudioRenderer (core or VideoThumbnails) outputs an HTML5 `<audio>` player

4. **Video & Other Types**:
   - Falls through to standard `$media->render()`
   - VideoThumbnails module handles video rendering
   - Other types use their registered renderers

### Complete Media Type Flow

| Media Type | Thumbnail Click | Destination | Viewer/Player |
|------------|----------------|-------------|---------------|
| **Image** | File block, media-embeds, item gallery | Lightbox (original image) | Lightbox overlay |
| **PDF** | File block, media-embeds, item gallery | Media show page | Embedded iframe viewer |
| **Audio** | File block, media-embeds, item gallery | Media show page | HTML5 audio player |
| **Video** | File block, media-embeds, item gallery | Media show page | HTML5 video player (VideoThumbnails) |

### Thumbnail Linking Logic

All three thumbnail templates implement type-aware linking:

1. **`view/common/block-layout/file.phtml`** (File block)
2. **`view/common/resource-page-block-layout/media-embeds.phtml`** (Media embeds)
3. **`view/omeka/site/item/show.phtml`** (Item show gallery)

**Type Detection Pattern**:
```php
$mediaType = method_exists($media, 'mediaType') ? (string) $media->mediaType() : '';
$isImage = (stripos($mediaType, 'image/') === 0);
$isPDF = (stripos($mediaType, 'pdf') !== false);
$isAudio = (stripos($mediaType, 'audio/') === 0);
$isVideo = (stripos($mediaType, 'video/') === 0);
```

**Link Target Logic**:
```php
if ($isImage && $media->hasOriginal()) {
    // Link to original image with lightbox attributes
    $linkUrl = $media->originalUrl();
    $linkAttribs = [
        'class' => 'lightbox-trigger',
        'data-lightbox' => 'gallery-name',
        'data-title' => $title,
    ];
} else {
    // Link to media show page (PDF, audio, video, etc.)
    $linkUrl = $media->url();
}
```

## Testing Results

### PDF Viewer ✅ WORKING
- Clicking PDF thumbnail navigates to media show page
- Page displays embedded PDF viewer in iframe
- PDF loads and displays immediately
- User can scroll through PDF, zoom, etc.
- Fallback message appears if browser doesn't support embedded PDFs

### Audio Player (Needs Testing)
- Clicking audio thumbnail should navigate to media show page
- Page should display HTML5 audio player with controls
- User should be able to play/pause, adjust volume, seek

### Video Player ✅ WORKING (Already confirmed)
- VideoThumbnails module handles video rendering
- Displays inline video player on media show page

### Image Lightbox ✅ WORKING (Already confirmed)
- Images open in lightbox overlay
- Shows original full-resolution image
- No navigation to media show page

## Browser Compatibility

### PDF Viewer
- **Modern browsers** (Chrome, Firefox, Edge, Safari): Native PDF rendering in iframe
- **Older browsers**: Fallback to download link
- **Mobile**: Most mobile browsers support PDF viewing

### Audio Player
- **All modern browsers**: HTML5 `<audio>` tag support
- **Formats**: MP3, OGG, WAV (browser-dependent)
- **VideoThumbnails module**: May provide derivative audio formats for better compatibility

## Future Enhancements

### Optional Improvements
1. **PDF Viewer Options**:
   - Add toolbar controls (zoom, page navigation)
   - Use PDF.js library for enhanced viewer
   - Make height configurable via theme settings

2. **Audio Player Styling**:
   - Custom player skin to match site design
   - Playlist support for multiple audio files
   - Waveform visualization

3. **Responsive Design**:
   - Adjust iframe height for mobile devices
   - Touch-friendly controls for audio/video players

4. **Performance**:
   - Lazy loading for large PDFs
   - Streaming support for large audio/video files

## Debug Markers

All templates include DEBUG comments for troubleshooting:
- `<!-- DEBUG LT media/show.phtml: PDF embedded viewer -->`
- `<!-- DEBUG LT media/show.phtml: Audio player via media->render() -->`
- `<!-- DEBUG LT media/show.phtml: Standard viewer via media->render() -->`
- `<!-- DEBUG LT file.phtml: image link=original lightbox -->`
- `<!-- DEBUG LT file.phtml: override link target to media viewer for playable/viewable media -->`

**TODO**: Remove DEBUG comments once all media types are tested and confirmed working.

## Deployment

Files modified:
- `view/omeka/site/media/show.phtml` - Smart media rendering
- `view/common/block-layout/file.phtml` - Type-aware linking
- `view/common/resource-page-block-layout/media-embeds.phtml` - Type-aware linking
- `view/omeka/site/item/show.phtml` - Type-aware linking

Deploy with:
```bash
sudo ./DEPLOY.sh
```

## Architecture Notes

### Why Template-Level Instead of File Renderer?

**Attempted Approach**: Register custom PDF renderer in LibraryThemeStyles module
- Created `external/LibraryThemeStyles/src/Media/FileRenderer/PdfRenderer.php`
- Added `file_renderers` configuration to `external/LibraryThemeStyles/config/module.config.php`
- Deployed to `/var/www/omeka-s/modules/LibraryThemeStyles/`

**Issue**: File renderer registration didn't work, likely due to:
- Module loading order
- Service manager configuration
- Cache issues

**Chosen Approach**: Template-level detection and embedding
- **Pros**: 
  - Simpler implementation
  - No module infrastructure required
  - Works immediately without cache clearing
  - Easy to customize per media type
  - Follows Omeka S template override pattern
- **Cons**:
  - Logic in template instead of service layer
  - Duplicates some type detection logic
  - Only works on media show page (not in other contexts)

For this use case, the template-level approach is appropriate because:
1. The goal is specifically to fix the media show page experience
2. Other contexts (File blocks, media embeds) already have custom linking logic
3. The implementation is straightforward and maintainable
4. It follows Omeka S best practices for theme customization

## Compatibility

- **Omeka S Version**: Tested on Omeka S 4.x
- **Required Modules**: None (works with core Omeka S)
- **Optional Modules**: VideoThumbnails (DerivativeMedia) for enhanced audio/video rendering
- **Theme**: LibraryTheme
- **PHP Version**: 7.4+

