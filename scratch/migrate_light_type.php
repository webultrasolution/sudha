<?php
$pdo = new PDO('mysql:host=localhost;dbname=easy_outdoor_crm', 'root', '');
$pdo->exec("ALTER TABLE proposals ADD COLUMN light_type VARCHAR(50) AFTER inventory_type");
echo "Column light_type added to proposals table\n";
