<?php
// LIVE SMS settings - this file is git-ignored (see .gitignore).
// 'log' mode is for local XAMPP testing: OTP texts are written to
// logs/sms.log (server-side only) and the page clearly shows test mode.
// Switch to 'http' + api_url/api_key when you sign up with a real
// SMS provider for production.
return [
    'mode'      => 'log',
    'api_url'   => '',
    'api_key'   => '',
    'sender_id' => 'MYANMARH',
];