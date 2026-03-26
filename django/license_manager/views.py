"""
Example Django views for License Manager integration.

Include in your urls.py:
    from license_manager.urls import urlpatterns as license_urls
    urlpatterns += license_urls
"""

from __future__ import annotations

import json

from django.http import HttpRequest, JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_GET, require_POST

from .client import LicenseManagerClient


def _staff_required(request: HttpRequest) -> JsonResponse | None:
    """Return a 403 JsonResponse if the user is not an authenticated staff member."""
    if not (request.user.is_authenticated and request.user.is_staff):
        return JsonResponse({"error": "Forbidden."}, status=403)
    return None


def _parse_json_body(request: HttpRequest) -> tuple[dict, JsonResponse | None]:
    """Parse JSON body; return (data, None) on success or ({}, error_response) on failure."""
    try:
        data = json.loads(request.body)
        if not isinstance(data, dict):
            raise ValueError("Expected a JSON object.")
        return data, None
    except (json.JSONDecodeError, ValueError) as exc:
        return {}, JsonResponse({"error": f"Invalid JSON: {exc}"}, status=400)


@require_GET
def connection_check(request: HttpRequest) -> JsonResponse:
    if (err := _staff_required(request)):
        return err
    client = LicenseManagerClient()
    return JsonResponse(client.check_connection())


@csrf_exempt
@require_POST
def activate(request: HttpRequest) -> JsonResponse:
    if (err := _staff_required(request)):
        return err
    body, err = _parse_json_body(request)
    if err:
        return err
    license_code = body.get("license_code", "").strip()
    client_name = body.get("client_name", "").strip()
    if not license_code:
        return JsonResponse({"error": "license_code is required."}, status=400)
    if not client_name:
        return JsonResponse({"error": "client_name is required."}, status=400)
    client = LicenseManagerClient()
    result = client.activate_license(
        license_code=license_code,
        client_name=client_name,
    )
    status = 200 if result.get("is_active") else 400
    return JsonResponse(result, status=status)


@require_GET
def verify(request: HttpRequest) -> JsonResponse:
    if (err := _staff_required(request)):
        return err
    client = LicenseManagerClient()
    result = client.verify_license()
    status = 200 if result.get("is_active") else 403
    return JsonResponse(result, status=status)


@csrf_exempt
@require_POST
def deactivate(request: HttpRequest) -> JsonResponse:
    if (err := _staff_required(request)):
        return err
    client = LicenseManagerClient()
    return JsonResponse(client.deactivate_license())


@csrf_exempt
@require_POST
def update_check(request: HttpRequest) -> JsonResponse:
    if (err := _staff_required(request)):
        return err
    body, err = _parse_json_body(request)
    if err:
        return err
    client = LicenseManagerClient()
    result = client.check_for_update(body.get("current_version", "0.0.0"))
    return JsonResponse(result)


@require_GET
def latest_version(request: HttpRequest) -> JsonResponse:
    if (err := _staff_required(request)):
        return err
    client = LicenseManagerClient()
    return JsonResponse(client.get_latest_version())
