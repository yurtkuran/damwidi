# DAM-008: Add structured import observability

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Medium |
| Area | Operations / Logging |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php`, `damwidiMain.php`, `UpdateDatabases.sh` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Provide one sanitized, structured result for every import so operators can distinguish complete success, no-change runs, source gaps, rejected data, rollbacks, and authentication failures.

## 2. Severity

**Medium.** Weak observability does not directly corrupt data, but it allows incomplete or failed imports to remain undetected and slows incident response.

## 3. Ticket size (effort)

**Medium (1-2 engineering days).** Result modeling and log routing are straightforward, though accurate reconciliation counts depend on the import redesign tickets.

## 4. Issue detail

The importer logs duration but does not provide enough structured information to prove that the remote ledger was complete or that the expected rows were imported. Failures are written to stderr while the cron script redirects only stdout to its primary log file. Verbose mode can emit full financial transaction details.

## 5. Supporting information and implementation guidance

### Evidence

- Completion logging lacks source-row, inserted, skipped, updated, rejected, and unknown counts.
- Oldest/newest remote dates and possible endpoint-window gaps are not logged.
- CLI errors go to stderr and `UpdateDatabases.sh` appends only stdout.
- Verbose transaction logs include descriptions, amounts, and other financial details.
- The `$debug` argument is currently unused by the importer.

### Impact

- A successful-looking run can import zero or incomplete data without an actionable summary.
- Operators may need multiple logs to understand a failed cron execution.
- Verbose logs can retain more financial information than needed.
- Provider contract changes may be detected only through downstream valuation differences.

### Design guidelines and proposed approach

1. Return a structured import result from the service layer.
2. Log source row count, candidate count, inserted/updated/unchanged/rejected counts, unknown types, oldest/newest source dates, duration, and gap status.
3. Emit sanitized failures through Monolog and stderr.
4. Decide whether cron should redirect stderr into its main log or a dedicated error log.
5. Redact or minimize transaction-level verbose context while retaining useful identifiers.
6. Define alert thresholds for authentication failure, schema mismatch, possible source gap, unknown types, and partial/rolled-back batches.

### Scope boundaries

- Never log credentials, cookies, login payloads, anti-CSRF values, or raw authentication responses.
- Avoid logging full financial records by default.
- Observability must not convert a successful committed import into a failed job solely because a remote log sink is unavailable.

### Acceptance criteria

- [ ] Every run emits one structured success or failure summary.
- [ ] The summary contains row counts and source date boundaries.
- [ ] Potential endpoint-window gaps and unknown transaction types generate visible alerts.
- [ ] Cron captures or routes stderr according to a documented policy.
- [ ] Logging failures do not obscure the database commit state.
- [ ] Default logs contain no secrets and minimize financial detail.
- [ ] Tests assert redaction and summary fields for success and failure scenarios.

### Validation plan

- Exercise no-change, new-row, validation-failure, authentication-failure, and rollback scenarios.
- Confirm stdout, stderr, local cron log, and remote log behavior.
- Search captured logs for credential values and session tokens using non-printing assertions.
- Verify verbose and non-verbose output separately.

### Dependencies and notes

- Accurate result counts depend on DAM-002 and DAM-003.
- Gap alerts depend on a documented provider-window policy.

### Closure record

When complete, record the structured log schema, alert destinations, cron routing change, implementation commit, and redaction test evidence before moving this file to the closed-ticket directory.
