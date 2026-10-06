<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'billing');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // First connect without DB to ensure database exists
            $initPdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Connect to actual database
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Ensure tables are initialized
            initDatabaseTables($pdo);

        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }
    return $pdo;
}

function initDatabaseTables($pdo) {
    // Clients table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `clients` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `company_name` VARCHAR(150) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `gstin` VARCHAR(30) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `state` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Suppliers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `company_name` VARCHAR(150) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `gstin` VARCHAR(30) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `bank_details` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Quotations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quotations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `quotation_number` VARCHAR(50) NOT NULL UNIQUE,
        `client_id` INT NOT NULL,
        `quotation_date` DATE NOT NULL,
        `valid_until` DATE DEFAULT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('Draft', 'Sent', 'Accepted', 'Rejected', 'Converted') DEFAULT 'Draft',
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Proforma Invoices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `proforma_invoices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `proforma_number` VARCHAR(50) NOT NULL UNIQUE,
        `client_id` INT NOT NULL,
        `issue_date` DATE NOT NULL,
        `due_date` DATE DEFAULT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('Draft', 'Sent', 'Converted', 'Cancelled') DEFAULT 'Draft',
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Invoices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `invoices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
        `client_id` INT NOT NULL,
        `issue_date` DATE NOT NULL,
        `due_date` DATE DEFAULT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('Unpaid', 'Partial', 'Paid') DEFAULT 'Unpaid',
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // LPOs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lpos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lpo_number` VARCHAR(50) NOT NULL UNIQUE,
        `supplier_id` INT NOT NULL,
        `issue_date` DATE NOT NULL,
        `delivery_date` DATE DEFAULT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('Issued', 'Received', 'Cancelled') DEFAULT 'Issued',
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Document Line Items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `document_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_type` ENUM('quotation', 'proforma', 'invoice', 'lpo') NOT NULL,
        `doc_id` INT NOT NULL,
        `item_description` TEXT NOT NULL,
        `hsn_code` VARCHAR(20) DEFAULT '',
        `qty` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
        `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Document Vehicles table (Supports multiple vehicles per document)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `document_vehicles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_type` ENUM('quotation', 'proforma', 'invoice', 'lpo') NOT NULL,
        `doc_id` INT NOT NULL,
        `vehicle_number` VARCHAR(50) DEFAULT '',
        `driver_name` VARCHAR(100) DEFAULT '',
        `driver_phone` VARCHAR(30) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Company Settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `company_settings` (
        `id` INT PRIMARY KEY DEFAULT 1,
        `company_name` VARCHAR(150) NOT NULL DEFAULT 'Apex Technologies & Billing',
        `email` VARCHAR(100) DEFAULT 'billing@apextech.com',
        `phone` VARCHAR(30) DEFAULT '+91 98765 43210',
        `address` TEXT DEFAULT '101 Corporate Tower, Tech Park, Chennai, Tamil Nadu, 600001',
        `state` VARCHAR(100) DEFAULT 'Tamil Nadu',
        `gstin` VARCHAR(30) DEFAULT '33AAAAA0000A1Z5',
        `bank_name` VARCHAR(100) DEFAULT 'HDFC Bank',
        `account_number` VARCHAR(50) DEFAULT '50200012345678',
        `ifsc_code` VARCHAR(20) DEFAULT 'HDFC0001234',
        `logo` VARCHAR(255) DEFAULT NULL,
        `cgst_rate` DECIMAL(5,2) DEFAULT 9.00,
        `sgst_rate` DECIMAL(5,2) DEFAULT 9.00
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure state, logo, cgst_rate, sgst_rate columns exist if table was created previously
    try {
        $pdo->exec("ALTER TABLE `company_settings` ADD COLUMN `state` VARCHAR(100) DEFAULT 'Tamil Nadu'");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE `company_settings` ADD COLUMN `logo` VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE `company_settings` ADD COLUMN `cgst_rate` DECIMAL(5,2) DEFAULT 9.00");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE `company_settings` ADD COLUMN `sgst_rate` DECIMAL(5,2) DEFAULT 9.00");
    } catch (Exception $e) {}

    // Transport / Vehicle columns for document tables
    $docTables = ['invoices', 'proforma_invoices', 'quotations', 'lpos'];
    foreach ($docTables as $tName) {
        try { $pdo->exec("ALTER TABLE `$tName` ADD COLUMN `enable_transport` TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `$tName` ADD COLUMN `vehicle_number` VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `$tName` ADD COLUMN `driver_name` VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `$tName` ADD COLUMN `driver_phone` VARCHAR(30) DEFAULT NULL"); } catch (Exception $e) {}
    }

    // Insert default company profile if empty
    $chk = $pdo->query("SELECT COUNT(*) FROM `company_settings`")->fetchColumn();
    if ($chk == 0) {
        $pdo->exec("INSERT INTO `company_settings` (`id`, `company_name`, `email`, `phone`, `address`, `state`, `gstin`, `bank_name`, `account_number`, `ifsc_code`, `logo`, `cgst_rate`, `sgst_rate`) VALUES (1, 'Apex Tech Billing Solutions', 'info@apextech.com', '+91 98765 43210', '101 Commercial Hub, MG Road, Chennai', 'Tamil Nadu', '33ABCDE1234F1Z5', 'HDFC Bank', '50200098765432', 'HDFC0001234', NULL, 9.00, 9.00)");
    }
}

// Function to fetch company settings globally
function getCompanySettings($forceRefresh = false) {
    static $companySettings = null;
    if ($companySettings === null || $forceRefresh) {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
        $companySettings = $stmt->fetch();
        if (!$companySettings) {
            $companySettings = [
                'company_name' => 'Billing System',
                'email' => '',
                'phone' => '',
                'address' => '',
                'state' => 'Tamil Nadu',
                'gstin' => '',
                'bank_name' => '',
                'account_number' => '',
                'ifsc_code' => '',
                'logo' => '',
                'cgst_rate' => 9.00,
                'sgst_rate' => 9.00
            ];
        }
    }
    return $companySettings;
}

// Function to format currency nicely
function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

// Helper for generating document reference numbers
function generateDocNumber($prefix, $table, $column) {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM `$table`");
    $row = $stmt->fetch();
    $next = ($row['max_id'] ?? 0) + 1;
    return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}
