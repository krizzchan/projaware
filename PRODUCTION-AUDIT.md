# Production readiness audit — Project AWARE

Audit date: 8 October 2026, Asia/Manila. Scope: C:/xampp-laravel13/htdocs/crud/cruduser-app and read-only inspection of local Apache/runtime configuration. No deployment, credential rotation, live SMS transmission, operational migration, or operational data modification was performed by this audit. Local anonymous HTTP smoke checks may create ordinary session rows.

## Verdict

**NOT READY FOR PRODUCTION.** Critical infrastructure and verification gaps remain. Code inspection is not a substitute for passing security tests or verifying the actual production environment.

The highest-risk finding was direct HTTP access to `.env` through the parent Apache document root. A headers-only check returned 200 before remediation and 403 after adding the root `.htaccess`. No secret file body was retrieved. `composer.json` is also blocked, while a public logo still returns 200. This is a local verified mitigation; the production document root must still be restricted to `public/` and equivalent protections must be tested on the deployment server.

## Actual stack and evidence

- `composer show --direct`: Laravel **12.69.2**, inertia-laravel **2.0.27**, Ziggy **2.6.4**, Pest **3.8.7**, Pint **1.30.4**. `composer.json` requires PHP ^8.2; versions are locked.
- `php -v`: **PHP 8.2.12**, Windows ZTS, build dated October 2023. PHP 8.2 security support ends 31 December 2026; this old patch is not an acceptable production runtime. Plan a currently patched compatible PHP branch, preferably 8.4/8.5 after testing.
- `SELECT VERSION()` returned **MariaDB 10.4.32** using Laravel's mysql driver. Community maintenance for 10.4 ended 18 June 2024. Upgrade through a separately approved, restore-tested database migration to a supported release.
- `npm ls --depth=0`: React **19.0.0**, @inertiajs/react **2.0.3**, Tailwind **4.0.8**, Vite **6.4.4**; TypeScript, shadcn/Radix and Lucide UI. Node **24.14.0** is the local build runtime.
- Authentication: Laravel web/session guard, Eloquent User provider, bcrypt/hashed model cast, built-in password broker. No WorkOS, Sanctum API, or public signup enabled. Admin/Staff operate one shared CDRRMO directory.
- Integration: iTexMo form POST over HTTPS, separate job per recipient, encrypted message/phone snapshots, Excel OOXML exports via XMLWriter/PharData, CSV-only contact imports.
- Current sanitized runtime: local environment, debug=false, HTTP APP_URL, database sessions/cache/queue, encrypted HTTP-only lax cookies, 120-minute session lifetime, secure cookie unset locally, mail transport=log, live SMS enabled, development hot file present. Values of keys/passwords/API codes were not displayed.
- Local Apache 2.4.58 / OpenSSL 3.1.3; `httpd.conf` exposes `C:/xampp-laravel13/htdocs`, AllowOverride All. No application-specific active virtual host, deployment workflow, supervisor configuration, monitoring setup or backup artifacts were found in the repository. Absence in the repository does not prove absence elsewhere; external systems remain unverified.
- CLI has PDO MySQL/SQLite, OpenSSL, XMLWriter and Phar; PCNTL and OPcache are absent. FPM/Apache extension configuration has not been established. On Windows Laravel worker timeouts lack PCNTL enforcement; production workers need appropriate process supervision and a tested timeout strategy.

## Prioritized requirements checklist

PASS means evidence supports the stated scope only. PARTIAL means some controls exist but a required part is missing. UNVERIFIED means the deployment evidence or executable verification is unavailable. FAIL identifies a demonstrated problem. NOT APPLICABLE is narrowly scoped to this application.

| ID / priority | Requirement | Status | Evidence / affected files | Impact and required fix |
|---|---|---|---|---|
| S01 P0 | Supported PHP/database runtime | FAIL | PHP 8.2.12; MariaDB 10.4.32; runtime inspection | Patch PHP and migrate off EOL MariaDB with backup/restore rehearsal. No upgrades applied. |
| S02 P1 | Supported framework/dependencies | PARTIAL | composer.lock/package-lock; Composer and npm audits report zero advisories | Laravel 12 security support ends 24 Feb 2027; plan upgrade before then. Zero known advisories is not proof of total security; automate recurring audits. |
| S03 P0 | Production environment/debug | PARTIAL | app provider forces debug=false in production; current APP_ENV=local | Deployment must set APP_ENV=production, APP_DEBUG=false and validate HTTP error responses. |
| S04 P0 | APP_KEY/credential protection | PARTIAL | key present; encrypted casts; expanded .gitignore; .env served before fix | Store secrets outside releases; secure and back up existing key. iTexMo credentials shared in chat need owner-authorized rotation. Do not regenerate APP_KEY. No Git history exists locally to prove secrets were never committed. |
| S05 P0 | Public-only document root | PARTIAL | Apache htdocs root; .htaccess now returns 403 for .env/composer.json, public logo 200 | Local disclosure mitigation verified. Restrict deployment root to public and test vendor/bootstrap/storage/backup paths, including alternate virtual hosts. |
| S06 P0 | HTTPS/certificate/redirect | UNVERIFIED | local APP_URL uses HTTP; production HSTS middleware exists | Configure valid certificate, HTTP-to-HTTPS redirect, supported TLS and test externally. No certificate assumed. |
| S07 P1 | Cookies/session expiration | PARTIAL | config/session.php: encrypted, HttpOnly, lax, 120 min; production forces secure | Test Secure on actual HTTPS/proxy chain; approve idle lifetime and persistent remember-me policy. |
| S08 P1 | Trusted proxies/hosts | PARTIAL | explicit TRUSTED_PROXIES config, TrustProxies registration; TrustHosts restricted to APP_URL host outside local/testing | Set only actual proxy IPs/CIDRs, never wildcard; configure actual host and verify forwarded-header spoofing in staging. |
| S09 P1 | Authentication | PARTIAL | LoginRequest, session regeneration/invalidation, disabled account checks; registration disabled | Inspected controls; 144 isolated SQLite tests passed; verify and authenticated browser workflows before release. MFA is not implemented; evaluate administrator requirement. |
| S10 P1 | Server authorization | PARTIAL | routes/web.php Gate operate/administer; users/audit/import route middleware inspected | Authorization regression tests passed in isolated SQLite. Confirm allowed role matrix and test all routes in staging. |
| S11 P1 | Record-level access | PARTIAL | profile bound to current user; global directory/incident/broadcast access allowed to Staff by design | No tenant/agency ownership boundaries exist. Obtain business acceptance of shared operational records; if restricted agency scopes are required, add policies/query scoping before launch. |
| S12 P1 | Password hashing/resets | PARTIAL | hashed User cast, minimum 12 mixed-case/number/symbol; token expiration/throttle; AuthenticateSession | Added remember-token/reset-token revocation and session deletion on own password change. Admin reset already revokes sessions. Password revocation tests passed; verify real reset mail and token expiry/reuse. |
| S13 P1 | Validation/mass assignment | PARTIAL | FormRequests, controller validation, fillable models; roles only force-filled from validated admin requests | Source inspection found controls. Run validation/privilege-escalation tests; keep bounded request sizes at proxy/PHP. |
| S14 P1 | CSRF | PARTIAL | default web middleware; no exemptions found | Verify rejected unauthenticated/invalid-token state-changing requests on actual HTTP deployment; tests normally disable CSRF. |
| S15 P1 | Escaped output/SQL | PASS | React rendering, Blade escaped syntax; fixed aggregate SQL and bound Eloquent filters; no dangerous output/raw user SQL found in scoped search | Keep this invariant in future changes. This is scoped code inspection, not a penetration-test claim. |
| S16 P2 | CORS | NOT APPLICABLE | no routes/api.php/API guard/cross-origin application frontend | Same-origin web application. If adding API/cross-origin clients, define exact origin/method/credential policy and test it. |
| S17 P1 | Rate limits | PARTIAL | login 5 failures/email+IP; reset 5/min/IP; SMS 2/min +20/hour; imports3/min; mutations60/min; daily recipient cap | Added password-confirmation throttle. Exports now limited to 2/min/user and recipient picker to 60/min/user; export size and other costly reads still need load-based limits. Proxy/IP correctness unverified. |
| S18 P1 | Uploads | PARTIAL | importCsv: CSV extension+MIME, 1MB, 1000 rows, validated IDs/phone; temporary upload, atomic import | Import regression tests passed in isolated SQLite. No public upload storage. Server body limits/temp directory permissions need verification. |
| S19 P0 | DB/filesystem least privilege | FAIL | current database username is root; process access and deployed ACLs unknown | Dedicated runtime DB user; separate migration identity; DB private network; writable only storage/bootstrap cache, restricted secret/read ACLs. |
| S20 P1 | Sensitive logging | PARTIAL | SMS exceptions deliberately discard provider body/credentials; audit metadata limited at call sites | MAIL_MAILER=log writes reset links to logs; replace with real private mail. Generic DB exceptions can include bound data; restrict/redact central logs and retention. No raw log contents inspected. |
| S21 P2 | Security headers/CSP | PARTIAL | SecurityHeaders has nosniff/DENY/referrer/permissions/basic CSP/HSTS | CSP lacks script-src nonce policy and does not block general inline scripts; stage a full CSP compatible with Ziggy/Vite. Headers not applied to /up/static files; server must supply them. |
| P01 P1 | Database indexes | PARTIAL | live Schema::getIndexes; foreign keys/unique phones/request IDs/recipient compound index | Prepared migration adds audience/activity/status/history indexes; NOT applied. Measure EXPLAIN and migration locking on staging-sized MariaDB data. |
| P02 P2 | N+1 avoidance | PASS | Contact index eager-loads category/barangay; reports eager-load broadcasts/contacts; lazy(200) export; withCount aggregates | Inspected main workflows. Verify query counts with representative data, especially growing directories. |
| P03 P1 | Pagination/bounds | PARTIAL | Contacts picker and account access paginate25; draft picker paginate20; isolated tests verify search/pages/deep-linked drafts | All pages remain accessible and selections persist. Synchronous exports and reference directories still require scale testing. |
| P04 P1 | Transactions/query efficiency | PARTIAL | imports/accounts/broadcasts use transactions and user lock; updated database job insertion atomic | Same DB queue now commits/rolls back with broadcast. Other queue backends still require transactional outbox if used. Daily quota now uses indexed UTC ranges matching Philippine days; tests cover midnight boundaries. Measure plans on MariaDB. |
| P05 P2 | Cache expiry/invalidation | PARTIAL | database limiter cache, no custom stale domain cache found; live analytics recomputed | No premature content cache added. Profile load, define TTL/invalidation before introducing shared analytics caches. |
| P06 P1 | Deployment cache | UNVERIFIED | local config/routes uncached; preflight checks this | In a release, cache config/routes/views and test startup; do not run against live local configuration during audit. |
| P07 P1 | env/config cache compatibility | PASS | env() confined to config; application uses config(); explicit proxy config | Source search supports config-cache compatibility; actual optimize smoke test remains deployment verification. |
| P08 P1 | Frontend assets/images | PARTIAL | temporary workaround Vite build succeeded; standard npm build EPERM in this sandbox; assets split/minified; PNG logo ~365KB | Clean normal build in CI required; validate manifest CSS/hashed assets and no hot file. Optimize logos losslessly/thumbnail variants; no AI/image alterations performed. |
| P09 P1 | Queues for slow work | PARTIAL | SMS queued; synchronous CSV/Excel exports | SMS worker verified previously, not supervised. Bound/queue large exports after targets are agreed. |
| P10 P0 | Traffic and latency targets/load testing | UNVERIFIED | no target document, load runner, or performance results | Establish concurrency/requests/sec, directory size, SMS burst and p95/p99 latency; run isolated synthetic staging test with iTexMo mocked. No load sent to operational app/provider. |
| P11 P2 | Runtime optimization | PARTIAL | CLI OPcache unavailable; no FPM tuning evidence | Verify production FPM OPcache, memory and worker sizing; CLI absence alone does not prove FPM absence. |
| R01 P0 | Supervised workers | FAIL | queue previously stalled when temporary worker exited; no verified service/supervisor | Deploy supervised workers with restart/boot/log/alert policy; terminal session is not production supervision. |
| R02 P1 | Retries/timeouts | PARTIAL | job tries1/timeout30; HTTP connect5/total20; queue retry_after90 | Correct ordering inspected. Preserve no retry after ambiguous SMS. Linux PCNTL + supervisor hard stop needed; current Windows lacks PCNTL. |
| R03 P1 | Idempotency | PARTIAL | request UUID unique + payload hash/user validation; atomic queued->processing claim | Idempotency and transaction regression tests passed in isolated SQLite. Crash after send can remain processing; alert/reconcile without blindly resending. Provider exactly-once delivery isn't guaranteed. |
| R04 P1 | Failed-job monitoring | PARTIAL | failed_jobs migration and job.failed state exist; caught provider failures don't enter failed_jobs | Monitor database result unknown/failed/processing age AND queue failures/age; wire alert recipients. No alert system verified. |
| R05 P2 | Scheduler/overlap | NOT APPLICABLE | routes/console.php has inspire only; no scheduled operational jobs | If backups/cleanup are implemented via scheduler, add cron/service and withoutOverlapping/onOneServer as appropriate. No cron assumed. |
| R06 P1 | External service failures | PARTIAL | SmsGateway fixed HTTPS endpoint, timeout, no redirects/retry; status unknown on ambiguity | Provider credits/quota/sender approval and egress/TLS must be validated. Keep secrets out of debug logs. No SMS/API call made by audit. |
| R07 P1 | Logging/errors/health | PARTIAL | /up returned200, logging config exists; health only boot check | Added private CLI operations:health with DB/cache probes, failed-job counts and stale recipient detection; wire centralized sanitized logs, on-call alerts and external uptime checks. Do not expose exceptions/credentials. |
| R08 P0 | Backups/restoration | UNVERIFIED | no verified backup records or restore drill | Encrypt automatic DB/key backups off-host; test isolated restore and key-based decryption; define RPO/RTO/retention/access before launch. |
| R09 P1 | Safe migrations/deployment/rollback | PARTIAL | reversible additive index migration; no Git repo; SHA-pinned quality workflow prepared for PHP8.4 and SQLite/MariaDB11.4, not executed; existing setup doc | Version releases, approve/apply index migration after staging; preserve current key and data; follow runbook. No migrate:fresh/down/rollback executed. |
| R10 P1 | Worker reload | UNVERIFIED | Laravel queue:restart available; worker is long-lived | Use supervised restart/reload after atomic release switch and shared cache restart signal; verify new workers use new release. Audit did not restart live workers. |
| R11 P0 | Critical automated tests | PARTIAL | Isolated Pest run: 144 tests, 923 assertions,0 failures/errors/skips; fake mail and blocked unmocked HTTP | SQLite suite passed. Normal runner/native access remains restricted; prepared MariaDB CI and full HTTP workflows must pass before release. |
| R12 P1 | Timezone accuracy | PARTIAL | Analytics and quota use Asia/Manila calendar days with UTC storage; SQLite boundary/quota tests pass | MariaDB DATE_ADD expression is fixed SQL; confirm equivalent integration results in prepared CI. |
| R13 P1 | Log/data retention | UNVERIFIED | daily log retention default14, stack single logs, no audited data cleanup | Agree contact/SMS/audit/session/log/backup retention; avoid unbounded disks and privacy leakage; no records deleted. |

## Changes implemented

- Root Apache deny rule and explicit public allow rule; public dotfiles denied. Verified `.env` and `composer.json` 403, public logo200 and login200 after change.
- `.gitignore` covers all environment variants except `.env.example`, credentials files and SQL dumps; repository history still unavailable because there is no local Git repository.
- Password-confirmation rate limit; own password changes transactionally revoke remember tokens, reset tokens and other database sessions, and record a metadata-free audit event.
- Explicit proxy allow-list environment option, initialized in provider after config loads; deferred trusted-host matcher uses the configured APP_URL host. Local/testing host behavior preserved.
- Broadcast enqueueing on the existing same-connection database queue moved into the broadcast transaction; a failed queue insert rolls back the broadcast and the crash window between commit and enqueue is removed for this backend. Other backends retain original after-commit behavior.
- Additive query-index migration prepared, not applied.
- `operations:check-production` read-only command reports configuration checks without printing credential values. It intentionally fails on the current local configuration; it cannot certify infrastructure.
- PHPUnit forces isolated SQLite, fake mail, test queue and dry-run SMS; Feature tests block unmocked outbound HTTP. Added tests for password revocation, confirmation throttling and atomic queue rollback.
- ESLint excludes the generated Ziggy file (32 escape warnings were from generated route code); handwritten frontend source still linted. No generated route content edited.

## Verification ledger

- Composer audit --locked: no advisories/abandoned packages; cache permission warning, uncached audit succeeded.
- npm audit: zero known vulnerabilities.
- npx tsc --noEmit: passed.
- npx eslint .: passed after excluding generated Ziggy artifact.
- PHP syntax checks: passed for all changed PHP files.
- Artisan route inspection: 57 app routes; audit/accounts/import routes confirm auth + role middleware.
- Production preflight ran successfully as a checker and returned failure for local environment/HTTP/nonsecure cookies/root DB/log mail/hot file/uncached config and routes. Failure is expected evidence, not a launch certificate.
- Headers-only Apache disclosure regression checks: .env403, composer.json403, public image200; local login200; /up200.
- Standard Vite build: blocked by sandbox EPERM realpath on app.css. Build with temporary preserveSymlinks configuration: succeeded; Tailwind stylesheet regenerated and manifest hash updated. Clean standard CI build still required.
- Pest: 144 tests, 923 assertions,zero failures/errors/skips in an isolated copy with SQLite in memory. Native is_readable prevents ordinary bootstrap; preloading the same Composer autoload before the unchanged Pest executable bypassed that restriction. Only the duplicate bootstrap attribute was omitted from the copied configuration. JUnit counts verified. No operational credentials/database or real HTTP used. MariaDB matrix remains unexecuted.
- Pint: --dirty cannot run without Git; explicit changed-file formatting then blocked by native file-write restrictions. PHP syntax is verified; final style check remains required.
- No penetration test, production TLS probe, authenticated end-to-end test, load test, restore drill, SMTP delivery check or server permission audit completed.

## Launch blockers in order

1. Confirm public-only virtual host and inaccessible secrets on every served origin. Local .htaccess protection now works, but the parent document root remains wrong.
2. Owner-authorized rotation of exposed iTexMo credentials; preserve existing APP_KEY. Upgrade old PHP and EOL MariaDB, with restore-tested staging first.
3. Passing MariaDB integration tests and clean standard build on unrestricted staging/CI; SQLite suite already passes. Then functional password-reset, import, reports/export and authorized SMS smoke checks.
4. Production HTTPS, least-privilege DB/filesystem identities, real SMTP and exact proxy/host configuration.
5. Supervised workers with queue/recipient-age/failure monitoring and escalation; tested graceful deployment restart.
6. Verified encrypted backups, isolated restoration, approved RPO/RTO/uptime/retention and rollback drill.
7. Agree shared record access policy, scale targets, verify new paginated pickers/accounts at scale, measure SQL plans and load test before projected directory growth.

## Official sources consulted

- Laravel 12 support: https://laravel.com/docs/12.x/releases (security fixes through 24 Feb 2027).
- PHP branch support: https://www.php.net/supported-versions.php.
- MariaDB maintenance: https://mariadb.org/about/#maintenance-policy (10.4 EOL18 Jun2024).
- Laravel 12 deployment, queues, requests and passwords: https://laravel.com/docs/12.x/deployment ; https://laravel.com/docs/12.x/queues ; https://laravel.com/docs/12.x/requests ; https://laravel.com/docs/12.x/passwords.
- Inertia v2: https://inertiajs.com/docs/v2/the-basics/links.

## Follow-up production preparation

- Searchable paginated accounts and recipient/draft pickers, preserving contact selections and independent deep-linked templates.
- Philippine calendar-day analytics and index-friendly SMS quota bounds, with boundary tests.
- Per-user export and directory-read limits, with export throttle regression coverage.
- Read-only operations:health reports aggregate DB/cache/queue indicators without personal data or provider calls. Existing unknown SMS requires manual reconciliation.
- SHA-pinned .github/workflows/quality.yml prepares PHP8.4/Node24, SQLite and isolated MariaDB11.4 checks; it is not executed or published. Native formatting remains unverified.
