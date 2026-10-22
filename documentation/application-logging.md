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

## Cron runner and aggregate outcome

`RunDatabaseUpdates.sh` resolves its own directory, changes to that directory, and runs the four scheduled jobs synchronously in dependency order:

1. `updateBivioTransactions`
2. `updatePerformanceData`
3. `updateValueTable`
4. `updateHistoryTable`

The next job does not start until the current PHP process exits successfully. If a job returns a nonzero exit code, the script records the job and its exit code, stops immediately, and does not run any dependent jobs that follow it. It then calls the CLI-only `logDatabaseUpdateSummary` mode with the failed job name.

Before starting the pipeline, the script verifies that the configured PHP CLI executable is available and that `damwidiMain.php` exists in the script directory. A preflight failure is written to standard error and exits immediately because the PHP summary endpoint is not available in that situation.

The script does not create or maintain a filesystem log. Start, completion, and end messages are written to standard output. Job failures, preflight failures, and summary-publication failures are written to standard error for the cron or container runtime to handle.

After all jobs succeed, or after the first job failure, the script attempts to publish one aggregate summary through Monolog:

- `Database updates successful`
- `Database updates fail: updateBivioTransactions`

The outcome and failed job name are part of the log message so they are visible in the Better Stack event list without opening the structured context. The record also contains:

| Field | Description |
| --- | --- |
| `event` | Always `database_updates.summary` |
| `status` | `successful` or `fail` |
| `jobs` | The complete ordered list of scheduled jobs |
| `failed_jobs` | Empty on success; contains the first failed job on failure |
| `duration_seconds` | Total pipeline duration before summary publication |

The failed command's numeric exit code is printed to standard error but is not currently included in the Better Stack summary. The script exits `0` only when all jobs and the summary command succeed. A failed job or failed summary command produces exit status `1`.

The runner supports these optional environment variables:

- `DAMWIDI_PLATFORM`: one of the supported platform values.
- `DAMWIDI_PHP_BINARY`: PHP CLI path; defaults to `/usr/local/bin/php`.

Example DigitalOcean cron entry for a daily 7:00 AM run:

```cron
0 7 * * * cd /opt/damwidi && /usr/bin/docker compose run --rm --no-deps --entrypoint /bin/bash php /var/www/RunDatabaseUpdates.sh
```

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
