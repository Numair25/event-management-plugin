# Project: Event Management QR Code Payment Flow

## Architecture
- Database tables involved:
  - `wp_emp_attendees`: stores registered attendees (only after payment is approved)
  - `wp_emp_payments`: logs the approved payment details
- Config Settings storage:
  - Option `emp_qr_payment_settings` mapping form IDs to:
    - `amount` (float)
    - `qr_image_url` (string)
    - `enabled` (boolean)
- File Upload storage:
  - Transaction screenshots will be uploaded to WP upload folder securely using `wp_handle_upload` through an AJAX handler.
- Frontend modal interceptor:
  - JS file loaded on public pages where the form is rendered, checking configuration and intercepting submission using a SweetAlert2 or custom CSS/HTML modal.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | QR Configuration Admin | R1. Admin page to set price and upload QR image per Gravity Form | none | PLANNED |
| 2 | Frontend Modal Interception | R2. JS modal popup, validation of transaction ID/screenshot, upload endpoint | M1 | PLANNED |
| 3 | Custom Approval Dashboard | R3. Pending QR approvals admin screen showing details and bulk actions | M1, M2 | PLANNED |
| 4 | Badge Issuance & Approval Integration | R4. Attendee registration, email dispatch, and confirmation screen download link suppression | M3 | PLANNED |
| 5 | E2E Testing Integration & Regression | R5. 100% pass of E2E tests, verifying free and QR payment forms | M4 | PLANNED |
| 6 | Adversarial Hardening (Tier 5) | Extra testing coverage and vulnerability verification | M5 | PLANNED |

## Code Layout
- Admin pages:
  - Settings: `admin/class-emp-qr-settings-admin.php`
  - Dashboard: `admin/class-emp-qr-approvals-admin.php`
- Frontend:
  - Assets: `public/js/emp-qr-payment.js`, `public/css/emp-qr-payment.css`
  - Integration script loading: `public/class-emp-qr-frontend.php`
- Services/Backend:
  - GF Integration hooks: Modified in `services/class-emp-gf-integration.php`
  - GF Addon: Modified in `services/class-emp-gf-addon.php`
  - AJAX upload handler: `services/class-emp-qr-upload-handler.php`

## Interface Contracts
### Admin configuration ↔ GF Integration
- Option `emp_qr_payment_settings` structure:
  ```json
  {
    "form_id": {
      "enabled": true,
      "amount": 1500.00,
      "qr_image_url": "http://..."
    }
  }
  ```
### Frontend JS ↔ AJAX Upload Endpoint
- Action: `emp_upload_qr_screenshot`
- Method: `POST`
- Payload: FormData containing file in `screenshot` field
- Response:
  ```json
  {
    "success": true,
    "data": {
      "url": "http://..."
    }
  }
  ```
### Frontend Form submission parameters
- Dynamic inputs appended:
  - `emp_qr_transaction_id` (string)
  - `emp_qr_screenshot_url` (string)
