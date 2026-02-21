"""
License Manager API client for Django.

Uses Django settings and cache framework. Add 'license_manager' to INSTALLED_APPS
and configure LICENSE_MANAGER settings in your settings.py.
"""

from __future__ import annotations

import hashlib
import logging
import os
from pathlib import Path
from typing import Any

import requests
from django.conf import settings
from django.core.cache import cache

logger = logging.getLogger(__name__)


def _get_setting(key: str, default: Any = None) -> Any:
    config = getattr(settings, "LICENSE_MANAGER", {})
    return config.get(key, default)


class LicenseManagerClient:
    """HTTP client for the License Manager External API."""

    def __init__(self) -> None:
        self.server_url: str = _get_setting("SERVER_URL", "").rstrip("/")
        self.api_key: str = _get_setting("API_KEY", "")
        self.product_id: str = _get_setting("PRODUCT_ID", "")
        self.timeout: int = _get_setting("TIMEOUT", 30)
        self.verify_ssl: bool = _get_setting("VERIFY_SSL", True)
        self.cache_ttl: int = _get_setting("CACHE_TTL", 3600)

        license_dir = _get_setting("LICENSE_DIR")
        if license_dir:
            self.license_file = os.path.join(license_dir, ".license")
        else:
            base = getattr(settings, "BASE_DIR", os.getcwd())
            self.license_file = os.path.join(str(base), ".license")

    # -- Configuration check ---------------------------------------------------

    def is_configured(self) -> bool:
        return bool(self.server_url and self.api_key and self.product_id)

    # -- Connection ------------------------------------------------------------

    def check_connection(self) -> dict[str, Any]:
        return self._get("/api/external/connection-check")

    # -- License operations ----------------------------------------------------

    def activate_license(
        self,
        license_code: str,
        client_name: str,
        verify_type: str = "non_envato",
    ) -> dict[str, Any]:
        result = self._post("/api/external/license/activate", {
            "product_id": self.product_id,
            "license_code": license_code,
            "client_name": client_name,
            "verify_type": verify_type,
        })

        if result.get("is_active"):
            lic = result.get("lic_response") or (result.get("data") or {}).get("license_data")
            if lic:
                self._store_license_data(lic)

        return result

    def verify_license(self) -> dict[str, Any]:
        license_data = self._read_license_data()
        if not license_data:
            return {"is_active": False, "message": "No license file found. Activate a license first."}

        cache_key = f"license_manager.verify.{hashlib.md5(license_data.encode()).hexdigest()}"
        if self.cache_ttl > 0:
            cached = cache.get(cache_key)
            if cached is not None:
                return cached

        result = self._post("/api/external/license/verify", {
            "product_id": self.product_id,
            "license_data": license_data,
        })

        if self.cache_ttl > 0:
            cache.set(cache_key, result, self.cache_ttl)

        return result

    def deactivate_license(self) -> dict[str, Any]:
        license_data = self._read_license_data()
        if not license_data:
            return {"is_active": False, "message": "No license file found."}

        result = self._post("/api/external/license/deactivate", {
            "product_id": self.product_id,
            "license_data": license_data,
        })

        if result.get("is_active"):
            self._remove_license_data()

        return result

    def is_licensed(self) -> bool:
        result = self.verify_license()
        return bool(result.get("is_active"))

    # -- Update operations -----------------------------------------------------

    def check_for_update(self, current_version: str) -> dict[str, Any]:
        return self._post("/api/external/update/check", {
            "product_id": self.product_id,
            "current_version": current_version,
        })

    def get_latest_version(self) -> dict[str, Any]:
        return self._post("/api/external/update/latest", {
            "product_id": self.product_id,
        })

    def download_update(
        self, update_id: str, output_dir: str, file_type: str = "main"
    ) -> str:
        """Download an update file. Returns the saved file path."""
        license_data = self._read_license_data()
        body: dict[str, str] = {}
        if license_data:
            body["license_data"] = license_data

        url = f"{self.server_url}/api/external/update/{requests.utils.quote(update_id)}/download/{requests.utils.quote(file_type)}"

        response = requests.post(
            url,
            json=body,
            headers=self._headers(),
            timeout=300,
            verify=self.verify_ssl,
            stream=True,
        )
        response.raise_for_status()

        ext = "sql" if file_type == "sql" else "zip"
        os.makedirs(output_dir, exist_ok=True)
        file_path = os.path.join(output_dir, f"update_{update_id}.{ext}")

        with open(file_path, "wb") as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)

        return file_path

    # -- License file helpers --------------------------------------------------

    def has_license_file(self) -> bool:
        return Path(self.license_file).exists()

    def _store_license_data(self, data: str) -> None:
        Path(self.license_file).write_text(data)

    def _read_license_data(self) -> str | None:
        path = Path(self.license_file)
        if not path.exists():
            return None
        return path.read_text().strip() or None

    def _remove_license_data(self) -> None:
        data = self._read_license_data()
        path = Path(self.license_file)
        if path.exists():
            path.unlink()
        if data:
            cache_key = f"license_manager.verify.{hashlib.md5(data.encode()).hexdigest()}"
            cache.delete(cache_key)

    # -- HTTP helpers ----------------------------------------------------------

    def _headers(self) -> dict[str, str]:
        app_url = _get_setting("APP_URL", "http://localhost:8000")
        return {
            "Content-Type": "application/json",
            "X-API-KEY": self.api_key,
            "X-API-URL": app_url,
            "X-API-IP": _get_setting("APP_IP", "127.0.0.1"),
            "X-API-LANGUAGE": getattr(settings, "LANGUAGE_CODE", "en"),
        }

    def _get(self, path: str) -> dict[str, Any]:
        if not self.is_configured():
            return {"is_active": False, "message": "License Manager is not configured."}
        try:
            r = requests.get(
                f"{self.server_url}{path}",
                headers=self._headers(),
                timeout=self.timeout,
                verify=self.verify_ssl,
            )
            return r.json()
        except (requests.RequestException, ValueError) as e:
            logger.exception("License Manager API error")
            return {"is_active": False, "message": str(e)}

    def _post(self, path: str, payload: dict) -> dict[str, Any]:
        if not self.is_configured():
            return {"is_active": False, "message": "License Manager is not configured."}
        try:
            r = requests.post(
                f"{self.server_url}{path}",
                json=payload,
                headers=self._headers(),
                timeout=self.timeout,
                verify=self.verify_ssl,
            )
            return r.json()
        except (requests.RequestException, ValueError) as e:
            logger.exception("License Manager API error")
            return {"is_active": False, "message": str(e)}
