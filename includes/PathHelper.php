<?php
/**
 * PathHelper - Dynamically resolves asset paths
 * Place this in: components/helpers/PathHelper.php
 */
class PathHelper {
    private static ?string $baseUrl = null;
    private static ?string $basePath = null;

    /**
     * Initialize the path helper
     */
    public static function init(): void {
        // Get the base path (directory where index.php is located)
        self::$basePath = dirname($_SERVER['SCRIPT_FILENAME']);
        
        // Get the base URL from the request
        // Fixed: Check HTTPS correctly
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        
        // Clean up the script name
        $scriptName = rtrim($scriptName, '/\\');
        
        // If scriptName is just a dot or empty, don't include it
        if ($scriptName === '.' || $scriptName === '') {
            self::$baseUrl = $protocol . '://' . $host;
        } else {
            self::$baseUrl = $protocol . '://' . $host . $scriptName;
        }
    }

    /**
     * Get the base URL
     */
    public static function getBaseUrl(): string {
        if (self::$baseUrl === null) {
            self::init();
        }
        return self::$baseUrl;
    }

    /**
     * Get the base path
     */
    public static function getBasePath(): string {
        if (self::$basePath === null) {
            self::init();
        }
        return self::$basePath;
    }

    /**
     * Get asset URL (for CSS, JS, images)
     */
    public static function asset(string $path): string {
        // Remove leading slash if present
        $path = ltrim($path, '/');
        return self::getBaseUrl() . '/' . $path;
    }

    /**
     * Check if a file exists in the project
     */
    public static function fileExists(string $path): bool {
        $path = ltrim($path, '/');
        $fullPath = self::getBasePath() . '/' . $path;
        return file_exists($fullPath);
    }

    /**
     * Get URL for a page (routes through index.php)
     */
    public static function page(string $pageName, array $params = []): string {
        // Remove any .php extension or /index from page name
        $pageName = str_replace(['.php', '/index'], '', $pageName);
        
        $url = self::getBaseUrl() . '/index.php?page=' . urlencode($pageName);
        
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $url .= '&' . urlencode($key) . '=' . urlencode($value);
            }
        }
        
        return $url;
    }

    /**
     * Load CSS file if it exists, otherwise return empty string
     */
    public static function loadCSS(string $path): string {
        if (self::fileExists($path)) {
            return '<link rel="stylesheet" href="' . self::asset($path) . '">';
        }
        return '<!-- CSS file not found: ' . htmlspecialchars($path) . ' -->';
    }

    /**
     * Load JS file if it exists, otherwise return empty string
     */
    public static function loadJS(string $path, bool $defer = false): string {
        if (self::fileExists($path)) {
            $deferAttr = $defer ? ' defer' : '';
            return '<script src="' . self::asset($path) . '"' . $deferAttr . '></script>';
        }
        return '<!-- JS file not found: ' . htmlspecialchars($path) . ' -->';
    }

    /**
     * Get image URL
     */
    public static function image(string $path): string {
        return self::asset('assets/images/' . ltrim($path, '/'));
    }
}