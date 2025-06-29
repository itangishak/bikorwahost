# BIKORWA SHOP Web Application

This repository contains the source code for **BIKORWA SHOP**, a PHP based web application for bar management.

## Installation

1. **Create the database**
   - Import `Bikorwa/sql/create_database.sql` into your MySQL server. This script creates all necessary tables including the `sessions` table used for login sessions.
   - Optionally run `Bikorwa/sql/migration_fix_reports.sql` to add performance indexes.
2. **Configure credentials**
   - Update `Bikorwa/src/config/database.php` if your database credentials differ from the defaults.
3. **Launch the application**
   - Point your web server's document root to this repository or create a virtual host pointing to `index.php`.
   - Navigate to `http://your-host/Bikorwa/src/views/auth/login.php` to access the login page.

If you encounter errors about the `sessions` table missing, ensure the SQL scripts were executed. The application will now attempt to create the table automatically on first run.
