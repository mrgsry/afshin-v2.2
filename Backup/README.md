
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
