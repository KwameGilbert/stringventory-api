<?php
/**
 * Template Generator - Creates all email and SMS templates
 * Run: php generate-templates.php
 */

$emailTemplates = [
    'ticket_delivery' => [
        'subject' => 'Your Tickets - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.ticket{background:white;padding:20px;margin:15px 0;border:2px solid #e5e7eb;border-radius:8px}.qr-code{text-align:center;padding:20px}.footer{background:#f3f4f6;padding:20px;text-align:center;font-size:12px;color:#6b7280}</style></head><body><div class="header"><h1>🎟️ Your Tickets Are Ready!</h1></div><div style="padding:30px"><p>Hi {{customer_name}},</p><p>Your tickets for <strong>{{event_name}}</strong> are ready!  Download and show your QR codes at the entrance.</p><div class="ticket"><h3>Event Details</h3><p><strong>Date:</strong> {{event_date}}</p><p><strong>Location:</strong> {{event_location}}</p><p><strong>Tickets:</strong> {{total_tickets}}</p></div><p style="color:#6b7280;font-size:14px">Save this email or download your tickets. You\'ll need to show the QR codes at check-in.</p></div><div class="footer"><p>&copy; 2025 {{app_name}}</p></div></body></html>'
    ],
    
    'payment_failed' => [
        'subject' => 'Payment Failed - Order #{{order_id}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#EF4444;color:white;padding:30px;text-align:center}.content{padding:30px}.button{background:#EF4444;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Payment Failed</h1></div><div class="content"><p>Hi {{customer_name}},</p><p>Unfortunately, your payment for Order #{{order_id}} was not successful.</p><p><strong>Reason:</strong> {{failure_reason}}</p><p>Your order will expire in 24 hours. Please try again to secure your tickets.</p><p style="text-align:center"><a href="{{retry_link}}" class="button">Try Again</a></p></div></body></html>'
    ],

    'order_cancelled' => [
        'subject' => 'Order Cancelled - #{{order_id}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#6b7280;color:white;padding:30px;text-align:center}.content{padding:30px}</style></head><body><div class="header"><h1>Order Cancelled</h1></div><div class="content"><p>Hi {{customer_name}},</p><p>Your order #{{order_id}} has been cancelled successfully.</p><p>If you paid for this order, your refund will be processed within 5-7 business days.</p><p>If you have any questions, please contact our support team.</p></div></body></html>'
    ],

    'new_sale' => [
        'subject' => '💰 New Sale - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}.content{padding:30px}.amount{font-size:28px;color:#10B981;font-weight:bold}</style></head><body><div class="header"><h1>New Ticket Sale!</h1></div><div class="content"><p>Hi {{organizer_name}},</p><p>You just made a sale for <strong>{{event_name}}</strong>!</p><div class="amount">GH₵ {{amount}}</div><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Tickets Sold:</strong> {{tickets_sold}}</p><p><strong>Order ID:</strong> #{{order_id}}</p></div></body></html>'
    ],

    'ticket_admitted' => [
        'subject' => 'Welcome to {{event_name}}!',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}</style></head><body><div class="header"><h1>✓ Check-in Confirmed</h1></div><div style="padding:30px"><p>Hi {{attendee_name}},</p><p>Welcome to <strong>{{event_name}}</strong>!</p><p>Your ticket {{ticket_code}} was scanned successfully at {{admitted_at}}.</p><p>Enjoy the event!</p></div></body></html>'
    ],

    'event_created' => [
        'subject' => 'Your Event is Live! 🎉',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.button{background:#4F46E5;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Event Published!</h1></div><div style="padding:30px"><p>Hi {{organizer_name}},</p><p>Congratulations! Your event <strong>{{event_name}}</strong> is now live!</p><p style="text-align:center"><a href="{{event_url}}" class="button">View Event</a> <a href="{{dashboard_link}}" class="button">Dashboard</a></p><p>Share your event to start selling tickets!</p></div></body></html>'
    ],

    'event_updated' => [
        'subject' => 'Important Update - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#F59E0B;color:white;padding:30px;text-align:center}</style></head><body><div class="header"><h1>Event Update</h1></div><div style="padding:30px"><p>Hi {{attendee_name}},</p><p>Important update for <strong>{{event_name}}</strong>:</p><p><strong>What Changed:</strong> {{changes}}</p><p>All other details remain the same. See you at the event!</p></div></body></html>'
    ],

    'event_cancelled' => [
        'subject' => '❌ Event Cancelled - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#EF4444;color:white;padding:30px;text-align:center}</style></head><body><div class="header"><h1>Event Cancelled</h1></div><div style="padding:30px"><p>Hi {{attendee_name}},</p><p>We regret to inform you that <strong>{{event_name}}</strong> has been cancelled.</p><p><strong>Reason:</strong> {{cancelled_reason}}</p><p><strong>Refund:</strong> GH₵{{refund_amount}} will be processed to your original payment method within 5-7 business days.</p><p>We apologize for any inconvenience.</p></div></body></html>'
    ],

    'event_reminder' => [
        'subject' => '⏰ Tomorrow - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.button{background:#4F46E5;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Event Tomorrow!</h1></div><div style="padding:30px"><p>Hi {{attendee_name}},</p><p><strong>{{event_name}}</strong> starts tomorrow!</p><p><strong>Date:</strong> {{event_date}}</p><p><strong>Time:</strong> {{event_time}}</p><p><strong>Location:</strong> {{location}}</p><p style="text-align:center"><a href="{{directions_link}}" class="button">Get Directions</a></p><p>Don\'t forget to bring your tickets! See you there!</p></div></body></html>'
    ],

    'welcome' => [
        'subject' => 'Welcome to {{app_name}}! 🎉',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.button{background:#4F46E5;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Welcome to {{app_name}}!</h1></div><div style="padding:30px"><p>Hi {{user_name}},</p><p>Welcome aboard! We\'re excited to have you join us.</p><p>Please verify your email to get started:</p><p style="text-align:center"><a href="{{verification_link}}" class="button">Verify Email</a></p><p>Start discovering amazing events near you!</p></div></body></html>'
    ],

    'email_verification' => [
        'subject' => 'Verify Your Email Address',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.code{font-size:32px;font-weight:bold;color:#4F46E5;text-align:center;padding:20px;background:#f3f4f6;border-radius:8px;margin:20px 0}</style></head><body><div class="header"><h1>Verify Your Email</h1></div><div style="padding:30px"><p>Hi {{user_name}},</p><p>Your verification code is:</p><div class="code">{{verification_code}}</div><p style="color:#6b7280;font-size:14px">This code expires in {{expires_in}}.</p></div></body></html>'
    ],

    'login_alert' => [
        'subject' => '🔒 Security Alert - New Login',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#EF4444;color:white;padding:30px;text-align:center}</style></head><body><div class="header"><h1>New Login Detected</h1></div><div style="padding:30px"><p>Hi {{user_name}},</p><p>A new login to your account was detected:</p><p><strong>Device:</strong> {{device}}</p><p><strong>Location:</strong> {{location}}</p><p><strong>IP Address:</strong> {{ip_address}}</p><p><strong>Time:</strong> {{login_time}}</p><p>If this wasn\'t you, please secure your account immediately.</p></div></body></html>'
    ],

    'password_reset_otp' => [
        'subject' => 'Reset Your Password',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.code{font-size:32px;font-weight:bold;color:#4F46E5;text-align:center;padding:20px;background:#f3f4f6;border-radius:8px;margin:20px 0}</style></head><body><div class="header"><h1>Password Reset</h1></div><div style="padding:30px"><p>Hi {{user_name}},</p><p>Your password reset code is:</p><div class="code">{{otp}}</div><p style="color:#6b7280;font-size:14px">This code expires in {{expires_in}}. Never share this code with anyone.</p></div></body></html>'
    ],

    'password_changed' => [
        'subject' => 'Password Changed Successfully',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}</style></head><body><div class="header"><h1>Password Changed</h1></div><div style="padding:30px"><p>Hi {{user_name}},</p><p>Your password was changed successfully at {{changed_at}}.</p><p>If you didn\'t make this change, contact support immediately.</p></div></body></html>'
    ],

    'vote_confirmed' => [
        'subject' => '✓ Vote Confirmed - {{nominee_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}.amount{font-size:28px;color:#10B981;font-weight:bold;text-align:center;margin:20px 0}</style></head><body><div class="header"><h1>Vote Confirmed!</h1></div><div style="padding:30px"><p>Hi {{voter_name}},</p><p>Thank you for voting for <strong>{{nominee_name}}</strong> in the <strong>{{category_name}}</strong> category!</p><div class="amount">{{votes_cast}} Votes</div><p><strong>Amount Paid:</strong> GH₵{{amount_paid}}</p><p>Your vote has been recorded. Check the leaderboard to see the current rankings!</p></div></body></html>'
    ],

    'vote_initiated' => [
        'subject' => 'Complete Your Vote - {{nominee_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.button{background:#4F46E5;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Complete Your Vote</h1></div><div style="padding:30px"><p>Hi {{voter_name}},</p><p>Complete your payment to vote for <strong>{{nominee_name}}</strong>!</p><p><strong>Votes:</strong> {{votes}}</p><p><strong>Amount:</strong> GH₵{{total_amount}}</p><p style="text-align:center"><a href="{{payment_link}}" class="button">Pay Now</a></p><p style="color:#6b7280;font-size:14px">Payment expires at {{expires_at}}</p></div></body></html>'
    ],

    'voting_started' => [
        'subject' => '🗳️ Voting is Open - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.button{background:#4F46E5;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Voting is Open!</h1></div><div style="padding:30px"><p>Voting is now open for <strong>{{event_name}}</strong> awards!</p><p>Cast your votes now to support your favorite nominees.</p><p style="text-align:center"><a href="{{vote_link}}" class="button">Vote Now</a></p><p><strong>Voting ends:</strong> {{voting_ends_at}}</p></div></body></html>'
    ],

    'voting_ending' => [
        'subject' => '⏰ Last Chance to Vote - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#F59E0B;color:white;padding:30px;text-align:center}.button{background:#F59E0B;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Last Chance to Vote!</h1></div><div style="padding:30px"><p>Voting for <strong>{{event_name}}</strong> awards ends in 24 hours!</p><p>Cast your votes now before it\'s too late!</p><p style="text-align:center"><a href="{{vote_link}}" class="button">Vote Now</a></p><p><strong>Voting ends:</strong> {{ends_at}}</p></div></body></html>'
    ],

    'organizer_approved' => [
        'subject' => '🎉 Welcome - Create Your First Event',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}.button{background:#10B981;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px 0}</style></head><body><div class="header"><h1>Organizer Account Approved!</h1></div><div style="padding:30px"><p>Hi {{organizer_name}},</p><p>Congratulations! Your organizer account has been approved.</p><p>You can now create and manage events, sell tickets, and grow your audience!</p><p style="text-align:center"><a href="{{dashboard_link}}" class="button">Go to Dashboard</a> <a href="{{create_event_link}}" class="button">Create Event</a></p></div></body></html>'
    ],

    'payout_processed' => [
        'subject' => '💰 Payout Sent - GH₵{{amount}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}.amount{font-size:32px;color:#10B981;font-weight:bold;text-align:center;margin:20px 0}</style></head><body><div class="header"><h1>Payout Processed</h1></div><div style="padding:30px"><p>Hi {{organizer_name}},</p><p>Your payout has been processed!</p><div class="amount">GH₵ {{amount}}</div><p><strong>Transaction ID:</strong> {{transaction_id}}</p><p><strong>Bank Account:</strong> {{bank_details}}</p><p>Funds should reach your account within 1-3 business days.</p></div></body></html>'
    ],

    'scanner_assigned' => [
        'subject' => 'Scanner Access Granted - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#4F46E5;color:white;padding:30px;text-align:center}.button{background:#4F46E5;color:white;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:15px  0}</style></head><body><div class="header"><h1>Scanner Access Granted</h1></div><div style="padding:30px"><p>Hi {{scanner_name}},</p><p>You\'ve been assigned as a scanner for <strong>{{event_name}}</strong>.</p><p><strong>Event Date:</strong> {{event_date}}</p><p>Download the scanner app to start checking in attendees:</p><p style="text-align:center"><a href="{{app_download_link}}" class="button">Download App</a></p></div></body></html>'
    ],

    'pos_sale' => [
        'subject' => 'Your Purchase - {{event_name}}',
        'body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px}.header{background:#10B981;color:white;padding:30px;text-align:center}</style></head><body><div class="header"><h1>Thank You for Your Purchase!</h1></div><div style="padding:30px"><p>Hi {{customer_name}},</p><p>Your purchase for <strong>{{event_name}}</strong> is confirmed!</p><p><strong>Amount:</strong> GH₵{{amount}}</p><p>Your tickets are attached to this email. See you at the event!</p></div></body></html>'
    ]
];

$smsTemplates = [
    'order_confirmation' => 'Order #{{order_id}} confirmed for {{event_name}}. Complete payment: {{payment_link}}',
    'payment_receipt' => 'Payment confirmed! GH₵{{amount_paid}} for {{event_name}}. Check email for tickets.',
    'ticket_delivery' => 'Your {{total_tickets}} tickets for {{event_name}} are ready! Check email for QR codes.',
    'ticket_admitted' => 'Welcome to {{event_name}}! Ticket {{ticket_code}} admitted at {{admitted_at}}. Enjoy!',
    'event_cancelled' => 'IMPORTANT: {{event_name}} on {{date}} has been cancelled. Full refund processing. Check email.',
    'event_reminder' => 'Reminder: {{event_name}} starts tomorrow at {{event_time}}. Location: {{location}}. See you!',
    'welcome' => 'Welcome to {{app_name}}! Verify your account: {{verification_link}}',
    'login_alert' => 'New login to your account from {{location}} on {{device}}. If not you, secure account now.',
    'password_reset_otp' => 'Your {{app_name}} password reset code: {{otp}}. Valid for {{expires_in}} minutes.',
    'password_changed' => 'Your {{app_name}} password was changed at {{changed_at}}. If not you, contact support NOW.',
    'voting_started' => 'Voting is now open for {{event_name}} awards! Vote now: {{vote_link}}',
    'scanner_assigned' => 'You\'re assigned as scanner for {{event_name}} on {{event_date}}. Download app: {{app_link}}',
    'pos_sale' => 'Thank you! Your {{total_tickets}} tickets for {{event_name}} are ready. Check email.',
    'vote_confirmed' => 'Vote confirmed! {{votes_cast}} votes for {{nominee_name}}. Thank you!',
    'payout_processed' => 'Payout processed: GH₵{{amount}} sent to your account. Check email for receipt.'
];

// Create email templates
$emailDir = __DIR__ . '/templates/email';
if (!is_dir($emailDir)) {
    mkdir($emailDir, 0755, true);
}

foreach ($emailTemplates as $name => $template) {
    $filename = $emailDir . '/' . $name . '.json';
    file_put_contents($filename, json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Created: $filename\n";
}

// Create SMS templates
$smsDir = __DIR__ . '/templates/sms';
if (!is_dir($smsDir)) {
    mkdir($smsDir, 0755, true);
}

foreach ($smsTemplates as $name => $message) {
    $filename = $smsDir . '/' . $name . '.txt';
    file_put_contents($filename, $message);
    echo "Created: $filename\n";
}

echo "\n✅ All templates created successfully!\n";
echo "Email templates: " . count($emailTemplates) . "\n";
echo "SMS templates: " . count($smsTemplates) . "\n";
