<?php
require_once 'PHPMailer-master/src/PHPMailer.php'; // Adjust the path 
require_once 'PHPMailer-master/src/SMTP.php'; // Adjust the path 
require_once 'PHPMailer-master/src/Exception.php'; // Adjust the path 
require 'db.php'; // Include your database connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Email addresses
$emailAddresses = ['aaron@acemovers.com.au', 'harry@acemovers.com.au', 'kevin@acemovers.com.au', 'nick@acemovers.com.au'];

// Function to send email
function sendEmail($formData, $nextEmailAddress) {
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.elasticemail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aaron@acemovers.com.au';
        $mail->Password = '8F1E23DEE343B60A0336456A6944E7B4F7DA';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        //Recipients
        $mail->setFrom('aaron@acemovers.com.au', 'Aaron');
        $mail->addAddress($nextEmailAddress);

        // Content
        $emailBody = "Name: {$formData['Name']}\nBedrooms: {$formData['Bedrooms']}\nPickup: {$formData['Pickup']}\nDropoff: {$formData['Dropoff']}\nDate: {$formData['Date']}\nPhone number: {$formData['Phone']}\nEmail: {$formData['Email']}\nDetails: {$formData['Details']}";
        $mail->isHTML(false);
        $mail->Subject = 'New Lead';
        $mail->Body    = $emailBody;

        $mail->send();
        echo 'Email sent to ' . $nextEmailAddress;
    } catch (Exception $e) {
        error_log('Error sending email: ' . $mail->ErrorInfo);
        throw $e; // Re-throw the error to be caught by the caller
    }
}

// Function to get the next email index and count
function getNextEmailInfo($conn) {
    $sql = "SELECT current_index, count FROM email_tracker WHERE id = 1";
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Error getting next email info: ' . $conn->error);
    }
    return $result->fetch_assoc();
}

// Function to update the email index and count
function updateEmailInfo($conn, $index, $count) {
    $stmt = $conn->prepare("UPDATE email_tracker SET current_index = ?, count = ? WHERE id = 1");
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        return;
    }
    $stmt->bind_param("ii", $index, $count);
    if (!$stmt->execute()) {
        error_log('Execute failed: ' . $stmt->error);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Debug: print received data
    error_log('Received input: ' . print_r($input, true));

    $name = $input['Name'] ?? null;
    $bedrooms = $input['Bedrooms'] ?? null;
    $pickup = $input['Pickup'] ?? null;
    $dropoff = $input['Dropoff'] ?? null;
    $date = $input['Date'] ?? null;
    $phone = $input['Phone'] ?? null;
    $email = $input['Email'] ?? null;
    $details = $input['Details'] ?? null;

    // Debug: print received variables
    error_log("Name: $name, Bedrooms: $bedrooms, Pickup: $pickup, Dropoff: $dropoff, Date: $date, Phone: $phone, Email: $email, Details: $details");

    $query = "INSERT INTO leads (lead_name, bedrooms, pickup, dropoff, lead_date, phone, email, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        echo json_encode(['error' => 'Database error: prepare failed']);
        exit;
    }
    $stmt->bind_param("sissssss", $name, $bedrooms, $pickup, $dropoff, $date, $phone, $email, $details);

    try {
        if (!$stmt->execute()) {
            error_log('Execute failed: ' . $stmt->error);
            echo json_encode(['error' => 'Database error: execute failed']);
            exit;
        }

        $emailInfo = getNextEmailInfo($conn);
        $currentIndex = $emailInfo['current_index'];
        $count = $emailInfo['count'];

        sendEmail($input, $emailAddresses[$currentIndex]);

        $newCount = $count + 1;
        $maxEmailsPerAddress = 3;
        $newIndex = $currentIndex;

        if ($newCount >= $maxEmailsPerAddress) {
            $newIndex = ($currentIndex + 1) % count($emailAddresses);
            updateEmailInfo($conn, $newIndex, 0);
        } else {
            updateEmailInfo($conn, $currentIndex, $newCount);
        }

        echo json_encode(['message' => 'Form data saved and email sent successfully!']);
    } catch (Exception $e) {
        error_log('Error processing form submission and sending email: ' . $e->getMessage());
        echo json_encode(['error' => 'Error processing request']);
        http_response_code(500);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
    http_response_code(405);
}
?>
