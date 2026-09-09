<?php

declare(strict_types=1);

/**
 * =========================================================================
 *  CUSTOMER FORGOT PASSWORD FLOW  -  shared helpers
 * =========================================================================
 *
 *  Security model:
 *  - The account is identified SERVER-SIDE (email/phone lookup) and kept
 *    in the PHP session. No customer/user id is ever accepted from the URL.
 *  - OTPs are 6 digits from random_int(), stored ONLY as password_hash(),
 *    expire after 5 minutes, are single-use, invalidated when a new one is
 *    issued, and die after 5 wrong attempts.
 *  - Resend cooldown (60s) + request rate limit (5 per 15 min per account).
 *  - After a successful OTP check a random 32-byte flow token is created;
 *    the session keeps the token, the database keeps only its SHA-256.
 *    The reset-password page refuses to run unless that pair matches, and
 *    the token is single-use and expires after 10 minutes.
 * =========================================================================
 */

const PR_OTP_TTL        = 300;   // OTP valid for 5 minutes
const PR_RESEND_COOLDOWN = 60;   // seconds between two OTP sends
const PR_RATE_WINDOW    = 900;   // rate limit window (15 minutes)
const PR_RATE_MAX       = 5;     // max OTP sends per window per account
const PR_MAX_ATTEMPTS   = 5;     // wrong entries allowed per OTP
const PR_VERIFY_GRACE   = 600;   // OTP-verified authorization valid 10 minutes
const PR_FLOW_TTL       = 1800;  // whole recovery flow expires after 30 minutes

if (!function_exists('pwreset_flow')) {
    /** Current recovery flow state from the session (or null). */
    function pwreset_flow(): ?array
    {
        $f = $_SESSION['pwreset'] ?? null;
        if (!is_array($f) || !isset($f['user_id'])) {
            return null;
        }
        // Whole-flow timeout: force a fresh start.
        if ((time() - (int) ($f['started_at'] ?? 0)) > PR_FLOW_TTL) {
            pwreset_clear();
            return null;
        }
        return $f;
    }
}

if (!function_exists('pwreset_clear')) {
    /** Forget every trace of the recovery flow. */
    function pwreset_clear(): void
    {
        unset($_SESSION['pwreset']);
    }
}

if (!function_exists('pwreset_start')) {
    /**
     * Remember the identified account for the rest of the flow.
     * $methods holds the RAW phone/email ('' when not available).
     */
    function pwreset_start(int $userId, array $methods): void
    {
        $_SESSION['pwreset'] = [
            'user_id'    => $userId,
            'methods'    => $methods,
            'method'     => null,
            'reset_id'   => null,
            'masked'     => '',
            'resend_at'  => 0,
            'verified'   => false,
            'flow_token' => null,
            'started_at' => time(),
        ];
    }
}

if (!function_exists('mask_email')) {
    /** "customer@gmail.com" -> "c******@gmail.com" */
    function mask_email(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }
        $local = substr($email, 0, 1) . str_repeat('*', max(1, min(6, $at - 1)));
        return $local . substr($email, $at);
    }
}

if (!function_exists('mask_phone')) {
    /** "09789012345" -> "09*****2345" (keeps first 2 / last 4) */
    function mask_phone(string $phone): string
    {
        $digits = normalize_phone($phone);
        if (strlen($digits) < 6) {
            return '***';
        }
        return substr($digits, 0, 2) . str_repeat('*', strlen($digits) - 6) . substr($digits, -4);
    }
}

if (!function_exists('pr_hash_ip')) {
    /** Anonymous client fingerprint for abuse auditing (no raw IP stored). */
    function pr_hash_ip(): string
    {
        return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    }
}

if (!function_exists('pr_find_customer_by_identifier')) {
    /**
     * Server-side account identification: looks up an ACTIVE customer by
     * email OR phone. Returns ['user_id','name','email','phone'] or null.
     * The phone comparison normalizes separators/+95 so customers can type
     * the number in any common format.
     */
    function pr_find_customer_by_identifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $emailHit = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : null;
        $digits = normalize_phone($identifier);

        // Build conditions explicitly - never compare against sentinel
        // values (empty/NULL-byte params can broaden the match!).
        $conds = [];
        $params = [];
        if ($emailHit !== null) {
            $conds[] = 'u.email = ?';
            $params[] = $emailHit;
        }
        if (strlen($digits) >= 6) {
            $conds[] = "REPLACE(REPLACE(REPLACE(REPLACE(c.phone,' ',''),'-',''),'(',''),')','') LIKE ?";
            $params[] = '%' . substr($digits, -6);
        }
        if (!$conds) {
            return null;
        }

        $stmt = db()->prepare(
            "SELECT u.user_id, u.name, u.email, u.status, c.phone
             FROM users u
             JOIN customers c ON c.user_id = u.user_id
             WHERE u.role = 'customer'
               AND (" . implode(' OR ', $conds) . ")
             LIMIT 2"
        );
        $stmt->execute($params);

        $found = null;
        while ($row = $stmt->fetch()) {
            if (($row['status'] ?? '') !== 'active') {
                continue; // inactive accounts can never self-recover
            }
            // Exact confirmation after the SQL narrowing.
            if (strtolower((string) $row['email']) === strtolower($identifier)) {
                $found = $row;
                break;
            }
            if ($digits !== '' && normalize_phone((string) $row['phone']) === $digits) {
                $found = $row;
                break;
            }
        }

        if ($found === null) {
            return null;
        }

        return [
            'user_id' => (int) $found['user_id'],
            'name'    => (string) $found['name'],
            'email'   => (string) $found['email'],
            'phone'   => (string) $found['phone'],
        ];
    }
}

if (!function_exists('pr_available_methods')) {
    /** Which recovery channels does this account actually have? */
    function pr_available_methods(array $account): array
    {
        return [
            'phone' => trim((string) ($account['phone'] ?? '')) !== '',
            'email' => trim((string) ($account['email'] ?? '')) !== '',
        ];
    }
}

if (!function_exists('pr_recent_request_count')) {
    /** How many OTPs were issued for this account within the window? */
    function pr_recent_request_count(int $userId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM password_resets
             WHERE user_id = ? AND created_at > (NOW() - INTERVAL ' . PR_RATE_WINDOW . ' SECOND)'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('pr_seconds_since_last_request')) {
    /** Seconds elapsed since the most recent OTP send (9999 when never). */
    function pr_seconds_since_last_request(int $userId): int
    {
        $stmt = db()->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM password_resets WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $v = $stmt->fetchColumn();
        return $v === null ? 9999 : (int) $v;
    }
}

if (!function_exists('pr_last_request_method')) {
    /** Delivery method of the most recent OTP send ('' when never). */
    function pr_last_request_method(int $userId): string
    {
        $stmt = db()->prepare(
            'SELECT delivery_method FROM password_resets WHERE user_id = ? ORDER BY reset_id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $v = $stmt->fetchColumn();
        return $v === false ? '' : (string) $v;
    }
}

if (!function_exists('pr_generate_otp')) {
    /** Cryptographically secure 6-digit OTP (random_int, never rand/mt_rand). */
    function pr_generate_otp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('pr_invalidate_pending')) {
    /** A new OTP always kills every still-pending one (switch/new request). */
    function pr_invalidate_pending(int $userId): void
    {
        db()->prepare(
            "UPDATE password_resets SET status = 'invalidated'
             WHERE user_id = ? AND status = 'pending'"
        )->execute([$userId]);
    }
}

if (!function_exists('pr_issue_otp')) {
    /**
     * Create an OTP row for the chosen method, enforcing cooldown and
     * rate limits BEFORE invalidating the previous code.
     *
     * Cooldown rule: the 60-second gap applies to re-sending to the SAME
     * channel. Switching to the OTHER channel is allowed immediately
     * (required "Use another method" flow); the per-window rate limit
     * still caps total sends, so alternating channels cannot be abused.
     *
     * Returns:
     *   ['ok' => true,  'reset_id' => int, 'otp' => string, 'resend_at' => int]
     *   ['ok' => false, 'reason' => 'cooldown'|'ratelimit', 'retry_after' => int]
     */
    function pr_issue_otp(int $userId, string $method, string $destination): array
    {
        $sinceLast = pr_seconds_since_last_request($userId);
        $lastMethod = pr_last_request_method($userId);
        $isSwitch = $lastMethod !== '' && $lastMethod !== $method;

        if (!$isSwitch && $sinceLast < PR_RESEND_COOLDOWN) {
            return ['ok' => false, 'reason' => 'cooldown', 'retry_after' => PR_RESEND_COOLDOWN - $sinceLast];
        }

        if (pr_recent_request_count($userId) >= PR_RATE_MAX) {
            $oldest = db()->prepare(
                'SELECT UNIX_TIMESTAMP(MIN(created_at)) FROM password_resets
                 WHERE user_id = ? AND created_at > (NOW() - INTERVAL ' . PR_RATE_WINDOW . ' SECOND)'
            );
            $oldest->execute([$userId]);
            $windowStart = (int) $oldest->fetchColumn();
            return ['ok' => false, 'reason' => 'ratelimit', 'retry_after' => max(1, $windowStart + PR_RATE_WINDOW - time())];
        }

        pr_invalidate_pending($userId);

        $otp = pr_generate_otp();
        $stmt = db()->prepare(
            "INSERT INTO password_resets
                (user_id, delivery_method, destination, otp_hash, expires_at, status, ip_hash, created_at)
             VALUES (?, ?, ?, ?, (NOW() + INTERVAL " . PR_OTP_TTL . " SECOND), 'pending', ?, NOW())"
        );
        $stmt->execute([$userId, $method, $destination, password_hash($otp, PASSWORD_DEFAULT), pr_hash_ip()]);

        return [
            'ok'        => true,
            'reset_id'  => (int) db()->lastInsertId(),
            'otp'       => $otp,
            'resend_at' => time() + PR_RESEND_COOLDOWN,
        ];
    }
}

if (!function_exists('pr_invalidate_row')) {
    /** Kill one specific row - used when the provider failed to send. */
    function pr_invalidate_row(int $resetId): void
    {
        db()->prepare(
            "UPDATE password_resets SET status = 'invalidated' WHERE reset_id = ? AND status = 'pending'"
        )->execute([$resetId]);
    }
}

if (!function_exists('pr_deliver_otp')) {
    /**
     * Send the OTP through the chosen channel.
     * (mailer.php / sms.php must be require_once-d by the caller.)
     */
    function pr_deliver_otp(string $method, string $destination, string $otp): bool
    {
        if ($method === 'email') {
            return send_password_reset_otp_email($destination, $otp);
        }
        if ($method === 'phone') {
            return send_sms(
                $destination,
                "Myanmar Horizons: your password reset code is {$otp}. It expires in 5 minutes. "
                . 'Never share this code - staff will never ask for it.'
            );
        }
        return false;
    }
}

if (!function_exists('pr_load_reset_row')) {
    /** Fetch one OTP row owned by this user (ownership enforced in SQL). */
    function pr_load_reset_row(int $resetId, int $userId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM password_resets WHERE reset_id = ? AND user_id = ?'
        );
        $stmt->execute([$resetId, $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

if (!function_exists('pr_verify_otp')) {
    /**
     * Check a submitted code against the pending row.
     *
     * Returns:
     *   ['ok' => true, 'flow_token' => hex]
     *   ['ok' => false, 'reason' => 'missing'|'used'|'expired'|'invalid'|'maxattempts']
     */
    function pr_verify_otp(int $resetId, int $userId, string $code): array
    {
        $row = pr_load_reset_row($resetId, $userId);
        if ($row === null) {
            return ['ok' => false, 'reason' => 'missing'];
        }
        if ($row['status'] === 'verified') {
            return ['ok' => false, 'reason' => 'used'];
        }
        if ($row['status'] !== 'pending') {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        if (strtotime((string) $row['expires_at']) <= time()) {
            db()->prepare(
                "UPDATE password_resets SET status = 'invalidated' WHERE reset_id = ?"
            )->execute([$row['reset_id']]);
            return ['ok' => false, 'reason' => 'expired'];
        }

        if (!password_verify(trim($code), (string) $row['otp_hash'])) {
            $attempts = (int) $row['attempts'] + 1;
            if ($attempts >= PR_MAX_ATTEMPTS) {
                db()->prepare(
                    'UPDATE password_resets SET attempts = ?, status = ? WHERE reset_id = ?'
                )->execute([$attempts, 'invalidated', $row['reset_id']]);
                return ['ok' => false, 'reason' => 'maxattempts'];
            }
            db()->prepare(
                'UPDATE password_resets SET attempts = ? WHERE reset_id = ?'
            )->execute([$attempts, $row['reset_id']]);
            return ['ok' => false, 'reason' => 'invalid', 'left' => PR_MAX_ATTEMPTS - $attempts];
        }

        // Correct code: burn the OTP and mint the single-use reset
        // authorization. Session keeps the token, DB keeps only the hash.
        $flowToken = bin2hex(random_bytes(32));
        db()->prepare(
            "UPDATE password_resets
             SET status = 'verified', verified_at = NOW(), flow_token_hash = ?
             WHERE reset_id = ? AND status = 'pending'"
        )->execute([hash('sha256', $flowToken), $row['reset_id']]);

        return ['ok' => true, 'flow_token' => $flowToken];
    }
}

if (!function_exists('pr_verified_authorization')) {
    /**
     * Reset-page guard: the session flow must be OTP-verified and match a
     * 'verified' row whose flow token hash equals the session token, still
     * inside the grace period. Returns the row or null (=> kick to start).
     */
    function pr_verified_authorization(): ?array
    {
        $flow = pwreset_flow();
        if ($flow === null || empty($flow['verified']) || empty($flow['flow_token']) || empty($flow['reset_id'])) {
            return null;
        }

        $row = pr_load_reset_row((int) $flow['reset_id'], (int) $flow['user_id']);
        if ($row === null || $row['status'] !== 'verified') {
            return null;
        }
        if (!hash_equals((string) ($row['flow_token_hash'] ?? ''), hash('sha256', (string) $flow['flow_token']))) {
            return null;
        }
        $verifiedAt = strtotime((string) ($row['verified_at'] ?? ''));
        if ($verifiedAt === false || (time() - $verifiedAt) > PR_VERIFY_GRACE) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('pr_consume_authorization')) {
    /**
     * Burn the authorization after a successful password change: the row
     * becomes 'used' and every remaining pending OTP for the account dies.
     */
    function pr_consume_authorization(int $resetId, int $userId): void
    {
        db()->prepare(
            "UPDATE password_resets SET status = 'used', used_at = NOW()
             WHERE reset_id = ? AND status = 'verified'"
        )->execute([$resetId]);
        pr_invalidate_pending($userId);
    }
}