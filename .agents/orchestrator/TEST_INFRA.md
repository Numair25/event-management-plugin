# E2E Test Infra: Event Management QR Code Payment Flow

## Test Philosophy
- Opaque-box, requirement-driven. Boot WordPress and test programmatically using the database and Gravity Forms API.
- Methodology: Programmatic test execution using a PHP CLI runner simulating user submits, uploads, and admin approval actions.

## Feature Inventory
| # | Feature | Source (requirement) | Tier 1 | Tier 2 | Tier 3 |
|---|---------|---------------------|:------:|:------:|:------:|
| 1 | QR Configuration | ORIGINAL_REQUEST §R1 | 5      | 5      | ✓      |
| 2 | Modal Interception | ORIGINAL_REQUEST §R2 | 5      | 5      | ✓      |
| 3 | Approval Dashboard | ORIGINAL_REQUEST §R3 | 5      | 5      | ✓      |
| 4 | Badge Issuance & Approval | ORIGINAL_REQUEST §R4 | 5      | 5      | ✓      |
| 5 | Backward Compatibility | ORIGINAL_REQUEST §R5 | 5      | 5      | ✓      |

## Test Architecture
- Test runner: `php wp-content/plugins/event-management-plugin/tests/e2e-runner.php`
- Expected: All test assertions return PASS, and exit code is 0.
- Layout:
  - `tests/e2e-runner.php` - Test runner and assertions.
  - `tests/fixtures/` - Test files and mocks.

## Test Case Inventory

### Tier 1 - Feature Coverage
- TC1.1: Enable QR payment configuration for Form A with positive amount.
- TC1.2: Disable QR payment configuration for Form A.
- TC1.3: Update QR payment configuration (change amount and QR image).
- TC1.4: Enable QR payment configuration for Form B (different form).
- TC1.5: Verify configuration retrieval maps correctly by Form ID.
- TC2.1: Intercept submission with dynamic inputs (trans ID & screenshot) present.
- TC2.2: Verify form submission includes `emp_qr_transaction_id` and `emp_qr_screenshot_url` parameters.
- TC2.3: Verify file upload handler uploads QR screenshot and returns URL.
- TC2.4: Reject screenshot upload with invalid mime type.
- TC2.5: Enforce at least transaction ID or screenshot in submissions.
- TC3.1: Verify pending entries are queryable by payment status "Processing".
- TC3.2: Verify pending entries contain the saved transaction meta.
- TC3.3: Verify non-QR entries are not returned in pending list.
- TC3.4: Verify pending entries list does not include approved/paid entries.
- TC3.5: Verify listing returns correct metadata (ID, email, name, screenshot).
- TC4.1: Approve pending entry: check payment status changes to Paid.
- TC4.2: Approve pending entry: check attendee record created in `wp_emp_attendees`.
- TC4.3: Approve pending entry: check confirmation email sent/logged.
- TC4.4: Reject pending entry: check payment status changes to Failed.
- TC4.5: Bulk approve multiple pending entries.
- TC5.1: Submit form without QR config: verify payment status is comp/paid.
- TC5.2: Submit form without QR config: verify attendee created instantly.
- TC5.3: Submit form without QR config: verify confirmation download link shown.
- TC5.4: Submit free form (amount=0) with QR enabled: verify instant registration (bypasses modal).
- TC5.5: Verify delete entry note sync handles deleted QR entries.

### Tier 2 - Boundary & Corner Cases
- TC2.1.1: Submit with extremely long transaction ID (e.g. 255 chars).
- TC2.1.2: Submit with empty transaction ID but valid screenshot upload.
- TC2.1.3: Submit with valid transaction ID but empty screenshot upload.
- TC2.1.4: Submit with invalid transaction ID format (if any custom validation).
- TC2.1.5: Submit with extremely large screenshot upload (verify file size limit rejection).
- TC4.1.1: Double approval attempt of already approved entry (idempotence).
- TC4.1.2: Approve an entry for a deleted event (verify elegant error handling).
- TC4.1.3: Approve entry when event capacity is fully reached (verify waitlist status is assigned).
- TC4.1.4: Approve entry where mapped ticket type is missing (verify fallback ticket type).
- TC4.1.5: Reject an already approved entry.
- TC5.1.1: Free form with 0 capacity event (verify waitlisting).
- TC5.1.2: Form without QR configured submits with fake qr parameters (verify ignored).
- TC5.1.3: Delete pending entry before approval (verify audit log & cleanup).
- TC5.1.4: Submit form with exact duplicate email registration (verify validation block).
- TC5.1.5: Update QR payment config to amount of 0 (verify it acts as free).

### Tier 3 - Cross-Feature Combinations
- TC3.1.1: Enable QR payment, submit, verify pending, then change QR config price, then approve (verify original amount used).
- TC3.1.2: Register attendee on QR form, approve, check attendee has correct event and ticket type mapped in feed.
- TC3.1.3: Group registration via QR form (multiple names): verify multiple attendees created upon approval.
- TC3.1.4: Bulk approval of mixture of group registration and individual entries.
- TC3.1.5: Capacity limit reached mid-bulk-approval: verify remaining become waitlisted.

### Tier 4 - Real-World Application Scenarios
- TC4.1.1: Complete Event Registration flow: Organizer sets QR code for Form 1 at 500 INR. User submits, uploads screenshot, enters Transaction ID. Admin dashboard displays pending QR transaction. Admin reviews screenshot and clicks "Approve". User is registered, receives email, can download badge.
- TC4.1.2: Capacity and Waitlist transition flow: Event capacity is 2. Entry 1 and 2 are submitted via QR and pending approval. Entry 3 is submitted via QR and pending. Admin approves 1, 2, and 3. Verify Entry 1 & 2 are registered, Entry 3 is waitlisted.
- TC4.1.3: Backward compatibility regression: User submits free form for free event. User is immediately registered, receives email, download button is visible instantly on confirmation screen.
- TC4.1.4: Admin bulk action rejection & approval: 5 entries submitted. Admin bulk-rejects 3, and bulk-approves 2. Verify statuses, database entries, and email counts.
- TC4.1.5: File integrity check: User uploads malicious script as screenshot. System rejects upload with mime-type mismatch, preventing any file-based exploits.

## Coverage Thresholds
- Tier 1: 25 test cases
- Tier 2: 25 test cases
- Tier 3: 5 test cases
- Tier 4: 5 test cases
- Total: 60 test cases
