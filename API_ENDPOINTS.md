# StringVentory API Endpoints Documentation

## BASE_URL
```
/api/v1
```

---

# 🆕 RECENT CHANGES (Feb 28, 2026)

## Access Model Update
- Frontend access control now uses **3 business roles only**:
  - `CEO` → full access to all dashboard modules and actions
  - `Manager` → full access except adding users
  - `Sales` → dashboard + view products/categories/customers + create sale (view-only for catalog/customer management)

## Single Dashboard UX
- Frontend now routes all authenticated users to `/dashboard`.
- Legacy `/superadmin/*` frontend flow is no longer used for daily operations.

## Permissions Compatibility
- Permission endpoints remain available for backward compatibility.
- Frontend authorization now primarily resolves by role matrix (`CEO`, `Manager`, `Sales`) rather than granular permission flags.

---

# 🔐 AUTHENTICATION

## 1. Register User
- **Endpoint:** `POST /auth/register`
- **Auth Required:** No
- **Request Body:**
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "password": "SecurePassword123!",
  "confirmPassword": "SecurePassword123!",
  "businessName": "John's Store",
  "businessType": "retail",
  "role": "CEO"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "User registered successfully. Verification email sent.",
  "data": {
    "id": "user_12345",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "status": "pending_verification",
    "role": "CEO"
},
  "token": null
}
```

---

## 2. Login
- **Endpoint:** `POST /auth/login`
- **Auth Required:** No
- **Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "User logged in successfully",
  "data": {
    "id": "user_12345",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "role": "CEO",
    "status": "active",
    "businessId": "business_88234",
    "subscriptionPlan": "professional",
    "subscriptionStatus": "active"
  },
  "tokens": {
    "accessToken": "eyJhbGc...",
    "refreshToken": "eyJhbGc...",
    "expiresIn": 3600
  }
}
```

---

## 3. Refresh Token
- **Endpoint:** `POST /auth/refresh-token`
- **Auth Required:** No (uses refresh token)
- **Request Body:**
```json
{
  "refreshToken": "eyJhbGc..."
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Token refreshed successfully",
  "tokens": {
    "accessToken": "eyJhbGc...",
    "refreshToken": "eyJhbGc...",
    "expiresIn": 3600
  }
}
```

---

## 4. Logout
- **Endpoint:** `POST /auth/logout`
- **Auth Required:** Yes (Bearer Token)
- **Request Body:**
```json
{
  "refreshToken": "eyJhbGc..."
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "User logged out successfully"
}
```

---

## 5. Forgot Password
- **Endpoint:** `POST /auth/forgot-password`
- **Auth Required:** No
- **Request Body:**
```json
{
  "email": "john@example.com"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Password reset link sent to your email"
}
```

---

## 6. Reset Password
- **Endpoint:** `POST /auth/reset-password`
- **Auth Required:** No
- **Request Body:**
```json
{
  "token": "reset_token_from_email",
  "newPassword": "NewSecurePassword123!",
  "confirmPassword": "NewSecurePassword123!"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Password reset successfully"
}
```

---

## 7. Verify Email
- **Endpoint:** `POST /auth/verify-email`
- **Auth Required:** No
- **Request Body:**
```json
{
  "token": "verification_token_from_email"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Email verified successfully"
}
```

---

# 👥 USER MANAGEMENT (Admin Required)

## 1. Get All Users
- **Endpoint:** `GET /admin/users`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=john&status=active&role=CEO
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Users retrieved successfully",
  "data": [
    {
      "id": "user_12345",
      "firstName": "John",
      "lastName": "Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "role": "CEO",
      "status": "active",
      "createdAt": "2026-01-15T10:30:00Z",
      "lastLogin": "2026-02-05T08:15:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 45,
    "totalPages": 3
  }
}
```

---

## 2. Get User by ID
- **Endpoint:** `GET /admin/users/:userId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "User retrieved successfully",
  "data": {
    "id": "user_12345",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "role": "CEO",
    "status": "active",
    
    "createdAt": "2026-01-15T10:30:00Z"
  }
}
```

---

## 3. Create User
- **Endpoint:** `POST /admin/users`
- **Auth Required:** Yes (Admin)
- **Role Notes:**
  - `CEO`: can create `CEO`, `Manager`, and `Sales` users
  - `Manager`: cannot create users (frontend restriction)
- **Request Body:**
```json
{
  "firstName": "Jane",
  "lastName": "Smith",
  "email": "jane@example.com",
  "phone": "+1234567891",
  "password": "pawword@123",
  "role": "Sales",
  "roleId": "role_sales",
  "status": "active",
  "twoFactorEnabled": false
}
```
- `permissions` is optional for role-based frontend flows.
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "User created successfully",
  "data": {
    "id": "user_12346",
    "firstName": "Jane",
    "lastName": "Smith",
    "email": "jane@example.com",
    "phone": "+1234567891",
    "role": "Sales",
    "roleId": "role_sales",
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Update User
- **Endpoint:** `PUT /admin/users/:userId`
- **Auth Required:** Yes (Admin)
- **Request Body:**
```json
{
  "firstName": "Jane",
  "lastName": "Smith",
  "phone": "+1234567891",
  "role": "Manager",
  "roleId": "role_manager",
  "status": "active"
}
```
- `permissions` is optional for role-based frontend flows.
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "User updated successfully",
  "data": {
    "id": "user_12346",
    "firstName": "Jane",
    "lastName": "Smith",
    "email": "jane@example.com",
    "phone": "+1234567891",
    "roleId": "role_002",
    "status": "active",
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 5. Delete User
- **Endpoint:** `DELETE /admin/users/:userId`
- **Auth Required:** Yes (Admin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "User deleted successfully"
}
```

---

## 7. Resend Verification Email
- **Endpoint:** `POST /admin/users/:userId/resend-verification`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Verification email sent successfully"
}
```

---

# 🏭 PRODUCTS & CATEGORIES

## 1. Get All Products
- **Endpoint:** `GET /products`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=laptop&category=electronics&status=active&sortBy=name&sortOrder=asc
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Products retrieved successfully",
  "data": [
    {
      "id": "product_001",
      "name": "Laptop Pro",
      "sku": "LP-001",
      "description": "High performance laptop",
      "categoryId": "category_001",
      "categoryName": "Electronics",
      "price": 1299.99,
      "cost": 900.00,
      "quantity": 45,
      "reorderLevel": 10,
      "status": "active",
      "image": "https://example.com/images/laptop.jpg",
      "createdAt": "2026-01-10T10:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 156,
    "totalPages": 8
  }
}
```

---

## 2. Get Product by ID
- **Endpoint:** `GET /products/:productId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Product retrieved successfully",
  "data": {
    "id": "product_001",
    "name": "Laptop Pro",
    "sku": "LP-001",
    "description": "High performance laptop with 16GB RAM and 512GB SSD",
    "categoryId": "category_001",
    "categoryName": "Electronics",
    "price": 1299.99,
    "cost": 900.00,
    "quantity": 45,
    "reorderLevel": 10,
    "reorderQuantity": 20,
    "unit": "piece",
    "status": "active",
    "image": "https://example.com/images/laptop.jpg",
    "barcode": "1234567890123",
    "supplierId": "supplier_001",
    "createdAt": "2026-01-10T10:00:00Z",
    "updatedAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 3. Create Product
- **Endpoint:** `POST /products`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Tablet Plus",
  "sku": "TP-001",
  "description": "10-inch display tablet",
  "categoryId": "category_001",
  "price": 599.99,
  "cost": 350.00,
  "quantity": 30,
  "reorderLevel": 5,
  "reorderQuantity": 15,
  "unit": "piece",
  "barcode": "9876543210123",
  "supplierId": "supplier_001",
  "image": "https://example.com/images/tablet.jpg"
}pnpm
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Product created successfully",
  "data": {
    "id": "product_002",
    "name": "Tablet Plus",
    "sku": "TP-001",
    "price": 599.99,
    "quantity": 30,
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Update Product
- **Endpoint:** `PUT /products/:productId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Tablet Plus Ultra",
  "price": 649.99,
  "cost": 380.00,
  "quantity": 28,
  "reorderLevel": 8,
  "status": "active"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Product updated successfully",
  "data": {
    "id": "product_002",
    "name": "Tablet Plus Ultra",
    "price": 649.99,
    "quantity": 28,
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 5. Delete Product
- **Endpoint:** `DELETE /products/:productId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Product deleted successfully"
}
```

---

## 6. Get Low Stock Products
- **Endpoint:** `GET /products/low-stock`
- **Auth Required:** Yes
- **Query Parameters:**
```
?limit=20
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Low stock products retrieved successfully",
  "data": [
    {
      "id": "product_001",
      "name": "Laptop Pro",
      "sku": "LP-001",
      "quantity": 8,
      "reorderLevel": 10,
      "reorderQuantity": 20,
      "status": "warning"
    }
  ],
  "pagination": {
    "total": 5
  }
}
```

---

## 7. Get Product Expiring Stock
- **Endpoint:** `GET /products/expiring`
- **Auth Required:** Yes
- **Query Parameters:**
```
?days=30
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expiring products retrieved successfully",
  "data": [
    {
      "id": "product_003",
      "name": "Medicine Pack",
      "sku": "MED-001",
      "quantity": 15,
      "expiryDate": "2026-03-05",
      "daysUntilExpiry": 28
    }
  ]
}
```

---

## 8. Get All Categories
- **Endpoint:** `GET /categories`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=electronics
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Categories retrieved successfully",
  "data": [
    {
      "id": "category_001",
      "name": "Electronics",
      "description": "Electronic devices and accessories",
      "productCount": 45,
      "status": "active",
      "createdAt": "2026-01-01T10:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 15,
    "totalPages": 1
  }
}
```

---

## 9. Create Category
- **Endpoint:** `POST /categories`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Furniture",
  "description": "Office and home furniture"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Category created successfully",
  "data": {
    "id": "category_016",
    "name": "Furniture",
    "description": "Office and home furniture",
    "productCount": 0,
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 10. Update Category
- **Endpoint:** `PUT /categories/:categoryId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Modern Furniture",
  "description": "Modern office and home furniture"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Category updated successfully",
  "data": {
    "id": "category_016",
    "name": "Modern Furniture",
    "description": "Modern office and home furniture",
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 11. Delete Category
- **Endpoint:** `DELETE /categories/:categoryId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Category deleted successfully"
}
```

---

# 📦 INVENTORY

## 1. Get Inventory
- **Endpoint:** `GET /inventory`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=laptop&status=active&sortBy=quantity
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Inventory retrieved successfully",
  "data": [
    {
      "id": "inventory_001",
      "productId": "product_001",
      "productName": "Laptop Pro",
      "sku": "LP-001",
      "quantity": 45,
      "reorderLevel": 10,
      "warehouseLocation": "A-001",
      "lastStockCheck": "2026-02-05T08:00:00Z",
      "status": "good"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 156,
    "totalPages": 8
  }
}
```

---

## 2. Get Inventory by Product
- **Endpoint:** `GET /inventory/product/:productId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Inventory retrieved successfully",
  "data": {
    "id": "inventory_001",
    "productId": "product_001",
    "productName": "Laptop Pro",
    "sku": "LP-001",
    "quantity": 45,
    "reorderLevel": 10,
    "reorderQuantity": 20,
    "warehouseLocation": "A-001",
    "batchNumbers": ["BATCH-001", "BATCH-002"],
    "lastStockCheck": "2026-02-05T08:00:00Z",
    "lastAdjustment": "2026-02-04T14:30:00Z",
    "status": "good"
  }
}
```

---

## 3. Add Inventory
- **Endpoint:** `POST /inventory/add`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "productId": "product_001",
  "quantity": 25,
  "batchNumber": "BATCH-003",
  "expiryDate": "2027-02-05",
  "warehouseLocation": "A-001",
  "reference": "PO-12345",
  "notes": "New stock received"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Inventory added successfully",
  "data": {
    "id": "inventory_001",
    "productId": "product_001",
    "quantity": 70,
    "previousQuantity": 45,
    "adjustment": 25,
    "createdAt": "2026-02-05T10:00:00Z"
  }
}
```

---

## 4. Adjust Inventory
- **Endpoint:** `POST /inventory/adjust`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "productId": "product_001",
  "adjustmentType": "decrease",
  "quantity": 5,
  "reason": "damaged_stock",
  "reference": "DAM-001",
  "notes": "5 units damaged in shipment"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Inventory adjusted successfully",
  "data": {
    "id": "inventory_001",
    "productId": "product_001",
    "quantity": 40,
    "previousQuantity": 45,
    "adjustment": -5,
    "adjustmentType": "decrease",
    "reason": "damaged_stock",
    "createdAt": "2026-02-05T10:05:00Z"
  }
}
```

---

## 5. Transfer Inventory
- **Endpoint:** `POST /inventory/transfer`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "productId": "product_001",
  "fromWarehouse": "A-001",
  "toWarehouse": "B-002",
  "quantity": 10,
  "reference": "TRANSFER-001",
  "notes": "Transfer to branch office"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Inventory transferred successfully",
  "data": {
    "id": "transfer_001",
    "productId": "product_001",
    "quantity": 10,
    "fromWarehouse": "A-001",
    "toWarehouse": "B-002",
    "status": "completed",
    "createdAt": "2026-02-05T10:10:00Z"
  }
}
```

---

# 🛒 CUSTOMERS

## 1. Get All Customers
- **Endpoint:** `GET /customers`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=john&status=active&sortBy=name
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Customers retrieved successfully",
  "data": [
    {
      "id": "customer_001",
      "firstName": "John",
      "lastName": "Smith",
      "email": "john.smith@example.com",
      "phone": "+1234567890",
      "businessName": "Smith Trading",
      "customerType": "retail",
      "totalOrders": 15,
      "totalSpent": 5000.00,
      "status": "active",
      "createdAt": "2026-01-05T10:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 234,
    "totalPages": 12
  }
}
```

---

## 2. Get Customer by ID
- **Endpoint:** `GET /customers/:customerId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Customer retrieved successfully",
  "data": {
    "id": "customer_001",
    "firstName": "John",
    "lastName": "Smith",
    "email": "john.smith@example.com",
    "phone": "+1234567890",
    "businessName": "Smith Trading",
    "customerType": "retail",
    "address": "123 Main Street, City, State 12345",
    "city": "City",
    "state": "State",
    "postalCode": "12345",
    "country": "Country",
    "taxId": "TAX-123456",
    "creditLimit": 10000.00,
    "creditUsed": 2000.00,
    "totalOrders": 15,
    "totalSpent": 5000.00,
    "loyaltyPoints": 500,
    "status": "active",
    "notes": "VIP customer",
    "createdAt": "2026-01-05T10:00:00Z",
    "updatedAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 3. Create Customer
- **Endpoint:** `POST /customers`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "firstName": "Jane",
  "lastName": "Doe",
  "email": "jane.doe@example.com",
  "phone": "+1234567891",
  "businessName": "Doe Enterprise",
  "customerType": "wholesale",
  "address": "456 Oak Avenue, City, State 54321",
  "city": "City",
  "state": "State",
  "postalCode": "54321",
  "country": "Country",
  "taxId": "TAX-654321",
  "creditLimit": 15000.00,
  "notes": "Potential high-value customer"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Customer created successfully",
  "data": {
    "id": "customer_002",
    "firstName": "Jane",
    "lastName": "Doe",
    "email": "jane.doe@example.com",
    "businessName": "Doe Enterprise",
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Update Customer
- **Endpoint:** `PUT /customers/:customerId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "firstName": "Jane",
  "lastName": "Doe",
  "phone": "+1234567891",
  "businessName": "Doe Enterprise Ltd",
  "creditLimit": 20000.00,
  "status": "active"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Customer updated successfully",
  "data": {
    "id": "customer_002",
    "firstName": "Jane",
    "lastName": "Doe",
    "businessName": "Doe Enterprise Ltd",
    "creditLimit": 20000.00,
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 5. Delete Customer
- **Endpoint:** `DELETE /customers/:customerId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Customer deleted successfully"
}
```

---

## 6. Get Customer Orders
- **Endpoint:** `GET /customers/:customerId/orders`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=10
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Customer orders retrieved successfully",
  "data": [
    {
      "id": "order_001",
      "orderNumber": "ORD-001",
      "date": "2026-02-05T08:00:00Z",
      "total": 1500.00,
      "status": "completed",
      "itemCount": 5
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 15
  }
}
```

---

# 📋 ORDERS

## 1. Get All Orders
- **Endpoint:** `GET /orders`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=ORD&status=pending&sortBy=date&sortOrder=desc
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Orders retrieved successfully",
  "data": [
    {
      "id": "order_001",
      "orderNumber": "ORD-001",
      "customerId": "customer_001",
      "customerName": "John Smith",
      "date": "2026-02-05T08:00:00Z",
      "dueDate": "2026-02-12T08:00:00Z",
      "subtotal": 1400.00,
      "tax": 100.00,
      "total": 1500.00,
      "status": "pending",
      "paymentStatus": "unpaid",
      "itemCount": 5,
      "createdAt": "2026-02-05T08:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 156,
    "totalPages": 8
  }
}
```

---

## 2. Get Order by ID
- **Endpoint:** `GET /orders/:orderId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Order retrieved successfully",
  "data": {
    "id": "order_001",
    "orderNumber": "ORD-001",
    "customerId": "customer_001",
    "customerName": "John Smith",
    "customerEmail": "john.smith@example.com",
    "customerPhone": "+1234567890",
    "date": "2026-02-05T08:00:00Z",
    "dueDate": "2026-02-12T08:00:00Z",
    "deliveryDate": null,
    "items": [
      {
        "id": "order_item_001",
        "productId": "product_001",
        "productName": "Laptop Pro",
        "sku": "LP-001",
        "quantity": 2,
        "unitPrice": 1299.99,
        "subtotal": 2599.98,
        "discount": 100.00,
        "total": 2499.98
      }
    ],
    "subtotal": 2499.98,
    "discount": 100.00,
    "tax": 180.00,
    "shippingCost": 50.00,
    "total": 2729.98,
    "status": "pending",
    "paymentStatus": "unpaid",
    "shippingAddress": "123 Main Street, City, State 12345",
    "notes": "Handle with care",
    "createdAt": "2026-02-05T08:00:00Z",
    "updatedAt": "2026-02-05T08:30:00Z"
  }
}
```

---

## 3. Create Order
- **Endpoint:** `POST /orders`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "customerId": "customer_001",
  "dueDate": "2026-02-12T08:00:00Z",
  "items": [
    {
      "productId": "product_001",
      "quantity": 2,
      "unitPrice": 1299.99,
      "discount": 50.00
    },
    {
      "productId": "product_002",
      "quantity": 1,
      "unitPrice": 599.99,
      "discount": 0
    }
  ],
  "tax": 180.00,
  "shippingCost": 50.00,
  "shippingAddress": "123 Main Street, City, State 12345",
  "notes": "Handle with care"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Order created successfully",
  "data": {
    "id": "order_001",
    "orderNumber": "ORD-001",
    "customerId": "customer_001",
    "date": "2026-02-05T08:00:00Z",
    "total": 2729.98,
    "status": "pending",
    "paymentStatus": "unpaid",
    "createdAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 4. Update Order
- **Endpoint:** `PUT /orders/:orderId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "status": "processing",
  "paymentStatus": "paid",
  "dueDate": "2026-02-15T08:00:00Z",
  "notes": "Expedited shipping requested"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Order updated successfully",
  "data": {
    "id": "order_001",
    "orderNumber": "ORD-001",
    "status": "processing",
    "paymentStatus": "paid",
    "updatedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 5. Delete Order
- **Endpoint:** `DELETE /orders/:orderId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Order deleted successfully"
}
```

---

## 6. Create Refund
- **Endpoint:** `POST /orders/:orderId/refund`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "refundType": "partial",
  "amount": 500.00,
  "reason": "customer_request",
  "items": [
    {
      "orderItemId": "order_item_001",
      "quantity": 1
    }
  ],
  "notes": "Customer changed mind"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Refund created successfully",
  "data": {
    "id": "refund_001",
    "orderId": "order_001",
    "amount": 500.00,
    "refundType": "partial",
    "reason": "customer_request",
    "status": "processed",
    "processedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

# 🏭 SUPPLIERS & PURCHASES

## 1. Get All Suppliers
- **Endpoint:** `GET /suppliers`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=supplier&status=active
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Suppliers retrieved successfully",
  "data": [
    {
      "id": "supplier_001",
      "name": "Tech Supplies Co",
      "email": "contact@techsupplies.com",
      "phone": "+1987654321",
      "city": "Tech City",
      "status": "active",
      "totalOrders": 25,
      "totalSpent": 50000.00,
      "createdAt": "2026-01-01T10:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 45,
    "totalPages": 3
  }
}
```

---

## 2. Get Supplier by ID
- **Endpoint:** `GET /suppliers/:supplierId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Supplier retrieved successfully",
  "data": {
    "id": "supplier_001",
    "name": "Tech Supplies Co",
    "email": "contact@techsupplies.com",
    "phone": "+1987654321",
    "address": "789 Industrial Ave, Tech City, TC 98765",
    "city": "Tech City",
    "state": "TC",
    "postalCode": "98765",
    "country": "Country",
    "contactPerson": "Bob Johnson",
    "contactTitle": "Sales Manager",
    "taxId": "TAX-789789",
    "paymentTerms": "Net 30",
    "leadTime": 5,
    "minOrderQuantity": 10,
    "totalOrders": 25,
    "totalSpent": 50000.00,
    "status": "active",
    "rating": 4.5,
    "notes": "Reliable supplier",
    "createdAt": "2026-01-01T10:00:00Z"
  }
}
```

---

## 3. Create Supplier
- **Endpoint:** `POST /suppliers`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Global Parts Ltd",
  "email": "sales@globalparts.com",
  "phone": "+1555123456",
  "address": "999 Supply Lane, Parts City, PC 54321",
  "city": "Parts City",
  "state": "PC",
  "postalCode": "54321",
  "country": "Country",
  "contactPerson": "Alice Cooper",
  "contactTitle": "Account Manager",
  "taxId": "TAX-999999",
  "paymentTerms": "Net 15",
  "leadTime": 7,
  "minOrderQuantity": 5
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Supplier created successfully",
  "data": {
    "id": "supplier_002",
    "name": "Global Parts Ltd",
    "email": "sales@globalparts.com",
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Update Supplier
- **Endpoint:** `PUT /suppliers/:supplierId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Global Parts Ltd International",
  "phone": "+1555123456",
  "paymentTerms": "Net 45",
  "leadTime": 10,
  "status": "active"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Supplier updated successfully",
  "data": {
    "id": "supplier_002",
    "name": "Global Parts Ltd International",
    "paymentTerms": "Net 45",
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 5. Delete Supplier
- **Endpoint:** `DELETE /suppliers/:supplierId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Supplier deleted successfully"
}
```

---

## 6. Get All Purchases
- **Endpoint:** `GET /purchases`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=PO&status=pending&supplierId=supplier_001
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Purchases retrieved successfully",
  "data": [
    {
      "id": "purchase_001",
      "purchaseNumber": "PO-001",
      "supplierId": "supplier_001",
      "supplierName": "Tech Supplies Co",
      "date": "2026-02-05T08:00:00Z",
      "dueDate": "2026-02-10T08:00:00Z",
      "total": 5000.00,
      "status": "pending",
      "paymentStatus": "unpaid",
      "itemCount": 3,
      "createdAt": "2026-02-05T08:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 78,
    "totalPages": 4
  }
}
```

---

## 7. Get Purchase by ID
- **Endpoint:** `GET /purchases/:purchaseId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Purchase retrieved successfully",
  "data": {
    "id": "purchase_001",
    "purchaseNumber": "PO-001",
    "supplierId": "supplier_001",
    "supplierName": "Tech Supplies Co",
    "supplierEmail": "contact@techsupplies.com",
    "date": "2026-02-05T08:00:00Z",
    "dueDate": "2026-02-10T08:00:00Z",
    "expectedDeliveryDate": "2026-02-12T08:00:00Z",
    "items": [
      {
        "id": "purchase_item_001",
        "productId": "product_001",
        "productName": "Laptop Pro",
        "sku": "LP-001",
        "quantity": 10,
        "costPrice": 450.00,
        "sellingPrice": 550.00,
        "total": 4500.00
      }
    ],
    "subtotal": 4500.00,
    "tax": 300.00,
    "shippingCost": 50.00,
    "discount": 0,
    "total": 4850.00,
    "status": "pending",
    "paymentStatus": "unpaid",
    "notes": "Rush order",
    "createdAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 8. Create Purchase Order
- **Endpoint:** `POST /purchases`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "supplierId": "supplier_001",
  "dueDate": "2026-02-12T08:00:00Z",
  "expectedDeliveryDate": "2026-02-15T08:00:00Z",
  "items": [
    {
      "productId": "product_001",
      "quantity": 10,
      "costPrice": 450.00,
      "sellingPrice": 550.00
    },
    {
      "productId": "product_002",
      "quantity": 5,
      "costPrice": 350.00,
      "sellingPrice": 420.00
    }
  ],
  "tax": 120.00,
  "shippingCost": 50.00,
  "notes": "Rush delivery needed"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Purchase order created successfully",
  "data": {
    "id": "purchase_001",
    "purchaseNumber": "PO-001",
    "supplierId": "supplier_001",
    "date": "2026-02-05T08:00:00Z",
    "total": 4920.00,
    "status": "pending",
    "paymentStatus": "unpaid",
    "createdAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 9. Update Purchase
- **Endpoint:** `PUT /purchases/:purchaseId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "status": "received",
  "paymentStatus": "paid",
  "receivedDate": "2026-02-12T08:00:00Z"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Purchase updated successfully",
  "data": {
    "id": "purchase_001",
    "purchaseNumber": "PO-001",
    "status": "received",
    "paymentStatus": "paid",
    "updatedAt": "2026-02-05T10:00:00Z"
  }
}
```

---

# 💰 EXPENSES

## 1. Get All Expenses
- **Endpoint:** `GET /expenses`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=rent&category=operations&status=paid&dateFrom=2026-01-01&dateTo=2026-02-05
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expenses retrieved successfully",
  "data": [
    {
      "id": "expense_001",
      "expenseNumber": "EXP-001",
      "description": "Office Rent",
      "categoryId": "exp_cat_001",
      "categoryName": "Operations",
      "amount": 2000.00,
      "date": "2026-02-01T08:00:00Z",
      "vendor": "Property Management Inc",
      "paymentMethod": "bank_transfer",
      "status": "paid",
      "createdAt": "2026-02-01T08:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 45,
    "totalPages": 3
  }
}
```

---

## 2. Get Expense by ID
- **Endpoint:** `GET /expenses/:expenseId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expense retrieved successfully",
  "data": {
    "id": "expense_001",
    "expenseNumber": "EXP-001",
    "description": "Office Rent",
    "categoryId": "exp_cat_001",
    "categoryName": "Operations",
    "amount": 2000.00,
    "date": "2026-02-01T08:00:00Z",
    "dueDate": "2026-02-05T08:00:00Z",
    "paymentDate": "2026-02-04T08:00:00Z",
    "vendor": "Property Management Inc",
    "paymentMethod": "bank_transfer",
    "reference": "REF-001",
    "status": "paid",
    "receipt": "https://example.com/receipts/receipt_001.pdf",
    "notes": "February monthly rent",
    "createdAt": "2026-02-01T08:00:00Z",
    "updatedAt": "2026-02-04T08:00:00Z"
  }
}
```

---

## 3. Create Expense
- **Endpoint:** `POST /expenses`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "description": "Electricity Bill",
  "categoryId": "exp_cat_001",
  "amount": 500.00,
  "date": "2026-02-05T08:00:00Z",
  "dueDate": "2026-02-10T08:00:00Z",
  "vendor": "City Power Company",
  "paymentMethod": "credit_card",
  "reference": "BILL-001",
  "receipt": "https://example.com/receipts/receipt_002.pdf",
  "notes": "Monthly electricity consumption"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Expense created successfully",
  "data": {
    "id": "expense_002",
    "expenseNumber": "EXP-002",
    "description": "Electricity Bill",
    "categoryName": "Operations",
    "amount": 500.00,
    "status": "pending",
    "createdAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 4. Update Expense
- **Endpoint:** `PUT /expenses/:expenseId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "description": "Electricity Bill - Updated",
  "amount": 520.00,
  "status": "paid",
  "paymentDate": "2026-02-05T08:00:00Z"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expense updated successfully",
  "data": {
    "id": "expense_002",
    "expenseNumber": "EXP-002",
    "amount": 520.00,
    "status": "paid",
    "updatedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 5. Delete Expense
- **Endpoint:** `DELETE /expenses/:expenseId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expense deleted successfully"
}
```

---

## 6. Get Expense Categories
- **Endpoint:** `GET /expense-categories`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expense categories retrieved successfully",
  "data": [
    {
      "id": "exp_cat_001",
      "name": "Operations",
      "description": "Day-to-day operational expenses",
      "budgetLimit": 10000.00,
      "spent": 2500.00,
      "remaining": 7500.00,
      "status": "active"
    },
    {
      "id": "exp_cat_002",
      "name": "Marketing",
      "description": "Marketing and advertising expenses",
      "budgetLimit": 5000.00,
      "spent": 1500.00,
      "remaining": 3500.00,
      "status": "active"
    }
  ]
}
```

---

## 7. Create Expense Category
- **Endpoint:** `POST /expense-categories`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Utilities",
  "description": "Utility expenses (water, electricity, gas)",
  "budgetLimit": 2000.00
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Expense category created successfully",
  "data": {
    "id": "exp_cat_003",
    "name": "Utilities",
    "description": "Utility expenses (water, electricity, gas)",
    "budgetLimit": 2000.00,
    "spent": 0,
    "remaining": 2000.00,
    "status": "active"
  }
}
```

---

# 💵 SALES

## 1. Get All Sales
- **Endpoint:** `GET /sales`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&search=SALE&status=completed&dateFrom=2026-01-01&dateTo=2026-02-05
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Sales retrieved successfully",
  "data": [
    {
      "id": "sale_001",
      "saleNumber": "SALE-001",
      "customerId": "customer_001",
      "customerName": "John Smith",
      "date": "2026-02-05T08:00:00Z",
      "items": 5,
      "subtotal": 1400.00,
      "tax": 100.00,
      "total": 1500.00,
      "paymentMethod": "credit_card",
      "status": "completed",
      "createdAt": "2026-02-05T08:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 345,
    "totalPages": 18
  }
}
```

---

## 2. Get Sale by ID
- **Endpoint:** `GET /sales/:saleId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Sale retrieved successfully",
  "data": {
    "id": "sale_001",
    "saleNumber": "SALE-001",
    "customerId": "customer_001",
    "customerName": "John Smith",
    "customerEmail": "john.smith@example.com",
    "date": "2026-02-05T08:00:00Z",
    "items": [
      {
        "id": "sale_item_001",
        "productId": "product_001",
        "productName": "Laptop Pro",
        "sku": "LP-001",
        "quantity": 2,
        "unitPrice": 1299.99,
        "discount": 100.00,
        "total": 2499.98
      }
    ],
    "subtotal": 2499.98,
    "discount": 100.00,
    "tax": 180.00,
    "shippingCost": 50.00,
    "total": 2729.98,
    "paymentMethod": "credit_card",
    "transactionId": "TXN-001",
    "status": "completed",
    "createdAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 3. Create Sale
- **Endpoint:** `POST /sales`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "customerId": "customer_001",
  "items": [
    {
      "productId": "product_001",
      "quantity": 2,
      "unitPrice": 1299.99,
      "discount": 50.00
    }
  ],
  "tax": 180.00,
  "shippingCost": 50.00,
  "paymentMethod": "credit_card",
  "transactionId": "TXN-001",
  "notes": "Thank you for your purchase"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Sale created successfully",
  "data": {
    "id": "sale_001",
    "saleNumber": "SALE-001",
    "customerId": "customer_001",
    "date": "2026-02-05T08:00:00Z",
    "total": 2729.98,
    "status": "completed",
    "createdAt": "2026-02-05T08:00:00Z"
  }
}
```

---

## 4. Update Sale
- **Endpoint:** `PUT /sales/:saleId`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "status": "completed",
  "paymentMethod": "bank_transfer"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Sale updated successfully",
  "data": {
    "id": "sale_001",
    "saleNumber": "SALE-001",
    "status": "completed",
    "updatedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 5. Delete Sale
- **Endpoint:** `DELETE /sales/:saleId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Sale deleted successfully"
}
```

---

# 📊 REPORTS & ANALYTICS

## 1. Get Dashboard Overview
- **Endpoint:** `GET /analytics/dashboard`
- **Auth Required:** Yes
- **Query Parameters:**
```
?dateFrom=2026-01-01&dateTo=2026-02-05
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Dashboard overview retrieved successfully",
  "data": {
    "metrics": {
      "grossRevenue": {
        "value": 125000.00,
        "change": 15.5,
        "trend": "up"
      },
      "totalOrders": {
        "value": 456,
        "change": 8.2,
        "trend": "up"
      },
      "totalExpenses": {
        "value": 35000.00,
        "change": 5.3,
        "trend": "up"
      },
      "netProfit": {
        "value": 90000.00,
        "change": 22.1,
        "trend": "up"
      },
      "totalCustomers": {
        "value": 234,
        "change": 12.5,
        "trend": "up"
      },
      "inventoryValue": {
        "value": 45000.00,
        "change": -3.2,
        "trend": "down"
      },
      "lowStockItems": 5,
      "pendingOrders": 12
    },
    "charts": {
      "revenueByDate": [
        {
          "date": "2026-01-01",
          "revenue": 5000,
          "expenses": 1500
        }
      ],
      "topProducts": [
        {
          "productId": "product_001",
          "productName": "Laptop Pro",
          "sales": 45,
          "revenue": 58495.55
        }
      ],
      "topCustomers": [
        {
          "customerId": "customer_001",
          "customerName": "John Smith",
          "orders": 15,
          "spent": 5000.00
        }
      ]
    }
  }
}
```

---

## 2. Get Sales Report
- **Endpoint:** `GET /analytics/sales-report`
- **Auth Required:** Yes
- **Query Parameters:**
```
?dateFrom=2026-01-01&dateTo=2026-02-05&groupBy=daily
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Sales report retrieved successfully",
  "data": {
    "summary": {
      "totalSales": 125000.00,
      "totalOrders": 456,
      "averageOrderValue": 274.12,
      "totalItems": 1234,
      "topPaymentMethod": "credit_card"
    },
    "byDate": [
      {
        "date": "2026-02-05",
        "sales": 5000.00,
        "orders": 18,
        "items": 42
      }
    ],
    "byProduct": [
      {
        "productId": "product_001",
        "productName": "Laptop Pro",
        "quantity": 45,
        "revenue": 58495.55
      }
    ],
    "byCustomer": [
      {
        "customerId": "customer_001",
        "customerName": "John Smith",
        "orders": 15,
        "spent": 5000.00
      }
    ]
  }
}
```

---

## 3. Get Inventory Report
- **Endpoint:** `GET /analytics/inventory-report`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Inventory report retrieved successfully",
  "data": {
    "summary": {
      "totalProducts": 156,
      "totalQuantity": 2345,
      "totalValue": 125000.00,
      "lowStockItems": 5,
      "outOfStockItems": 2
    },
    "byCategory": [
      {
        "categoryId": "category_001",
        "categoryName": "Electronics",
        "productCount": 45,
        "quantity": 500,
        "value": 45000.00
      }
    ],
    "lowStockItems": [
      {
        "productId": "product_001",
        "productName": "Laptop Pro",
        "sku": "LP-001",
        "quantity": 8,
        "reorderLevel": 10
      }
    ]
  }
}
```

---

## 4. Get Financial Report
- **Endpoint:** `GET /analytics/financial-report`
- **Auth Required:** Yes
- **Query Parameters:**
```
?dateFrom=2026-01-01&dateTo=2026-02-05
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Financial report retrieved successfully",
  "data": {
    "income": {
      "sales": 125000.00,
      "other": 5000.00,
      "total": 130000.00
    },
    "expenses": {
      "costOfGoods": 50000.00,
      "operationalExpenses": 25000.00,
      "other": 10000.00,
      "total": 85000.00
    },
    "summary": {
      "grossProfit": 75000.00,
      "netProfit": 45000.00,
      "profitMargin": 34.6,
      "roi": 52.9
    }
  }
}
```

---

## 5. Get Customer Report
- **Endpoint:** `GET /analytics/customer-report`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Customer report retrieved successfully",
  "data": {
    "summary": {
      "totalCustomers": 234,
      "newCustomers": 25,
      "activeCustomers": 189,
      "inactiveCustomers": 45,
      "averageOrderValue": 274.12,
      "customerRetentionRate": 82.1
    },
    "topCustomers": [
      {
        "customerId": "customer_001",
        "customerName": "John Smith",
        "orders": 15,
        "spent": 5000.00,
        "lastOrderDate": "2026-02-05T08:00:00Z"
      }
    ]
  }
}
```

---

## 6. Get Expense Report
- **Endpoint:** `GET /analytics/expense-report`
- **Auth Required:** Yes
- **Query Parameters:**
```
?dateFrom=2026-01-01&dateTo=2026-02-05
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Expense report retrieved successfully",
  "data": {
    "summary": {
      "totalExpenses": 35000.00,
      "totalExpenseItems": 45,
      "averageExpense": 777.78
    },
    "byCategory": [
      {
        "categoryId": "exp_cat_001",
        "categoryName": "Operations",
        "amount": 20000.00,
        "itemCount": 25,
        "budgetLimit": 30000.00,
        "spent": 20000.00,
        "remaining": 10000.00
      }
    ]
  }
}
```

---

## 7. Export Report
- **Endpoint:** `GET /analytics/export/:reportType`
- **Auth Required:** Yes
- **Query Parameters:**
```
?dateFrom=2026-01-01&dateTo=2026-02-05&format=pdf
```
- **Response (200 OK - File Download):**
```
File: stringventory_sales_report_2026-01-01_2026-02-05.pdf
```

---

# 💬 MESSAGING

## 1. Get All Messages
- **Endpoint:** `GET /messaging/messages`
- **Auth Required:** Yes
- **Query Parameters:**
```
?page=1&limit=20&customerId=customer_001&status=unread
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Messages retrieved successfully",
  "data": [
    {
      "id": "msg_001",
      "customerId": "customer_001",
      "customerName": "John Smith",
      "sender": "admin",
      "direction": "outbound",
      "messageType": "sms",
      "content": "Hello John, your order has been shipped!",
      "status": "delivered",
      "sentAt": "2026-02-05T08:00:00Z",
      "readAt": "2026-02-05T08:15:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 156,
    "totalPages": 8
  }
}
```

---

## 2. Get Message by ID
- **Endpoint:** `GET /messaging/messages/:messageId`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Message retrieved successfully",
  "data": {
    "id": "msg_001",
    "customerId": "customer_001",
    "customerName": "John Smith",
    "sender": "admin",
    "senderName": "Admin User",
    "direction": "outbound",
    "messageType": "sms",
    "content": "Hello John, your order has been shipped!",
    "attachments": [],
    "status": "delivered",
    "sentAt": "2026-02-05T08:00:00Z",
    "deliveredAt": "2026-02-05T08:05:00Z",
    "readAt": "2026-02-05T08:15:00Z"
  }
}
```

---

## 3. Send Message
- **Endpoint:** `POST /messaging/messages`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "customerId": "customer_001",
  "messageType": "sms",
  "content": "Your order ORD-001 is ready for pickup!",
  "attachments": [],
  "scheduledFor": null
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Message sent successfully",
  "data": {
    "id": "msg_002",
    "customerId": "customer_001",
    "messageType": "sms",
    "content": "Your order ORD-001 is ready for pickup!",
    "status": "sent",
    "sentAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Send Bulk Messages
- **Endpoint:** `POST /messaging/bulk-messages`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "customerIds": ["customer_001", "customer_002", "customer_003"],
  "messageType": "sms",
  "content": "February sale is now live! Get 20% off on all electronics.",
  "scheduledFor": null
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Bulk messages sent successfully",
  "data": {
    "id": "bulk_msg_001",
    "recipientCount": 3,
    "sentCount": 3,
    "failedCount": 0,
    "status": "completed",
    "sentAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 5. Get Message Templates
- **Endpoint:** `GET /messaging/templates`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Message templates retrieved successfully",
  "data": [
    {
      "id": "template_001",
      "name": "Order Shipped",
      "content": "Your order {orderNumber} has been shipped! Tracking: {trackingNumber}",
      "variables": ["orderNumber", "trackingNumber"],
      "type": "sms"
    }
  ]
}
```

---

## 6. Create Message Template
- **Endpoint:** `POST /messaging/templates`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "name": "Payment Reminder",
  "content": "Hi {customerName}, your invoice {invoiceNumber} is due on {dueDate}. Please pay to avoid late fees.",
  "variables": ["customerName", "invoiceNumber", "dueDate"],
  "type": "sms"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Message template created successfully",
  "data": {
    "id": "template_002",
    "name": "Payment Reminder",
    "type": "sms",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

# ⚙️ SETTINGS

## 1. Get Business Settings
- **Endpoint:** `GET /settings/business`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business settings retrieved successfully",
  "data": {
    "businessId": "business_88234",
    "businessName": "John's Store",
    "businessType": "retail",
    "email": "contact@johnsstore.com",
    "phone": "+1234567890",
    "address": "123 Main Street, City, State 12345",
    "city": "City",
    "state": "State",
    "postalCode": "12345",
    "country": "Country",
    "taxId": "TAX-123456",
    "website": "https://johnstore.com",
    "logo": "https://example.com/logo.png",
    "currency": "USD",
    "timezone": "UTC-5",
    "dateFormat": "MM/DD/YYYY",
    "timeFormat": "12h",
    "language": "en"
  }
}
```

---

## 2. Update Business Settings
- **Endpoint:** `PUT /settings/business`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "businessName": "John's Store Ltd",
  "phone": "+1234567890",
  "email": "contact@johnsstore.com",
  "address": "123 Main Street, City, State 12345",
  "currency": "USD",
  "timezone": "UTC-5",
  "language": "en"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business settings updated successfully",
  "data": {
    "businessId": "business_88234",
    "businessName": "John's Store Ltd",
    "email": "contact@johnsstore.com",
    "updatedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 3. Get Notification Settings
- **Endpoint:** `GET /settings/notifications`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Notification settings retrieved successfully",
  "data": {
    "userId": "user_12345",
    "emailNotifications": {
      "orderCreated": true,
      "orderShipped": true,
      "orderDelivered": true,
      "lowStock": true,
      "newCustomer": true,
      "expenseApproved": false
    },
    "smsNotifications": {
      "orderCreated": true,
      "lowStock": true,
      "urgentAlerts": true
    },
    "pushNotifications": {
      "orderCreated": true,
      "dashboardAlerts": true
    },
    "quietHours": {
      "enabled": true,
      "startTime": "20:00",
      "endTime": "08:00"
    }
  }
}
```

---

## 4. Update Notification Settings
- **Endpoint:** `PUT /settings/notifications`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "emailNotifications": {
    "orderCreated": true,
    "orderShipped": true,
    "lowStock": true,
    "newCustomer": true
  },
  "smsNotifications": {
    "lowStock": true,
    "urgentAlerts": true
  },
  "quietHours": {
    "enabled": true,
    "startTime": "20:00",
    "endTime": "08:00"
  }
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Notification settings updated successfully",
  "data": {
    "userId": "user_12345",
    "updatedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 5. Get Payment Settings
- **Endpoint:** `GET /settings/payment`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Payment settings retrieved successfully",
  "data": {
    "businessId": "business_88234",
    "paymentMethods": [
      {
        "id": "pm_001",
        "name": "Credit Card",
        "type": "card",
        "enabled": true,
        "provider": "stripe"
      },
      {
        "id": "pm_002",
        "name": "Bank Transfer",
        "type": "bank",
        "enabled": true,
        "provider": "internal"
      }
    ],
    "defaultPaymentMethod": "pm_001",
    "autoReconciliation": true,
    "receiptEmail": "accounting@johnsstore.com"
  }
}
```

---

## 6. Update Payment Settings
- **Endpoint:** `PUT /settings/payment`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "defaultPaymentMethod": "pm_002",
  "autoReconciliation": true,
  "receiptEmail": "accounting@johnsstore.com",
  "enabledMethods": ["pm_001", "pm_002"]
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Payment settings updated successfully",
  "data": {
    "businessId": "business_88234",
    "updatedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 7. Get API Settings
- **Endpoint:** `GET /settings/api`
- **Auth Required:** Yes
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "API settings retrieved successfully",
  "data": {
    "businessId": "business_88234",
    "apiKey": "sk_live_abc123...",
    "apiKeyPublic": "pk_live_xyz789...",
    "webhookUrl": "https://example.com/webhooks",
    "webhookSecret": "whsec_xyz...",
    "rateLimit": 1000,
    "rateLimitWindow": 3600,
    "ipWhitelist": ["192.168.1.1", "10.0.0.1"],
    "enabledEndpoints": ["products", "orders", "customers", "inventory"]
  }
}
```

---

## 8. Regenerate API Key
- **Endpoint:** `POST /settings/api/regenerate-key`
- **Auth Required:** Yes
- **Request Body:**
```json
{
  "keyType": "secret"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "API key regenerated successfully",
  "data": {
    "apiKey": "sk_live_new123...",
    "regeneratedAt": "2026-02-05T09:00:00Z"
  }
}
```

---

# 🏢 SUPERADMIN - BUSINESSES

> **Note:** Superadmin endpoints remain valid for platform operations.
> The current frontend UX is unified into one `/dashboard` flow with role-based gating.

## 1. Get All Businesses
- **Endpoint:** `GET /superadmin/businesses`
- **Auth Required:** Yes (Superadmin)
- **Query Parameters:**
```
?page=1&limit=20&search=john&status=active&subscriptionPlan=professional
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Businesses retrieved successfully",
  "data": [
    {
      "id": "business_88234",
      "name": "John's Store",
      "businessType": "retail",
      "ownerName": "John Doe",
      "ownerEmail": "john@example.com",
      "subscriptionPlan": "professional",
      "subscriptionStatus": "active",
      "startDate": "2026-01-15T00:00:00Z",
      "renewalDate": "2026-04-15T00:00:00Z",
      "status": "active",
      "createdAt": "2026-01-15T10:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 125,
    "totalPages": 7
  }
}
```

---

## 2. Get Business by ID
- **Endpoint:** `GET /superadmin/businesses/:businessId`
- **Auth Required:** Yes (Superadmin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business retrieved successfully",
  "data": {
    "id": "business_88234",
    "name": "John's Store",
    "businessType": "retail",
    "ownerName": "John Doe",
    "ownerEmail": "john@example.com",
    "ownerPhone": "+1234567890",
    "address": "123 Main Street, City, State 12345",
    "city": "City",
    "state": "State",
    "postalCode": "12345",
    "country": "Country",
    "taxId": "TAX-123456",
    "website": "https://johnstore.com",
    "subscriptionPlan": "professional",
    "subscriptionStatus": "active",
    "subscriptionStartDate": "2026-01-15T00:00:00Z",
    "subscriptionRenewalDate": "2026-04-15T00:00:00Z",
    "planPrice": 149.00,
    "billingCycle": "monthly",
    "status": "active",
    "apiKey": "sk_live_abc...",
    "totalUsers": 8,
    "totalProducts": 156,
    "totalOrders": 456,
    "monthlyRevenue": 25000.00,
    "createdAt": "2026-01-15T10:00:00Z"
  }
}
```

---

## 3. Create Business
- **Endpoint:** `POST /superadmin/businesses`
- **Auth Required:** Yes (Superadmin)
- **Request Body:**
```json
{
  "name": "Jane's Enterprise",
  "businessType": "wholesale",
  "ownerName": "Jane Smith",
  "ownerEmail": "jane@enterprise.com",
  "ownerPhone": "+1987654321",
  "address": "456 Business Ave, City, State 54321",
  "city": "City",
  "state": "State",
  "postalCode": "54321",
  "country": "Country",
  "taxId": "TAX-654321",
  "website": "https://janeenterprise.com",
  "subscriptionPlan": "professional",
  "billingCycle": "monthly"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Business created successfully",
  "data": {
    "id": "business_88235",
    "name": "Jane's Enterprise",
    "ownerName": "Jane Smith",
    "ownerEmail": "jane@enterprise.com",
    "subscriptionPlan": "professional",
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Update Business
- **Endpoint:** `PUT /superadmin/businesses/:businessId`
- **Auth Required:** Yes (Superadmin)
- **Request Body:**
```json
{
  "name": "Jane's Enterprise Ltd",
  "subscriptionPlan": "enterprise",
  "subscriptionStatus": "active",
  "status": "active"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business updated successfully",
  "data": {
    "id": "business_88235",
    "name": "Jane's Enterprise Ltd",
    "subscriptionPlan": "enterprise",
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 5. Delete Business
- **Endpoint:** `DELETE /superadmin/businesses/:businessId`
- **Auth Required:** Yes (Superadmin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business deleted successfully"
}
```

---

## 6. Suspend Business
- **Endpoint:** `POST /superadmin/businesses/:businessId/suspend`
- **Auth Required:** Yes (Superadmin)
- **Request Body:**
```json
{
  "reason": "Non-payment of subscription",
  "suspensionDays": 30
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business suspended successfully",
  "data": {
    "id": "business_88235",
    "status": "suspended",
    "suspendedAt": "2026-02-05T09:00:00Z",
    "suspensionReason": "Non-payment of subscription"
  }
}
```

---

## 7. Reactivate Business
- **Endpoint:** `POST /superadmin/businesses/:businessId/reactivate`
- **Auth Required:** Yes (Superadmin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Business reactivated successfully",
  "data": {
    "id": "business_88235",
    "status": "active",
    "reactivatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

# 💳 SUPERADMIN - PRICING PLANS

## 1. Get All Pricing Plans
- **Endpoint:** `GET /superadmin/pricing-plans`
- **Auth Required:** Yes (Superadmin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Pricing plans retrieved successfully",
  "data": [
    {
      "id": "plan_001",
      "name": "Free Trial",
      "slug": "free-trial",
      "description": "Limited trial for new users",
      "priceMonthly": 0,
      "priceYearly": 0,
      "popular": false,
      "color": "gray",
      "features": [
        "Dashboard access",
        "Up to 100 products",
        "1 user",
        "1 location"
      ],
      "limits": {
        "maxProducts": 100,
        "maxUsers": 1,
        "maxLocations": 1,
        "maxStorageMB": 1024
      },
      "featureFlags": ["dashboard", "products", "orders"],
      "status": "active",
      "createdAt": "2026-01-01T00:00:00Z"
    }
  ]
}
```

---

## 2. Get Plan by ID
- **Endpoint:** `GET /superadmin/pricing-plans/:planId`
- **Auth Required:** Yes (Superadmin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Pricing plan retrieved successfully",
  "data": {
    "id": "plan_001",
    "name": "Free Trial",
    "slug": "free-trial",
    "description": "Limited trial for new users",
    "priceMonthly": 0,
    "priceYearly": 0,
    "popular": false,
    "color": "gray",
    "features": [
      "Dashboard access",
      "Up to 100 products",
      "1 user",
      "1 location",
      "Basic reports"
    ],
    "limits": {
      "maxProducts": 100,
      "maxUsers": 1,
      "maxLocations": 1,
      "maxStorageMB": 1024,
      "maxOrdersPerMonth": 100,
      "maxCategories": 10,
      "maxSuppliers": 5,
      "maxCustomers": 100
    },
    "featureFlags": [
      "dashboard",
      "products",
      "orders",
      "customers",
      "inventory",
      "basic_reports"
    ],
    "status": "active",
    "trialDays": 14,
    "subscriptionCount": 0,
    "monthlyRecurringRevenue": 0.00,
    "createdAt": "2026-01-01T00:00:00Z"
  }
}
```

---

## 3. Create Pricing Plan
- **Endpoint:** `POST /superadmin/pricing-plans`
- **Auth Required:** Yes (Superadmin)
- **Request Body:**
```json
{
  "name": "Growth",
  "slug": "growth",
  "description": "For growing businesses",
  "priceMonthly": 249,
  "priceYearly": 2390,
  "popular": false,
  "color": "blue",
  "features": [
    "10,000 products",
    "25 users",
    "10 locations",
    "100GB storage",
    "Advanced analytics",
    "API access",
    "Priority support"
  ],
  "limits": {
    "maxProducts": 10000,
    "maxUsers": 25,
    "maxLocations": 10,
    "maxStorageMB": 102400,
    "maxOrdersPerMonth": 50000,
    "maxCategories": 500,
    "maxSuppliers": 200,
    "maxCustomers": 10000
  },
  "featureFlags": [
    "dashboard",
    "products",
    "orders",
    "customers",
    "inventory",
    "suppliers",
    "purchases",
    "expenses",
    "categories",
    "users",
    "settings",
    "multi_location",
    "advanced_analytics",
    "advanced_reports",
    "api_access",
    "bulk_operations"
  ],
  "trialDays": 0
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Pricing plan created successfully",
  "data": {
    "id": "plan_005",
    "name": "Growth",
    "slug": "growth",
    "priceMonthly": 249,
    "priceYearly": 2390,
    "status": "active",
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

## 4. Update Pricing Plan
- **Endpoint:** `PUT /superadmin/pricing-plans/:planId`
- **Auth Required:** Yes (Superadmin)
- **Request Body:**
```json
{
  "priceMonthly": 199,
  "priceYearly": 1990,
  "popular": true,
  "status": "active"
}
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Pricing plan updated successfully",
  "data": {
    "id": "plan_005",
    "name": "Growth",
    "priceMonthly": 199,
    "priceYearly": 1990,
    "popular": true,
    "updatedAt": "2026-02-05T09:15:00Z"
  }
}
```

---

## 5. Delete Pricing Plan
- **Endpoint:** `DELETE /superadmin/pricing-plans/:planId`
- **Auth Required:** Yes (Superadmin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Pricing plan deleted successfully"
}
```

---

# 📊 SUPERADMIN - ANALYTICS

## 1. Get Platform Analytics
- **Endpoint:** `GET /superadmin/analytics/platform`
- **Auth Required:** Yes (Superadmin)
- **Query Parameters:**
```
?dateFrom=2026-01-01&dateTo=2026-02-05
```
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Platform analytics retrieved successfully",
  "data": {
    "metrics": {
      "totalBusinesses": {
        "value": 125,
        "change": 8.5,
        "trend": "up"
      },
      "activeBusinesses": {
        "value": 98,
        "change": 5.2,
        "trend": "up"
      },
      "totalUsers": {
        "value": 1250,
        "change": 12.3,
        "trend": "up"
      },
      "monthlyRecurringRevenue": {
        "value": 45000.00,
        "change": 15.7,
        "trend": "up"
      },
      "totalGrossVolume": {
        "value": 2500000.00,
        "change": 22.1,
        "trend": "up"
      },
      "averageBusinessValue": {
        "value": 20000.00,
        "change": 10.5,
        "trend": "up"
      }
    },
    "subscriptionBreakdown": {
      "freeTrial": 12,
      "starter": 35,
      "professional": 42,
      "enterprise": 11
    },
    "charts": {
      "businessesByMonth": [
        {
          "month": "2026-01",
          "new": 15,
          "total": 110
        }
      ],
      "revenueByPlan": [
        {
          "plan": "professional",
          "revenue": 25000.00,
          "businessCount": 42
        }
      ]
    }
  }
}
```

---

# 🔗 ROLES & PERMISSIONS

## Current Role Set (Frontend Access Matrix)

| Role | Dashboard | Users | Products/Categories | Sales | Customers |
|------|-----------|-------|---------------------|-------|-----------|
| CEO | Full | Full (add/edit/delete) | Full | Full | Full |
| Manager | Full | View/Edit only (no add user) | Full | Full | Full |
| Sales | View | No access | View only (no add/edit/delete) | Create + View | View only |

---

## 1. Get All Roles
- **Endpoint:** `GET /roles`
- **Auth Required:** Yes (Admin)
- **Response (200 OK):**
```json
{
  "status": "success",
  "message": "Roles retrieved successfully",
  "data": [
    {
      "id": "role_ceo",
      "name": "CEO",
      "description": "Full system access",
      "level": "business",
      
      "isDefault": false,
      "isSystemRole": true,
      "createdAt": "2026-01-01T00:00:00Z"
    },
    {
      "id": "role_manager",
      "name": "Manager",
      "description": "Operational access except user creation",
      "level": "business",
      
      "isDefault": false,
      "isSystemRole": true,
      "createdAt": "2026-01-01T00:00:00Z"
    },
    {
      "id": "role_sales",
      "name": "Sales",
      "description": "Sales-focused view role",
      "level": "business",
      
      "isDefault": false,
      "isSystemRole": true,
      "createdAt": "2026-01-01T00:00:00Z"
    }
  ]
}
```

---

## 2. Create Role
- **Endpoint:** `POST /roles`
- **Auth Required:** Yes (Admin)
- **Request Body:**
```json
{
  "name": "Sales Manager",
  "description": "Manages sales and customer orders"
}
```
- **Response (201 Created):**
```json
{
  "status": "success",
  "message": "Role created successfully",
  "data": {
    "id": "role_007",
    "name": "Sales Manager",
    "description": "Manages sales and customer orders",
    
    "isDefault": false,
    "createdAt": "2026-02-05T09:00:00Z"
  }
}
```

---

# ✅ HTTP STATUS CODES

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Request successful |
| 201 | Created | Resource created |
| 204 | No Content | Request successful, no response body |
| 400 | Bad Request | Invalid request format |
| 401 | Unauthorized | Missing/invalid authentication |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Resource already exists |
| 422 | Unprocessable Entity | Validation error |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

---

# 📋 ERROR RESPONSE FORMAT

```json
{
  "status": "error",
  "message": "Error description",
  "code": "ERROR_CODE",
  "details": {
    "field": ["error message"]
  }
}
```

**Example:**
```json
{
  "status": "error",
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "details": {
    "email": ["Email is required"],
    "password": ["Password must be at least 8 characters"]
  }
}
```

---

# 🔐 AUTHENTICATION HEADERS

All protected endpoints require:
```
Authorization: Bearer {accessToken}
Content-Type: application/json
```

---

# 🚀 BASE IMPLEMENTATION NOTES

1. **Pagination:** All list endpoints support `page`, `limit`, `search`, `sortBy`, `sortOrder`
2. **Timestamps:** All timestamps in ISO 8601 format (UTC)
3. **Currency:** All monetary values in business currency (USD by default)
4. **IDs:** Use UUID or similar unique identifiers
5. **Soft Deletes:** Consider soft deletes for historical data
6. **Audit Logs:** Track all user actions for compliance
7. **Rate Limiting:** Implement rate limiting per user/API key
8. **Caching:** Cache frequently accessed data (products, categories, etc.)
9. **Transactions:** Use database transactions for multi-step operations (orders, purchases)
10. **Webhooks:** Support webhooks for order, payment, and subscription events

---

**Document Version:** 1.1
**Last Updated:** February 28, 2026
**Status:** Complete
