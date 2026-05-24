<?php
$file = 'd:\xampp\htdocs\easy-outdoor-crm\modules\operations\view_booking.php';
$content = file_get_contents($file);

$content = str_replace(
    '<?php if ($invoiceFinalized || !canEdit(\'bookings\')): ?>',
    '<?php if (($invoiceFinalized && !$isAdmin) || !canEdit(\'bookings\')): ?>',
    $content
);

file_put_contents($file, $content);
echo "Replaced.";
