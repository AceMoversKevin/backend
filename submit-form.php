<?php
require_once 'db.php'; // Include your database connection

// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: https://alphamovers.com.au");
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

// Email addresses
$emailAddresses = ['aaron@acemovers.com.au', 'harry@acemovers.com.au', 'kevin@acemovers.com.au', 'nick@acemovers.com.au'];

// Function to get the next email index and count
function getNextEmailInfo($conn)
{
    $sql = "SELECT current_index, count FROM email_tracker WHERE id = 1";
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Error getting next email info: ' . $conn->error);
        return null;
    }
    return $result->fetch_assoc();
}

// Function to update the email index and count
function updateEmailInfo($conn, $index, $count)
{
    $stmt = $conn->prepare("UPDATE email_tracker SET current_index = ?, count = ? WHERE id = 1");
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param("ii", $index, $count);
    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        return false;
    }
    return true;
}

// Function to split workflow over multiple threads
function processInThreads($id, $input, $nextEmailAddress)
{
    switch ($id) {
        case 0:
            // Thread 0 handles email sending
            $emailScript = escapeshellarg("php send-email.php '{$input['Name']}' '{$input['Bedrooms']}' '{$input['Pickup']}' '{$input['Dropoff']}' '{$input['Date']}' '{$input['Phone']}' '{$input['Email']}' '{$input['Details']}' '$nextEmailAddress'");
            popen("$emailScript > /dev/null 2>&1 &", 'r');
            break;
        case 1:
            // Thread 1: logging some details
            $logData = "Log: Processing lead for {$input['Name']} on thread 1";
            file_put_contents('thread1.log', $logData . PHP_EOL, FILE_APPEND);
            break;
        case 2:
            // Thread 2: data transformation
            $transformedData = strtoupper(json_encode($input));
            file_put_contents('thread2.log', "Transformed Data: $transformedData" . PHP_EOL, FILE_APPEND);
            break;
        case 3:
            // Thread 3: additional logging and transformation
            $details = "{$input['Details']} processed on thread 3";
            file_put_contents('thread3.log', "Details: $details" . PHP_EOL, FILE_APPEND);
            break;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }

    $name = $input['Name'] ?? null;
    $bedrooms = $input['Bedrooms'] ?? null;
    $pickup = $input['Pickup'] ?? null;
    $dropoff = $input['Dropoff'] ?? null;
    $date = $input['Date'] ?? null;
    $phone = $input['Phone'] ?? null;
    $email = $input['Email'] ?? null;
    $details = $input['Details'] ?? null;

    // Insert lead data into the database
    $query = "INSERT INTO leads (lead_name, bedrooms, pickup, dropoff, lead_date, phone, email, details, acceptanceLimit, booking_status, created_at, isReleased) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, 0, NOW(), 0)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        echo json_encode(['error' => 'Database error: prepare failed']);
        exit;
    }
    $stmt->bind_param("ssssssss", $name, $bedrooms, $pickup, $dropoff, $date, $phone, $email, $details);

    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
        echo json_encode(['error' => 'Database error: execute failed']);
        exit;
    }

    $emailInfo = getNextEmailInfo($conn);
    if ($emailInfo) {
        $currentIndex = $emailInfo['current_index'];
        $count = $emailInfo['count'];
        $nextEmailAddress = $emailAddresses[$currentIndex];

        for ($i = 0; $i < 4; $i++) {
            processInThreads($i, $input, $nextEmailAddress);
        }

        $newCount = $count + 1;
        $maxEmailsPerAddress = 3;
        $newIndex = $currentIndex;

        if ($newCount >= $maxEmailsPerAddress) {
            $newIndex = ($currentIndex + 1) % count($emailAddresses);
            $newCount = 0; // Reset the count for the new index
        }

        updateEmailInfo($conn, $newIndex, $newCount);
    }

    echo json_encode(['message' => 'Form data saved successfully!']);
} else {
    echo json_encode(['message' => 'Submit a POST request to this endpoint for form handling.']);
}
