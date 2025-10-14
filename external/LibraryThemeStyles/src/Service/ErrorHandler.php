<?php declare(strict_types=1);

namespace LibraryThemeStyles\Service;

/**
 * Error handling service for LibraryThemeStyles module
 * 
 * Provides centralized error logging and handling functionality
 * with support for different error levels and contexts.
 */
class ErrorHandler
{
    /**
     * Log an error message
     * 
     * @param string $message Error message
     * @param array $context Additional context data
     * @return void
     */
    public function logError(string $message, array $context = []): void
    {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log('[LibraryThemeStyles] ERROR: ' . $message . $contextStr);
    }

    /**
     * Log a warning message
     * 
     * @param string $message Warning message
     * @param array $context Additional context data
     * @return void
     */
    public function logWarning(string $message, array $context = []): void
    {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log('[LibraryThemeStyles] WARNING: ' . $message . $contextStr);
    }

    /**
     * Log an info message
     * 
     * @param string $message Info message
     * @param array $context Additional context data
     * @return void
     */
    public function logInfo(string $message, array $context = []): void
    {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log('[LibraryThemeStyles] INFO: ' . $message . $contextStr);
    }

    /**
     * Log a debug message
     * 
     * @param string $message Debug message
     * @param array $context Additional context data
     * @return void
     */
    public function logDebug(string $message, array $context = []): void
    {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log('[LibraryThemeStyles] DEBUG: ' . $message . $contextStr);
    }

    /**
     * Handle an exception by logging it
     * 
     * @param \Throwable $e Exception to handle
     * @param string $context Context description
     * @return void
     */
    public function handleException(\Throwable $e, string $context = ''): void
    {
        $message = $context ? "{$context}: {$e->getMessage()}" : $e->getMessage();
        $this->logError($message, [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Validate a value and log error if invalid
     * 
     * @param mixed $value Value to validate
     * @param callable $validator Validation function that returns bool
     * @param string $errorMessage Error message if validation fails
     * @return bool True if valid, false otherwise
     */
    public function validateAndLog($value, callable $validator, string $errorMessage): bool
    {
        if (!$validator($value)) {
            $this->logError($errorMessage, ['value' => $value]);
            return false;
        }
        return true;
    }
}

