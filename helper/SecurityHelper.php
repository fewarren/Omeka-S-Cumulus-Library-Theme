<?php declare(strict_types=1);

namespace OmekaTheme\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Security Helper for Sufism Library Theme
 * Provides additional security functions for template escaping and validation
 */
class SecurityHelper extends AbstractHelper
{
    /**
     * Return the helper instance to allow the helper to be invoked as a function.
     *
     * @return self The helper instance.
     */
    public function __invoke(): self
    {
        return $this;
    }
    
    /**
     * Escape HTML content and optionally preserve a small, safe subset of basic tags.
     *
     * When $allowBasicTags is true, a limited whitelist of tags is retained and
     * dangerous attributes are removed before the content is escaped.
     *
     * @param string $content The content to sanitize and escape.
     * @param bool $allowBasicTags Whether to retain a small, safe subset of basic HTML tags before escaping.
     * @return string The sanitized and escaped content safe for output.
     */
    public function secureEscape($content, $allowBasicTags = false): string
    {
        if (empty($content)) {
            return '';
        }
        
        $escape = $this->getView()->plugin('escapeHtml');
        
        if ($allowBasicTags) {
            // Allow only safe HTML tags
            $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            $content = strip_tags($content, $allowedTags);
            
            // Remove potentially dangerous attributes
            $content = preg_replace('/(<[^>]+)\s+(on\w+|javascript:|data:|style=)[^>]*>/i', '$1>', $content);
        }
        
        return $escape($content);
    }
    
    /**
     * Generates and returns a CSRF token for the current session.
     *
     * Ensures a PHP session is started and stores a 32-byte random token
     * (hex-encoded) in $_SESSION['csrf_token'] if none exists.
     *
     * @return string The CSRF token stored in the session.
     */
    public function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
         * Check whether a provided token matches the stored CSRF token.
         *
         * Ensures a PHP session is started, then compares the provided token against the value stored in $_SESSION['csrf_token'] using a timing-attack-resistant comparison.
         *
         * @param string $token The CSRF token to validate.
         * @return bool `true` if the provided token matches the stored CSRF token, `false` otherwise.
         */
    public function validateCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate a cryptographically secure hex string of the given length.
     *
     * @param int $length Desired length in characters of the resulting hex string.
     * @return string Hex-encoded string of the specified length composed of cryptographically secure random bytes.
     */
    public function generateSecureId(int $length = 16): string
    {
        // Generate ceil($length/2) bytes and trim hex output to exact length
        $bytes = (int) ceil($length / 2);
        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }
    
    /**
         * Return a URL sanitized for safe output.
         *
         * Strips leading dangerous protocols (`javascript:`, `data:`, `vbscript:`) and validates the result.
         * If the sanitized value is neither a valid absolute URL nor a root-relative path, `'#'` is returned.
         *
         * @param string $url URL to sanitize
         * @return string Sanitized URL; `'#'` if the input is invalid or unsafe
         */
    public function sanitizeUrl(string $url): string
    {
        // Remove dangerous protocols
        $url = preg_replace('/^(javascript|data|vbscript):/i', '', $url);
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\//', $url)) {
            return '#';
        }
        
        return $url;
    }
    
    /**
     * Retrieve a theme setting and strip embedded script/iframe/object/embed tags from string values.
     *
     * @param string $setting The theme setting name.
     * @param mixed $default Value to return if the setting is not set.
     * @return mixed The setting value; if a string, any occurrences of `<script`, `<iframe`, `<object`, or `<embed` (case-insensitive) are removed. 
     */
    public function getSecureThemeSetting(string $setting, $default = null)
    {
        $themeSetting = $this->getView()->plugin('themeSetting');
        $value = $themeSetting($setting, $default);
        
        // Sanitize based on setting type
        if (is_string($value)) {
            // Remove potentially dangerous content
            $value = preg_replace('/(<script|<iframe|<object|<embed)/i', '', $value);
        }
        
        return $value;
    }
    
    /**
     * Validate and sanitize input according to the specified type.
     *
     * Supported $type values:
     * - 'email'  : returns the email string if valid, `false` otherwise.
     * - 'url'    : returns the URL string if valid, `false` otherwise.
     * - 'int'    : returns the integer value if valid, `false` otherwise.
     * - 'float'  : returns the float value if valid, `false` otherwise.
     * - 'text'   : removes null bytes and control characters, then trims and returns the string.
     *
     * @param string $input The value to validate or sanitize.
     * @param string $type  The validation type: 'email', 'url', 'int', 'float', or 'text'.
     * @return string|int|float|false The validated or sanitized value on success; `false` if validation fails. 
     */
    public function validateInput(string $input, string $type = 'text')
    {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
                
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
                
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
                
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT);
                
            case 'text':
            default:
                // Remove null bytes and control characters
                $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
                return trim($input);
        }
    }
    
    /**
     * Get the Content Security Policy (CSP) nonce for the current session.
     *
     * Ensures a PHP session is started and generates a base64-encoded 16-byte nonce
     * stored in $_SESSION['csp_nonce'] if one does not already exist.
     *
     * @return string The base64-encoded 16-byte CSP nonce stored in the session.
     */
    public function generateCspNonce(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csp_nonce'])) {
            $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
        }
        
        return $_SESSION['csp_nonce'];
    }
    
    /**
     * Determine whether the current HTTP request was made over HTTPS.
     *
     * @return bool `true` if the request is over HTTPS, `false` otherwise.
     */
    public function isSecureRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
         * Enforces a session-backed rate limit for a given identifier.
         *
         * Tracks requests in the PHP session and allows up to `$maxRequests` within a rolling `$timeWindow` (seconds); the count resets after the time window elapses.
         *
         * @param string $identifier Unique key to identify the requester (e.g., IP address or user ID).
         * @param int $maxRequests Maximum allowed requests within the time window.
         * @param int $timeWindow Time window in seconds used to count requests.
         * @return bool `true` if the request is permitted under the limit, `false` otherwise.
         */
    public function checkRateLimit(string $identifier, int $maxRequests = 60, int $timeWindow = 3600): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if ($now - $data['start'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxRequests) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Record a security-related event with attached context and metadata.
     *
     * The entry includes a timestamp, client IP, user agent, the provided event string,
     * and any additional context. The default implementation emits a JSON-encoded entry
     * to PHP's error log.
     *
     * @param string $event Short event message or identifier.
     * @param array $context Additional contextual data to include in the log entry.
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context
        ];
        
        // In a production environment, this should write to a proper log file
        // For now, we'll use error_log
        error_log('SECURITY EVENT: ' . json_encode($logEntry));
    }
}