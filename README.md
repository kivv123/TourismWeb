# Myanmar Horizons — XAMPP setup

1. Install XAMPP with PHP 8+ and start **Apache** and **MySQL**.
2. Copy this project folder to `C:\xampp\htdocs\tourism_website` (the folder name must match `BASE_URL` in `config/database.php`).
3. Open phpMyAdmin, create/import the database by importing `database/tourism_db.sql`.
4. Create the initial administrator securely. Generate a hash in a terminal where PHP is available:

   `php -r "echo password_hash('choose-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"`

   Then in phpMyAdmin run the commented `INSERT INTO users` statement at the bottom of `database/tourism_db.sql`, replacing `PASTE_HASH_HERE`. This keeps the password out of source code and allows PHP `password_verify()` authentication.
5. Update `DB_USER` / `DB_PASS` in `config/database.php` if your MySQL credentials are not XAMPP defaults.
6. Confirm the `uploads/destinations`, `uploads/packages`, `uploads/hotels`, and `uploads/drivers` directories are writable by Apache.
7. Open [http://localhost/tourism_website/](http://localhost/tourism_website/).

Use the Admin dashboard to create driver and hotel accounts. Customer accounts are created through the public registration form. Never place real payment credentials in this project; payment records keep only a method and optional transaction reference.
