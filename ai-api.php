<?php
/**
 * AI API Proxy for Groq Cloud
 * Routes requests to Groq's GPT-OSS-120B model securely
 * Keeps API key server-side for security
 */

// Force JSON output - suppress any PHP HTML error output
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

require_once 'config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require login
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please log in first.']);
        exit;
    }
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : -1;
}
catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Session error.']);
    exit;
}

// Parse request body
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body.']);
    exit;
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (!isset($input['messages']) || !is_array($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. Messages array required.']);
    exit;
}

// Groq Cloud API Configuration
$GROQ_API_KEY = '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';
$GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
$MODEL = 'openai/gpt-oss-120b'; // GPT OSS 120B on Groq

// Simple rate limiting (max 10 requests per minute per user)
$rateKey = 'ai_rate_' . $userId;
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = [];
}
$now = time();
// Clean old entries (older than 60 seconds)
$_SESSION[$rateKey] = array_values(array_filter($_SESSION[$rateKey], function ($t) use ($now) {
    return ($now - $t) < 60;
}));
if (count($_SESSION[$rateKey]) >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait a moment before trying again.']);
    exit;
}
$_SESSION[$rateKey][] = $now;

// Prepare payload for Groq API
$payload = json_encode([
    'model' => $MODEL,
    'messages' => $input['messages'],
    'max_tokens' => 8192,
    'temperature' => 0.3,
    'top_p' => 0.9,
    'stream' => false
]);

if ($payload === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode request payload.']);
    exit;
}

// Make request to Groq
$ch = curl_init($GROQ_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $GROQ_API_KEY
    ],
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

// Handle cURL errors
if ($curlErrno !== 0) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to AI service: ' . $curlError]);
    exit;
}

// Handle non-200 responses from Groq
if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMsg = 'AI service error (HTTP ' . $httpCode . ')';
    if (isset($errorData['error']['message'])) {
        $errorMsg = $errorData['error']['message'];
    }
    elseif (isset($errorData['error']) && is_string($errorData['error'])) {
        $errorMsg = $errorData['error'];
    }
    http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 502);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

// Parse Groq response
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from AI service.']);
    exit;
}

if (!isset($data['choices'][0]['message']['content'])) {
    http_response_code(502);
    echo json_encode(['error' => 'AI service returned an empty response. Please try again.']);
    exit;
}

// Return clean response
echo json_encode([
    'content' => $data['choices'][0]['message']['content'],
    'model' => $data['model'] ?? $MODEL,
    'usage' => $data['usage'] ?? null
]);
