# DAM-005: Replace line-based CSV parsing

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Medium |
| Area | Input validation / Maintainability |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php:retrieveBivioTransactions` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Replace physical-line splitting with standards-compliant CSV record parsing and strict typed validation before any transaction reaches persistence.

## 2. Severity

**Medium.** Current Bivio rows parse successfully, but a valid multiline field or malformed date/amount can stop the job or be normalized incorrectly.

## 3. Ticket size (effort)

**Medium (1-2 engineering days).** The implementation is localized, with most effort in normalized row design and fixture coverage for CSV edge cases.

## 4. Issue detail

The CSV response is split into physical lines with `explode("\n", ...)` and each line is parsed independently. Valid CSV permits quoted fields to contain newlines, so a valid Bivio description could be split into multiple invalid records. Dates and numeric values are also not validated strictly before persistence.

## 5. Supporting information and implementation guidance

### Evidence

- CSV records are separated with `explode()` rather than a CSV-aware stream parser.
- Row validation compares only field counts.
- Dates are normalized with `strtotime()`, which accepts ambiguous formats and can silently normalize invalid input.
- Amount strings are passed directly to PDO/MySQL.

### Impact

- Valid multiline CSV can stop the nightly job.
- Invalid dates or numbers may be normalized or coerced instead of rejected.
- Parsing, validation, and persistence operate on loosely typed arrays, making failures harder to diagnose.

### Design guidelines and proposed approach

1. Write the response to an in-memory stream such as `php://temp`.
2. Read complete records with `fgetcsv()`.
3. Normalize an optional UTF-8 BOM and CRLF safely.
4. Validate an explicit required-header contract and map columns by header name.
5. Parse dates with an exact expected format and check `DateTimeImmutable::getLastErrors()`.
6. Validate monetary and share values as decimal strings before database binding.
7. Return normalized transaction DTO-like arrays separately from persistence.

### Scope boundaries

- Preserve source descriptions exactly except for encoding normalization that is explicitly documented.
- Do not convert monetary values through binary floating-point during validation.
- Provider-window reconciliation belongs to DAM-002.

### Acceptance criteria

- [ ] Quoted commas, quotes, CRLF, BOM, and embedded newlines parse correctly.
- [ ] Missing, duplicate, or renamed required headers fail with a sanitized diagnostic.
- [ ] Invalid dates and amounts are rejected before database access.
- [ ] Extra provider columns do not break parsing.
- [ ] Parsing produces a normalized, documented row structure.
- [ ] Existing valid Bivio CSV fixtures produce equivalent transaction data.

### Validation plan

- Test LF and CRLF fixtures.
- Test quoted descriptions containing commas, escaped quotes, and embedded newlines.
- Test BOM-prefixed headers and reordered/extra columns.
- Test invalid dates, empty required fields, nonnumeric amounts, and inconsistent row widths.
- Compare normalized output from a current sanitized Bivio fixture with expected rows.

### Dependencies and notes

- Coordinate normalized row structure with DAM-003 and parser rules in DAM-004.
- Tests should be implemented through DAM-009.

### Closure record

When complete, record the normalized row contract, implementation commit, supported encoding assumptions, and fixture results before moving this file to the closed-ticket directory.
