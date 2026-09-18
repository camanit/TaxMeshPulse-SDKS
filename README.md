# ⚡ TaxMeshPulse SDKs & Connectors Hub
### Official Multi-Language SDKs, WordPress Plugin & Shopify Connector for TaxMeshPulse (TMP)

[![Website](https://img.shields.io/badge/Platform-TaxMeshPulse%20Live-emerald)](https://taxmeshpulse.ctar.tech)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Indonesian Tax: Coretax 2026](https://img.shields.io/badge/Tax%20Compliance-DJP%20Coretax%20PPN%2012%25-red)](https://pajak.go.id)

Repositori ini memuat seluruh kode sumber **SDK resmi, Plugin E-Commerce, dan Webhook Bridge** untuk mengintegrasikan toko online, SaaS, atau aplikasi ERP Anda ke infrastruktur perpajakan otomatis **TaxMeshPulse (TaaS)**.

---

## 📑 Daftar Isi & Direktori

1. [🛒 WordPress WooCommerce Plugin (`woocommerce/`)](#1--wordpress-woocommerce-plugin)
2. [🛍️ Shopify Webhook Connector (`shopify/`)](#2--shopify-webhook-connector)
3. [🐘 PHP & Laravel SDK (`php/`)](#3--php--laravel-sdk)
4. [📦 Node.js & TypeScript SDK (`nodejs/`)](#4--nodejs--typescript-sdk)
5. [🌐 Spesifikasi REST API & Autentikasi](#5--spesifikasi-rest-api--autentikasi)

---

## 1. 🛒 WordPress WooCommerce Plugin

Folder: [`woocommerce/`](./woocommerce/)

Plugin resmi untuk toko online WordPress yang otomatis menghitung PPN 12% pada keranjang belanja dan menerbitkan e-Faktur resmi Coretax DJP (lengkap dengan NSFP, NTTE, dan QR Code DJP).

### Cara Instalasi:
1. Unduh file zip: [`taxmeshpulse-woocommerce.zip`](./woocommerce/taxmeshpulse-woocommerce.zip) (atau unduh langsung dari dashboard web).
2. Di WordPress Admin: Masuk ke **Plugins ➔ Add New ➔ Upload Plugin**.
3. Pilih file zip tersebut lalu klik **Install Now** dan **Activate Plugin**.
4. Masuk ke **WooCommerce ➔ Settings ➔ TaxMeshPulse**:
   - Masukkan **API Key** Anda (Sandbox/Live).
   - Centang opsi *"Otomatisasi e-Faktur DJP saat order Completed"*.
   - Simpan pengaturan.

---

## 2. 🛍️ Shopify Webhook Connector

Folder: [`shopify/`](./shopify/)

Hubungkan toko Shopify Anda tanpa perlu aplikasi pihak ketiga yang mahal.

### File Tersedia:
- [`shopify_receiver.php`](./shopify/shopify_receiver.php): Script receiver siap pasang di server PHP / hosting Anda.
- [`shopifyWebhookHandler.ts`](./shopify/shopifyWebhookHandler.ts): Class transformer TypeScript / Node.js.

### Cara Setup di Shopify Admin:
1. Buka **Shopify Admin ➔ Settings ➔ Notifications ➔ Webhooks**.
2. Klik **Create Webhook**.
3. Konfigurasi:
   - **Event**: `Order payment` atau `Order creation`
   - **Format**: `JSON`
   - **URL**: Masukkan URL script receiver Anda (contoh: `https://domain-anda.com/shopify_receiver.php`)
   - **Webhook API Version**: `Latest`
4. Masukkan Webhook Secret ke konfigurasi script. Setiap ada order yang dibayar, faktur pajak resmi akan otomatis terbit di TaxMeshPulse!

---

## 3. 🐘 PHP & Laravel SDK

Folder: [`php/`](./php/)

File Client: [`TaxMeshPulseClient.php`](./php/TaxMeshPulseClient.php)

Client PHP modern berbasis cURL, kompatibel dengan PHP 7.4, 8.0, 8.1, 8.2, 8.3, dan 8.4.

### Contoh Penggunaan:
```php
<?php
require_once 'TaxMeshPulseClient.php';

use TaxMeshPulse\TaxMeshPulseClient;

// Inisialisasi client dengan API Key
$tmp = new TaxMeshPulseClient('tax_live_your_api_key_here');

// 1. Kalkulasi Pajak Pra-Checkout (PPN 12%)
$taxEstimate = $tmp->calculate([
    'currency' => 'IDR',
    'customer_country' => 'ID',
    'customer_type' => 'business',
    'items' => [
        [
            'sku' => 'PROD-001',
            'name' => 'Langganan Cloud Software',
            'unit_price' => 1000000,
            'quantity' => 1
        ]
    ]
]);

// 2. Terbitkan Faktur Pajak Resmi (Async 202 Accepted)
$invoice = $tmp->createInvoice([
    'reference_id' => 'INV-2026-001',
    'currency' => 'IDR',
    'customer' => [
        'name' => 'PT Solusi Teknologi',
        'email' => 'finance@solusitekno.id',
        'country' => 'ID',
        'type' => 'business',
        'npwp' => '0123456789012345',
        'address' => 'Jl. Jenderal Sudirman Kav. 1, Jakarta'
    ],
    'items' => [
        [
            'sku' => 'PROD-001',
            'name' => 'Langganan Cloud Software',
            'quantity' => 1,
            'unit_price' => 1000000
        ]
    ]
]);

// 3. Verifikasi Signature Webhook HMAC
$isValid = TaxMeshPulseClient::verifyWebhookSignature($rawBody, $signatureHeader, $webhookSecret);
```

---

## 4. 📦 Node.js & TypeScript SDK

Folder: [`nodejs/`](./nodejs/)

File Source: [`index.ts`](./nodejs/index.ts)

### Instalasi:
```bash
npm install taxmeshpulse
```

### Contoh Penggunaan:
```typescript
import { TaxClient } from 'taxmeshpulse';

const client = new TaxClient({
  apiKey: 'tax_live_your_api_key_here',
  environment: 'live',
});

// Hitung tarif pajak
const calculation = await client.tax.calculate({
  currency: 'IDR',
  customerCountry: 'ID',
  items: [
    { sku: 'SKU-A', name: 'Software License', unitPrice: 2500000, quantity: 1 }
  ]
});

// Submit faktur pajak
const invoice = await client.invoices.create({
  reference_id: 'TRX-9901',
  currency: 'IDR',
  customer: {
    name: 'PT Digital Inovasi',
    email: 'billing@digitalinovasi.id',
    country: 'ID',
    type: 'business',
    npwp: '0123456789012345'
  },
  items: [
    { sku: 'SKU-A', name: 'Software License', quantity: 1, unit_price: 2500000 }
  ]
});
```

---

## 5. 🌐 Spesifikasi REST API & Autentikasi

- **Base URL Live**: `https://taxmeshpulse.ctar.tech/v1/tax`
- **Header Autentikasi**: `Authorization: Bearer <YOUR_API_KEY>`
- **Content-Type**: `application/json`

### Endpoints Utama:
- `POST /v1/tax/calculate` - Kalkulasi pajak instan
- `POST /v1/tax/invoices` - Penerbitan e-Faktur Coretax & Dokumen Pajak Resmi
- `GET /v1/tax/invoices/:id` - Cek status faktur, NSFP & QR Code pengesahan DJP
- `POST /v1/ai/classify` - Klasifikasi otomatis HS Code & Kategori Pajak via AI GPlay
- `POST /v1/ai/audit` - Audit anomali NPWP 16 digit & tarif pra-submit

---

## 🤝 Lisensi & Ekosistem
Dikelola oleh tim pengembang ekosistem CTARTech.  
Lisensi: **MIT License**.
