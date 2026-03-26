"""
License Manager API client.

Works with any Python app: CLI, Django, Flask, FastAPI, desktop (Tkinter/PyQt).
Requires: requests (pip install requests)
"""

from __future__ import annotations

import json
import os
from pathlib import Path
from typing import Any

import requests


class LicenseManagerClient:
    """HTTP client for the License Manager External API."""

    def __init__(
        self,
        server_url: str,
        api_key: str,
        application_url: str,
        ip_address: str = "127.0.0.1",
        language: str = "en",
        license_file_path: str | None = None,
        timeout: int = 30,
    ):
        self.server_url = server_url.rstrip("/")
        self.api_key = api_key
        self.application_url = application_url
        self.ip_address = ip_address
        self.language = language
        self.timeout = timeout
        self.license_file_path = license_file_path or os.path.join(os.getcwd(), ".license")

    # ── Connection ──────────────────────────────────────────────────────

    def check_connection(self) -> dict[str, Any]:
        return self._get("/api/external/connection-check")

    # ── License Operations ──────────────────────────────────────────────

    def activate_license(
        self, product_id: str, license_code: str, client_name: str
    ) -> dict[str, Any]:
        result = self._post("/api/external/license/activate", {
            "product_id": product_id,
            "license_code": license_code,
            "client_name": client_name,
            "verify_type": "non_envato",
        })

        if result.get("is_active"):
            license_data = result.get("lic_response") or (result.get("data") or {}).get("license_data")
            if license_data:
                path = Path(self.license_file_path)
                path.write_text(license_data)
                os.chmod(path, 0o600)

        return result

    def verify_license(self, product_id: str) -> dict[str, Any]:
        license_data = self._read_license_data()
        if not license_data:
            return {"status": False, "is_active": False, "message": "No license file found."}

        return self._post("/api/external/license/verify", {
            "product_id": product_id,
            "license_data": license_data,
        })

    def deactivate_license(self, product_id: str) -> dict[str, Any]:
        license_data = self._read_license_data()
        if not license_data:
            return {"status": False, "is_active": False, "message": "No license file found."}

        result = self._post("/api/external/license/deactivate", {
            "product_id": product_id,
            "license_data": license_data,
        })

        if result.get("is_active"):
            path = Path(self.license_file_path)
            if path.exists():
                path.unlink()

        return result

    # ── Update Operations ───────────────────────────────────────────────

    def check_for_update(self, product_id: str, current_version: str) -> dict[str, Any]:
        return self._post("/api/external/update/check", {
            "product_id": product_id,
            "current_version": current_version,
        })

    def get_latest_version(self, product_id: str) -> dict[str, Any]:
        return self._post("/api/external/update/latest", {
            "product_id": product_id,
        })

    def download_update(
        self, update_id: str, output_dir: str, file_type: str = "main"
    ) -> dict[str, Any]:
        """Download an update file. Returns a dict with 'file_path' on success."""
        try:
            license_data = self._read_license_data()
            body = {"license_data": license_data} if license_data else {}

            url = f"{self.server_url}/api/external/update/{requests.utils.quote(update_id)}/download/{requests.utils.quote(file_type)}"

            response = requests.post(
                url,
                json=body,
                headers=self._headers(),
                timeout=300,
                stream=True,
            )

            if not response.ok:
                return {"status": False, "message": f"HTTP {response.status_code}"}

            ext = "sql" if file_type == "sql" else "zip"
            file_path = os.path.join(output_dir, f"update_{update_id}.{ext}")
            os.makedirs(output_dir, exist_ok=True)

            with open(file_path, "wb") as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)

            os.chmod(file_path, 0o600)

            return {"status": True, "message": "Download complete.", "file_path": file_path}
        except (requests.RequestException, OSError) as e:
            return {"status": False, "message": str(e)}

    # ── Helpers ──────────────────────────────────────────────────────────

    def has_license_file(self) -> bool:
        return Path(self.license_file_path).exists()

    def _read_license_data(self) -> str | None:
        path = Path(self.license_file_path)
        if not path.exists():
            return None
        return path.read_text().strip() or None

    def _headers(self) -> dict[str, str]:
        return {
            "Content-Type": "application/json",
            "X-API-KEY": self.api_key,
            "X-API-URL": self.application_url,
            "X-API-IP": self.ip_address,
            "X-API-LANGUAGE": self.language,
        }

    def _get(self, path: str) -> dict[str, Any]:
        try:
            r = requests.get(
                f"{self.server_url}{path}",
                headers=self._headers(),
                timeout=self.timeout,
            )
            return r.json()
        except (requests.RequestException, json.JSONDecodeError) as e:
            return {"status": False, "is_active": False, "message": str(e)}

    def _post(self, path: str, payload: dict) -> dict[str, Any]:
        try:
            r = requests.post(
                f"{self.server_url}{path}",
                json=payload,
                headers=self._headers(),
                timeout=self.timeout,
            )
            return r.json()
        except (requests.RequestException, json.JSONDecodeError) as e:
            return {"status": False, "is_active": False, "message": str(e)}
