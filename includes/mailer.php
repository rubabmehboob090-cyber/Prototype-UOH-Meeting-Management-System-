<?php
/**
 * Gmail SMTP Mailer & In-App Notification Manager
 * University Meeting Management System
 */

require_once dirname(__DIR__) . '/config/config.php';

class Mailer {

    /**
     * Dispatch an in-app and email notification to a user
     * 
     * @param int $recipientId
     * @param int|null $meetingId
     * @param string $type
     * @param string $subject
     * @param string $message
     * @param int|null $changeRequestId
     * @return bool
     */
    public static function notify(
        int $recipientId,
        ?int $meetingId,
        string $type,
        string $subject,
        string $message,
        ?int $changeRequestId = null
    ): bool {
        $pdo = Database::getConnection();

        // 1. Get recipient email and name
        $stmt = $pdo->prepare("SELECT email, full_name, status FROM users WHERE id = ?");
        $stmt->execute([$recipientId]);
        $recipient = $stmt->fetch();

        if (!$recipient || $recipient['status'] !== 'active') {
            return false;
        }

        // 2. Insert In-App Notification Record
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (recipient_id, meeting_id, change_request_id, notification_type, subject, message, channel, email_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'both', 'pending', NOW())
        ");
        $stmtNotif->execute([
            $recipientId,
            $meetingId,
            $changeRequestId,
            $type,
            $subject,
            $message
        ]);
        $notifId = $pdo->lastInsertId();

        // 3. Attempt Email Delivery via Gmail SMTP
        $emailSent = false;
        if (SMTP_ENABLED && !empty(SMTP_USER) && !empty(SMTP_PASS)) {
            $emailSent = self::sendSmtpMail($recipient['email'], $recipient['full_name'], $subject, $message);
        } else {
            // If SMTP password not configured yet, record as pending/logged
            error_log("SMTP notification simulated for {$recipient['email']}: {$subject}");
        }

        if ($emailSent) {
            $stmtUpd = $pdo->prepare("UPDATE notifications SET email_status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$notifId]);
        }

        return true;
    }

    /**
     * Send email via Direct Socket Gmail SMTP
     * Supports TLS (port 587) or SSL (port 465)
     */
    public static function sendSmtpMail(string $toEmail, string $toName, string $subject, string $body): bool {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USER;
        $password = SMTP_PASS;
        $fromEmail = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        $timeout = 10;
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }

        $readResponse = function() use ($socket) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) == ' ') break;
            }
            return $data;
        };

        $sendCommand = function($cmd) use ($socket, $readResponse) {
            fputs($socket, $cmd . "\r\n");
            return $readResponse();
        };

        $resp = $readResponse();
        if (substr($resp, 0, 3) != '220') {
            fclose($socket);
            return false;
        }

        $sendCommand("EHLO " . gethostname());

        // Start TLS
        if (SMTP_SECURE === 'tls' || $port == 587) {
            $sendCommand("STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand("EHLO " . gethostname());
        }

        // Auth Login
        $sendCommand("AUTH LOGIN");
        $sendCommand(base64_encode($username));
        $resp = $sendCommand(base64_encode($password));

        if (substr($resp, 0, 3) != '235') {
            error_log("SMTP Authentication Failed: " . $resp);
            fclose($socket);
            return false;
        }

        // Sender & Recipient
        $sendCommand("MAIL FROM: <{$fromEmail}>");
        $sendCommand("RCPT TO: <{$toEmail}>");

        // Data payload
        $sendCommand("DATA");

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date(DATE_RFC2822),
            "X-Mailer: UoH-MMS-Mailer/1.0"
        ];

        $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
        $resp = $sendCommand($emailData);

        $sendCommand("QUIT");
        fclose($socket);

        return (substr($resp, 0, 3) == '250');
    }
}
