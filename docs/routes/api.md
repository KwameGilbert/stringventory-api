# API Route Registry

Source: ``src/routes/api.php``

This file maps request prefixes to versioned route files. More specific prefixes should be listed before broader prefixes.

## Definition

The API route registry maps incoming request prefixes to their versioned route files. It is related to API bootstrapping and determines which route definitions are loaded for each request path.

| Prefix | Route file |
| --- | --- |
| `/v1/auth` | `src/routes/v1/AuthRoute.php` |
| `/v1/users` | `src/routes/v1/UserRoute.php` |
| `/v1/admin` | `src/routes/v1/AdminRoute.php` |
| `/v1/analytics` | `src/routes/v1/AnalyticsRoute.php` |
| `/v1/settings` | `src/routes/v1/SettingsRoute.php` |
| `/v1/categories` | `src/routes/v1/CategoryRoute.php` |
| `/v1/suppliers` | `src/routes/v1/SupplierRoute.php` |
| `/v1/expense-categories` | `src/routes/v1/ExpenseCategoryRoute.php` |
| `/v1/discounts` | `src/routes/v1/DiscountRoute.php` |
| `/v1/units-of-measure` | `src/routes/v1/UnitOfMeasureRoute.php` |
| `/v1/products` | `src/routes/v1/ProductRoute.php` |
| `/v1/inventory` | `src/routes/v1/InventoryRoute.php` |
| `/v1/purchases` | `src/routes/v1/PurchaseRoute.php` |
| `/v1/customers` | `src/routes/v1/CustomerRoute.php` |
| `/v1/orders` | `src/routes/v1/OrderRoute.php` |
| `/v1/refunds` | `src/routes/v1/RefundRoute.php` |
| `/v1/expenses` | `src/routes/v1/ExpenseRoute.php` |
| `/v1/expense-schedules` | `src/routes/v1/ExpenseScheduleRoute.php` |
| `/v1/transactions` | `src/routes/v1/TransactionRoute.php` |
| `/v1/audit-logs` | `src/routes/v1/AuditLogRoute.php` |
| `/v1/notifications` | `src/routes/v1/NotificationRoute.php` |
| `/v1/logs` | `src/routes/v1/LoggingRoute.php` |
| `/v1/utils` | `src/routes/v1/UtilsRoute.php` |
| `/v1/businesses` | `src/routes/v1/BusinessRoute.php` |
| `/v1/plans` | `src/routes/v1/PlanRoute.php` |
| `/v1/subscriptions` | `src/routes/v1/SubscriptionRoute.php` |
| `/v1/payment-methods` | `src/routes/v1/PaymentMethodRoute.php` |
| `/v1/push-subscriptions` | `src/routes/v1/PushSubscriptionRoute.php` |
| `/v1/refresh-tokens` | `src/routes/v1/RefreshTokenRoute.php` |
| `/v1/order-items` | `src/routes/v1/OrderItemRoute.php` |
| `/v1/purchase-items` | `src/routes/v1/PurchaseItemRoute.php` |
| `/v1/email-verification-tokens` | `src/routes/v1/EmailVerificationTokenRoute.php` |
| `/v1/exchange-rate-histories` | `src/routes/v1/ExchangeRateHistoryRoute.php` |
| `/v1/messaging-campaign-recipients` | `src/routes/v1/MessagingCampaignRecipientRoute.php` |
| `/v1/messaging-campaigns` | `src/routes/v1/MessagingCampaignRoute.php` |
| `/v1/messaging-templates` | `src/routes/v1/MessagingTemplateRoute.php` |
| `/v1/messaging` | `src/routes/v1/MessagingRoute.php` |
| `/v1/user-settings` | `src/routes/v1/UserSettingRoute.php` |


