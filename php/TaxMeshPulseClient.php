<?php
/**
 * TaxMeshPulse (TMP) PHP & Laravel SDK
 * Unified Tax-as-a-Service & DJP Coretax 2026 Engine
 * 
 * @package TaxMeshPulse
 * @version 1.0.0
 * @link https://taxmeshpulse.ctar.tech
 */

namespace TaxMeshPulse;

class TaxMeshPulseClient
{
    private string $apiKey;
    private string $baseUrl;
    private string $environment;
    private int $timeout;

    /**
     * @param string $apiKey     API Key (e.g. tax_test_... or tax_live_...)
     * @param string $environment 'sandbox' or 'live'
     * @param string $baseUrl    Default: https://taxmeshpulse.ctar.tech
     * @param int    $timeout    Timeout in seconds (default 15)
     */
    public function __construct(
        string $apiKey,
        string $environment = 'sandbox',
        string $baseUrl = 'https://taxmeshpulse.ctar.tech',
        int $timeout = 15
    ) {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('TaxMeshPulse API Key is required.');
        }

        $this->apiKey = $apiKey;
        $this->environment = $environment;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * 1. Hitung Pajak Pra-Checkout (PPN 12%, US Sales Tax, EU VAT, Singapore GST)
     *
     * @param array $params [
     *   'currency' => 'IDR',
     *   'customer_country' => 'ID',
     *   'customer_type' => 'business'|'individual',
     *   'items' => [
     *     ['sku' => 'SKU-01', 'name' => 'Produk A', 'unit_price' => 100000, 'quantity' => 1]
     *   ]
     * ]
     * @return array
     */
    public function calculate(array $params): array
    {
        return $this->request('POST', '/v1/tax/calculate', [
            'currency' => $params['currency'] ?? 'IDR',
            'customer_country' => $params['customer_country'] ?? 'ID',
            'customer_type' => $params['customer_type'] ?? 'business',
            'items' => $params['items'] ?? [],
        ]);
    }

    /**
     * 2. Terbitkan Faktur Pajak Resmi / e-Faktur Coretax DJP (Async 202 Accepted)
     *
     * @param array $payload Universal Tax Payload
     * @return array
     */
    public function createInvoice(array $payload): array
    {
        return $this->request('POST', '/v1/tax/invoices', $payload);
    }

    /**
     * 3. Ambil Status e-Faktur dan Dokumen Kepatuhan berdasarkan ID Faktur
     *
     * @param string $invoiceId ID Faktur (misal: inv_...)
     * @return array
     */
    public function getInvoice(string $invoiceId): array
    {
        return $this->request('GET', '/v1/tax/invoices/' . urlencode($invoiceId));
    }

    /**
     * 4. AI Central Intelligence: Klasifikasi HS Code & Objek Pajak DJP
     *
     * @param string $description Nama/Deskripsi Produk
     * @param string $country     Kode Negara (default 'ID')
     * @return array
     */
    public function classifySku(string $description, string $country = 'ID'): array
    {
        return $this->request('POST', '/v1/ai/classify', [
            'description' => $description,
            'country' => $country,
        ]);
    }

    /**
     * 5. AI Audit: Deteksi Anomali NPWP 16 Digit & Tarif Sebelum Submit
     *
     * @param array $payload
     * @return array
     */
    public function auditTransaction(array $payload): array
    {
        return $this->request('POST', '/v1/ai/audit', $payload);
    }

    /**
     * 6. Verifikasi Keamanan Signature Webhook (HMAC-SHA256)
     *
     * @param string $rawBody   Payload mentah dari webhook request (php://input)
     * @param string $signature Nilai header X-TaxMeshPulse-Signature
     * @param string $secret    Webhook Secret Anda
     * @return bool
     */
    public static function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool
    {
        $computed = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($computed, $signature);
    }

    /**
     * Internal cURL Request Dispatcher
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: TaxMeshPulse-PHP-SDK/1.0.0',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('TaxMeshPulse API Connection Error: ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);
        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? 'API Request Failed with status ' . $httpCode;
            throw new \RuntimeException("TaxMeshPulse Error [{$httpCode}]: {$msg}");
        }

        return $decoded ?? [];
    }
}
