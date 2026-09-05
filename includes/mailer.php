<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

function send_verification_email($toEmail, $code) {
    $mail = new PHPMailer(true);

    try {
        // --- FOR DEBUGGING ---
        $mail->SMTPDebug = 2; // 2 = client and server messages
        // ------------------------------------

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zwethihazaw08@gmail.com';   //sender mail 
        $mail->Password   = 'nwjb ahie rgct bpez';       //app password 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@tourismportal.com', 'Tourism Portal');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Account';
        $mail->Body    = "Your verification code is: <b>$code</b>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->ErrorInfo; 
        return false;
    }
}