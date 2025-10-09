<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

use LibraryThemeStyles\Config\ModuleConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Centralized error handling service for LibraryThemeStyles module
 * Provides consistent error handling, logging, and user-friendly messages
 */
class ErrorHandler
{
    private LoggerInterface $logger;
    
    /**
     * Initializes the error handler with a logger instance.
     *
     * If no logger is provided, a NullLogger is used as the default to ensure logging calls are safe.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Handle an exception by logging detailed error information and producing a user-facing message.
     *
     * The method logs the exception along with a generated error identifier and optional context,
     * and returns a concise message suitable for presenting to end users which includes the error ID.
     *
     * @param \Throwable $exception The exception to handle and log.
     * @param string $context Optional context string appended to the log entry to aid troubleshooting.
     * @return string The user-facing error message that includes the generated error ID.
     */
    public function handleException(\Throwable $exception, string $context = ''): string
    {
        // Generate cryptographically secure error ID with fallback
        try {
            $errorId = 'lts_error_' . bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            // Fallback to uniqid if secure generation fails
            $errorId = uniqid('lts_error_');
        }

        $contextInfo = $context ? " (Context: {$context})" : '';
        
        // Log the full exception details
        $this->logger->error(
            "LibraryThemeStyles Error [{$errorId}]: {$exception->getMessage()}{$contextInfo}",
            [
                'exception' => $exception,
                'context' => $context,
                'error_id' => $errorId,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]
        );
        
        // Return user-friendly message with error ID for support
        return $this->getUserFriendlyMessage($exception, $errorId);
    }
    
    /**
     * Validate a preset name and return an error message when it is invalid.
     *
     * @return string|null An error message when the preset is invalid, `null` otherwise.
     */
    public function validatePreset(string $preset): ?string
    {
        if (!ModuleConfig::isValidPreset($preset)) {
            $this->logger->warning("Invalid preset requested: {$preset}");
            return ModuleConfig::getErrorMessage('unknown_preset', $preset);
        }
        
        return null;
    }
    
    /**
     * Validate a site slug and produce an error message when a required slug is missing.
     *
     * @param string|null $siteSlug The site slug to validate.
     * @param bool $required Whether a non-empty site slug is required (defaults to true).
     * @return string|null An error message when the slug is required but missing, or `null` if no error.
     */
    public function validateSiteSlug(?string $siteSlug, bool $required = true): ?string
    {
        if ($required && empty($siteSlug)) {
            $this->logger->warning("Missing required site slug");
            return ModuleConfig::getErrorMessage('missing_site_slug');
        }
        
        return null;
    }
    
    /**
     * Validate theme settings and return any validation error messages.
     *
     * Checks that each setting value is a string; validates keys containing `_color` as colors
     * and keys containing `_font_size` as font sizes. Logs a warning if any errors are found.
     *
     * @param array<string,mixed> $settings Associative map of setting keys to values to validate.
     * @return string[] An array of validation error messages (empty if all settings are valid).
     */
    public function validateThemeSettings(array $settings): array
    {
        $errors = [];
        
        foreach ($settings as $key => $value) {
            if (!is_string($value)) {
                $errors[] = "Setting '{$key}' must be a string, got " . gettype($value);
                continue;
            }
            
            // Validate color values
            if (str_contains($key, '_color') && !ModuleConfig::isValidColor($value)) {
                $errors[] = "Setting '{$key}' has invalid color format: {$value}";
            }
            
            // Validate font size values
            if (str_contains($key, '_font_size') && !ModuleConfig::isValidFontSize($value)) {
                $errors[] = "Setting '{$key}' has invalid font size format: {$value}";
            }
        }
        
        if (!empty($errors)) {
            $this->logger->warning("Theme settings validation failed", ['errors' => $errors]);
        }
        
        return $errors;
    }
    
    /**
     * Log an API-related exception and produce a module-specific error message.
     *
     * @param \Throwable $exception The caught exception from the API operation.
     * @param string $operation A short identifier or description of the API operation that failed.
     * @return string A module-specific error message for API failures that includes the exception message.
     */
    public function handleApiError(\Throwable $exception, string $operation): string
    {
        $this->logger->error(
            "API error during {$operation}: {$exception->getMessage()}",
            [
                'operation' => $operation,
                'exception' => $exception,
            ]
        );
        
        return ModuleConfig::getErrorMessage('api_error', $exception->getMessage());
    }
    
    /**
     * Record a successful operation to the logger with optional contextual data.
     *
     * @param string $operation The name or description of the operation that succeeded.
     * @param array $context Optional additional context to include in the log entry.
     */
    public function logSuccess(string $operation, array $context = []): void
    {
        $this->logger->info("LibraryThemeStyles: {$operation}", $context);
    }
    
    /**
         * Produce a user-facing error message tailored to the given exception.
         *
         * The message is adjusted for specific exception types or content and always
         * includes the provided error ID appended in parentheses.
         *
         * @param \Throwable $exception The exception to derive the message from.
         * @param string $errorId The error identifier to append to the returned message.
         * @return string The composed user-facing message including the error ID.
         */
    private function getUserFriendlyMessage(\Throwable $exception, string $errorId): string
    {
        $baseMessage = "An error occurred while processing your request.";
        
        // Customize message based on exception type
        if ($exception instanceof \InvalidArgumentException) {
            $baseMessage = "Invalid input provided: " . $exception->getMessage();
        } elseif ($exception instanceof \RuntimeException) {
            $baseMessage = "Operation failed: " . $exception->getMessage();
        } elseif (str_contains($exception->getMessage(), 'API')) {
            $baseMessage = "Database operation failed. Please try again.";
        }
        
        return "{$baseMessage} (Error ID: {$errorId})";
    }
    
    /**
     * Execute a callable with centralized error handling and return a standardized result payload.
     *
     * If the operation completes successfully the payload contains the returned value; if an exception is thrown
     * the exception is handled centrally and an error message is returned. When a non-empty $context is provided
     * a success entry is logged on successful execution.
     *
     * @param callable $operation The operation to execute.
     * @param string $context Optional context label used in logging and error reporting.
     * @return array{
     *     success: bool,
     *     data: mixed|null,
     *     error: string|null
     * } An associative array where `success` indicates operation outcome, `data` holds the operation result on success (or null on failure), and `error` holds a user-facing error message on failure (or null on success).
     */
    public function wrapOperation(callable $operation, string $context = ''): array
    {
        try {
            $result = $operation();
            
            // Log success if context provided
            if ($context) {
                $this->logSuccess($context, ['result_type' => gettype($result)]);
            }
            
            return ['success' => true, 'data' => $result, 'error' => null];
            
        } catch (\Throwable $exception) {
            $errorMessage = $this->handleException($exception, $context);
            return ['success' => false, 'data' => null, 'error' => $errorMessage];
        }
    }
    
    /**
         * Builds a standardized error response array for API consumers.
         *
         * @param string $message The human-readable error message to include.
         * @param array $details Optional additional error details.
         * @return array Associative array containing:
         *               - `success` => false
         *               - `error` => the provided message
         *               - `details` => the provided details array
         *               - `timestamp` => ISO 8601 formatted timestamp
         */
    public function createErrorResponse(string $message, array $details = []): array
    {
        return [
            'success' => false,
            'error' => $message,
            'details' => $details,
            'timestamp' => date('c'),
        ];
    }
    
    /**
     * Builds a standardized success response payload for API consumers.
     *
     * @param mixed $data The response payload to return under the `data` key.
     * @param string $message Optional human-readable message describing the result.
     * @return array An associative array with keys:
     *               - `success` (bool): always true
     *               - `data` (mixed): the provided payload
     *               - `message` (string): the provided message
     *               - `timestamp` (string): ISO 8601 formatted timestamp
     */
    public function createSuccessResponse($data, string $message = ''): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('c'),
        ];
    }
}