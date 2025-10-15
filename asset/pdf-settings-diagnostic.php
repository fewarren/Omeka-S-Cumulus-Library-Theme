<?php
/**
 * PDF Settings Diagnostic Tool
 * 
 * This script helps diagnose PDF viewer settings and routing issues.
 * Place this file in the theme's asset directory and access via:
 * http://your-site.com/themes/LibraryTheme/asset/pdf-settings-diagnostic.php
 */

// Basic security check
if (!isset($_SERVER['HTTP_HOST'])) {
    die('This script must be run via web browser');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Settings Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .button { display: inline-block; padding: 8px 16px; margin: 5px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; }
        .button:hover { background: #005a87; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PDF Settings Diagnostic Tool</h1>
        <p>This tool helps diagnose PDF viewer settings and routing issues in the Library Theme.</p>
        
        <div class="section info">
            <h2>Browser Information</h2>
            <table>
                <tr><th>Property</th><th>Value</th></tr>
                <tr><td>User Agent</td><td><?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?></td></tr>
                <tr><td>Is Firefox</td><td><?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Firefox') !== false) ? 'YES' : 'NO'; ?></td></tr>
                <tr><td>Is Chrome</td><td><?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Chrome') !== false) ? 'YES' : 'NO'; ?></td></tr>
                <tr><td>Is Safari</td><td><?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Safari') !== false && strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Chrome') === false) ? 'YES' : 'NO'; ?></td></tr>
            </table>
        </div>

        <div class="section info">
            <h2>File System Check</h2>
            <?php
            $checks = [
                'PDF Custom Viewer' => 'pdf-custom-viewer.html',
                'PDF.js Viewer' => 'pdfjs/web/viewer.html',
                'PDF.js Build' => 'pdfjs/build/pdf.mjs',
                'PDF.js Worker' => 'pdfjs/build/pdf.worker.mjs'
            ];
            
            echo '<table>';
            echo '<tr><th>Component</th><th>Status</th><th>Path</th></tr>';
            foreach ($checks as $name => $path) {
                $fullPath = __DIR__ . '/' . $path;
                $exists = file_exists($fullPath);
                $status = $exists ? '<span style="color: green;">✓ EXISTS</span>' : '<span style="color: red;">✗ MISSING</span>';
                echo "<tr><td>{$name}</td><td>{$status}</td><td>{$path}</td></tr>";
            }
            echo '</table>';
            ?>
        </div>

        <div class="section warning">
            <h2>Theme Settings Check</h2>
            <p><strong>Note:</strong> This diagnostic cannot directly access Omeka S theme settings from this standalone script.</p>
            <p>To check your current theme settings:</p>
            <ol>
                <li>Go to <strong>Admin → Appearance → Themes</strong></li>
                <li>Click <strong>Configure</strong> on the Library Theme</li>
                <li>Scroll to the <strong>Media Controls</strong> section</li>
                <li>Check these settings:
                    <ul>
                        <li><strong>Hide Download Links:</strong> Should be "Hide Download Links"</li>
                        <li><strong>Use Custom PDF Viewer:</strong> Should be "Enabled"</li>
                    </ul>
                </li>
            </ol>
        </div>

        <div class="section info">
            <h2>Test Links</h2>
            <p>Use these links to test the PDF viewer functionality:</p>
            <a href="pdf-viewer-debug-test.html" class="button">Debug Test Page</a>
            <a href="pdf-custom-viewer.html?file=pdfjs/web/compressed.tracemonkey-pldi-09.pdf&hideDownloads=1" class="button">Test Custom Viewer</a>
            <a href="pdfjs/web/viewer.html?file=compressed.tracemonkey-pldi-09.pdf" class="button">Test PDF.js Directly</a>
        </div>

        <div class="section info">
            <h2>Expected Debug Output</h2>
            <p>When viewing a PDF in Omeka S, you should see HTML comments like this in the page source:</p>
            <pre>&lt;!-- ===== PDF VIEWER ROUTING DEBUG ===== --&gt;
&lt;!-- Theme Setting 'hide_download_links': '1' --&gt;
&lt;!-- Theme Setting 'use_custom_pdfjs_viewer': '1' --&gt;
&lt;!-- hideDownloads boolean: TRUE --&gt;
&lt;!-- useCustomPdfjs boolean: TRUE --&gt;
&lt;!-- User Agent: 'Mozilla/5.0...' --&gt;
&lt;!-- isFirefox: TRUE --&gt;
&lt;!-- isChrome: FALSE --&gt;
&lt;!-- isSafari: FALSE --&gt;
&lt;!-- Condition Check: hideDownloads(T) && useCustomPdfjs(T) = TRUE --&gt;
&lt;!-- ===================================== --&gt;
&lt;!-- DEBUG PDF ROUTING: using custom pdf.js viewer; base='...', viewerUrl='...' --&gt;</pre>
        </div>

        <div class="section warning">
            <h2>Troubleshooting Steps</h2>
            <ol>
                <li><strong>Check Theme Settings:</strong> Ensure both "Hide Download Links" and "Use Custom PDF Viewer" are enabled</li>
                <li><strong>View Page Source:</strong> Look for the debug comments shown above when viewing a PDF</li>
                <li><strong>Check Browser Console:</strong> Look for JavaScript errors or PDF viewer debug messages</li>
                <li><strong>Test Different Browsers:</strong> Try Firefox, Chrome, and Safari to see if behavior differs</li>
                <li><strong>Clear Cache:</strong> Clear browser cache and Omeka S cache</li>
                <li><strong>Check File Permissions:</strong> Ensure PDF.js files are readable by the web server</li>
            </ol>
        </div>

        <div class="section error">
            <h2>Common Issues</h2>
            <ul>
                <li><strong>Settings not saved:</strong> Make sure to click "Save" after changing theme settings</li>
                <li><strong>Wrong browser:</strong> Original implementation was Firefox-only (now fixed for all browsers)</li>
                <li><strong>Missing PDF.js:</strong> Ensure PDF.js assets are properly installed under asset/pdfjs/</li>
                <li><strong>Cache issues:</strong> Clear both browser and server-side caches</li>
                <li><strong>File permissions:</strong> Check that www-data can read the PDF.js files</li>
            </ul>
        </div>

        <div class="section info">
            <h2>Manual Test</h2>
            <p>To manually test if the custom viewer works, try this URL (replace with your actual PDF URL):</p>
            <pre>http://your-site.com/themes/LibraryTheme/asset/pdf-custom-viewer.html?file=YOUR_PDF_URL&hideDownloads=1</pre>
        </div>
    </div>
</body>
</html>
