<?php
require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';
require_once 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get command line arguments
list($script, $name, $bedrooms, $pickup, $dropoff, $date, $phone, $email, $details, $nextEmailAddress) = $argv;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.elasticemail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'aaron@acemovers.com.au';
    $mail->Password = '8F1E23DEE343B60A0336456A6944E7B4F7DA';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('aaron@acemovers.com.au', 'Aaron');
    $mail->addAddress($nextEmailAddress);

    $emailBody = "Name: $name\nBedrooms: $bedrooms\nPickup: $pickup\nDropoff: $dropoff\nDate: $date\nPhone number: $phone\nEmail: $email\nDetails: $details";
    $mail->isHTML(false);
    $mail->Subject = 'New Lead';
    $mail->Body = $emailBody;

    $mail->send();
} catch (Exception $e) {
    error_log('Error sending email: ' . $mail->ErrorInfo);
}
?>
