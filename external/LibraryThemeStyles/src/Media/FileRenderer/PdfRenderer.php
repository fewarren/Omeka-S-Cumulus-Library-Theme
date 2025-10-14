<?php
namespace LibraryThemeStyles\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;

/**
 * PDF File Renderer
 * Embeds PDF files in an iframe or object tag for immediate viewing
 */
class PdfRenderer implements RendererInterface
{
    const DEFAULT_OPTIONS = [
        'width' => '100%',
        'height' => '800px',
        'embed_type' => 'iframe', // 'iframe' or 'object'
    ];

    /**
     * Render a PDF media item as embedded HTML (iframe or object) for in-browser viewing.
     *
     * Renders a container with either an <iframe> or <object> pointing to the media's original URL
     * (optionally appending toolbar parameters), includes an accessible title and a fallback message
     * with an optional download link, and may emit HTML debug comments when APP_DEBUG is "true".
     *
     * @param PhpRenderer $view View renderer providing helpers (escaping, translation, theme settings, assetUrl).
     * @param MediaRepresentation $media Media representation whose originalUrl(), displayTitle(), and filename() are used.
     * @param array $options Rendering options. Recognized keys:
     *                       - string 'width'  : container width (default '100%')
     *                       - string 'height' : container height (default '800px')
     *                       - string 'embed_type' : 'iframe' or 'object' (default 'iframe')
     * @return string HTML markup for the embedded PDF viewer and any debug comments.
    /**
     * Render HTML for embedding a PDF in the browser using an iframe or object element.
     *
     * Renders a container div sized by the provided options and returns an iframe-based
     * or object-based embedded PDF viewer. Behavior is influenced by theme settings
     * (hiding download links and using a custom PDF.js viewer) and by the `embed_type`
     * option.
     *
     * @param PhpRenderer $view Renderer used to access view helpers, translations, and assets.
     * @param MediaRepresentation $media Media representation providing `originalUrl()`, `displayTitle()`, and `filename()`.
     * @param array $options Rendering options merged with DEFAULT_OPTIONS. Recognized keys:
     *                       - `width` (string): container width (e.g., "100%")
     *                       - `height` (string): container height (e.g., "800px")
     *                       - `embed_type` (string): "iframe" (default) or "object"
     * @return string The HTML string for the embedded PDF viewer (container with iframe or object and accessible fallback).
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $options = array_merge(self::DEFAULT_OPTIONS, $options);
        $escape = $view->plugin('escapeHtml');
        $escapeAttr = $view->plugin('escapeHtmlAttr');

        // Check if downloads should be hidden
        $hideDownloads = $view->plugin('themeSetting')('hide_download_links', '0') === '1';
        $useCustomPdfjs = $view->plugin('themeSetting')('use_custom_pdfjs_viewer', '0') === '1';
        $toolbarParam = $hideDownloads ? '#toolbar=0' : '';

        $pdfUrl = $media->originalUrl() . $toolbarParam;

        // DEBUG: PDF renderer download control logic
        $debugOutput = "\n<!-- DEBUG PDF RENDERER: hide_download_links='" . $view->plugin('themeSetting')('hide_download_links', '0') .
                      "', use_custom_pdfjs_viewer='" . $view->plugin('themeSetting')('use_custom_pdfjs_viewer', '0') .
                      "', hideDownloads=" . ($hideDownloads ? 'TRUE' : 'FALSE') .
                      ", toolbarParam='" . $toolbarParam .
                      "', originalUrl='" . $media->originalUrl() .
                      "', finalPdfUrl='" . $pdfUrl . "' -->\n";
        $title = $media->displayTitle() ?: $media->filename();
        
        $width = $escapeAttr($options['width']);
        $height = $escapeAttr($options['height']);
        
        if ($options['embed_type'] === 'object') {
            // Use object tag (better for some browsers)
            $fallbackMessage = $hideDownloads
                ? $escape($view->translate('Your browser does not support embedded PDFs.'))
                : $escape($view->translate('Your browser does not support embedded PDFs. Please')) . ' <a href="' . $escapeAttr($media->originalUrl()) . '">' . $escape($view->translate('download the PDF to view it')) . '</a>';

            return $debugOutput . sprintf(
                '<div class="pdf-viewer-container" style="width: %s; height: %s;">
    <object data="%s" type="application/pdf" width="100%%" height="100%%">
        <p>%s</p>
    </object>
</div>',
                $width,
                $height,
                $escapeAttr($pdfUrl),
                $fallbackMessage
            );
        } else {
            // Use iframe (default, works in most modern browsers)
            $fallbackMessage = $hideDownloads
                ? $escape($view->translate('Your browser does not support embedded PDFs.'))
                : $escape($view->translate('Your browser does not support embedded PDFs. Please')) . ' <a href="' . $escapeAttr($media->originalUrl()) . '">' . $escape($view->translate('download the PDF to view it')) . '</a>';

            // Use native or custom viewer based on settings
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $isFirefox = strpos($userAgent, 'Firefox') !== false;

            // Enhanced debug output (only in debug mode)
            if (getenv('APP_DEBUG') === 'true') {
                $debugOutput .= "<!-- PDF RENDERER DEBUG: userAgent='" . $userAgent . "', isFirefox=" . ($isFirefox ? 'TRUE' : 'FALSE') . " -->\n";
                $debugOutput .= "<!-- PDF RENDERER CONDITION: hideDownloads(" . ($hideDownloads ? 'T' : 'F') . ") && useCustomPdfjs(" . ($useCustomPdfjs ? 'T' : 'F') . ") = " . (($hideDownloads && $useCustomPdfjs) ? 'TRUE' : 'FALSE') . " -->\n";
            }

            if ($hideDownloads && $useCustomPdfjs) {
                $viewerUrl = $view->assetUrl('pdf-custom-viewer.html') . '?file=' . rawurlencode($media->originalUrl()) . '&hideDownloads=1';
                return $debugOutput . sprintf(
                    '<div class="pdf-viewer-container" style="width: %s; height: %s;">
    <iframe src="%s" width="100%%" height="100%%" style="border: none;" title="%s"></iframe>
</div>',
                    $width,
                    $height,
                    $escapeAttr($viewerUrl),
                    $escapeAttr($title)
                );
            }

            return $debugOutput . sprintf(
                '<div class="pdf-viewer-container" style="width: %s; height: %s;">
    <iframe src="%s" width="100%%" height="100%%" style="border: none;" title="%s">
        <p>%s</p>
    </iframe>
</div>',
                $width,
                $height,
                $escapeAttr($pdfUrl),
                $escapeAttr($title),
                $fallbackMessage
            );
        }
    }
}