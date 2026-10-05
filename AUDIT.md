# Technical audit — 6 October 2026

## Scope and status

This is a partial security, accessibility, and reliability audit, not a completed redesign or full end-to-end certification. The catalog includes cannabis/Vijaya products and THC-related assets. Sales optimization, merchandising redesign, and transactional testing for those products were not undertaken.

Reviewed the shared header, footer, navigation, CSS/JavaScript, account-page initialization, Apache access rules, six API entry points, existing test harnesses, and portions of authentication/database code. HTTP checks cover public pages and unauthenticated admin access. Authenticated admin CRUD, every component, every responsive layout, and external payment/email services have not been comprehensively audited.

## Changes made

- Require a signed-in customer with a valid user record before accessing saved addresses. Previously a guest selected the first customer in the database, allowing access to that customer's address operations.
- Isolate header and mobile-navigation user variables from the containing page. Previously they replaced the guest user object, causing PHP warnings on order history and tracking.
- Block HTTP access to internal includes, scratch scripts, migration/test scripts, and legacy JSON data/configuration. CLI access remains available. Existing SQL and environment-file protection remains in place.
- Render live-search fields and toast messages as text nodes instead of interpolated HTML. Restrict search image URL protocols and ignore stale asynchronous responses.
- Make the closed mobile dialog and collapsed category links inert. Add focus containment, Escape dismissal, focus restoration, accurate expanded state, and accessible search labels.
- Add keyboard access to dropdowns, visible focus indicators, reduced-motion support, and dynamic viewport height for the open drawer.
- Version shared CSS and JavaScript by modification time to prevent stale cached JavaScript from conflicting with changed markup.

## Unresolved findings

| Priority | Location | Finding |
| --- | --- | --- |
| Critical | `api/confirm_manual_upi.php` | Customer-provided references are passed directly to payment confirmation without independent provider verification. No ownership or CSRF guard is visible in this endpoint. |
| Critical | `api/simulate_upi_payment.php` | The simulator can confirm a payment without an environment/authentication guard. It remains an exposed API; the internal-script restrictions do not fix this endpoint. |
| High | `api/payment_status.php` | Transaction information is returned based on a transaction ID without an ownership check in the endpoint. |
| High | `api/payment_webhook.php`, `api/cashfree_webhook.php` | Failure updates are not conditioned on the current state; a delayed failure notification can overwrite a successful status. |
| High | `includes/db.php` | Embedded database credentials and permissive local fallback remain. Remove embedded secrets and rotate any credentials that have been used outside disposable development. No credentials are reproduced here. |
| Medium | `includes/footer.php` | Newsletter submission displays a success toast and resets the form without storing or transmitting the subscription. |
| Medium | Shared header / homepage | Missing `logo.png` is hidden on image failure. The configured homepage CTA points to `#products-grid`, while the visible product section uses `#featured-products`. |
| Unverified | Storefront / admin | Full authenticated account, admin, payment, email, form submission, and responsive coverage remains outstanding. Existing payment tests mutate inventory and create orders; the general suite can send email, so they were not executed. |

These are source-review findings, not evidence that exploitation or unauthorized payment occurred. No live payment was attempted and no order was created during verification.

## Verification

- PHP syntax checks: 105 files passed at the initial lint pass; subsequently changed PHP files were rechecked.
- JavaScript syntax: `node --check assets/js/theme.js` passed.
- Read-only HTTP suite: **57/57 passed** using `C:/xampp/php/php.exe scratch/audit_smoke.php`.
- The suite checks expected HTTP status and absence of rendered PHP diagnostics. It includes twelve admin login redirects, public pages, API method/missing-input responses, and restricted-file access. Passing these checks does not prove API authorization or complete business correctness.
- Browser checks: 390 × 844 mobile drawer opening, Shift+Tab focus containment, category expansion, Escape closure, and focus return passed. Desktop FAQ at 1440 × 900 showed no document-level horizontal overflow. This does not establish that every page is responsive.
- Screenshot: `scratch/audit-mobile-navigation.jpg`.

The first smoke run revealed the account warnings and incorrect expected statuses for PHP include-based aliases. The warnings were fixed and those route expectations were corrected after inspecting their source; the subsequent run passed all 57 checks.
