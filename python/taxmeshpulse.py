"""
TaxMeshPulse (TMP) Python SDK
Unified Tax-as-a-Service & DJP Coretax 2026 Engine
Official Client for Python 3.8+

Platform: https://taxmeshpulse.ctar.tech
Ecosystem: CTARTech AI
"""

import hmac
import hashlib
import json
import urllib.request
import urllib.error
from typing import Dict, Any, List, Optional


class TaxMeshPulseError(Exception):
    """Base exception for TaxMeshPulse API errors."""
    def __init__(self, message: str, status_code: Optional[int] = None, response_body: Optional[Any] = None):
        super().__init__(message)
        self.status_code = status_code
        self.response_body = response_body


class TaxClient:
    """Official TaxMeshPulse Python Client."""

    def __init__(
        self,
        api_key: str,
        environment: str = "sandbox",
        base_url: str = "https://taxmeshpulse.ctar.tech",
        timeout: int = 15,
    ):
        if not api_key:
            raise ValueError("TaxMeshPulse API key is required (e.g. tax_test_... or tax_live_...)")

        self.api_key = api_key
        self.environment = environment.lower()
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout

    def calculate(
        self,
        items: List[Dict[str, Any]],
        currency: str = "IDR",
        customer_country: str = "ID",
        customer_type: str = "business",
    ) -> Dict[str, Any]:
        """
        Kalkulasi tarif pajak pra-checkout (PPN 12% DJP, US Sales Tax, EU VAT OSS).
        """
        payload = {
            "currency": currency,
            "customer_country": customer_country,
            "customer_type": customer_type,
            "items": items,
        }
        return self._request("POST", "/v1/tax/calculate", payload)

    def create_invoice(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        """
        Terbitkan faktur pajak resmi / e-Faktur Coretax DJP (Async 202 Accepted).
        """
        return self._request("POST", "/v1/tax/invoices", payload)

    def get_invoice(self, invoice_id: str) -> Dict[str, Any]:
        """
        Ambil status faktur pajak dan dokumen kepatuhan (NSFP & QR Code DJP).
        """
        return self._request("GET", f"/v1/tax/invoices/{invoice_id}")

    def classify_sku(self, description: str, country: str = "ID") -> Dict[str, Any]:
        """
        AI Smart SKU Classification: Klasifikasi otomatis HS Code & objek pajak DJP via GPlay.
        """
        payload = {"description": description, "country": country}
        return self._request("POST", "/v1/ai/classify", payload)

    def audit_transaction(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        """
        AI Tax Shield: Deteksi anomali NPWP 16 digit & tarif sebelum submit ke DJP.
        """
        return self._request("POST", "/v1/ai/audit", payload)

    @staticmethod
    def verify_webhook_signature(raw_body: bytes, signature_header: str, secret: str) -> bool:
        """
        Verifikasi keaslian webhook callback menggunakan HMAC-SHA256.
        """
        computed = hmac.new(secret.encode("utf-8"), raw_body, hashlib.sha256).hexdigest()
        return hmac.compare_digest(computed, signature_header)

    def _request(self, method: str, endpoint: str, data: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        url = f"{self.base_url}{endpoint}"
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "TaxMeshPulse-PythonSDK/1.0.0",
        }

        body_bytes = None
        if data is not None:
            body_bytes = json.dumps(data).encode("utf-8")

        req = urllib.request.Request(url, data=body_bytes, headers=headers, method=method)

        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as response:
                resp_text = response.read().decode("utf-8")
                return json.loads(resp_text)
        except urllib.error.HTTPError as e:
            error_body = e.read().decode("utf-8")
            try:
                parsed = json.loads(error_body)
                msg = parsed.get("message", f"HTTP Error {e.code}")
            except Exception:
                msg = error_body
            raise TaxMeshPulseError(f"TaxMeshPulse API Error [{e.code}]: {msg}", status_code=e.code, response_body=error_body)
        except urllib.error.URLError as e:
            raise TaxMeshPulseError(f"Connection failed: {str(e.reason)}")
