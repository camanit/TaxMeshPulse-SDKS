import {
  UniversalTaxPayload,
  TaxCalculationResult,
  TaxInvoiceRecord,
  TaxItem,
} from '../types/index.js';

export interface TaxClientOptions {
  apiKey: string;
  baseUrl?: string;
  environment?: 'sandbox' | 'live';
  timeoutMs?: number;
}

export interface CalculateParams {
  currency?: string;
  customerCountry?: string;
  customerType?: 'business' | 'individual';
  items: TaxItem[];
}

export class InvoicesResource {
  private client: TaxClient;

  constructor(client: TaxClient) {
    this.client = client;
  }

  /**
   * Submit transaction & auto-file invoice (Async 202 Accepted)
   */
  public async create(payload: UniversalTaxPayload): Promise<any> {
    return this.client.request('/v1/tax/invoices', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  /**
   * Get invoice status and compliance document by ID
   */
  public async get(invoiceId: string): Promise<{ status: string; data: TaxInvoiceRecord }> {
    return this.client.request(`/v1/tax/invoices/${invoiceId}`, {
      method: 'GET',
    });
  }

  /**
   * List all invoices
   */
  public async list(): Promise<{ status: string; data: TaxInvoiceRecord[]; count: number }> {
    return this.client.request('/v1/tax/invoices', {
      method: 'GET',
    });
  }
}

export class TaxResource {
  private client: TaxClient;

  constructor(client: TaxClient) {
    this.client = client;
  }

  /**
   * Calculate tax rate pra-checkout
   */
  public async calculate(params: CalculateParams): Promise<{ status: string; data: TaxCalculationResult }> {
    return this.client.request('/v1/tax/calculate', {
      method: 'POST',
      body: JSON.stringify({
        currency: params.currency || 'IDR',
        customer_country: params.customerCountry || 'ID',
        customer_type: params.customerType || 'business',
        items: params.items,
      }),
    });
  }

  /**
   * Submit or schedule monthly tax filing
   */
  public async fileMonthly(period: number, year: number): Promise<any> {
    return this.client.request('/v1/tax/filing/monthly', {
      method: 'POST',
      body: JSON.stringify({ period, year }),
    });
  }
}

export class AiResource {
  private client: TaxClient;

  constructor(client: TaxClient) {
    this.client = client;
  }

  /**
   * AI Smart Classification for SKU
   */
  public async classify(description: string, country = 'ID'): Promise<any> {
    return this.client.request('/v1/ai/classify', {
      method: 'POST',
      body: JSON.stringify({ description, country }),
    });
  }

  /**
   * AI Pre-submission anomaly inspection
   */
  public async audit(payload: UniversalTaxPayload): Promise<any> {
    return this.client.request('/v1/ai/audit', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }
}

export class TaxClient {
  public apiKey: string;
  public baseUrl: string;
  public environment: 'sandbox' | 'live';
  public timeoutMs: number;

  public invoices: InvoicesResource;
  public tax: TaxResource;
  public ai: AiResource;

  constructor(options: TaxClientOptions) {
    if (!options.apiKey) {
      throw new Error('TaxClient requires an apiKey (e.g. tax_test_... or tax_live_...)');
    }
    this.apiKey = options.apiKey;
    this.environment = options.environment || 'sandbox';
    this.baseUrl = options.baseUrl || 'http://localhost:3000';
    this.timeoutMs = options.timeoutMs || 10000;

    this.invoices = new InvoicesResource(this);
    this.tax = new TaxResource(this);
    this.ai = new AiResource(this);
  }

  public async request<T = any>(endpoint: string, init: RequestInit = {}): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = new Headers(init.headers);

    headers.set('Authorization', `Bearer ${this.apiKey}`);
    headers.set('Content-Type', 'application/json');
    headers.set('User-Agent', 'TaxMeshPulse-NodeSDK/1.0.0');

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    try {
      const response = await fetch(url, {
        ...init,
        headers,
        signal: controller.signal,
      });

      clearTimeout(timer);

      const json = await response.json();
      if (!response.ok) {
        throw new Error(
          `TaxMeshPulse API Error [${response.status}]: ${json.message || JSON.stringify(json)}`
        );
      }

      return json;
    } catch (err: any) {
      clearTimeout(timer);
      throw err;
    }
  }
}

// Named alias as requested in spec
export { TaxClient as TaxEngine };
