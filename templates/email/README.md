# Email Templates

This directory contains all email templates for the Eventic platform.

## Template Structure

Each email template consists of two files:
1. **JSON configuration file** (`template_name.json`) - Contains metadata
2. **HTML content file** (`template_name.html`) - Contains the email body content

Plus a shared base template:
- **base.html** - The wrapper template with header, footer, and branding

## JSON Configuration Format

```json
{
    "name": "template_name",
    "subject": "Email Subject Line",
    "preheader": "Preview text shown in email clients",
    "content_file": "template_name.html"
}
```

## Available Placeholders

### Common Placeholders (Available in all templates)
| Placeholder | Description |
|------------|-------------|
| `{{app_url}}` | Frontend application URL |
| `{{support_email}}` | Support email address |
| `{{year}}` | Current year |
| `{{social_facebook}}` | Facebook page URL |
| `{{social_twitter}}` | Twitter page URL |
| `{{social_instagram}}` | Instagram page URL |

### Template-Specific Placeholders

#### Email Verification (`email_verification`)
| Placeholder | Description |
|------------|-------------|
| `{{user_name}}` | User's full name |
| `{{user_email}}` | User's email address |
| `{{verification_url}}` | Verification link URL |

#### Welcome (`welcome`)
| Placeholder | Description |
|------------|-------------|
| `{{user_name}}` | User's full name |
| `{{user_email}}` | User's email address |

#### Password Reset (`password_reset`)
| Placeholder | Description |
|------------|-------------|
| `{{user_name}}` | User's full name |
| `{{user_email}}` | User's email address |
| `{{reset_url}}` | Password reset link URL |

#### Password Changed (`password_changed`)
| Placeholder | Description |
|------------|-------------|
| `{{user_name}}` | User's full name |
| `{{user_email}}` | User's email address |
| `{{timestamp}}` | Date and time of password change |

#### Ticket Confirmation (`ticket_confirmation`)
| Placeholder | Description |
|------------|-------------|
| `{{user_name}}` | Customer's full name |
| `{{user_email}}` | Customer's email address |
| `{{order_reference}}` | Order/payment reference |
| `{{total_amount}}` | Total amount paid (formatted) |
| `{{currency}}` | Currency code (e.g., GHS) |
| `{{total_tickets}}` | Total number of tickets |
| `{{tickets_list}}` | HTML block of ticket details (auto-generated) |
| `{{tickets_url}}` | Link to view tickets in app |

## Adding New Templates

1. Create a JSON config file: `templates/email/new_template.json`
2. Create an HTML content file: `templates/email/new_template.html`
3. Use the template in code:
   ```php
   $emailService->sendFromTemplate('new_template', $email, $name, [
       'custom_variable' => 'value',
   ]);
   ```

## Template Design Guidelines

- Use inline CSS styles for email client compatibility
- Use tables for layout (email clients don't support flexbox/grid)
- Keep images hosted externally with full URLs
- Test across multiple email clients (Gmail, Outlook, Apple Mail)
- Use the brand color `#dc2626` (red) for primary buttons
- Follow the existing visual style for consistency

## Environment Variables

The following environment variables are used by the template system:

```env
FRONTEND_URL=https://eventic.com
MAIL_FROM_ADDRESS=noreply@eventic.com
SOCIAL_FACEBOOK=https://facebook.com/eventic
SOCIAL_TWITTER=https://twitter.com/eventic
SOCIAL_INSTAGRAM=https://instagram.com/eventic
CURRENCY=GHS
```

## File List

| File | Description |
|------|-------------|
| `base.html` | Base template wrapper |
| `email_verification.json` | Email verification metadata |
| `email_verification.html` | Email verification content |
| `welcome.json` | Welcome email metadata |
| `welcome.html` | Welcome email content |
| `password_reset.json` | Password reset metadata |
| `password_reset.html` | Password reset content |
| `password_changed.json` | Password changed metadata |
| `password_changed.html` | Password changed content |
| `ticket_confirmation.json` | Ticket confirmation metadata |
| `ticket_confirmation.html` | Ticket confirmation content |

