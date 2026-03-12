# Notification Templates - Complete Specification

**All Email & SMS Templates for the Notification System**

---

## 📧 Email Templates (35 Total)

### Template Structure:
Each template is a JSON file in `/templates/email/` with:
```json
{
  "subject": "Email subject with {{variables}}",
  "from_name": "Sender Name",
  "body": "HTML email body"
}
```

### Variables Available in All Templates:
- `{{app_name}}` - Application name
- `{{app_url}}` - Frontend URL
- `{{support_email}}` - Support email
- `{{support_phone}}` - Support phone

---

## 1. ORDER NOTIFICATIONS (6 templates)

### `order_confirmation.json` ✅ Created
**Subject:** Order Confirmation - {{event_name}}  
**When:** After order is created  
**Variables:**
- customer_name, order_id, event_name, event_date, event_location
- total_amount, payment_link, items[]

### `payment_receipt.json`
**Subject:** Payment Received - Order #{{order_id}}  
**When:** After successful payment  
**Variables:**
- customer_name, order_id, payment_reference, amount_paid
- event_name, event_date, tickets[], receipt_link

**SMS:** "Payment confirmed! GH₵{{amount}} for {{event_name}}. Check your email for tickets."

### `ticket_delivery.json`
**Subject:** Your Tickets - {{event_name}}  
**When:** After payment confirmed  
**Variables:**
- customer_name, event_name, event_date, event_location
- tickets[] (each with: id, type, qr_code, code)
- total_tickets

**SMS:** "Your {{total_tickets}} tickets for {{event_name}} are ready! Check your email for QR codes."

### `payment_failed.json`
**Subject:** Payment Failed - Order #{{order_id}}  
**When:** Payment verification fails  
**Variables:**
- customer_name, order_id, failure_reason, retry_link
- expires_at

### `order_cancelled.json`
**Subject:** Order Cancelled - #{{order_id}}  
**When:** Customer cancels order  
**Variables:**
- customer_name, order_id, cancelled_at, refund_info

### `new_sale.json`
**Subject:** New Sale - {{event_name}}  
**When:** Organizer receives new order  
**Variables:**
- organizer_name, event_name, customer_name, amount
- tickets_sold, order_id, dashboard_link

---

## 2. TICKET NOTIFICATIONS (2 templates)

### `ticket_admitted.json`
**Subject:** Welcome to {{event_name}}!  
**When:** Ticket is scanned at entrance  
**Variables:**
- attendee_name, event_name, admitted_at, ticket_code
- event_info, emergency_contacts

**SMS:** "Welcome to {{event_name}}! Ticket {{ticket_code}} admitted at {{admitted_at}}. Enjoy!"

### `ticket_transferred.json`
**Subject:** Ticket Transfer Confirmation  
**When:** Ticket ownership changes  
**Variables:**
- old_owner, new_owner, ticket_code, event_name

---

## 3. EVENT NOTIFICATIONS (5 templates)

### `event_created.json`
**Subject:** Your Event is Live - {{event_name}}  
**When:** Event is published  
**Variables:**
- organizer_name, event_name, event_url, share_links
- dashboard_link, next_steps

### `event_updated.json`
**Subject:** Important Update - {{event_name}}  
**When:** Event details change  
**Variables:**
- attendee_name, event_name, changes[], new_date, new_location
- refund_link

### `event_cancelled.json`
**Subject:** Event Cancelled - {{event_name}}  
**When:** Event is cancelled  
**Variables:**
- attendee_name, event_name, cancelled_reason, refund_amount
- refund_process, support_email

**SMS:** "IMPORTANT: {{event_name}} on {{date}} has been cancelled. Full refund processing. Check email."

### `event_reminder.json`
**Subject:** Tomorrow - {{event_name}}  
**When:** 24 hours before event  
**Variables:**
- attendee_name, event_name, event_date, event_time
- location, directions_link, tickets[], weather

**SMS:** "Reminder: {{event_name}} starts tomorrow at {{event_time}}. Location: {{location}}. See you!"

### `event_starting_soon.json`
**Subject:** Event Starts in 2 Hours!  
**When:** 2 hours before event  
**Variables:**
- attendee_name, event_name, starts_at, location

---

## 4. AUTHENTICATION NOTIFICATIONS (5 templates)

### `welcome.json`
**Subject:** Welcome to {{app_name}}!  
**When:** User registers  
**Variables:**
- user_name, verification_link, getting_started_link

**SMS:** "Welcome to {{app_name}}! Verify your account: {{verification_link}}"

### `email_verification.json`
**Subject:** Verify Your Email  
**When:** Email verification sent  
**Variables:**
- user_name, verification_code, verification_link, expires_in

### `login_alert.json`
**Subject:** Security Alert - New Login  
**When:** Login from new device/location  
**Variables:**
- user_name, device, location, ip_address, login_time
- secure_account_link

**SMS:** "New login to your {{app_name}} account from {{location}} on {{device}}. If not you, secure account now."

### `account_verified.json`
**Subject:** Account Activated!  
**When:** Email is verified  
**Variables:**
- user_name, browse_events_link, profile_link

### `account_deactivated.json`
**Subject:** Account Deactivated  
**When:** Account is deactivated  
**Variables:**
- user_name, reason, reactivate_link, data_retention

---

## 5. PASSWORD RESET NOTIFICATIONS (2 templates)

### `password_reset_otp.json`
**Subject:** Reset Your Password  
**When:** Password reset requested  
**Variables:**
- user_name, otp, reset_link, expires_in

**SMS:** "Your {{app_name}} password reset code: {{otp}}. Valid for {{expires_in}} minutes."

### `password_changed.json`
**Subject:** Password Changed Successfully  
**When:** Password is changed  
**Variables:**
- user_name, changed_at, ip_address, device

**SMS:** "Your {{app_name}} password was changed at {{changed_at}}. If not you, contact support NOW."

---

## 6. VOTING NOTIFICATIONS (4 templates)

### `vote_confirmed.json`
**Subject:** Vote Confirmed - {{nominee_name}}  
**When:** Vote payment confirmed  
**Variables:**
- voter_name, nominee_name, category_name, votes_cast
- amount_paid, receipt_link, leaderboard_link

### `vote_initiated.json`
**Subject:** Complete Your Vote - {{nominee_name}}  
**When:** Vote created (pending payment)  
**Variables:**
- voter_name, nominee_name, category_name, votes
- total_amount, payment_link, expires_at

### `voting_started.json`
**Subject:** Voting is Open - {{event_name}}  
**When:** Voting period starts  
**Variables:**
- event_name, categories[], voting_ends_at, vote_link

**SMS:** "Voting is now open for {{event_name}}! Vote now: {{vote_link}}"

### `voting_ending.json`
**Subject:** Last Chance to Vote - {{event_name}}  
**When:** 24h before voting closes  
**Variables:**
- event_name, ends_at, categories[], vote_link

---

## 7. ORGANIZER NOTIFICATIONS (3 templates)

### `organizer_approved.json`
**Subject:** Welcome - Create Your First Event  
**When:** Organizer account approved  
**Variables:**
- organizer_name, dashboard_link, create_event_link, guide_link

### `payout_processed.json`
**Subject:** Payout Sent - GH₵{{amount}}  
**When:** Payout is processed  
**Variables:**
- organizer_name, amount, transaction_id, bank_details
- sales_breakdown, period

### `low_sales_alert.json`
**Subject:** Boost Your Sales - {{event_name}}  
**When:** Sales are low close to event  
**Variables:**
- organizer_name, event_name, tickets_sold, tickets_remaining
- tips[], promo_tools_link

---

## 8. SCANNER NOTIFICATIONS (2 templates)

### `scanner_assigned.json`
**Subject:** Scanner Access Granted - {{event_name}}  
**When:** Scanner is assigned to event  
**Variables:**
- scanner_name, event_name, event_date, app_download_link
- instructions

**SMS:** "You're a scanner for {{event_name}} on {{event_date}}. Download app: {{app_link}}"

### `scanning_alert.json`
**Subject:** Suspicious Scanning Activity  
**When:** Unusual scanning patterns detected  
**Variables:**
- organizer_name, event_name, scanner_name, issue
- action_required

---

## 9. POS NOTIFICATIONS (2 templates)

### `pos_sale.json`
**Subject:** Your Purchase - {{event_name}}  
**When:** POS completes sale  
**Variables:**
- customer_name, event_name, tickets[], amount, receipt

**SMS:** "Thank you! Your tickets for {{event_name}} are ready. Check email for QR codes."

### `daily_summary.json`
**Subject:** Sales Report - {{date}}  
**When:** End of day summary  
**Variables:**
- organizer_name, total_sales, tickets_sold, revenue
- top_sellers[], report_link

---

## 10. WINNER NOTIFICATIONS (2 templates)

### `winner_announced.json`
**Subject:** Congratulations! You Won!  
**When:** Winner is announced  
**Variables:**
- winner_name, category_name, event_name, final_votes
- prize_info

### `voting_results.json`
**Subject:** Winners Announced - {{event_name}}  
**When:** Results are published  
**Variables:**
- event_name, winners[], leaderboard_link

---

## 📱 SMS Templates (15 Total)

### Template Structure:
Plain text files in `/templates/sms/` with {{variables}}

Maximum: 160 characters (or 2-3 SMS)

### Files to Create:

1. `order_confirmation.txt`
   ```
   Order #{{order_id}} confirmed for {{event_name}}. Complete payment: {{payment_link}}
   ```

2. `payment_receipt.txt`
   ```
   Payment confirmed! GH₵{{amount}} for {{event_name}}. Check email for tickets.
   ```

3. `ticket_delivery.txt`
   ```
   Your {{total_tickets}} tickets for {{event_name}} are ready! Check email for QR codes.
   ```

4. `ticket_admitted.txt`
   ```
   Welcome to {{event_name}}! Ticket {{ticket_code}} admitted at {{admitted_at}}. Enjoy!
   ```

5. `event_cancelled.txt`
   ```
   IMPORTANT: {{event_name}} on {{date}} has been cancelled. Full refund processing. Check email for details.
   ```

6. `event_reminder.txt`
   ```
   Reminder: {{event_name}} starts tomorrow at {{event_time}}. Location: {{location}}. See you there!
   ```

7. `welcome.txt`
   ```
   Welcome to {{app_name}}! Verify your account: {{verification_link}}
   ```

8. `login_alert.txt`
   ```
   New login to your account from {{location}} on {{device}}. If this wasn't you, secure your account immediately.
   ```

9. `password_reset_otp.txt`
   ```
   Your {{app_name}} password reset code: {{otp}}. Valid for {{expires_in}} minutes. Never share this code.
   ```

10. `password_changed.txt`
    ```
    Your {{app_name}} password was changed at {{changed_at}}. If this wasn't you, contact support immediately.
    ```

11. `voting_started.txt`
    ```
    Voting is now open for {{event_name}} awards! Vote now: {{vote_link}}
    ```

12. `scanner_assigned.txt`
    ```
    You're assigned as scanner for {{event_name}} on {{event_date}}. Download scanner app: {{app_link}}
    ```

13. `pos_sale.txt`
    ```
    Thank you for your purchase! Your {{total_tickets}} tickets for {{event_name}} are ready. Check your email.
    ```

14. `vote_confirmed.txt` (if needed)
    ```
    Vote confirmed! {{votes}} votes for {{nominee_name}}. Thank you for voting!
    ```

15. `payout_processed.txt` (if needed)
    ```
    Payout processed: GH₵{{amount}} sent to your account. Check email for receipt.
    ```

---

## 🎨 Email Design Guidelines

### Color Scheme:
- Primary: #4F46E5 (Indigo)
- Success: #10B981 (Green)
- Warning: #F59E0B (Amber)
- Danger: #EF4444 (Red)
- Background: #F9FAFB
- Text: #111827

### Structure:
1. **Header** (Colored background, white text, logo)
2. **Content** (White background, main message)
3. **Action Button** (CTA - colored, prominent)
4. **Footer** (Gray background, support info, unsubscribe)

### Typography:
- Headings: 24px, bold
- Body: 16px, normal
- Small: 14px
- Footer: 12px

### Mobile Responsive:
- Max width: 600px
- Padding: 20px
- Font size >= 14px
- Buttons min height: 44px

---

## 🔒 Security Features in Templates

1. **XSS Prevention**: All variables HTML-escaped
2. **No External Resources**: All CSS inline
3. **Safe Links**: All links validated
4. **Privacy**: No tracking pixels by default
5. **Unsubscribe**: Optional unsubscribe link in footer

---

## 📋 Template Creation Checklist

For each template:
- [ ] Subject line clear and concise
- [ ] All variables defined
- [ ] Mobile responsive
- [ ] Brand colors used
- [ ] CTA button included (where appropriate)
- [ ] Footer with support contact
- [ ] Test with sample data
- [ ] SMS version (if applicable)
- [ ] Unsubscribe link (marketing emails)
- [ ] Legal compliance (GDPR, etc.)

---

## 🚀 Quick Start

### Creating a New Template:

1. **Email Template**:
   ```json
   {
     "subject": "Subject with {{variable}}",
     "from_name": "Eventic",
     "body": "<html>...</html>"
   }
   ```
   Save as: `/templates/email/template_name.json`

2. **SMS Template**:
   ```
   Short message with {{variable}}. Max 160 chars.
   ```
   Save as: `/templates/sms/template_name.txt`

3. **Use in Code**:
   ```php
   $notificationService->sendOrderConfirmation($orderData);
   ```

---

## 📊 Template Variables Reference

### Common Variables:
- User: `user_name`, `user_email`, `user_phone`
- Order: `order_id`, `total_amount`, `payment_reference`
- Event: `event_name`, `event_date`, `event_time`, `event_location`
- Ticket: `ticket_code`, `ticket_type`, `qr_code`
- Vote: `nominee_name`, `category_name`, `votes_cast`
- Security: `ip_address`, `device`, `location`, `timestamp`
- Links: `payment_link`, `dashboard_link`, `app_url`

---

**Total Templates: 50** (35 Email + 15 SMS)  
**Status**: Specifications complete, ready for implementation

All templates follow best practices for deliverability, accessibility, and security! 🎉
