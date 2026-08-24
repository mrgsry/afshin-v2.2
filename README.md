
Simple CRM/Quotation/Invoice application (PHP + MySQL)
-----------------------------------------------------
Structure:
- db.php          : database connection
- functions.php   : helper functions
- index.php       : dashboard (requires login)
- login.php       : login page
- logout.php      : logout
- customers_create.php, customers_list.php
- quotations_create.php, quotations_list.php
- invoices_create.php, invoices_list.php
- users_create.php, users_manage.php
- schema.sql      : SQL to create database and seed admin user

Notes:
- Uses mysqli and procedural PHP for simplicity.
- Uses CDN for Bootstrap, jQuery, AdminLTE CSS/JS and Font Awesome.
- Default DB credentials are in db.php (change to match your environment).
- To use: import schema.sql into MySQL, update db.php, and place files in your webroot.

Security configuration:
- Gemini API key is stored encrypted in the `ai_settings` table.
- For production, configure a long random `AFSHIN_ENCRYPTION_KEY` environment variable. If it is absent, local development uses the built-in development fallback so the application remains usable.
- Generate a production key with PHP: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`
- Keep the same `AFSHIN_ENCRYPTION_KEY` when deploying updates. Changing it makes existing encrypted API keys unreadable; save the API key again after an intentional key rotation.
- Do not put the Gemini API key in PHP source, SQL dumps, browser JavaScript, or Git history.
