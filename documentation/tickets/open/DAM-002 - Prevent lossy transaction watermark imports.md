# DAM-002: Prevent lossy transaction watermark imports

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Blocker |
| Area | Import correctness |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php:updateBivioTransactions` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Replace the date-only incremental watermark with a reconciliation strategy that cannot silently lose same-day, backdated, corrected, retried, or remotely windowed transactions.

The production import is scheduled by cron to run once daily at 7:00 AM. Its intended behavior is to persist every transaction added in Bivio since the preceding successful update.

## 2. Severity

**Blocker.** Silent ledger gaps directly corrupt cash, holdings, basis, performance, and valuations and may be impossible to reconstruct automatically after the watermark advances.

## 3. Ticket size (effort)

**Large (3-5 engineering days).** Work includes provider-window investigation, identity design, reconciliation logic, downstream invalidation behavior, and comprehensive edge-case tests.

## 4. Issue detail

The importer uses the maximum stored transaction date as its only watermark and imports only remote rows with a strictly later date. This can permanently skip same-day additions, backdated entries, corrections, and rows left after a partial failure.

The current Bivio CSV is ordered newest-first and returned exactly 30 recent rows during validation. The importer has no pagination, completeness check, or gap detection, so it assumes that the returned window contains every missing transaction.

## 5. Supporting information and implementation guidance

### Evidence and observed data

- The production database update runs daily via cron at 7:00 AM.
- Each scheduled run is intended to save all Bivio transactions added since the previous successful update.
- The local watermark is selected with `ORDER BY transaction_date DESC LIMIT 1`.
- Rows are accepted only when `remote_date > latest_local_date`.
- Live validation on 2026-10-21 returned 30 rows ordered newest-first.
- Multiple legitimate transactions can occur on the same date.
- Historical data contains backdated entries and corrections.

### Operational schedule and watermark semantics

The daily schedule reduces the likelihood that more than the provider's recent-row window will accumulate between normal runs. It does not, by itself, guarantee the intended incremental behavior because the current watermark represents the transaction's effective date rather than the time the transaction became available to the importer.

For example, if the 7:00 AM run imports one transaction dated today and another transaction with today's date is added afterward, the next run's strict `remote_date > latest_local_date` comparison will reject the later transaction. The same limitation applies to transactions entered after the prior run with a backdated effective date. A failed or skipped cron run can also allow the number of unseen transactions to exceed the observed remote window.

### Impact

- Missing transactions corrupt cash, positions, cost basis, performance, and valuation results.
- A partial import of the newest date can cause all remaining rows on that date to be skipped on retry.
- If the remote endpoint is capped, a long outage can create an undetected permanent gap.
- Existing records are never reconciled when Bivio changes or removes them.

### Design guidelines and proposed approach

1. Establish whether Bivio provides a stable transaction identifier or a complete/paginated export.
2. Parse and validate the full available response before deciding which rows to import.
3. Import the watermark date as an overlap and deduplicate at row level rather than using strict date comparison.
4. Prefer full staged reconciliation when the endpoint provides an authoritative snapshot.
5. Detect a possible remote-window gap by comparing the local watermark with the oldest returned remote date and known pagination/completeness metadata.
6. Return the earliest changed transaction date so downstream derived data can be recomputed.

### Scope boundaries

- Do not use a naive unique key that can collapse two legitimate identical transactions.
- Do not silently treat the current 30-row response as a guaranteed complete history without confirming the provider contract.
- Database transactionality and concurrency controls are tracked in DAM-003 but should be designed together with this ticket.

### Acceptance criteria

- [ ] A normal 7:00 AM scheduled run imports every transaction added since the previous successful run.
- [ ] A transaction added later on the current maximum date is imported exactly once.
- [ ] A transaction added since the previous run with a backdated effective date is handled according to the documented reconciliation policy.
- [ ] A retry after a mid-batch failure imports all remaining rows without duplicates.
- [ ] Backdated additions or corrections are detected according to the documented reconciliation policy.
- [ ] The importer detects and reports when the remote window may not reach the local watermark.
- [ ] Remote rows are processed in a deterministic order.
- [ ] The import result identifies the earliest affected date.
- [ ] Tests cover same-day additions, backdated rows, corrections, retries, and an endpoint-window gap.

### Validation plan

- Model two consecutive daily runs at 7:00 AM, with transactions added between them, and verify that every new source row is persisted exactly once.
- Use fixtures with multiple rows on the same date and newest-first ordering.
- Simulate failure after the first row, rerun the import, and compare final rows to the source fixture.
- Test a local watermark older than the oldest remote row.
- Test a changed historical row and confirm the documented correction behavior.
- Confirm downstream recalculation is triggered or explicitly queued from the earliest changed date.

### Dependencies and notes

- Coordinate identity and deduplication design with DAM-003.
- The live response includes `Date`, `Exec.`, `Description`, `Amount`, and `Balance`; the full row may help construct a fingerprint if no provider ID exists.

### Closure record

When complete, record the chosen identity strategy, provider completeness findings, migration requirements, implementation commit, and test evidence before moving this file to the closed-ticket directory.
