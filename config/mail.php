<?php

declare(strict_types=1);

/**
 * =========================================================================
 *  LIVE MAIL / SMTP CONFIGURATION  -  Myanmar Horizons
 * =========================================================================
 *
 *  SECURITY RULES
 *  --------------
 *  1. Real SMTP credentials belong ONLY in this file.
 *  2. This file is listed in .gitignore - NEVER commit it to git.
 *  3. Never paste credentials anywhere else in the source code.
 *
 *  GMAIL SETUP (recommended for XAMPP)
 *  -----------------------------------
 *  - Enable 2-Step Verification on the Google account.
 *  - Create an App Password: https://myaccount.google.com/apppasswords
 *    (16-character password, e.g. "abcd efgh ijkl mnop").
 *  - Put the Gmail address in 'username' and the App Password in
 *    'password' (spaces are OK, PHPMailer strips them).
 *  - Use port 587 with encryption 'tls' (or 465 with 'ssl').
 *
 *  Every value can also be supplied through environment variables
 *  (MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USERNAME, MAIL_PASSWORD,
 *  MAIL_FROM_ADDRESS, MAIL_FROM_NAME) - they override the values here.
 *
 *  If 'username'/'password' are left empty, email sending is skipped and
 *  the reason is written to logs/mail.log; account creation is NOT affected.
 * =========================================================================
 */

return [
    // Absolute URL of the app, used for the login link in emails.
    // Leave '' to auto-detect (e.g. http://localhost/TourismWeb).
    'app_url' => '',

    'host' => 'smtp.gmail.com',
    'port' => 587,                    // 587 = STARTTLS, 465 = SSL
    'encryption' => 'tls',            // 'tls' | 'ssl' | '' (none)

    'username' => 'zwethihazaw08@gmail.com',                 // e.g. yourmail@gmail.com
    'password' => 'wpfh jlsw ztrn ncul',                 // Gmail App Password (NOT the account password)

    'from_email' => '',               // leave '' to use 'username'
    'from_name' => 'Myanmar Horizons',

    // SMTP debug output: 0 = off (keep 0 in production), 2 = verbose (testing only)
    'debug' => 0,
];