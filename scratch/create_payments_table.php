<?php
include 'config/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `client_id` int(11) NOT NULL,
        `booking_id` int(11) DEFAULT NULL,
        `invoice_id` int(11) DEFAULT NULL,
        `amount` decimal(15,2) NOT NULL,
        `payment_date` date NOT NULL,
        `payment_method` varchar(50) DEFAULT 'Bank Transfer',
        `reference_no` varchar(100) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        FOREIGN KEY (`client_id`) REFERENCES `partners`(`id`),
        FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`),
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Payments table created successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
