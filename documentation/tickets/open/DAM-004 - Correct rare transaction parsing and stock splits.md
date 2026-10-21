# DAM-004: Correct rare transaction parsing and stock splits

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | High |
| Area | Financial correctness / Parsing |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php:parseTransaction`, `php/damwidiUpdateValue.php:returnOpenPositions` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Define and correctly implement parsing and accounting semantics for stock splits and other uncommon Bivio transaction descriptions before they can corrupt reconstructed holdings.

## 2. Severity

**High.** A misparsed position-changing event can produce materially incorrect share counts and valuations while still appearing to be a successful import.

## 3. Ticket size (effort)

**Large (3-5 engineering days).** The parser changes are moderate, but verified financial semantics, historical fixtures, downstream position logic, and possible data correction increase the effort.

## 4. Issue detail

The transaction parser correctly handles the current ordinary buy, sell, dividend, and expense rows, but several uncommon transaction types are parsed incorrectly or are not consumed by downstream position reconstruction. Stock splits are classified as `L` with no symbol or share delta, and downstream positions ignore `L` entirely.

## 5. Supporting information and implementation guidance

### Evidence and observed data

- Symbol extraction is limited to types `B`, `S`, and `D`.
- Share extraction is limited to types `B` and `S`.
- `returnOpenPositions()` handles buys, sells, and dividends but not split type `L`.
- Dividend detection uses a truthy `strpos()` result and fails when the term begins at offset zero.
- Withdrawal matching recognizes `WITHDRAWL` but not the standard spelling `WITHDRAWAL`.
- Unknown rows are saved as `X` without an alert or quarantine step.
- Historical backups contain stock splits represented as positive share adjustments, which the current parser cannot reproduce.

### Impact

- Split-adjusted share counts and portfolio valuations can be wrong.
- Uncommon cash or security events may be silently misclassified.
- A changed Bivio description format can create zero-share or symbol-less position transactions.

### Design guidelines and proposed approach

1. Define the supported transaction taxonomy and downstream semantics for every type.
2. Use anchored, named regular expressions for buy, sell, split, dividend, payment, interest, return-of-capital, expense, and withdrawal descriptions.
3. Correct offset-safe substring checks and support both withdrawal spellings.
4. Parse stock-split symbol and share delta, then teach position reconstruction how to apply it.
5. Validate required symbol/share fields for position-changing transactions.
6. Quarantine or reject unknown descriptions with an alert instead of silently persisting them as usable ledger data.

### Scope boundaries

- Do not infer split economics without fixtures or verified Bivio examples.
- Preserve original source descriptions unchanged for auditability.
- Avoid silently rewriting historical transaction types without a reviewed migration or reconciliation plan.

### Acceptance criteria

- [ ] Every supported type has documented parsing and accounting semantics.
- [ ] Stock splits produce the correct symbol and share change and affect reconstructed positions correctly.
- [ ] Dividend detection works at every string offset.
- [ ] Both `WITHDRAWAL` and legacy `WITHDRAWL` descriptions are handled.
- [ ] Malformed buy/sell/split rows are rejected or quarantined before persistence.
- [ ] Unknown types produce visible metrics and a non-success policy appropriate to financial data.
- [ ] Fixture tests cover all supported types, fractional shares, malformed descriptions, and symbol formats.

### Validation plan

- Build sanitized fixtures from representative historical descriptions.
- Compare parsed type, symbol, and shares with expected values.
- Reconstruct holdings across buy, sell, forward split, and reverse split sequences.
- Confirm cash calculations remain consistent for cash-only transaction types.
- Run current live rows through the parser in memory and confirm no regressions.

### Dependencies and notes

- DAM-009 should provide the reusable fixture test harness.
- Historical corrections may require the reconciliation capability from DAM-002.

### Closure record

When complete, record the supported taxonomy, split representation, historical migration decision, implementation commit, and fixture results before moving this file to the closed-ticket directory.
