# Break Tracker

Manual break-time tracking: PHP 8.2+, MySQL/MariaDB (PDO), Bootstrap 5, vanilla JS + fetch. No frameworks, no Composer, no desktop agent.

## Folder structure
```
break-tracker/
├── config/config.php          Database + base path settings
├── database/schema.sql        Tables + default settings/policy
├── includes/                  bootstrap (session, PDO, CSRF, auth, audit), breaks (calculations/reports), header, footer
├── tools/create_admin.php     CLI: create the first administrator
└── public/                    <-- web server document root
    ├── login.php, logout.php, index.php
    ├── employee/dashboard.php
    ├── admin/                 dashboard, employees, employee_form, policies, history, record_edit, reports, export, audit, settings
    ├── api/                   break_start, break_end, status (employee) · admin_status (admin) – all JSON
    └── assets/{css,js}
```

## Installation
1. **Requirements:** PHP 8.2+ with `pdo_mysql` and `mbstring`; MySQL 5.7+ / MariaDB 10.3+; Apache or Nginx.
2. **Copy** the project to the server, e.g. `/var/www/break-tracker`.
3. **Database:**
   ```
   mysql -u root -p < database/schema.sql
   mysql -u root -p -e "CREATE USER 'break_user'@'localhost' IDENTIFIED BY 'a-strong-password'; GRANT SELECT,INSERT,UPDATE,DELETE ON break_tracker.* TO 'break_user'@'localhost';"
   ```
4. **Configure** `config/config.php` (DB password; `base_path` only if installed in a sub-folder).
5. **Create the first admin** (on the server): `php tools/create_admin.php admin "a-long-password-12+"`
6. **Point the web server's document root at `public/`** so `config/`, `includes/` and `database/` are never web-accessible.

**Apache**
```
<VirtualHost *:443>
    ServerName breaks.company.local
    DocumentRoot /var/www/break-tracker/public
    <Directory /var/www/break-tracker/public>
        AllowOverride Options
        Require all granted
    </Directory>
    # SSLEngine on + certificate directives
</VirtualHost>
```
**Nginx (PHP-FPM)**
```
server {
    listen 443 ssl;
    server_name breaks.company.local;
    root /var/www/break-tracker/public;
    index index.php;
    location / { try_files $uri $uri/ =404; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.2-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
    location ~ /\. { deny all; }
}
```
7. **Log in** as admin → **Settings**: set the company timezone (default UTC) and thresholds → **Policies**: adjust allowance → **Employees**: add accounts.

## How it works
- **Start/End break** (both Option A and B are the same buttons): the server stamps the time in UTC. Refreshing/closing the browser changes nothing; the timer is recomputed from the server start time on reload.
- **One active break per employee** is enforced in PHP and by a unique index in the database.
- **Forgotten breaks** stay open and are flagged (“Long break – check”) after the configured minutes. An admin fixes them via *Employees → History → Correct* (reason mandatory, written to the audit log).
- **Exceeding the allowance** is allowed and flagged (red) for employees and admins. A break counts toward the day it started in (company timezone).
- **Admin dashboard** polls every 15 s; timers tick locally each second.
- **Reports:** Reports → daily or monthly → *Export CSV* (Excel-safe UTF-8, formula-injection protected).

## Security notes
- Use **HTTPS** (session cookies become `Secure` automatically). 
- Passwords: `password_hash` (bcrypt/argon default); 5 failed logins lock an account for 15 minutes.
- All queries use PDO prepared statements; all output is HTML-escaped; every POST needs a CSRF token; every page/API checks the role on the server.
- Bootstrap is loaded from the jsDelivr CDN. For an offline/internal server, download Bootstrap 5.3.3 into `public/assets/` and change the two URLs in `includes/header.php` and `includes/footer.php`.
- Admin accounts are created only via the CLI tool. Employees cannot change their own password; admins reset it in *Edit employee*.

## Limitations
- Changing a policy's allowance applies to past days in reports too (allowances are not versioned).
- Records are corrected, never deleted, to keep the history auditable.
- Back up the database regularly (`mysqldump break_tracker`).
