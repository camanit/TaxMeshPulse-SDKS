<?php
/**
 * Standalone Shopify Webhook Receiver for TaxMeshPulse
 * Deploy this single file to your web server (e.g. yourdomain.com/tax-webhook.php)
 */

$tmp_api_key = getenv('TAXMESHPULSE_API_KEY') ?: 'tax_test_demo_9a8b7c6d5e4f3a2b1';
$shopify_secret = getenv('SHOPIFY_WEBHOOK_SECRET') ?: 'your_shopify_shared_secret';
$tmp_endpoint = 'https://taxmeshpulse.ctar.tech/v1/tax/invoices';

// 1. Read input and signature
$raw_payload = file_get_contents('php://input');
$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

// 2. Verify Shopify HMAC
$calculated_hmac = base64_encode(hash_hmac('sha256', $raw_payload, $shopify_secret, true));
if (!hash_equals($calculated_hmac, $hmac_header)) {
    http_response_code(401);
    die(json_encode(['error' => 'Invalid Shopify Webhook Signature']));
}

$order = json_decode($raw_payload, true);
if (!$order) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON payload']));
}

// 3. Map line items
$items = [];
foreach ($order['line_items'] ?? [] as $line) {
    $items[] = [
        'sku' => $line['sku'] ?: 'SKU-' . $line['id'],
        'name' => $line['title'],
        'quantity' => intval($line['quantity']),
        'unit_price' => round(floatval($line['price'])),
    ];
}

$billing = $order['billing_address'] ?? [];
$customer = $order['customer'] ?? [];

// 4. Construct TaxMeshPulse payload
$tmp_payload = [
    'reference_id' => 'SHOPIFY-' . ($order['name'] ?? $order['id']),
    'currency' => $order['currency'] ?? 'IDR',
    'customer' => [
        'name' => $billing['name'] ?: ($customer['first_name'] . ' ' . $customer['last_name']),
        'email' => $customer['email'] ?: 'buyer@shopify.com',
        'country' => $billing['country_code'] ?: 'ID',
        'type' => 'individual',
        'npwp' => '0000000000000000',
        'address' => $billing['address1'] ?: 'Indonesia',
    ],
    'items' => $items,
];

// 5. Send to TaxMeshPulse API
$ch = curl_init($tmp_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tmp_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tmp_api_key,
    'Content-Type: application/json',
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code);
echo $response;
