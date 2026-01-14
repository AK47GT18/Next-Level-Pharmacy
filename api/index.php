<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../config/config.php';
require_once '../includes/helpers.php';

// Get request URI and method
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Extract endpoint from URI
$uri_parts = explode('/api/', $request_uri);
$endpoint = $uri_parts[1] ?? '';
$endpoint = trim($endpoint, '/');

// Route API requests
try {
    switch($endpoint) {
        case 'auth/login':
            require_once 'auth/login.php';
            break;
            
        case 'auth/logout':
            require_once 'auth/logout.php';
            break;
            
        case 'inventory/list':
            require_once 'inventory/list.php';
            break;
            
        case 'sales/create':
            require_once 'sales/create.php';
            break;
            
        default:
            throw new Exception('Endpoint not found', 404);
    }
} catch (Exception $e) {
    https_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}