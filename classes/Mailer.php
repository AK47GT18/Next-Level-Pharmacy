<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        // Server settings from your request
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'websmtp47@gmail.com';
        $this->mail->Password   = 'jbvkukdacbphzaet'; // Use an App Password for Gmail
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;

        // Sender
        $this->mail->setFrom('websmtp47@gmail.com', 'RxPMS Notification');
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
    public function send(string $toEmail, string $toName, string $subject, string $body): bool {
        try {
            // Recipients
            $this->mail->addAddress($toEmail, $toName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body); // Plain text version

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            // In a real app, you'd log this error instead of echoing it.
            // error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
?>