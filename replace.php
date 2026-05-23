<?php
$file = 'd:\xampp\htdocs\easy-outdoor-crm\modules\operations\view_booking.php';
$content = file_get_contents($file);

$content = str_replace(
    '<?php if (!$invoiceFinalized && canEdit(\'bookings\')): ?>',
    '<?php if ((!$invoiceFinalized || $isAdmin) && canEdit(\'bookings\')): ?>',
    $content
);

$content = str_replace(
    '<?php echo ($invoiceFinalized || !canEdit(\'bookings\')) ? \'disabled\' : \'\'; ?>',
    '<?php echo (($invoiceFinalized && !$isAdmin) || !canEdit(\'bookings\')) ? \'disabled\' : \'\'; ?>',
    $content
);

file_put_contents($file, $content);
echo "Replaced.";
