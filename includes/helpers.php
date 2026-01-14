<?php
/**
 * Helper Functions
 * Utility functions used across the application
 */

// Prevent direct access
//defined('APP_ENTRY') or die('Direct access not permitted');

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format currency
 */


/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

/**
 * Get time ago (e.g., "5 mins ago")
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time_difference = time() - $time;
    
    if ($time_difference < 60) {
        return 'just now';
    }
    
    $condition = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60      => 'month',
        24 * 60 * 60           => 'day',
        60 * 60                => 'hour',
        60                     => 'minute',
    ];
    
    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Redirect to a page
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
}

/**
 * Check if request is POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data
 */
function post($key, $default = null) {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

/**
 * Get GET data
 */
function get($key, $default = null) {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

/**
 * Flash message to session
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display and clear flash message as a modal
 */
function displayFlashModal() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $icon = $flash['type'] === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500';
        $title = ucfirst($flash['type']);
        
        echo "<div id='flashModal' data-type='{$flash['type']}' data-message='{$flash['message']}' data-title='{$title}' data-icon='{$icon}'></div>";
    }
}
/**
 * Debug function
 */


/**
 * Log activity to database
 * @param int $user_id
 * @param string $action_type
 * @param string $description
 * @return bool
 */
function logActivity($user_id, $action_type, $description) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([
            $user_id,
            $action_type,
            $description,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log error for debugging
 * @param string $type Error type
 * @param string $message Error message
 * @param array $context Additional context
 */
function logError($type, $message, $context = []) {
    $log_file = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $context_str = json_encode($context);
    $log_entry = "[{$timestamp}] {$type}: {$message} | Context: {$context_str}\n";
    error_log($log_entry, 3, $log_file);
}

/**
 * Validate date format
 * @param string $date Date string to validate
 * @param string $format Expected format
 * @return bool
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Generate random string
 * @param int $length Length of string
 * @return string
 */


/**
 * Format currency
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatCurrency($amount, $currency = 'MWK') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Clean input
 * @param string $input
 * @return string
 */
function cleanInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Generate invoice number
 * @return string
 */
function generateInvoiceNumber() {
    $prefix = 'INV';
    $date = date('Ymd');
    $random = strtoupper(substr(uniqid(), -4));
    return "{$prefix}{$date}{$random}";
}

/**
 * Calculate days until expiry
 * @param string $expiry_date
 * @return int
 */
function daysUntilExpiry($expiry_date) {
    $expiry = new DateTime($expiry_date);
    $today = new DateTime();
    return $today->diff($expiry)->days;
}

/**
 * Send email
 * @param string $to
 * @param string $subject
 * @param string $message
 * @return bool
 */
function sendEmail($to, $subject, $message) {
    // Configure email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: RxPMS <noreply@rxpms.com>'
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Debug function
 * @param mixed $data
 * @param bool $die
 */
function dd($data, $die = true) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    if ($die) die();
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjax() {
    return !empty($_SERVER['https_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['https_X_REQUESTED_WITH']) == 'xmlhttpsrequest';
}