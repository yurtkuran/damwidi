# DAM-003: Make transaction persistence atomic and idempotent

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | High |
| Area | Database reliability / Concurrency |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php`, `php/dataHandlerMySQL.php` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Persist each Bivio import as one concurrency-safe, atomic, and repeatable batch instead of committing each transaction independently.

## 2. Severity

**High.** A failure or concurrent run can leave a partial or duplicated financial ledger, and the current design cannot roll the batch back safely.

## 3. Ticket size (effort)

**Large (3-5 engineering days).** The work spans repository APIs, transaction boundaries, locking, identity constraints, migrations, and database integration tests.

## 4. Issue detail

Each imported transaction opens a new PDO connection and performs an independent insert. The batch has no database transaction, no source identity constraint, and no concurrency lock. A failure leaves a partially committed batch, while overlapping executions can insert duplicates.

## 5. Supporting information and implementation guidance

### Evidence

- `saveTransactionData()` calls `connect()` for every row.
- `updateBivioTransactions()` writes rows as it parses them.
- The transaction table has only an auto-increment primary key.
- There is no import lock, unique source key, staging table, or rollback boundary.
- `ALTER TABLE ... ORDER BY` runs after inserts and can implicitly commit.

### Impact

- Partial imports become visible to all downstream jobs.
- Retries can miss or duplicate transactions depending on the date watermark.
- Concurrent cron/manual runs can import the same source rows twice.
- Repeated database connections add avoidable latency and failure points.

### Design guidelines and proposed approach

1. Fetch and validate remote data before beginning a database transaction.
2. Reuse one PDO connection and one prepared insert/upsert statement.
3. Acquire a MySQL advisory lock or equivalent application lock for the import.
4. Stage or normalize all candidate rows before writing.
5. Define a provider ID or collision-safe source fingerprint and enforce the selected identity policy.
6. Commit the complete batch atomically; roll back on any failure.
7. Remove DDL from the import transaction.
8. Return structured counts for inserted, updated, unchanged, rejected, and total source rows.

### Scope boundaries

- Do not hold a database transaction open during Bivio network requests.
- Do not use `INSERT IGNORE` as a substitute for a documented identity policy.
- Schema/index work must be delivered through an explicit migration and coordinated with DAM-007.

### Acceptance criteria

- [ ] A failed batch leaves the transaction table unchanged.
- [ ] Reprocessing the same source data produces no duplicate rows.
- [ ] Two concurrent import attempts cannot write overlapping batches.
- [ ] One PDO connection and reusable prepared statements are used for the batch.
- [ ] The import produces accurate structured result counts.
- [ ] Database exceptions cause a rollback and nonzero CLI exit.
- [ ] Integration tests verify rollback, retry, idempotency, and concurrency behavior.

### Validation plan

- Inject a failure after one staged row and confirm no rows commit.
- Run the same fixture twice and compare table contents and result counts.
- Attempt two imports concurrently and confirm the lock behavior.
- Verify that a legitimate pair of similar transactions is not incorrectly deduplicated.
- Measure connection count and batch execution time before and after the change.

### Dependencies and notes

- The source identity design is shared with DAM-002.
- Removing the table-ordering DDL is tracked in DAM-007.

### Closure record

When complete, record the migration, identity strategy, lock behavior, implementation commit, rollback test, and concurrency test before moving this file to the closed-ticket directory.
