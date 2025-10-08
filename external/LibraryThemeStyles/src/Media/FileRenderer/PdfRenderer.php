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

    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $options = array_merge(self::DEFAULT_OPTIONS, $options);
        $escape = $view->plugin('escapeHtml');
        $escapeAttr = $view->plugin('escapeHtmlAttr');
        
        $pdfUrl = $media->originalUrl();
        $title = $media->displayTitle() ?: $media->filename();
        
        $width = $escapeAttr($options['width']);
        $height = $escapeAttr($options['height']);
        
        if ($options['embed_type'] === 'object') {
            // Use object tag (better for some browsers)
            return sprintf(
                '<div class="pdf-viewer-container" style="width: %s; height: %s;">
    <object data="%s" type="application/pdf" width="100%%" height="100%%">
        <p>%s <a href="%s">%s</a></p>
    </object>
</div>',
                $width,
                $height,
                $escapeAttr($pdfUrl),
                $escape($view->translate('Your browser does not support embedded PDFs. Please')),
                $escapeAttr($pdfUrl),
                $escape($view->translate('download the PDF to view it'))
            );
        } else {
            // Use iframe (default, works in most modern browsers)
            return sprintf(
                '<div class="pdf-viewer-container" style="width: %s; height: %s;">
    <iframe src="%s" width="100%%" height="100%%" style="border: none;" title="%s">
        <p>%s <a href="%s">%s</a></p>
    </iframe>
</div>',
                $width,
                $height,
                $escapeAttr($pdfUrl),
                $escapeAttr($title),
                $escape($view->translate('Your browser does not support embedded PDFs. Please')),
                $escapeAttr($pdfUrl),
                $escape($view->translate('download the PDF to view it'))
            );
        }
    }
}

