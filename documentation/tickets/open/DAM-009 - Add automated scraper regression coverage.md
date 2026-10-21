# DAM-009: Add automated scraper regression coverage

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Medium |
| Area | Testing / Maintainability |
| Created | 2026-10-21 |
| Related code | Bivio login, CSV parsing, transaction parsing, persistence orchestration |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Create a network-independent automated test suite for Bivio authentication parsing, CSV validation, transaction classification, valuation parsing, and persistence failure behavior.

## 2. Severity

**Medium.** The absence of tests increases the likelihood and duration of regressions across every higher-severity scraper issue, although it is not itself a production data defect.

## 3. Ticket size (effort)

**Large (3-5 engineering days).** Work includes selecting/configuring the test runner, extracting testable seams, sanitizing fixtures, and building both unit and disposable-database integration coverage.

## 4. Issue detail

The project has no automated regression suite for the Bivio integration. Current confidence depends on live manual requests and production-shaped data, which makes provider markup changes, parser edge cases, retry behavior, and database failures difficult to validate safely.

## 5. Supporting information and implementation guidance

### Evidence

- No project PHPUnit configuration or Bivio-focused test files were found.
- The login parser, CSV parser, transaction classifier, and persistence workflow are coupled procedural functions.
- Live checks verified the current provider contract but cannot safely exercise failure and historical edge cases.

### Impact

- Changes can regress authentication or financial parsing without detection before deployment.
- Rare transaction types remain effectively untested.
- Tests may require real credentials or network access unless explicit seams and fixtures are introduced.
- Partial failure, retry, and concurrency behavior cannot be proven reliably.

### Design guidelines and proposed approach

1. Select a PHP test runner compatible with the supported PHP versions.
2. Separate pure login-form, CSV, and transaction parsing from network and database side effects.
3. Create sanitized fixtures for login success/failure pages, transaction CSV variants, and valuation pages.
4. Add unit tests for pure parsers and contract validation.
5. Add integration tests against a disposable database for atomicity, idempotency, and concurrency.
6. Keep live Bivio checks optional, explicitly authorized, read-only, and excluded from normal CI.
7. Document how fixtures are sanitized and refreshed without exposing credentials or member financial data.

### Scope boundaries

- Normal tests must not access Bivio, production MySQL, or real credentials.
- Fixtures must not contain secrets, cookies, anti-CSRF tokens, personally identifying member data, or unnecessary financial details.
- Live smoke tests must never write to the database.

### Acceptance criteria

- [ ] Tests run locally without network access or real credentials.
- [ ] Login parsing covers current markup and representative variations/failures.
- [ ] CSV tests cover BOM, CRLF, commas, quotes, embedded newlines, missing headers, and invalid rows.
- [ ] Transaction tests cover every supported type, fractional shares, splits, withdrawals, and malformed descriptions.
- [ ] Persistence tests cover rollback, retries, idempotency, same-day additions, and concurrent execution.
- [ ] Valuation parsing has success and malformed-page fixtures.
- [ ] CI or the documented validation command runs the suite with a nonzero status on failure.

### Validation plan

- Run the complete suite in a network-disabled environment.
- Confirm tests fail when representative validation and parser defects are reintroduced.
- Run integration tests against a fresh disposable schema and verify teardown.
- Audit fixtures and output for secrets and sensitive source data.

### Dependencies and notes

- This ticket supports every other Bivio ticket and can begin immediately with pure parser fixtures.
- DAM-002 through DAM-006 should add their regression cases to the shared suite rather than introducing separate ad hoc scripts.

### Closure record

When complete, record the test command, supported PHP versions, fixture-sanitization process, coverage summary, CI integration, and implementation commit before moving this file to the closed-ticket directory.
