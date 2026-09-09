<?php

declare(strict_types=1);

/**
 * =========================================================================
 *  SMS LAYER  -  Myanmar Horizons
 * =========================================================================
 *
 *  The project has no built-in SMS provider. This file adds a small,
 *  honest abstraction with three modes (config/sms.php):
 *
 *    'log'      DEVELOPMENT / TESTING ONLY. The message is appended to
 *               logs/sms.log on the server. It is NEVER shown in the
 *               browser and the UI clearly displays a "test mode" notice,
 *               so the system never pretends a real SMS was delivered.
 *
 *    'http'     PRODUCTION. Sends the message to your SMS gateway over
 *               HTTP(S). Configure api_url / api_key / sender_id in
 *               config/sms.php (git-ignored). Any provider that accepts a
 *               JSON POST (or form POST) works, e.g. local Myanmar SMS
 *               gateways, Twilio-style providers, etc.
 *
 *    'disabled' Phone delivery is switched off. Password recovery then
 *               falls back to Email OTP (the user simply picks email).
 *
 *  Credentials are NEVER hard-coded here - they live only in the
 *  git-ignored config/sms.php or environment variables.
 *
 *  Every function returns a boolean. Failures are written to
 *  logs/sms.log and are never echoed to the browser.
 * =========================================================================
 */

if (!function_exists('sms_config')) {
    /**
     * Load SMS configuration once per request. config/sms.php (live
     * values) is preferred; config/sms.example.php is the safe template.
     * Environment variables override file values.
     */
    function sms_config(): array
    {
        static $config = null;

        if ($config === null) {
            $live = __DIR__ . '/../config/sms.php';
            $template = __DIR__ . '/../config/sms.example.php';
            $config = is_file($live) ? require $live : require $template;
            if (!is_array($config)) {
                $config = [];
            }

            $envMap = [
                'mode' => 'SMS_MODE',
                'api_url' => 'SMS_API_URL',
                'api_key' => 'SMS_API_KEY',
                'sender_id' => 'SMS_SENDER_ID',
            ];
            foreach ($envMap as $key => $env) {
                $value = getenv($env);
                if ($value !== false && $value !== '') {
                    $config[$key] = $value;
                }
            }
        }

        return $config;
    }
}

if (!function_exists('sms_log')) {
    /** Append a line to logs/sms.log (web access blocked by logs/.htaccess). */
    function sms_log(string $message): void
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $dir . '/sms.log');
    }
}

if (!function_exists('normalize_phone')) {
    /**
     * Normalize a phone number to plain local digits for reliable
     * matching. "09-789 012 345", "+959789012345" and "09789012345"
     * all normalize to the same value.
     */
    function normalize_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        // +95 / 95 international prefix -> local leading zero.
        if (str_starts_with($digits, '95') && strlen($digits) >= 10) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }
}

if (!function_exists('send_sms')) {
    /**
     * Send an SMS according to config/sms.php.
     * Returns true only when delivery was actually accepted (or, in
     * 'log' test mode, when the message was written to the server log).
     */
    function send_sms(string $toPhone, string $message): bool
    {
        $c = sms_config();
        $mode = (string) ($c['mode'] ?? 'disabled');

        if ($mode === 'log') {
            // DEVELOPMENT / TEST MODE ONLY - never presented as a real delivery.
            sms_log('TEST MODE (not delivered) | to ' . normalize_phone($toPhone) . ' | body: ' . str_replace("\n", ' / ', $message));
            return true;
        }

        if ($mode !== 'http') {
            sms_log('SMS mode is "disabled" - message for ' . normalize_phone($toPhone) . ' was NOT sent.');
            return false;
        }

        $url = trim((string) ($c['api_url'] ?? ''));
        $key = trim((string) ($c['api_key'] ?? ''));
        if ($url === '' || $key === '') {
            sms_log('SMS api_url/api_key missing in config/sms.php - message for ' . normalize_phone($toPhone) . ' was NOT sent.');
            return false;
        }

        $payload = json_encode([
            'to' => normalize_phone($toPhone),
            'from' => (string) ($c['sender_id'] ?? ''),
            'message' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $key,
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            sms_log('HTTP SMS gateway failed | to ' . normalize_phone($toPhone) . ' | http ' . $status . ' | ' . ($error !== '' ? $error : (string) $body));
            return false;
        }

        sms_log('SMS accepted by gateway | to ' . normalize_phone($toPhone) . ' | http ' . $status);
        return true;
    }
}

if (!function_exists('sms_test_mode')) {
    /** True when SMS runs in the clearly-marked development log mode. */
    function sms_test_mode(): bool
    {
        return (string) (sms_config()['mode'] ?? 'disabled') === 'log';
    }
}