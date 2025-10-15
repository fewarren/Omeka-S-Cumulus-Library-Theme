<?php declare(strict_types=1);

namespace OmekaTheme\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Security Helper for Sufism Library Theme
 * Provides additional security functions for template escaping and validation
 */
class SecurityHelper extends AbstractHelper
{
    public function __invoke(): self
    {
        return $this;
    }
    
    /**
     * Safely escape HTML content with additional security measures
     *
     * @param string $content Content to escape
     * @param bool $allowBasicTags Whether to allow basic HTML tags
     * @return string Escaped content
     */
    public function secureEscape($content, bool $allowBasicTags = false): string
    {
        if (empty($content)) {
            return '';
        }

        if ($allowBasicTags) {
            // Allow only safe HTML tags
            $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            $content = strip_tags($content, $allowedTags);

            // Use DOMDocument for robust attribute sanitization
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//*[@*]');

            foreach ($nodes as $node) {
                $attributesToRemove = [];
                foreach ($node->attributes as $attr) {
                    // Remove event handlers and dangerous protocols
                    if (preg_match('/^on/i', $attr->name) ||
                        preg_match('/javascript:|data:|vbscript:/i', $attr->value)) {
                        $attributesToRemove[] = $attr->name;
                    }
                }
                foreach ($attributesToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }

            $content = $dom->saveHTML();
            $content = preg_replace('/^<\?xml[^>]+>/', '', $content);
            return $content;
        } else {
            $escape = $this->getView()->plugin('escapeHtml');
            return $escape($content);
        }
    }
    
    /**
     * Generate secure CSRF token for forms
     *
     * @return string CSRF token
     */
    public function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) ||
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     *
     * @param string $token Token to validate
     * @return bool Whether token is valid
     */
    public function validateCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $valid = isset($_SESSION['csrf_token']) &&
                 isset($_SESSION['csrf_token_time']) &&
                 (time() - $_SESSION['csrf_token_time']) <= 3600 &&
                 hash_equals($_SESSION['csrf_token'], $token);

        // Invalidate token after use (single-use)
        if ($valid) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
        }

        return $valid;
    }
    
    /**
     * Generate secure random string for IDs
     * 
     * @param int $length Length of string
     * @return string Random string
     */
    public function generateSecureId(int $length = 16): string
    {
        // Generate ceil($length/2) bytes and trim hex output to exact length
        $bytes = (int) ceil($length / 2);
        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }
    
    /**
     * Sanitize URL for safe output
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    public function sanitizeUrl(string $url): string
    {
        // Remove dangerous protocols
        $url = preg_replace('/^(javascript|data|vbscript|file|about|blob):/i', '', $url);

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\//', $url)) {
            return '#';
        }

        // Basic SSRF protection: block common internal hosts
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host && (
                $host === 'localhost' ||
                preg_match('/^127\./', $host) ||
                preg_match('/^10\./', $host) ||
                preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) ||
                preg_match('/^192\.168\./', $host)
            )) {
                return '#';
            }
        }

        return $url;
    }
    
    /**
     * Get theme setting with security validation
     * 
     * @param string $setting Setting name
     * @param mixed $default Default value
     * @return mixed Setting value
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
     * Validate and sanitize user input
     * 
     * @param string $input User input
     * @param string $type Type of validation (email, url, text, etc.)
     * @return string|false Sanitized input or false if invalid
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
     * Generate Content Security Policy nonce
     *
     * @return string CSP nonce
     */
    public function generateCspNonce(): string
    {
        // Generate a fresh nonce for each request
        return base64_encode(random_bytes(16));
    }
    
    /**
     * Check if current request is HTTPS
     *
     * NOTE: X-Forwarded-Proto check requires a trusted reverse proxy (e.g., nginx, Apache).
     * Disable this check if not behind a proxy to prevent header spoofing.
     *
     * @return bool Whether request is secure
     */
    public function isSecureRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * Rate limiting check (basic implementation)
     *
     * WARNING: Session-based rate limiting is easily bypassed and only suitable for
     * low-security scenarios or development environments. For production, consider:
     * - Redis/Memcached: Shared state across servers, atomic operations
     * - Database: Persistent tracking with cleanup jobs
     * - API Gateway/CDN: Cloudflare, AWS WAF, etc.
     *
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @return bool Whether request is allowed
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
     * Log security event (basic implementation)
     *
     * @param string $event Event description
     * @param array $context Additional context
     * @return void
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        // Sanitize context to prevent log injection
        $sanitizedContext = array_map(function($value) {
            if (is_string($value)) {
                // Remove newlines and control characters
                return preg_replace('/[\x00-\x1F\x7F]/', '', $value);
            }
            return $value;
        }, $context);

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $sanitizedContext
        ];

        // In a production environment, this should write to a proper log file
        // For now, we'll use error_log
        error_log('SECURITY EVENT: ' . json_encode($logEntry));
    }
}
