<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class InitialDataSeeder extends AbstractSeed
{
    /**
     * Run Method.
     */
    public function run(): void
    {
        // 1. Users Seed
        $usersTable = $this->table('users');
        $usersData = [
            [
                'firstName' => 'Anthony',
                'lastName' => 'Afriyie',
                'email' => 'afriyieanthony013@gmail.com',
                'passwordHash' => password_hash('Password123!', PASSWORD_ARGON2ID),
                'role' => 'ceo',
                'status' => 'active',
                'emailVerified' => true,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ],
            [
                'firstName' => 'Kobby',
                'lastName' => 'Dalton',
                'email' => 'kobbydalton@icloud.com',
                'passwordHash' => password_hash('Password123!', PASSWORD_ARGON2ID),
                'role' => 'manager',
                'status' => 'active',
                'emailVerified' => true,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ],
            [
                'firstName' => 'Kwame',
                'lastName' => 'Gilbert',
                'email' => 'kwamegilbert1114@gmail.com',
                'passwordHash' => password_hash('Password123!', PASSWORD_ARGON2ID),
                'role' => 'ceo',
                'status' => 'active',
                'emailVerified' => true,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ],
            [
                'firstName' => 'Gilbert',
                'lastName' => 'Kukah',
                'email' => 'gkukah1@gmail.com',
                'passwordHash' => password_hash('Password123!', PASSWORD_ARGON2ID),
                'role' => 'salesperson',
                'status' => 'active',
                'emailVerified' => true,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ],
            [
                'firstName' => 'Dummy',
                'lastName' => 'User',
                'email' => 'dummy@stringventory.com',
                'passwordHash' => password_hash('Password123!', PASSWORD_ARGON2ID),
                'role' => 'salesperson',
                'status' => 'active',
                'emailVerified' => false,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ]
        ];

        // Clear existing users if any and insert
        $this->execute('DELETE FROM users');
        $usersTable->insert($usersData)->saveData();

        // 2. Categories Seed
        $categoriesTable = $this->table('categories');
        $categoriesData = [
            ['name' => 'Electronics', 'description' => 'Gadgets and hardware', 'status' => 'active', 'createdAt' => date('Y-m-d H:i:s')],
            ['name' => 'Office Supplies', 'description' => 'Stationery and furniture', 'status' => 'active', 'createdAt' => date('Y-m-d H:i:s')],
            ['name' => 'Clothing', 'description' => 'Apparel and accessories', 'status' => 'active', 'createdAt' => date('Y-m-d H:i:s')],
        ];
        $this->execute('DELETE FROM categories');
        $categoriesTable->insert($categoriesData)->saveData();

        // 3. Suppliers Seed
        $suppliersTable = $this->table('suppliers');
        $suppliersData = [
            ['name' => 'Global Tech Corp', 'email' => 'contact@globaltech.com', 'phone' => '+123456789', 'address' => 'Silicon Valley', 'createdAt' => date('Y-m-d H:i:s')],
            ['name' => 'Elite Office Supplies', 'email' => 'sales@eliteoffice.com', 'phone' => '+987654321', 'address' => 'London, UK', 'createdAt' => date('Y-m-d H:i:s')],
        ];
        $this->execute('DELETE FROM suppliers');
        $suppliersTable->insert($suppliersData)->saveData();

        // 4. Products Seed
        $productsTable = $this->table('products');
        $productsData = [
            [
                'name' => 'Smartphone X',
                'sku' => 'PH-001',
                'categoryId' => 1,
                'supplierId' => 1,
                'sellingPrice' => 899.99,
                'costPrice' => 600.00,
                'status' => 'active',
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Wireless Keyboard',
                'sku' => 'KB-002',
                'categoryId' => 1,
                'supplierId' => 1,
                'sellingPrice' => 49.99,
                'costPrice' => 25.00,
                'status' => 'active',
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Herman Miller Chair',
                'sku' => 'CH-003',
                'categoryId' => 2,
                'supplierId' => 2,
                'sellingPrice' => 1200.00,
                'costPrice' => 800.00,
                'status' => 'active',
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ]
        ];
        $this->execute('DELETE FROM products');
        $productsTable->insert($productsData)->saveData();

        // 5. Inventory Seed
        $inventoryTable = $this->table('inventory');
        $inventoryData = [
            ['productId' => 1, 'quantity' => 10, 'status' => 'low_stock', 'createdAt' => date('Y-m-d H:i:s')],
            ['productId' => 2, 'quantity' => 150, 'status' => 'in_stock', 'createdAt' => date('Y-m-d H:i:s')],
            ['productId' => 3, 'quantity' => 5, 'status' => 'low_stock', 'createdAt' => date('Y-m-d H:i:s')],
        ];
        $this->execute('DELETE FROM inventory');
        $inventoryTable->insert($inventoryData)->saveData();

        // 6. Expense Categories Seed
        $expCategoriesTable = $this->table('expenseCategories');
        $expCategoriesData = [
            ['name' => 'Rent', 'description' => 'Office space rent', 'status' => 'active', 'createdAt' => date('Y-m-d H:i:s')],
            ['name' => 'Utilities', 'description' => 'Water, Electricity, Internet', 'status' => 'active', 'createdAt' => date('Y-m-d H:i:s')],
            ['name' => 'Marketing', 'description' => 'Ads and promotions', 'status' => 'active', 'createdAt' => date('Y-m-d H:i:s')],
        ];
        $this->execute('DELETE FROM expenseCategories');
        $expCategoriesTable->insert($expCategoriesData)->saveData();
    }
}
