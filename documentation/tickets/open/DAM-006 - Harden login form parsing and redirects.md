# DAM-006: Harden login form parsing and redirects

| Field | Value |
| --- | --- |
| Status | Open |
| Priority | Medium |
| Area | Scraping resilience / Security |
| Created | 2026-10-21 |
| Related code | `php/damwidiScrapeBivio.php:bivioLogin`, `returnBivioLoginFields` |
| Origin | Pre-merge review of `feature/improve-bivio-scraping` |

## 1. High-level summary

Replace regex-based login-form extraction with structured HTML parsing and enforce an explicit same-origin HTTPS policy for credential submission and redirects.

## 2. Severity

**Medium.** Authentication works against the current live form, but ordinary provider markup changes can break the job and redirect policy is not explicitly constrained to an approved host.

## 3. Ticket size (effort)

**Medium (1-2 engineering days).** The network flow is already correct; effort centers on DOM parsing, URL validation, form-control semantics, and fixture tests.

## 4. Issue detail

The revised browser-like login succeeds today, but login form extraction uses regular expressions and requires an exact quoted `/pub/login` action. It also collects every input without fully reproducing browser rules for disabled, unchecked, or duplicate controls. Redirects are followed automatically without explicitly enforcing a same-origin credential policy.

## 5. Supporting information and implementation guidance

### Evidence and observed data

- Form and input elements are parsed with regular expressions.
- The form action matcher requires the exact relative path `/pub/login`.
- Input collection does not consider control type, disabled state, checked state, or repeated names.
- `CURLOPT_FOLLOWLOCATION` is enabled for both the initial GET and credential POST.
- Live authentication passed on 2026-10-21, so this is a resilience risk rather than a current outage.

### Impact

- Benign HTML changes can break authentication.
- Changed browser form semantics can produce an incorrect login payload.
- A cross-origin redirect policy is not explicitly guarded before credential submission.
- Troubleshooting future provider changes will depend on regex behavior rather than a structured DOM model.

### Design guidelines and proposed approach

1. Parse the login page with `DOMDocument` and `DOMXPath`.
2. Resolve relative and absolute form actions safely.
3. Require HTTPS and an approved Bivio host before submitting credentials.
4. Reproduce successful-control rules for hidden fields and the selected submit control.
5. Limit redirect protocols to HTTPS and validate the effective host.
6. Keep cookies in memory and retain the current TLS verification and timeout behavior.
7. Separate login-page parsing from network execution so it can be fixture-tested.

### Scope boundaries

- Do not introduce a browser automation dependency unless ordinary HTTP/form parsing proves insufficient.
- Do not persist cookies or authentication responses to disk.
- Do not log hidden-field values because they may contain session or anti-CSRF tokens.

### Acceptance criteria

- [ ] Login form parsing uses a structured HTML parser.
- [ ] Relative, absolute, and query-bearing approved form actions are resolved correctly.
- [ ] Credentials are submitted only to approved HTTPS Bivio hosts.
- [ ] Disabled and unchecked controls are not submitted incorrectly.
- [ ] Redirect behavior is bounded and host-validated.
- [ ] Authentication rejection produces a sanitized nonzero failure.
- [ ] Fixture tests cover representative markup variations and hostile redirect/form actions.

### Validation plan

- Test saved login fixtures with reordered attributes, single/double quotes, extra forms, and absolute actions.
- Test missing token, missing credential fields, and altered submit controls.
- Test rejection of HTTP or non-Bivio form actions.
- Perform one authorized read-only live authentication and CSV retrieval after fixture tests pass.

### Dependencies and notes

- Keep the current working login flow until the replacement is validated against fixtures and Bivio.
- DAM-009 should provide the fixture test infrastructure.

### Closure record

When complete, record the approved host policy, redirect policy, implementation commit, fixture results, and authorized live validation date before moving this file to the closed-ticket directory.
