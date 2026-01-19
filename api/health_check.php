<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = [
    'status' => 'pending',
    'checks' => []
];

// 1. Check PHP Version
$response['checks']['php_version'] = [
    'name' => 'PHP Version',
    'status' => 'ok',
    'message' => phpversion()
];

// 2. Check Required Extensions
$required_exts = ['mysqli', 'gd', 'json'];
foreach ($required_exts as $ext) {
    if (extension_loaded($ext)) {
        $response['checks'][$ext] = ['name' => "Extension: $ext", 'status' => 'ok', 'message' => 'Loaded'];
    } else {
        $response['checks'][$ext] = ['name' => "Extension: $ext", 'status' => 'error', 'message' => 'Not Loaded'];
    }
}

// 3. Database Connection
require_once 'db.php';
if ($conn->connect_error) {
    $response['checks']['database'] = ['name' => 'Database Connection', 'status' => 'error', 'message' => $conn->connect_error];
} else {
    $response['checks']['database'] = ['name' => 'Database Connection', 'status' => 'ok', 'message' => 'Connected successfully'];
}

// 4. Check Uploads Directory
$uploadsDir = '../uploads';
if (!is_dir($uploadsDir)) {
    if (mkdir($uploadsDir, 0777, true)) {
        $response['checks']['uploads_dir'] = ['name' => 'Uploads Directory', 'status' => 'ok', 'message' => 'Created successfully'];
    } else {
        $response['checks']['uploads_dir'] = ['name' => 'Uploads Directory', 'status' => 'error', 'message' => 'Missing and could not create'];
    }
} else {
    if (is_writable($uploadsDir)) {
        $response['checks']['uploads_dir'] = ['name' => 'Uploads Directory', 'status' => 'ok', 'message' => 'Writable'];
    } else {
        $response['checks']['uploads_dir'] = ['name' => 'Uploads Directory', 'status' => 'error', 'message' => 'Not Writable'];
    }
}

// Final Status
$hasError = false;
foreach ($response['checks'] as $check) {
    if ($check['status'] === 'error')
        $hasError = true;
}
$response['status'] = $hasError ? 'error' : 'ok';

echo json_encode($response, JSON_PRETTY_PRINT);
?>