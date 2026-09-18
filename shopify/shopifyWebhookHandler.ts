/**
 * Shopify Webhook Bridge for TaxMeshPulse (TMP)
 * Maps Shopify `orders/paid` or `orders/create` payload to Universal Tax Payload
 */

import crypto from 'crypto';

export interface ShopifyOrderPayload {
  id: number;
  name: string;
  currency: string;
  total_price: string;
  total_tax: string;
  billing_address?: {
    name?: string;
    country_code?: string;
    address1?: string;
    company?: string;
  };
  customer?: {
    email?: string;
    first_name?: string;
    last_name?: string;
    note?: string; // Sometimes stores NPWP
  };
  line_items: Array<{
    sku: string;
    title: string;
    price: string;
    quantity: number;
  }>;
}

export class ShopifyTaxBridge {
  private taxMeshPulseApiKey: string;
  private taxMeshPulseEndpoint: string;
  private shopifyWebhookSecret: string;

  constructor(options: {
    taxMeshPulseApiKey: string;
    shopifyWebhookSecret: string;
    taxMeshPulseEndpoint?: string;
  }) {
    this.taxMeshPulseApiKey = options.taxMeshPulseApiKey;
    this.shopifyWebhookSecret = options.shopifyWebhookSecret;
    this.taxMeshPulseEndpoint = options.taxMeshPulseEndpoint || 'https://taxmeshpulse.ctar.tech';
  }

  /**
   * Verify HMAC signature from Shopify webhook
   */
  public verifyShopifyHmac(rawBody: string, hmacHeader: string): boolean {
    const hash = crypto
      .createHmac('sha256', this.shopifyWebhookSecret)
      .update(rawBody, 'utf8')
      .digest('base64');
    return crypto.timingSafeEqual(Buffer.from(hash), Buffer.from(hmacHeader));
  }

  /**
   * Map Shopify Order to Universal Tax Payload
   */
  public transformOrder(order: ShopifyOrderPayload) {
    const customerCountry = order.billing_address?.country_code || 'ID';
    const customerName = order.billing_address?.name || 
      `${order.customer?.first_name || ''} ${order.customer?.last_name || ''}`.trim() || 
      'Shopify Customer';

    // Parse potential NPWP stored in customer notes or company
    const npwpMatch = (order.customer?.note || order.billing_address?.company || '').match(/\d{16}/);
    const npwp = npwpMatch ? npwpMatch[0] : '0000000000000000';

    return {
      reference_id: `SHOPIFY-${order.id || order.name}`,
      currency: order.currency || 'IDR',
      customer: {
        name: customerName,
        email: order.customer?.email || 'customer@shopify-store.com',
        country: customerCountry,
        type: npwp !== '0000000000000000' ? ('business' as const) : ('individual' as const),
        npwp: npwp,
        address: order.billing_address?.address1 || 'Indonesia',
      },
      items: order.line_items.map((item) => ({
        sku: item.sku || `ITEM-${item.title.slice(0, 10)}`,
        name: item.title,
        quantity: item.quantity,
        unit_price: Math.round(parseFloat(item.price)),
        tax_category: 'standard' as const,
      })),
    };
  }

  /**
   * Relay to TaxMeshPulse API
   */
  public async submitToTaxMeshPulse(order: ShopifyOrderPayload) {
    const payload = this.transformOrder(order);

    const res = await fetch(`${this.taxMeshPulseEndpoint}/v1/tax/invoices`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${this.taxMeshPulseApiKey}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    return await res.json();
  }
}
