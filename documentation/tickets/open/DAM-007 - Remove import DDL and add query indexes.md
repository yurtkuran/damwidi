# DAM-007: Remove import DDL and add query indexes

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Medium |
| Area | Database performance / Reliability |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php`, `data_transactions` schema |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Remove the unnecessary table-ordering DDL from every import and add only evidence-backed indexes for transaction watermark and downstream ledger queries.

## 2. Severity

**Medium.** The current DDL adds locking, I/O, and implicit-commit risk but does not itself change financial logic under normal execution.

## 3. Ticket size (effort)

**Medium (1-2 engineering days), plus migration rollout.** Removing the DDL is small; query inventory, plan analysis, migration testing, and production rollout account for most of the work.

## 4. Issue detail

Every successful importer run executes `ALTER TABLE data_transactions ORDER BY transaction_date ASC`. Physical table ordering does not guarantee result ordering, can rebuild or lock the table, and can create implicit transaction boundaries. The table also lacks indexes for its common date and symbol/date access patterns.

## 5. Supporting information and implementation guidance

### Evidence

- `updateBivioTransactions()` runs the `ALTER TABLE` even when no rows were inserted.
- Queries already require explicit `ORDER BY` for deterministic results.
- The recorded schema has only an auto-increment primary key.
- Common reads filter or sort by `transaction_date`, `symbol`, and transaction type.

### Impact

- Unnecessary locking and I/O occur during the nightly workflow.
- The DDL prevents clean atomic-import design.
- Date-range and position queries require avoidable scans as the ledger grows.

### Design guidelines and proposed approach

1. Remove `ALTER TABLE ... ORDER BY` from the importer.
2. Inventory real transaction-table query patterns.
3. Use `EXPLAIN` against representative data to select indexes.
4. Add indexes through an explicit, reversible migration; likely candidates include `transaction_date` and `(symbol, transaction_date)`.
5. Ensure every consumer requiring order specifies `ORDER BY` explicitly.
6. Coordinate any source-identity index with DAM-003.

### Scope boundaries

- Do not add speculative indexes without examining query plans and write overhead.
- Do not modify production schema outside the normal migration/deployment process.
- Exact monetary and encoding schema improvements may be handled separately unless included in the same reviewed migration.

### Acceptance criteria

- [ ] The importer performs no table-ordering DDL.
- [ ] Required result ordering is explicit in all relevant queries.
- [ ] Selected indexes are documented with the queries they support.
- [ ] The migration is reversible and tested against a representative backup.
- [ ] Query plans show the intended indexes for watermark, position, and date-range reads.
- [ ] Import transaction boundaries are not broken by DDL.

### Validation plan

- Capture `EXPLAIN` output before and after the migration.
- Run the migration against a disposable database restored from a representative backup.
- Verify all transaction consumers return the same logical results.
- Measure importer and downstream query duration before and after the change.

### Dependencies and notes

- Coordinate identity/unique indexes with DAM-003.
- This ticket requires explicit approval before applying a production schema change.

### Closure record

When complete, record migration identifiers, query-plan evidence, performance measurements, implementation commit, and production rollout confirmation before moving this file to the closed-ticket directory.
