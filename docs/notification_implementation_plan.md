# Critical & High Priority Notifications - Implementation Plan

## 🎯 Implementation Strategy

### **Controllers to Update:**
1. ✅ OrderController (CRITICAL - 5 notifications)
2. ⏳ AuthController (CRITICAL - 3 notifications)
3. ⏳ PasswordResetController (CRITICAL - 2 notifications)
4. ⏳ EventController (HIGH - 3 notifications)
5. ⏳ TicketController (CRITICAL - 1 notification)
6. ⏳ AwardVoteController (HIGH - 2 notifications)

---

## 1. OrderController Notifications

### **A. Order Confirmation** (CRITICAL)
**Trigger:** After order is created  
**Location:** Line 134 (after order creation)  
**Method:** `sendOrderConfirmation()`  
**Channels:** Email + SMS

**Data needed:**
```php
[
    'order_id' => $order->id,
    'customer_name' => $order->customer_name,
    'customer_email' => $order->customer_email,
    'customer_phone' => $order->customer_phone,
    'event_name' => $firstEvent->title,
    'event_date' => $firstEvent->start_time->format('F d, Y'),
    'event_location' => $firstEvent->location,
    'total_amount' => number_format($order->total_amount, 2),
    'payment_link' => $_ENV['FRONTEND_URL'] . '/payment/' . $order->payment_reference,
]
```

### **B. Payment Receipt** (CRITICAL)
**Trigger:** After payment is verified successfully  
**Location:** Line 430 (in processSucce ssfulPayment, after commit)  
**Method:** `sendPaymentReceipt()`  
**Channels:** Email + SMS

**Data needed:**
```php
[
    'order_id' => $order->id,
    'customer_name' => $order->customer_name,
    'customer_email' => $order->customer_email,
    'customer_phone' => $order->customer_phone,
    'payment_reference' => $reference,
    'amount_paid' => number_format($order->total_amount, 2),
    'event_name' => $firstEvent->title,
    'event_date' => $firstEvent->start_time->format('F d, Y'),
    'receipt_link' => $_ENV['FRONTEND_URL'] . '/orders/' . $order->id,
]
```

### **C. Ticket Delivery** (CRITICAL)
**Trigger:** After tickets are generated  
**Location:** Line 430 (in processSuccessfulPayment, after tickets created)  
**Method:** `sendTickets()`  
**Channels:** Email + SMS

**Data needed:**
```php
[
    'customer_name' => $order->customer_name,
    'customer_email' => $order->customer_email,
    'customer_phone' => $order->customer_phone,
    'event_name' => $firstEvent->title,
    'event_date' => $firstEvent->start_time->format('F d, Y'),
    'event_location' => $firstEvent->location,
    'total_tickets' => $order->tickets->count(),
    'tickets' => $order->tickets->map(function($ticket) {
        return [
            'id' => $ticket->id,
            'code' => $ticket->ticket_code,
            'type' => $ticket->ticketType->name,
           'qr_code' => base64_encode(QRCode::generate($ticket->ticket_code))
        ];
    })->toArray()
]
```

### **D. Payment Failed** (HIGH)
**Trigger:** When payment fails  
**Location:** Line 396 (in handleChargeFailed, after commit)  
**Method:** `sendPaymentFailed()`  
**Channels:** Email

**Data needed:**
```php
[
    'order_id' => $order->id,
    'customer_name' => $order->customer_name,
    'customer_email' => $order->customer_email,
    'failure_reason' => $data['gateway_response'] ?? 'Payment declined',
    'retry_link' => $_ENV['FRONTEND_URL'] . '/payment/' . $order->payment_reference,
    'expires_at' => Carbon::now()->addHours(24)->format('F d, Y g:i A'),
]
```

### **E. New Sale Notification** (HIGH - to organizer)
**Trigger:** After payment successful  
**Location:** Line 430 (in processSuccessfulPayment)  
**Method:** `sendNewSaleNotification()`  
**Channels:** Email

**Data needed:**
```php
[
    'order_id' => $order->id,
    'customer_name' => $order->customer_name,
    'amount' => number_format($order->total_amount, 2),
    'tickets_sold' => $order->tickets->count(),
    'event_name' => $event->title,
    'dashboard_link' => $_ENV['FRONTEND_URL'] . '/organizer/orders/' . $order->id,
], 
$event->organizer->user->email
```

---

## 2. AuthController Notifications

### **A. Welcome Email** (CRITICAL)
**Trigger:** After registration  
**Method:** `sendWelcomeEmail()`  
**Channels:** Email + SMS

### **B. Email Verification** (CRITICAL)
**Trigger:** After registration  
**Method:** `sendEmailVerification()`  
**Channels:** Email

### **C. Login Alert** (CRITICAL - Security)
**Trigger:** Login from new device/location  
**Method:** `sendLoginAlert()`  
**Channels:** Email + SMS

---

## 3. PasswordResetController Notifications

### **A. Password Reset OTP** (CRITICAL)
**Trigger:** Password reset requested  
**Method:** `sendPasswordResetOTP()`  
**Channels:** Email + SMS

### **B. Password Changed** (CRITICAL - Security)
**Trigger:** Password changed successfully  
**Method:** `sendPasswordChanged()`  
**Channels:** Email + SMS

---

## 4. EventController Notifications

### **A. Event Created** (HIGH)
**Trigger:** Event published  
**Method:** `sendEventCreated()`  
**Channels:** Email

### **B. Event Updated** (HIGH)
**Trigger:** Critical event details change  
**Method:** `sendEventUpdated()`  
**Channels:** Email (to all ticket holders)

### **C. Event Cancelled** (CRITICAL)
**Trigger:** Event is cancelled  
**Method:** `sendEventCancelled()`  
**Channels:** Email + SMS (to all ticket holders)

---

## 5. TicketController Notifications

### **A. Ticket Admitted** (CRITICAL)
**Trigger:** Ticket scanned at entrance  
**Method:** `sendTicketAdmitted()`  
**Channels:** Email + SMS

---

## 6. AwardVoteController Notifications

### **A. Vote Confirmed** (HIGH)
**Trigger:** Vote payment verified  
**Method:** `sendVoteConfirmation()`  
**Channels:** Email

### **B. Vote Initiated** (HIGH)
**Trigger:** Vote created (pending payment)  
**Method:** `sendVoteInitiated()`  
**Channels:** Email

---

## 📊 Implementation Priority

### **Phase 1 - CRITICAL (Today)**
1. ✅ OrderController - All 5 notifications
2. ⏳ PasswordResetController - Both notifications
3. ⏳ TicketController - Admission notification
4. ⏳ AuthController - Welcome & verification

### **Phase 2 - HIGH (Next)**
5. ⏳ EventController - All 3 notifications
6. ⏳ AwardVoteController - Both notifications
7. ⏳ AuthController - Login alert

### **Phase 3 - Scheduled (Cron Jobs)**
8. ⏳ Event reminders (24h before)
9. ⏳ Voting ending reminders
10. ⏳ Daily summaries

---

## ✅ What I'm Implementing Now

**Starting with OrderController (5 notifications):**
1. Order confirmation - After order created
2. Payment receipt - After payment verified
3. Ticket delivery - After tickets generated
4. Payment failed - When payment fails
5. New sale alert - To organizer

These are the most critical as they directly affect revenue and customer experience.

Let's implement Phase 1!
