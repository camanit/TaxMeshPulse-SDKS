# ⚡ TaxMeshPulse SDKs, Plugins & Connectors Hub
### Official Multi-Language SDKs, WordPress Plugin & Shopify Connector for TaxMeshPulse (TMP)

[![Website](https://img.shields.io/badge/Platform-TaxMeshPulse%20Live-emerald)](https://taxmeshpulse.ctar.tech)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Indonesian Tax: Coretax 2026](https://img.shields.io/badge/Tax%20Compliance-DJP%20Coretax%20PPN%2012%25-red)](https://pajak.go.id)

Repositori ini memuat seluruh kode sumber **SDK resmi multi-bahasa, Plugin E-Commerce, dan Webhook Bridge** untuk mengintegrasikan toko online, SaaS, atau aplikasi ERP Anda ke infrastruktur perpajakan otomatis **TaxMeshPulse (TaaS)**.

---

## 📑 Daftar Isi & Direktori

1. [🛒 WordPress WooCommerce Plugin (`woocommerce/`)](#1--wordpress-woocommerce-plugin)
2. [🛍️ Shopify Webhook Connector (`shopify/`)](#2--shopify-webhook-connector)
3. [🐍 Python SDK (`python/`)](#3--python-sdk)
4. [🔵 Go (Golang) SDK (`go/`)](#4--go-golang-sdk)
5. [🐘 PHP & Laravel SDK (`php/`)](#5--php--laravel-sdk)
6. [📦 Node.js & TypeScript SDK (`nodejs/`)](#6--nodejs--typescript-sdk)
7. [🌐 Spesifikasi REST API & Autentikasi](#7--spesifikasi-rest-api--autentikasi)

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
2. Klik **Create Webhook**:
   - **Event**: `Order payment` atau `Order creation`
   - **Format**: `JSON`
   - **URL**: Masukkan URL script receiver Anda (contoh: `https://domain-anda.com/shopify_receiver.php`)
   - **Webhook API Version**: `Latest`
3. Masukkan Webhook Secret ke konfigurasi script. Faktur pajak resmi otomatis terbit setiap ada pesanan yang lunas!

---

## 3. 🐍 Python SDK

Folder: [`python/`](./python/)

File Client: [`taxmeshpulse.py`](./python/taxmeshpulse.py)

Mendukung Python 3.8+ untuk backend Django, FastAPI, Flask, dan data pipeline.

### Contoh Penggunaan:
```python
from taxmeshpulse import TaxClient

client = TaxClient(api_key="tax_live_your_api_key_here")

# 1. Hitung Pajak Pra-Checkout (PPN 12% DJP)
calc = client.calculate(
    currency="IDR",
    customer_country="ID",
    customer_type="business",
    items=[
        {"sku": "SKU-01", "name": "Langganan Cloud CRM", "unit_price": 750000, "quantity": 1}
    ]
)
print("Pajak Terhitung:", calc["data"]["total_tax"])

# 2. Terbitkan Faktur Resmi Coretax DJP
invoice = client.create_invoice({
    "reference_id": "INV-PY-1001",
    "currency": "IDR",
    "customer": {
        "name": "PT Solusi Cloud Indonesia",
        "email": "finance@solusicloud.id",
        "country": "ID",
        "type": "business",
        "npwp": "0123456789012345",
        "address": "Jakarta Selatan"
    },
    "items": [
        {"sku": "SKU-01", "name": "Langganan Cloud CRM", "quantity": 1, "unit_price": 750000}
    ]
})
print("NSFP e-Faktur:", invoice["data"]["compliance"]["nsfp"])
```

---

## 4. 🔵 Go (Golang) SDK

Folder: [`go/`](./go/)

File Client: [`taxmeshpulse.go`](./go/taxmeshpulse.go)

Client performa tinggi dengan context timeout, zero third-party dependencies, dan koncurrency-safe.

### Instalasi:
```bash
go get github.com/camanit/TaxMeshPulse-SDKS/go
```

### Contoh Penggunaan:
```go
package main

import (
	"context"
	"fmt"
	"log"

	"github.com/camanit/TaxMeshPulse-SDKS/go"
)

func main() {
	client, err := taxmeshpulse.NewClient("tax_live_your_api_key_here")
	if err != nil {
		log.Fatal(err)
	}

	ctx := context.Background()

	// 1. Kalkulasi Pajak
	calc, err := client.Calculate(ctx, taxmeshpulse.CalculateParams{
		Currency:        "IDR",
		CustomerCountry: "ID",
		Items: []taxmeshpulse.TaxItem{
			{SKU: "API-01", Name: "API Gateway Quota", UnitPrice: 500000, Quantity: 2},
		},
	})
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Grand Total: Rp%.2f\n", calc.Data.GrandTotal)

	// 2. Terbitkan Faktur DJP Resmi
	inv, err := client.CreateInvoice(ctx, taxmeshpulse.UniversalTaxPayload{
		ReferenceID: "GO-TX-8801",
		Currency:    "IDR",
		Customer: taxmeshpulse.Customer{
			Name:    "PT Golang Microservices",
			Country: "ID",
			Type:    "business",
			NPWP:    "0123456789012345",
		},
		Items: []taxmeshpulse.TaxItem{
			{SKU: "API-01", Name: "API Gateway Quota", UnitPrice: 500000, Quantity: 2},
		},
	})
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Faktur Created. Status: %s, QR: %s\n", inv.Data.Status, inv.Data.Compliance.QRCodeURL)
}
```

---

## 5. 🐘 PHP & Laravel SDK

Folder: [`php/`](./php/)

File Client: [`TaxMeshPulseClient.php`](./php/TaxMeshPulseClient.php)

Client PHP native berbasis cURL, kompatibel dengan PHP 7.4 hingga 8.4.

```php
<?php
require_once 'TaxMeshPulseClient.php';

use TaxMeshPulse\TaxMeshPulseClient;

$tmp = new TaxMeshPulseClient('tax_live_your_api_key_here');

// Kalkulasi Pajak
$taxEstimate = $tmp->calculate([
    'items' => [
        ['sku' => 'PROD-001', 'name' => 'Software Hosting', 'unit_price' => 1000000, 'quantity' => 1]
    ]
]);
```

---

## 6. 📦 Node.js & TypeScript SDK

Folder: [`nodejs/`](./nodejs/)

File Source: [`index.ts`](./nodejs/index.ts)

```bash
npm install taxmeshpulse
```

```typescript
import { TaxClient } from 'taxmeshpulse';

const client = new TaxClient({ apiKey: 'tax_live_...' });
const calc = await client.tax.calculate({
  currency: 'IDR',
  customerCountry: 'ID',
  items: [{ sku: 'SKU-1', name: 'Cloud Hosting', unitPrice: 200000, quantity: 1 }]
});
```

---

## 7. 🌐 Spesifikasi REST API & Autentikasi

- **Base URL Live**: `https://taxmeshpulse.ctar.tech/v1/tax`
- **Header Autentikasi**: `Authorization: Bearer <YOUR_API_KEY>`
- **Content-Type**: `application/json`

---

## 🤝 Lisensi & Ekosistem
Dikelola oleh tim pengembang ekosistem CTARTech.  
Lisensi: **MIT License**.
