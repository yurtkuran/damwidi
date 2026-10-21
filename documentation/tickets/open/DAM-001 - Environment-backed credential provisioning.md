# DAM-001: Environment-backed credential provisioning

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Blocker |
| Area | Security / Deployment |
| Created | 2026-10-21 |
| Related code | `php-includes/definedConstants.php`, `php/damwidiScrapeBivio.php` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Make Bivio credential delivery secure and deployment-safe by sourcing credentials from the runtime environment, validating configuration explicitly, and rotating the historically exposed password.

## 2. Severity

**Blocker.** The merged scraper can fail in any environment whose ignored configuration file has not been updated, while plaintext or historically exposed credentials create a direct security risk.

## 3. Ticket size (effort)

**Small (0.5-1 engineering day), plus deployment coordination.** The code change is limited, but cron configuration, secret rollout, and credential rotation must be coordinated and verified in every runtime.

## 4. Issue detail

The scraper requires `BIVIO_USERNAME` and `BIVIO_PASSWORD`, but `php-includes/definedConstants.php` is intentionally excluded from Git. Merging the scraper commit therefore does not provision the new constants in another checkout or deployment. The current local configuration also stores the Bivio credentials as plaintext constants instead of reading them from the runtime environment.

On runtimes where either constant is absent, behavior can vary by PHP version. The scraper should fail clearly before referencing an undefined constant.

## 5. Supporting information and implementation guidance

### Evidence

- `.gitignore` excludes `php-includes/definedConstants.php`.
- `bivioLogin()` directly references both Bivio constants.
- The production update is run by cron, whose environment may differ from an interactive shell.
- A former Bivio credential existed in tracked source history and must be considered exposed.

### Impact

- A deployment can fail immediately after merge because the expected constants are missing.
- Plaintext credentials remain available on disk to anyone who can read the configuration file.
- A cron-only configuration problem may not be detected by an interactive manual test.
- An exposed historical credential may remain usable if it is not rotated.

### Design guidelines and proposed approach

1. Define `BIVIO_USERNAME` and `BIVIO_PASSWORD` from environment variables in the ignored local configuration layer.
2. Validate with `defined()` before reading either constant, then reject empty values.
3. Configure the variables in every execution environment, including the production cron context.
4. Add a tracked, non-secret configuration example or deployment note listing the required variable names.
5. Rotate the Bivio password through Bivio and update the runtime secret.
6. Confirm that credentials, login payloads, cookies, and authentication responses are never logged.

### Scope boundaries

- Do not commit real credential values.
- Do not place secrets in a tracked example file, shell script, test fixture, or CI log.
- A broader migration of unrelated application secrets is desirable but not required to close this ticket.

### Acceptance criteria

- [ ] No Bivio username or password is hardcoded in tracked source.
- [ ] The Bivio constants are populated from environment variables or an approved secret store.
- [ ] Missing or empty credentials produce a sanitized, nonzero CLI failure.
- [ ] The scheduled job receives both variables in its real cron environment.
- [ ] A tracked non-secret document identifies the required variable names.
- [ ] The previously exposed Bivio password has been rotated.
- [ ] Logs and test output contain no credential values.

### Validation plan

- Run PHP syntax checks on modified PHP files.
- Run the job with variables absent and confirm a nonzero, sanitized failure before any request.
- Run the read-only scraper with the configured environment and confirm authentication succeeds.
- Inspect verbose and error output for secret leakage.
- Verify the production cron environment without printing secret values.

### Dependencies and notes

- Coordinate password rotation with deployment so the old password is not invalidated before the new runtime secret is available.
- This ticket should be completed before deploying the merged scraper.

### Closure record

When complete, record the implementation commit, validation evidence, deployment date, and credential-rotation confirmation before moving this file to the closed-ticket directory.
