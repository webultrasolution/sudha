<?php
require 'config/db.php';
$stmt = $pdo->query("UPDATE bookings b JOIN proposals p ON b.proposal_id = p.id SET b.billing_gstin = p.billing_gstin WHERE (b.billing_gstin IS NULL OR b.billing_gstin = '') AND p.billing_gstin != '' AND p.billing_gstin IS NOT NULL");
echo $stmt->rowCount() . " rows updated.";
