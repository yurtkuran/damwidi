# Application logging

Damwidi uses Monolog to send application records to Better Stack through the Logtail handler. Scheduled-update failures are also written as JSON to standard error so cron and container runtimes retain a local failure record.

## Platform configuration

Every record includes a lowercase `platform` field. The supported values are:

- `local`
- `digitalocean`
- `hostgator`

Set the platform with the `DAMWIDI_PLATFORM` environment variable. An identically named constant in the ignored `php-includes/definedConstants.php` file is supported for environments that cannot provide environment variables, but the environment variable takes precedence.

If neither setting exists, the application uses `local` and emits a `logging.platform.defaulted` warning. Production cron and container environments must set an explicit value so records are not mislabeled.

Current defaults are configured as follows:

- Local Docker Compose sets `DAMWIDI_PLATFORM=local`.
- The existing `UpdateDatabases.sh` script defaults to `DAMWIDI_PLATFORM=hostgator` while preserving an explicitly configured runtime value.
- The production Docker image defaults to `DAMWIDI_PLATFORM=digitalocean`; its runtime environment may override this value.

Any other value is rejected during logger initialization.

## Common record fields

All Monolog records contain the following structured context fields (stored under `monolog.context` in the Logtail payload):

| Field | Description |
| --- | --- |
| `application` | Always `damwidi` |
| `platform` | Runtime platform from the supported list |
| `environment` | Existing application environment such as `PROD` or `DEV` |
| `host` | Server or container hostname |

Scheduled update records also contain:

| Field | Description |
| --- | --- |
| `job` | Scheduled function name |
| `run_id` | Random identifier shared by all records from one execution |
| `duration_ms` | Numeric elapsed time on completion or failure |

The four scheduled updates emit the lifecycle events `job.started`, `job.completed`, and `job.failed`. Completion records include job-specific counts such as retrieved records, written records, processed symbols, and reconciliation notices.

## Migration monitoring

During the HostGator-to-DigitalOcean transition, use the same Better Stack source and filter or group records by `platform`, `job`, and `run_id`.

Recommended alerts and saved queries include:

1. A `job.failed` event on either production platform.
2. No `job.completed` event after the expected daily update window.
3. A `logging.platform.defaulted` event from a production environment.
4. Material differences in completion counts between HostGator and DigitalOcean.
5. Abnormally high `duration_ms` values.

Platform tagging does not make concurrent database writes safe. During parallel validation, only one platform should execute write-enabled scheduled jobs unless database locking and idempotent persistence are implemented. Disable the HostGator cron before enabling DigitalOcean as the active writer.

## Sensitive information

Do not include credentials, cookies, authentication payloads, anti-CSRF fields, raw provider responses, or complete financial records in log context. Bivio verbose output is intentionally limited to transaction date, symbol, and type. Scheduled-job exception messages redact common API-key, token, password, secret, login-password, and bearer-authorization patterns before remote or terminal logging.
