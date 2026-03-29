# Backend Push Notification Integration Guide (VAPID)

To enable real-time background push notifications, your backend needs to follow these steps:

### 1. Generate VAPID Keys
You need a **Public Key** (shared with the frontend) and a **Private Key** (kept secure on the server).
- **Node.js (web-push)**: `npx web-push generate-vapid-keys`
- **Python (pywebpush)**: `vapid --gen`

### 2. Implement the Subscription Endpoint
Create an API endpoint (e.g., `POST /v1/notifications/subscribe`) that accepts the following JSON object from the frontend:

```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/...",
  "expirationTime": null,
  "keys": {
    "p256dh": "...",
    "auth": "..."
  }
}
```
**Action**: Store this "Subscription Object" in your database linked to the `userId`. One user might have multiple subscriptions (e.g., Phone and Desktop).

### 3. Sending a Push Notification
When a notification is triggered, use a standard library to send the message.

#### Node.js Example (web-push)
```javascript
const webpush = require('web-push');

webpush.setVapidDetails(
  'mailto:admin@example.com',
  process.env.VAPID_PUBLIC_KEY,
  process.env.VAPID_PRIVATE_KEY
);

const pushPayload = JSON.stringify({
  title: 'New Order!',
  body: 'Order #1234 has been placed.',
  icon: '/favicon.ico',
  url: '/dashboard/orders/1234'
});

// Send to all stored subscriptions for this user
userSubscriptions.forEach(sub => {
  webpush.sendNotification(sub, pushPayload).catch(err => {
    if (err.statusCode === 410) {
      // Remove expired/invalid subscriptions from DB
    }
  });
});
```

### 4. Payload Requirements
The push payload **must** be stringified JSON so the Service Worker ([sw.js](file:///c:/Users/kobby/Desktop/projects/stringventory/frontend/public/sw.js)) can parse it and show the notification.
