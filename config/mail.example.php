<?php

declare(strict_types=1);

/**
 * MAIL CONFIG TEMPLATE - copy to config/mail.php and fill in real values:
 *   cp config/mail.example.php config/mail.php
 * config/mail.php is git-ignored; this template is safe to commit.
 *
 * OTHER PROVIDERS:
 *  - Outlook/Office365: host smtp.office365.com, port 587, encryption tls
 *  - Yahoo:             host smtp.mail.yahoo.com,  port 587, encryption tls
 *  - Local test relay (e.g. Mailpit): host 127.0.0.1, port 1025, encryption ''
 */

return [
    'app_url' => '',               // e.g. 'http://localhost/TourismWeb'; '' = auto-detect
    'host' => 'smtp.gmail.com',
    'port' => 587,                 // 587 = STARTTLS, 465 = SSL
    'encryption' => 'tls',         // 'tls' | 'ssl' | '' (none)
    'username' => '',              // SMTP username (full email address for Gmail)
    'password' => '',              // SMTP password / Gmail App Password
    'from_email' => '',            // leave '' to use 'username'
    'from_name' => 'Myanmar Horizons',
    'debug' => 0,                  // 0 = off (production), 2 = verbose (testing only)
];