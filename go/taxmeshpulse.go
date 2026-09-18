// Package taxmeshpulse provides the official Go client for TaxMeshPulse (TMP)
// Unified Tax-as-a-Service & DJP Coretax 2026 Engine.
package taxmeshpulse

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

const (
	DefaultBaseURL = "https://taxmeshpulse.ctar.tech"
	DefaultTimeout = 15 * time.Second
	Version        = "1.0.0"
)

// Client represents the TaxMeshPulse API client
type Client struct {
	apiKey     string
	baseURL    string
	httpClient *http.Client
}

// Option configures the Client
type Option func(*Client)

func WithBaseURL(url string) Option {
	return func(c *Client) {
		c.baseURL = strings.TrimRight(url, "/")
	}
}

func WithHTTPClient(httpClient *http.Client) Option {
	return func(c *Client) {
		c.httpClient = httpClient
	}
}

// NewClient creates a new TaxMeshPulse API client
func NewClient(apiKey string, opts ...Option) (*Client, error) {
	if apiKey == "" {
		return nil, fmt.Errorf("taxmeshpulse: API key is required")
	}

	c := &Client{
		apiKey:  apiKey,
		baseURL: DefaultBaseURL,
		httpClient: &http.Client{
			Timeout: DefaultTimeout,
		},
	}

	for _, opt := range opts {
		opt(c)
	}

	return c, nil
}

// TaxItem represents a line item in a transaction
type TaxItem struct {
	SKU         string  `json:"sku"`
	Name        string  `json:"name"`
	UnitPrice   float64 `json:"unit_price"`
	Quantity    int     `json:"quantity"`
	TaxCategory string  `json:"tax_category,omitempty"`
}

// Customer represents the tax counterparty
type Customer struct {
	Name    string `json:"name"`
	Email   string `json:"email,omitempty"`
	Country string `json:"country"`
	Type    string `json:"type"` // "business" or "individual"
	NPWP    string `json:"npwp,omitempty"`
	Address string `json:"address,omitempty"`
}

// CalculateParams request payload for pra-checkout tax calculation
type CalculateParams struct {
	Currency        string    `json:"currency"`
	CustomerCountry string    `json:"customer_country"`
	CustomerType    string    `json:"customer_type,omitempty"`
	Items           []TaxItem `json:"items"`
}

// CalculateResult response from tax calculation
type CalculateResult struct {
	Status string `json:"status"`
	Data   struct {
		Subtotal   float64 `json:"subtotal"`
		TotalTax   float64 `json:"total_tax"`
		GrandTotal float64 `json:"grand_total"`
		Currency   string  `json:"currency"`
		Breakdown  []struct {
			TaxType string  `json:"tax_type"`
			Rate    float64 `json:"rate"`
			Amount  float64 `json:"amount"`
		} `json:"breakdown"`
	} `json:"data"`
}

// UniversalTaxPayload payload to file official tax invoice
type UniversalTaxPayload struct {
	ReferenceID string    `json:"reference_id"`
	Currency    string    `json:"currency"`
	Customer    Customer  `json:"customer"`
	Items       []TaxItem `json:"items"`
}

// InvoiceResponse response after filing invoice
type InvoiceResponse struct {
	Status  string `json:"status"`
	Message string `json:"message"`
	Data    struct {
		ID          string  `json:"id"`
		ReferenceID string  `json:"reference_id"`
		GrandTotal  float64 `json:"grand_total"`
		Status      string  `json:"status"`
		Compliance  struct {
			NSFP       string `json:"nsfp"`
			NTTE       string `json:"ntte"`
			QRCodeURL  string `json:"qr_code_url"`
			FiledAt    string `json:"filed_at"`
		} `json:"compliance"`
	} `json:"data"`
}

// Calculate estimates taxes before checkout
func (c *Client) Calculate(ctx context.Context, params CalculateParams) (*CalculateResult, error) {
	if params.Currency == "" {
		params.Currency = "IDR"
	}
	if params.CustomerCountry == "" {
		params.CustomerCountry = "ID"
	}

	var result CalculateResult
	err := c.request(ctx, http.MethodPost, "/v1/tax/calculate", params, &result)
	if err != nil {
		return nil, err
	}
	return &result, nil
}

// CreateInvoice files an official tax invoice asynchronously
func (c *Client) CreateInvoice(ctx context.Context, payload UniversalTaxPayload) (*InvoiceResponse, error) {
	var result InvoiceResponse
	err := c.request(ctx, http.MethodPost, "/v1/tax/invoices", payload, &result)
	if err != nil {
		return nil, err
	}
	return &result, nil
}

// GetInvoice retrieves invoice status and compliance document
func (c *Client) GetInvoice(ctx context.Context, invoiceID string) (*InvoiceResponse, error) {
	var result InvoiceResponse
	err := c.request(ctx, http.MethodGet, "/v1/tax/invoices/"+invoiceID, nil, &result)
	if err != nil {
		return nil, err
	}
	return &result, nil
}

// VerifyWebhookSignature checks HMAC SHA256 signature on incoming callbacks
func VerifyWebhookSignature(rawBody []byte, signatureHeader, secret string) bool {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(rawBody)
	expectedMAC := hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(expectedMAC), []byte(signatureHeader))
}

func (c *Client) request(ctx context.Context, method, path string, in, out interface{}) error {
	url := c.baseURL + path

	var bodyReader io.Reader
	if in != nil {
		b, err := json.Marshal(in)
		if err != nil {
			return fmt.Errorf("taxmeshpulse: failed to marshal json: %w", err)
		}
		bodyReader = bytes.NewReader(b)
	}

	req, err := http.NewRequestWithContext(ctx, method, url, bodyReader)
	if err != nil {
		return fmt.Errorf("taxmeshpulse: failed to create request: %w", err)
	}

	req.Header.Set("Authorization", "Bearer "+c.apiKey)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "TaxMeshPulse-GoSDK/"+Version)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("taxmeshpulse: http request error: %w", err)
	}
	defer resp.Body.Close()

	respBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("taxmeshpulse: failed to read response: %w", err)
	}

	if resp.StatusCode >= 400 {
		return fmt.Errorf("taxmeshpulse: API error (status %d): %s", resp.StatusCode, string(respBytes))
	}

	if out != nil {
		if err := json.Unmarshal(respBytes, out); err != nil {
			return fmt.Errorf("taxmeshpulse: failed to unmarshal response: %w", err)
		}
	}

	return nil
}
