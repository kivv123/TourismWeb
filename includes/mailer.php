<?php

declare(strict_types=1);

/**
 * =========================================================================
 *  EMAIL LAYER  -  Myanmar Horizons
 * =========================================================================
 *
 *  PHPMailer is bundled in includes/src/ (no Composer required).
 *
 *  All SMTP credentials live in config/mail.php (git-ignored; see
 *  config/mail.example.php for a documented template). Environment
 *  variables override file values when present.
 *
 *  Every send function returns a boolean. Failures are written to
 *  logs/mail.log and are NEVER echoed to the browser, so no technical
 *  server details or credentials can leak to end users.
 * =========================================================================
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

if (!function_exists('mail_config')) {
    /**
     * Load the mail configuration once per request.
     * config/mail.php (live values) is preferred; the committed
     * config/mail.example.php acts as a safe fallback template.
     */
    function mail_config(): array
    {
        static $config = null;

        if ($config === null) {
            $live = __DIR__ . '/../config/mail.php';
            $template = __DIR__ . '/../config/mail.example.php';
            $config = is_file($live) ? require $live : require $template;
            if (!is_array($config)) {
                $config = [];
            }

            // Environment variables override file values (useful on shared hosting).
            $envMap = [
                'host' => 'MAIL_HOST',
                'port' => 'MAIL_PORT',
                'encryption' => 'MAIL_ENCRYPTION',
                'username' => 'MAIL_USERNAME',
                'password' => 'MAIL_PASSWORD',
                'from_email' => 'MAIL_FROM_ADDRESS',
                'from_name' => 'MAIL_FROM_NAME',
            ];

            foreach ($envMap as $key => $env) {
                $value = getenv($env);
                if ($value !== false && $value !== '') {
                    $config[$key] = ($env === 'MAIL_PORT') ? (int) $value : $value;
                }
            }
        }

        return $config;
    }
}

if (!function_exists('mail_log')) {
    /**
     * Append a line to logs/mail.log (web access to the folder is blocked
     * by logs/.htaccess, and *.log entries are git-ignored).
     */
    function mail_log(string $message): void
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $dir . '/mail.log');
    }
}

if (!function_exists('app_base_url')) {
    /**
     * Absolute base URL used in email links, e.g. http://localhost/TourismWeb
     */
    function app_base_url(): string
    {
        $configured = trim((string) (mail_config()['app_url'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $base = defined('BASE_URL') ? BASE_URL : '/TourismWeb';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https' : 'http') . '://' . $host . rtrim($base, '/');
    }
}

if (!function_exists('build_smtp_mailer')) {
    /**
     * Create a PHPMailer instance pre-configured from config/mail.php.
     */
    function build_smtp_mailer(): PHPMailer
    {
        $c = mail_config();

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = (string) ($c['host'] ?? 'smtp.gmail.com');
        $mail->Port = (int) ($c['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = (string) ($c['username'] ?? '');
        $mail->Password = (string) ($c['password'] ?? '');

        $encryption = strtolower((string) ($c['encryption'] ?? 'tls'));
        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            // Explicitly unencrypted (local development sinks): disable
            // PHPMailer's automatic STARTTLS upgrade, which would fail
            // against a plain local relay.
            $mail->SMTPAutoTLS = false;
        }

        $from = trim((string) ($c['from_email'] ?? ''));
        if ($from === '') {
            $from = (string) ($c['username'] ?? '');
        }
        $mail->setFrom($from, (string) ($c['from_name'] ?? 'Myanmar Horizons'));

        // 0 = off (production). Only raise temporarily while testing.
        $mail->SMTPDebug = (int) ($c['debug'] ?? 0);

        return $mail;
    }
}

if (!function_exists('send_email')) {
    /**
     * Low-level sender used by every email in the project.
     * Returns true only when the message was accepted by the SMTP server.
     */
    function send_email(string $toEmail, string $subject, string $htmlBody, string $textBody): bool
    {
        $c = mail_config();

        if (trim((string) ($c['username'] ?? '')) === '' || trim((string) ($c['password'] ?? '')) === '') {
            mail_log('SMTP credentials missing in config/mail.php - message for ' . $toEmail . ' was NOT sent.');
            return false;
        }

        $mail = null;
        try {
            $mail = build_smtp_mailer();
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            mail_log(
                'Email to ' . $toEmail . ' failed | subject "' . $subject . '" | reason: '
                . (($mail instanceof PHPMailer) ? $mail->ErrorInfo : $e->getMessage())
            );
            return false;
        }
    }
}

if (!function_exists('send_verification_email')) {
    /**
     * Customer registration verification code (existing feature).
     * Kept signature-compatible with the previous mailer.php.
     */
    function send_verification_email(string $toEmail, string $code): bool
    {
        $hCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $subject = 'Verify Your Account';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(15,23,42,0.08);">
        <tr><td style="background-color:#075985;padding:24px 32px;">
          <span style="color:#ffffff;font-size:20px;font-weight:bold;">Myanmar Horizons</span>
        </td></tr>
        <tr><td style="padding:32px;text-align:center;">
          <h2 style="margin:0 0 16px;color:#0f172a;font-size:20px;">Verify Your Account</h2>
          <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.6;">Use the verification code below to finish creating your account. The code expires in 10 minutes.</p>
          <p style="margin:0 0 24px;">
            <span style="display:inline-block;background-color:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:14px 28px;font-size:28px;font-weight:bold;letter-spacing:8px;color:#075985;font-family:Consolas,monospace;">{$hCode}</span>
          </p>
          <p style="margin:0;color:#94a3b8;font-size:12px;">If you did not request this code, you can safely ignore this email.</p>
        </td></tr>
        <tr><td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;">
          <p style="margin:0;color:#94a3b8;font-size:12px;">This is an automated message from the Myanmar Horizons registration system. Please do not reply.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        $text = "Hello,\n\n"
            . "Your Myanmar Horizons verification code is: {$code}\n\n"
            . "The code expires in 10 minutes.\n\n"
            . "Myanmar Horizons";

        return send_email($toEmail, $subject, $html, $text);
    }
}

if (!function_exists('send_hotel_account_email')) {
    /**
     * Welcome email with login credentials, sent to a hotel right after
     * the administrator creates its account. Also used by the
     * "Resend credentials" action (with a freshly generated password).
     *
     * The plain-text password exists ONLY for the duration of this call;
     * the database keeps only the password_hash.
     */
    function send_hotel_account_email(string $toEmail, string $hotelName, string $loginEmail, string $temporaryPassword): bool
    {
        $loginUrl = app_base_url() . '/login.php';

        $hHotel = htmlspecialchars($hotelName, ENT_QUOTES, 'UTF-8');
        $hEmail = htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8');
        $hPass = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
        $hUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $subject = 'Your Hotel Account Has Been Created';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(15,23,42,0.08);">
        <tr><td style="background-color:#075985;padding:28px 32px;">
          <span style="color:#ffffff;font-size:20px;font-weight:bold;">Myanmar Horizons</span><br>
          <span style="color:#bae6fd;font-size:13px;">Hotel Management Portal</span>
        </td></tr>
        <tr><td style="padding:32px;">
          <h2 style="margin:0 0 16px;color:#0f172a;font-size:22px;">Your Hotel Account Has Been Created</h2>
          <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">Hello {$hHotel},</p>
          <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.6;">Your hotel account has been successfully created by the administrator. You can now log in to the Hotel Management Dashboard using the following credentials:</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:24px;">
            <tr><td style="padding:18px 20px;">
              <p style="margin:0 0 12px;font-size:14px;color:#64748b;">Hotel Email<br>
                <strong style="color:#0f172a;font-size:15px;">{$hEmail}</strong></p>
              <p style="margin:0;font-size:14px;color:#64748b;">Temporary Password<br>
                <strong style="color:#0f172a;font-size:15px;font-family:Consolas,monospace;">{$hPass}</strong></p>
            </td></tr>
          </table>
          <p style="margin:0 0 28px;text-align:center;">
            <a href="{$hUrl}" style="display:inline-block;background-color:#075985;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:15px;font-weight:bold;">Login to Hotel Dashboard</a>
          </p>
          <p style="margin:0 0 8px;color:#334155;font-size:15px;line-height:1.6;"><strong>Important:</strong> for security reasons, please change your temporary password after your first login. You will be asked to set a new password as soon as you sign in.</p>
          <p style="margin:24px 0 0;color:#94a3b8;font-size:12px;line-height:1.6;">If the button does not work, copy this address into your browser:<br>{$hUrl}</p>
        </td></tr>
        <tr><td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;">
          <p style="margin:0;color:#94a3b8;font-size:12px;">This is an automated message from the Myanmar Horizons administration system. Please do not reply.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        $text = "Hello {$hotelName},\n\n"
            . "Your hotel account has been successfully created by the administrator.\n\n"
            . "You can now log in to the Hotel Management Dashboard using the following credentials:\n\n"
            . "Hotel Email:\n{$loginEmail}\n\n"
            . "Temporary Password:\n{$temporaryPassword}\n\n"
            . "Hotel Dashboard:\n{$loginUrl}\n\n"
            . "For security reasons, please change your password after your first login.\n"
            . "You will be asked to set a new password as soon as you sign in.\n\n"
            . "Thank you,\nMyanmar Horizons";

        return send_email($toEmail, $subject, $html, $text);
    }
}

if (!function_exists('send_password_reset_otp_email')) {
    /**
     * Password reset OTP (customer forgot-password flow).
     * Contains the application name, the 6-digit code, the expiry time
     * and a security warning. NEVER contains the user's password.
     */
    function send_password_reset_otp_email(string $toEmail, string $code): bool
    {
        $hCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $subject = 'Your Password Reset Code';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(15,23,42,0.08);">
        <tr><td style="background-color:#075985;padding:24px 32px;">
          <span style="color:#ffffff;font-size:20px;font-weight:bold;">Myanmar Horizons</span>
        </td></tr>
        <tr><td style="padding:32px;text-align:center;">
          <h2 style="margin:0 0 16px;color:#0f172a;font-size:20px;">Password Reset Code</h2>
          <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.6;">We received a request to reset the password for your account. Enter the verification code below to continue. The code expires in 5 minutes.</p>
          <p style="margin:0 0 24px;">
            <span style="display:inline-block;background-color:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:14px 28px;font-size:28px;font-weight:bold;letter-spacing:8px;color:#075985;font-family:Consolas,monospace;">{$hCode}</span>
          </p>
          <p style="margin:0 0 8px;color:#334155;font-size:14px;line-height:1.6;"><strong>Security warning:</strong> this code is single-use and was sent only to you. Myanmar Horizons staff will never ask you for this code.</p>
          <p style="margin:0;color:#94a3b8;font-size:12px;">If you did not request a password reset, you can safely ignore this email - your current password remains unchanged.</p>
        </td></tr>
        <tr><td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;">
          <p style="margin:0;color:#94a3b8;font-size:12px;">This is an automated message from the Myanmar Horizons account system. Please do not reply.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        $text = "Hello,\n\n"
            . "We received a request to reset the password for your Myanmar Horizons account.\n\n"
            . "Your password reset code is: {$code}\n\n"
            . "The code expires in 5 minutes and can be used only once.\n\n"
            . "Security warning: Myanmar Horizons staff will never ask you for this code.\n"
            . "If you did not request a password reset, you can safely ignore this email -\n"
            . "your current password remains unchanged.\n\n"
            . "Myanmar Horizons";

        return send_email($toEmail, $subject, $html, $text);
    }
}