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
 * Note: Real GST data fetching via RapidAPI.
 * You need to get a free API Key from RapidAPI (e.g., GST Search or GST Verification API).
 */
function performGstLookup($gstin) {
    // Using the RapidAPI Key provided by the user
    $apiKey = '9e8f811eeamsh33238160ce02f0dp10ada1jsna3ec6f5d1138'; 
    $apiHost = 'gst-return-status.p.rapidapi.com'; 

    $url = "https://{$apiHost}/free/gstin/{$gstin}";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "X-RapidAPI-Host: {$apiHost}",
            "X-RapidAPI-Key: {$apiKey}"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['error' => "cURL Error #:" . $err];
    } else {
        $res = json_decode($response, true);
        
        // Debugging: Save response to file
        file_put_contents('gst_debug_json.txt', $response);

        $d = $res['data'] ?? $res['taxpayerDetails'] ?? $res['taxpayer'] ?? $res;

        if (is_array($d) && (isset($d['lgnm']) || isset($d['legalName']) || isset($d['trade_name']) || isset($d['stj']))) {
            // Address logic
            $addr = $d['pradr']['addr'] ?? $d['address'] ?? [];
            $fullAddress = "";
            if (is_array($addr)) {
                $fullAddress = implode(', ', array_filter([
                    $addr['bnm'] ?? $addr['buildingName'] ?? '',
                    $addr['st'] ?? $addr['street'] ?? '',
                    $addr['loc'] ?? $addr['location'] ?? '',
                    $addr['dst'] ?? $addr['district'] ?? ''
                ]));
            } else {
                $fullAddress = $addr;
            }

            return [
                'name' => $d['lgnm'] ?? $d['legalName'] ?? $d['trade_name'] ?? $d['tradeName'] ?? 'Name Not Found',
                'address' => $fullAddress,
                'city' => $addr['dst'] ?? $addr['district'] ?? $d['city'] ?? '',
                'district' => $addr['dst'] ?? $addr['district'] ?? '',
                'pincode' => $addr['pncd'] ?? $addr['pincode'] ?? $d['pincode'] ?? '',
                'state' => $addr['stcd'] ?? $addr['state'] ?? '',
                'phone' => $d['mobNum'] ?? $d['contact'] ?? $d['phone'] ?? '', 
                'email' => $d['emailId'] ?? $d['email'] ?? '',
                'pan' => substr($gstin, 2, 10),
                'success' => true
            ];
        } else {
            // API returned something but not what we expected
            $msg = $res['message'] ?? 'API format mismatch or not subscribed';
            return [
                'name' => 'Error: ' . $msg,
                'address' => 'Raw Response: ' . substr($response, 0, 100),
                'success' => false,
                'pan' => substr($gstin, 2, 10)
            ];
        }
    }

    return [
        'name' => 'Connection Failed',
        'address' => 'Could not connect to GST API',
        'success' => false,
        'pan' => substr($gstin, 2, 10)
    ];
}


$result = performGstLookup($gstin);

// Merge with detected state from GSTIN prefix if API didn't provide it
if (empty($result['state'])) {
    $result['state'] = $detected_state;
}

echo json_encode([
    'success' => $result['success'] ?? true,
    'data' => $result,
    'message' => isset($result['success']) && $result['success'] ? 'GST details fetched successfully' : 'Using extraction/mock data'
]);

