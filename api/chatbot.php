+++<?php
// Chatbot API endpoint
header('Content-Type: application/json');

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include required files
require_once(BASE_PATH . '/includes/chatbot.php');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate request data
if (!isset($data['message']) || empty(trim($data['message']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Initialize chatbot
$chatbot = new Chatbot();

// Process message and get response
$message = trim($data['message']);
$response = $chatbot->getResponse($message);

// Check if message contains symptoms to suggest departments
if (strpos(strtolower($message), 'symptom') !== false || 
    strpos(strtolower($message), 'feeling') !== false || 
    strpos(strtolower($message), 'pain') !== false) {
    $suggestions = $chatbot->suggestDepartment($message);
    if (!empty($suggestions)) {
        $response .= "\n\nBased on your symptoms, you might want to consult these departments: " . 
                   implode(', ', array_map('ucfirst', $suggestions));
    }
}

// Return response
echo json_encode([
    'response' => $response,
    'timestamp' => date('Y-m-d H:i:s')
]);