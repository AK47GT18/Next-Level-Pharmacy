<?php
// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httpsonly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['https']));
    
    session_start();
}

// Session security measures
function regenerateSession() {
    if (!empty($_SESSION['last_regeneration'])) {
        // Regenerate session ID every 30 minutes
        if (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    } else {
        $_SESSION['last_regeneration'] = time();
    }
}

// Call regenerate on each request
regenerateSession();