<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Correct path to vendor autoload
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    private $mail;
    private $lastError = '';

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        try {
            // Server settings - Using SSL/465 for InfinityFree compatibility
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.gmail.com';
            $this->mail->SMTPAuth = true;
            $this->mail->Username = 'websmtp47@gmail.com';
            $this->mail->Password = 'jbvkukdacbphzaet';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL instead of TLS
            $this->mail->Port = 465; // SSL port (more likely to work on shared hosts)

            // Timeout settings
            $this->mail->Timeout = 30;

            // Character encoding
            $this->mail->CharSet = 'UTF-8';

            // Sender
            $this->mail->setFrom('websmtp47@gmail.com', 'Next-Level Pharmacy');
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Mailer initialization error: " . $e->getMessage());
        }
    }

    /**
     * Sends an email.
     *
     * @param string $toEmail The recipient's email address.
     * @param string $toName The recipient's name.
     * @param string $subject The email subject.
     * @param string $body The HTML email body.
     * @return bool True on success, false on failure.
     */
    public function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        try {
            // Clear any previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAllRecipients();

            // Recipients
            $this->mail->addAddress($toEmail, $toName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            $this->lastError = $this->mail->ErrorInfo;
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Get the last error message
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }
}