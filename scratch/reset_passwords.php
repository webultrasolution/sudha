<?php
include_once __DIR__ . '/../config/db.php';

$users = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'role' => 'admin',
        'name' => 'System Admin',
        'full_name' => 'System Admin',
        'email' => 'admin@easyoutdoor.com'
    ],
    [
        'username' => 'sales',
        'password' => 'sales123',
        'role' => 'sales',
        'name' => 'Sales Manager',
        'full_name' => 'Sales Manager',
        'email' => 'sales@easyoutdoor.com'
    ],
    [
        'username' => 'ops',
        'password' => 'ops123',
        'role' => 'operations',
        'name' => 'Ops Lead',
        'full_name' => 'Ops Lead',
        'email' => 'ops@easyoutdoor.com'
    ],
    [
        'username' => 'accounts',
        'password' => 'accounts123',
        'role' => 'accounts',
        'name' => 'Accountant',
        'full_name' => 'Accountant',
        'email' => 'accounts@easyoutdoor.com'
    ]
];

try {
    foreach ($users as $u) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$u['username']]);
        $exists = $stmt->fetchColumn();
        
        $pwd_hash = password_hash($u['password'], PASSWORD_DEFAULT);
        
        if ($exists) {
            // Update
            $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ?, name = ?, full_name = ?, email = ? WHERE username = ?");
            $stmt->execute([$pwd_hash, $u['role'], $u['name'], $u['full_name'], $u['email'], $u['username']]);
            echo "Successfully updated user: " . $u['username'] . "\n";
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, full_name, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$u['username'], $pwd_hash, $u['role'], $u['name'], $u['full_name'], $u['email']]);
            echo "Successfully created user: " . $u['username'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
