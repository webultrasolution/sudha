<?php
include_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS entities (
        id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name varchar(255) NOT NULL,
        logo varchar(255) DEFAULT NULL,
        gstin varchar(15) DEFAULT NULL,
        pan varchar(10) DEFAULT NULL,
        address text DEFAULT NULL,
        bank_details text DEFAULT NULL,
        created_at timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Insert current company as the first entity if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM entities")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
        $name = $stmt->fetchColumn() ?: 'Primary Entity';
        
        $gst = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_gstin'")->fetchColumn();
        $pan = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_pan'")->fetchColumn();
        $addr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_address'")->fetchColumn();
        $bank = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_bank_details'")->fetchColumn();
        $logo = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'")->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO entities (name, gstin, pan, address, bank_details, logo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $gst, $pan, $addr, $bank, $logo]);
    }
    
    echo "Entities table created and initialized!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
