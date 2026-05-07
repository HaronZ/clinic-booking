<?php
declare(strict_types=1);

namespace Clinic\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

/**
 * Thin wrapper around PHPMailer.
 *
 * Configure via .env:
 *   MAIL_HOST     SMTP hostname (e.g. smtp.gmail.com)
 *   MAIL_PORT     587 (STARTTLS) or 465 (SSL)
 *   MAIL_USER     SMTP username
 *   MAIL_PASS     SMTP password / app password
 *   MAIL_FROM     Sender address (e.g. noreply@yourclinic.com)
 *   MAIL_FROM_NAME Sender display name
 *   MAIL_ENABLED  1 to send, 0 to log-only (default 0 in dev)
 */
final class EmailService
{
    private bool   $enabled;
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;
    private string $fromName;

    public function __construct()
    {
        $this->enabled   = ($_ENV['MAIL_ENABLED'] ?? '0') === '1';
        $this->host      = $_ENV['MAIL_HOST']      ?? 'smtp.gmail.com';
        $this->port      = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $this->user      = $_ENV['MAIL_USER']      ?? '';
        $this->pass      = $_ENV['MAIL_PASS']      ?? '';
        $this->from      = $_ENV['MAIL_FROM']      ?? 'noreply@clinic.local';
        $this->fromName  = $_ENV['MAIL_FROM_NAME'] ?? 'Clinic Booking';
    }

    /**
     * Send a booking confirmation to the patient.
     * Safe to call even if patient_email is null — it's a no-op.
     *
     * @param array<string,mixed> $booking
     */
    public function sendBookingConfirmation(array $booking): void
    {
        $to = $booking['patient_email'] ?? null;
        if ($to === null || $to === '') {
            return; // no email on file — silently skip
        }

        $subject = sprintf(
            '[Clinic Booking] Confirmed — %s on %s',
            $booking['type_name'],
            date('M j, Y', strtotime((string) $booking['start_time'])),
        );

        $startFormatted = date('F j, Y \a\t g:i A', strtotime((string) $booking['start_time']));
        $endFormatted   = date('g:i A', strtotime((string) $booking['end_time']));

        $html = $this->bookingEmailHtml(
            patientName:  (string) ($booking['patient_name'] ?? 'Patient'),
            providerName: (string) ($booking['provider_name'] ?? ''),
            typeName:     (string) ($booking['type_name'] ?? ''),
            start:        $startFormatted,
            end:          $endFormatted,
            reference:    (string) ($booking['id'] ?? ''),
        );

        if (!$this->enabled) {
            // Dev mode — just log that we would have sent.
            error_log(sprintf(
                '[EmailService] MAIL_ENABLED=0 — would send "%s" to patient (id=%s)',
                $subject,
                $booking['id'] ?? 'unknown',
            ));
            return;
        }

        try {
            $mail = $this->mailer();
            $mail->addAddress((string) $to);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $html));
            $mail->send();
        } catch (Throwable $e) {
            // Email failure must never break the booking transaction.
            error_log('[EmailService] Failed to send confirmation: ' . $e->getMessage());
        }
    }

    /**
     * Send daily schedule digest to a staff member.
     *
     * @param array<string,mixed>[] $appointments
     */
    public function sendDailySchedule(string $to, string $staffName, array $appointments, string $date): void
    {
        if (!$this->enabled) {
            error_log(sprintf('[EmailService] MAIL_ENABLED=0 — would send daily schedule to %s', $to));
            return;
        }

        try {
            $mail = $this->mailer();
            $mail->addAddress($to);
            $mail->Subject = sprintf('[Clinic] Schedule for %s', date('F j, Y', strtotime($date)));
            $mail->Body    = $this->scheduleEmailHtml($staffName, $appointments, $date);
            $mail->AltBody = "See your schedule in the staff dashboard.";
            $mail->send();
        } catch (Throwable $e) {
            error_log('[EmailService] Failed to send schedule: ' . $e->getMessage());
        }
    }

    private function mailer(): PHPMailer
    {
        $mail = new PHPMailer(exceptions: true);
        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->user;
        $mail->Password   = $this->pass;
        $mail->SMTPSecure = $this->port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;
        $mail->setFrom($this->from, $this->fromName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        return $mail;
    }

    private function bookingEmailHtml(
        string $patientName,
        string $providerName,
        string $typeName,
        string $start,
        string $end,
        string $reference,
    ): string {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family:system-ui,sans-serif;color:#1f2937;max-width:600px;margin:auto;padding:24px">
          <div style="background:#2563eb;padding:24px;border-radius:8px 8px 0 0">
            <h1 style="color:#fff;margin:0;font-size:1.4rem">Booking Confirmed</h1>
          </div>
          <div style="border:1px solid #e5e7eb;border-top:none;padding:24px;border-radius:0 0 8px 8px">
            <p>Hello <strong>{$patientName}</strong>,</p>
            <p>Your appointment has been received. Please arrive <strong>10 minutes early</strong>.</p>
            <table style="width:100%;border-collapse:collapse;margin:16px 0">
              <tr><td style="padding:8px;color:#6b7280;width:140px">Provider</td><td style="padding:8px"><strong>{$providerName}</strong></td></tr>
              <tr style="background:#f9fafb"><td style="padding:8px;color:#6b7280">Type</td><td style="padding:8px">{$typeName}</td></tr>
              <tr><td style="padding:8px;color:#6b7280">Date &amp; Time</td><td style="padding:8px">{$start} – {$end}</td></tr>
              <tr style="background:#f9fafb"><td style="padding:8px;color:#6b7280">Reference</td><td style="padding:8px;font-family:monospace;font-size:0.85rem">{$reference}</td></tr>
            </table>
            <p style="color:#6b7280;font-size:0.85rem">Keep this reference ID in case you need to contact us about your appointment.</p>
          </div>
        </body>
        </html>
        HTML;
    }

    private function scheduleEmailHtml(string $staffName, array $appointments, string $date): string
    {
        $rows = '';
        foreach ($appointments as $a) {
            $time   = date('g:i A', strtotime((string) $a['start_time']));
            $name   = htmlspecialchars((string) ($a['patient_name'] ?? ''));
            $type   = htmlspecialchars((string) ($a['type_name']    ?? ''));
            $status = strtoupper((string) ($a['status'] ?? ''));
            $rows .= "<tr><td style='padding:8px'>{$time}</td><td style='padding:8px'>{$name}</td><td style='padding:8px'>{$type}</td><td style='padding:8px'><strong>{$status}</strong></td></tr>";
        }
        $count = count($appointments);
        return <<<HTML
        <!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;color:#1f2937;max-width:600px;margin:auto;padding:24px">
          <h2>Daily Schedule — {$date}</h2>
          <p>Hello {$staffName}, you have <strong>{$count}</strong> appointment(s) today.</p>
          <table style="width:100%;border-collapse:collapse">
            <thead><tr style="background:#f3f4f6"><th style="padding:8px;text-align:left">Time</th><th style="padding:8px;text-align:left">Patient</th><th style="padding:8px;text-align:left">Type</th><th style="padding:8px;text-align:left">Status</th></tr></thead>
            <tbody>{$rows}</tbody>
          </table>
        </body></html>
        HTML;
    }
}
