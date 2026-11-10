# Project AWARE setup

Project: C:/xampp-laravel13/htdocs/crud/cruduser-app

## First administrator

Open a terminal in the project directory and run:

    php artisan operations:create-admin

Enter your name, lowercase email, and a password with at least 12 characters including uppercase/lowercase letters, a number, and a symbol. Password entry is hidden. No default administrator credentials exist. This command refuses to create another first administrator when an active administrator exists.

To promote an existing account instead, use its actual email:

    php artisan operations:set-role YOUR_EXISTING_EMAIL admin

Sign in at http://localhost:8000/login. Open Account Access to create Administrator or Staff accounts, change roles, and enable/disable access. Account changes require your current password. The last active administrator cannot be removed. Staff can manage contacts, send test SMS, and record incidents; directory configuration, imports, account management, and audit history require Administrator access.

## SMS test mode

For test mode, configure ITEXMO_DRY_RUN=true. The current local environment may be live; check its setting before dispatch. New broadcasts are queued and processed as simulated; they do not contact iTexMo. Run the queue worker in a separate terminal:

    php artisan queue:work --tries=1 --timeout=30

If your development command already runs a queue worker, keep that worker running instead. Reports & SMS History displays actual processing outcomes. Unknown live outcomes require manual review before another send; the application avoids automatically retrying ambiguous transmissions.

## iTexMo configuration

Provider documentation: https://itexmo.com/apidocs

The integration uses https://api.itexmo.com/api/broadcast with Email, Password, ApiCode, Recipients, and Message. Configure ITEXMO_EMAIL, ITEXMO_PASSWORD, and ITEXMO_API_CODE privately in .env. Keep ITEXMO_DRY_RUN=true until the account, credits, approved sender configuration, and recipient permissions are verified. Credentials are never returned to the browser.

Provider acceptance is recorded as accepted; this does not establish handset delivery. Automated tests must not send real SMS. Operational sends performed separately are recorded in Reports & SMS History.

## Before production

Use HTTPS, APP_ENV=production, APP_DEBUG=false, the real APP_URL, a dedicated least-privilege database account, and a supervised queue worker. The web server document root must be the public directory. Configure trusted proxies for your actual deployment if TLS terminates upstream. Configure real mail delivery for password recovery.

Back up the database and existing APP_KEY securely. Broadcast messages and recipient phone snapshots are encrypted using that key: regenerating it can make those records unreadable. Define retention and backup access appropriate to the contact data.

Deploy dependencies and assets, review migrations, then run:

    php artisan migrate --force
    php artisan optimize
    php artisan queue:restart

The new migration has already been applied to your local MySQL database. Do not run migrate:fresh against your operational database.

## Current production audit

See [PRODUCTION-AUDIT.md](PRODUCTION-AUDIT.md) for the current evidence-based checklist and [DEPLOYMENT-RUNBOOK.md](DEPLOYMENT-RUNBOOK.md) for deployment and rollback instructions. Earlier test counts are historical and do not certify the present checkout. The current isolated SQLite suite passed 144 tests and 923 assertions using a bootstrap workaround. MariaDB CI and production infrastructure checks remain outstanding; production readiness is not established.
