<?php
require_once 'db.php'; // Include your database connection

// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust the Access-Control-Allow-Origin header to allow requests from Google Apps Script
header("Access-Control-Allow-Origin: *"); // Alternatively, specify the exact domain if known
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle the preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}
header('Content-Type: application/json');

// Redirect all errors to the log file to avoid sending invalid JSON responses
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }

    $emailBody = $input['EmailBody'] ?? null;
    $teamMember = $input['teamMember'] ?? null;

    // Insert raw email data into the database
    $query = "INSERT INTO rawMovingSelectLeads (EmailBody, teamMember) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        echo json_encode(['error' => 'Database error: prepare failed']);
        exit;
    }
    $stmt->bind_param("ss", $emailBody, $teamMember);

    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        echo json_encode(['error' => 'Database error: execute failed']);
        exit;
    }

    echo json_encode(['message' => 'Raw email data saved successfully!']);
} else {
    echo json_encode(['message' => 'Submit a POST request to this endpoint for form handling.']);
}
?>
