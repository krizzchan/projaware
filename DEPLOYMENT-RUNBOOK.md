# Deployment and rollback runbook — Project AWARE

Status: preparation only. Do not execute production changes until the owner approves deployment, migration and credential work. The actual production host, domain and OS are unspecified. Local Windows paths below are verified; Linux service examples are templates to adapt, not proof that services exist.

## Decisions required before release

| Target | Current state | Required owner decision |
|---|---|---|
| Expected traffic | Missing | Concurrent Administrator/Staff users, peak requests/sec, public page requests, contact count and annual growth |
| Response time | Missing | p95/p99 targets for login/dashboard/search; maximum export time |
| SMS dispatch | Missing | Recipients/broadcast, bursts/hour, provider rate/credit limits, acceptable queue wait and emergency escalation |
| Uptime | Missing | Monthly target, maintenance window, on-call response and SMS fallback procedure |
| Acceptable data loss (RPO) | Missing | Maximum minutes/hours of lost contacts/audit/broadcast data |
| Restoration time (RTO) | Missing | Maximum outage until directory and SMS operations restored |
| Retention | Missing | Periods for contacts, message contents, recipient results, audit history, sessions, logs and backups |
| Record access | Shared operations today | Confirm all active Staff may read/edit shared directory/incidents and view broadcast contents/results, or specify agency restrictions |
| Hosting | Missing | Domain, Linux/Windows, load balancer/proxy IPs, DB/SMTP providers, responsible operator |

Do not invent these values. Acceptance requires measured staging results against the approved targets.

## Prepare the hosting environment

1. Use a supported patched PHP runtime compatible with Laravel12 and required extensions. Plan for Laravel12 support expiry24 Feb2027. Upgrade MariaDB10.4 through its supported migration path in isolated staging; never change DB binaries against the operational database during this audit.
2. For Linux, use Nginx/Apache with PHP-FPM and PCNTL-capable CLI workers. For Windows, use an actual Windows service/process supervisor with restart-on-failure/boot, stop policy and bounded execution; do not treat an interactive terminal or Codex session as supervision. Test Windows job timeout behavior because PCNTL is absent locally.
3. Web document root: on the current Windows project it must be **C:/xampp-laravel13/htdocs/crud/cruduser-app/public**. Disable aliases/other virtual hosts that expose its parent tree. Prefer storing the application outside a globally served htdocs directory. Retain the repository .htaccess defense, but configure equivalent protections if using Nginx or disabling AllowOverride. Never serve storage, vendor, bootstrap/cache, .env, Git data or backup files.
4. Install a valid TLS certificate; redirect HTTP to HTTPS; configure actual host names; test from outside the network. Restrict DB inbound traffic. Enable patched TLS libraries and FPM OPcache; measure sizing instead of copying arbitrary values.
5. Use separate runtime and deployment identities. Runtime may read application code but only write storage/bootstrap/cache. Keep environment files and generated config cache readable only to app/deployment identities. No chmod777. Use a dedicated DB user for required application CRUD/queue/cache/session operations; use a separate approved migration identity for schema changes. Verify actual grants; username alone does not prove least privilege.
6. Secret provisioning must use a private managed secret store or restricted external .env. Copy the existing APP_KEY through a secure channel; never run key:generate on the current installation. Backups require the same key to decrypt broadcast/phone snapshots. Rotate exposed iTexMo credentials only with owner authorization; never put values in Git or terminal logs.

Required non-secret settings (supply private credentials separately):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_APPROVED_DOMAIN
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=90
CACHE_STORE=database
MAIL_MAILER=smtp
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
TRUSTED_PROXIES=
ITEXMO_DRY_RUN=true
```

120-minute session lifetime and14-day log retention are current defaults/examples, not approved policy. Set TRUSTED_PROXIES only to known IPs/CIDRs and verify the network strips spoofed incoming forwarded headers. Set real SMTP host/TLS/credentials, then test password recovery without logging reset links. Choose live SMS explicitly after account/credits/sender/recipients and delivery acceptance are verified. Do not blindly copy local live-SMS setting into staging.

## Build and validate an immutable release

Use version control and a tagged immutable artifact; this checkout currently has no Git repository. Ensure .env, auth.json, dumps, storage logs and public/hot are absent from release artifacts. Save previous artifact and dependency lockfiles for rollback.

On an unrestricted CI/staging machine:

```bash
composer install --no-interaction --prefer-dist
npm ci
composer audit --locked
npm audit
npx tsc --noEmit
npx eslint .
php artisan test --compact
php vendor/bin/pint --format agent
npm run build
```

The tests force in-memory SQLite, fake mail and SMS dry-run and block unmocked outbound HTTP. Supply no operational credentials to CI. Also run critical integration tests against an isolated supported MariaDB instance with representative synthetic data; SQLite alone does not prove row-lock/concurrency behavior on MariaDB. Smoke-test desktop/mobile, role access, account resets, CSV+Excel templates, shared directory edits and exports. A provider integration test requires explicit consent and an owned test number; no live SMS in load tests.

Build before removing dev dependencies from the runtime artifact:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
```

Do not ship Node dev server, public/hot, or development .env. This audit used temporary preserveSymlinks/Tailwind workarounds due sandbox restrictions; those support scripts are outside the project and are not the production build process. Require a successful ordinary npm run build with complete CSS before release.

## Approved deployment sequence

1. Verify a recent encrypted backup and isolated restore, including APP_KEY-based decryption. Record release ID, migration state, current queues and unknown recipients. Monitor provider sending history before any resend.
2. Drain or pause dispatch submissions for the approved maintenance window. Let in-flight SMS requests finish. Do not kill/resend an ambiguous provider call. Avoid queue:retry all/clearing queued rows during deployment.
3. Stage the new immutable release with private shared .env and persistent storage. Cache files belong to that release; prevent public access. Have a DB restore/release rollback checkpoint before migration.
4. Review migration SQL on an isolated clone. The new index migration is additive but may lock large tables; measure on supported MariaDB with production-sized synthetic data and schedule appropriately. Only after explicit migration authorization:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan operations:check-production
```

No migration has been applied by this audit. The checker may fail until this exact environment is configured. Its success alone cannot certify TLS/backups/grants/supervision.

5. Atomically switch current release and reload PHP-FPM/process service as needed for OPcache. Signal workers using shared cache:

```bash
php artisan queue:restart
```

Verify the supervisor starts replacement workers from the new release. An index-only migration remains compatible with the previous code, so do not roll it back just to switch releases.
6. Verify public landing/login, invalid login, Administrator vs Staff authorization, known CSV/Excel test fixture, exports, /up, and private dependency checks. Check correct HTTPS links/cookies, secret403/404, no dev assets and no debug errors. Confirm fresh worker timestamp/queue age. Send a single consented test SMS only if separately authorized, and distinguish gateway acceptance from handset receipt.
7. End maintenance only after checks pass. Watch error rate, latency, disk space, queue lag, unknown/rejected messages and credits for the agreed observation period.

## Worker supervision

For the existing database queue, HTTP timeout20 seconds < job timeout30 < worker timeout35 < retry_after90. Keep one attempt for SMS; ambiguous sends must not be retried blindly.

Example **Linux Supervisor** configuration (adapt all paths/user names and obtain deployment authorization):

```ini
[program:aware-queue]
command=/usr/bin/php /srv/project-aware/current/artisan queue:work database --sleep=3 --tries=1 --timeout=35 --max-time=3600
user=aware
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/log/project-aware/queue.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
```

Adjust worker count only after provider quota, database lock behavior and load tests. Protect logs; don't log SMS contents/credentials. Set alert thresholds for oldest jobs, failed_jobs, recipients processing too long, unknown/rejected totals, worker exits and provider-credit exhaustion. caught gateway failures are recipient states and may not appear in failed_jobs. Don't rely solely on queue:failed.

Current routes/console.php has no scheduled operational tasks. A cron entry is not currently required for application tasks; if backup/pruning/health jobs use Laravel scheduling later, deploy `schedule:run` each minute with overlap prevention and tested single-server behavior. Configure external backups/monitoring separately.

## Backup and recovery acceptance

- Automatic encrypted DB backups and key backup stored off-host with restricted service identities; monitor success, age and storage growth.
- Decide full/incremental/PITR strategy from RPO and DB version. Also preserve release artifacts, migrations and private runtime settings.
- Restore to an isolated non-serving host; disable live SMS and unmocked egress BEFORE starting workers. A restored backup may contain queued jobs and must not automatically resend operational broadcasts.
- Verify counts/relationships, decrypt SMS snapshots with the preserved key, test role access and restoration time. Reconcile queued/processing/unknown recipients with provider history before reopening dispatch.
- Record restore duration and actual data-loss window; backups are UNVERIFIED until this drill meets approved RPO/RTO.

## Rollback

Before switching releases, record the old release path/tag and back up secrets/key separately. If code smoke tests fail, stop new dispatch, allow in-flight requests to settle, switch back to the previous tested immutable artifact, restore its configuration cache from the same private environment and reload web/queue services. Retain additive indexes unless an approved DB review establishes a need to remove them.

Do not run migrate:fresh, blanket migrate:rollback, key:generate, truncate queues or queue:retry all. Database rollback/restore loses or changes data and needs explicit approval. Restore only to the approved checkpoint after reconciling post-checkpoint writes/provider side effects. Restoring rows cannot retract SMS already sent. Never automatically replay ambiguous transmissions.

## Load-test protocol

Targets are missing, so no pass/fail capacity result is claimed. In isolated staging, seed synthetic contacts/recipients only; use HTTP fakes or a private stub gateway. Model approved concurrent users and contact sizes, bursts of sends and exports; collect p50/p95/p99 latency, error rate, SQL queries/plans, memory, worker throughput and queue wait. Verify recipient idempotency under concurrency and that unknown side effects aren't retried. Agree duration and acceptance thresholds before executing. Never load-test the actual emergency gateway or operational database.

## Prepared quality and health checks

The SHA-pinned .github/workflows/quality.yml runs PHP8.4/Node24 checks against SQLite and isolated MariaDB11.4 after version control is established. This workflow has not run. The local isolated SQLite run passed 144 tests / 923 assertions; MariaDB concurrency and normal production builds still require verification.

Run php artisan operations:health --json from a private monitored process. Exit 0 means its dependency/age checks passed; exit 1 requires investigation. It checks failed jobs from the last 24 hours, queued recipients older than SMS_QUEUE_WARNING_SECONDS (default 300), stale processing and failed/unknown recipient results. Route its output to the approved monitoring system; no automatic alerts are installed. It never sends or retries SMS. Reconcile unknown results with provider history.
