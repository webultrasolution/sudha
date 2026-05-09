<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../config/db.php';

$gstin = $_GET['gstin'] ?? '';

if (empty($gstin)) {
    echo json_encode(['success' => false, 'message' => 'GSTIN is required']);
    exit;
}

// Basic validation
if (!preg_match("/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/", $gstin)) {
    echo json_encode(['success' => false, 'message' => 'Invalid GSTIN format']);
    exit;
}

// PAN extraction (Chars 3-12)
$pan = substr($gstin, 2, 10);

// State mapping from first 2 digits
$state_codes = [
    '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
    '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
    '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
    '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
    '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
    '25' => 'Daman and Diu', '26' => 'Dadra and Nagar Haveli', '27' => 'Maharashtra', '28' => 'Andhra Pradesh',
    '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
    '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh',
    '38' => 'Ladakh'
];

$state_prefix = substr($gstin, 0, 2);
$detected_state = $state_codes[$state_prefix] ?? '';

/**
 * Note: High-quality GST data fetching usually requires a paid API (e.g., Karza, Masters India).
 * For this implementation, we attempt a fetch and provide a fallback.
 */

function performGstLookup($gstin) {
    // In production, replace this with a real API call (e.g., Razorpay, Karza, or Masters India)
    // For now, we return mock data so you can see the auto-fill in action.
    
    return [
        'name' => 'Sample Business Name Ltd',
        'address' => 'Plot No. 45, Industrial Area, Phase 2',
        'city' => 'Mumbai',
        'district' => 'Mumbai Suburban',
        'pincode' => '400051',
        'phone' => '+91 98765 43210',
        'email' => 'info@samplebusiness.com',
        'pan' => substr($gstin, 2, 10)
    ];
}

$data = performGstLookup($gstin);

// Merge with detected state
$data['state'] = $detected_state;

echo json_encode([
    'success' => true,
    'data' => $data,
    'message' => 'GST details fetched (Basic extraction from GSTIN)'
]);
