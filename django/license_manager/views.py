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


@require_GET
def connection_check(request: HttpRequest) -> JsonResponse:
    client = LicenseManagerClient()
    return JsonResponse(client.check_connection())


@csrf_exempt
@require_POST
def activate(request: HttpRequest) -> JsonResponse:
    client = LicenseManagerClient()
    body = json.loads(request.body)
    result = client.activate_license(
        license_code=body.get("license_code", ""),
        client_name=body.get("client_name", ""),
    )
    status = 200 if result.get("is_active") else 400
    return JsonResponse(result, status=status)


@require_GET
def verify(request: HttpRequest) -> JsonResponse:
    client = LicenseManagerClient()
    result = client.verify_license()
    status = 200 if result.get("is_active") else 403
    return JsonResponse(result, status=status)


@csrf_exempt
@require_POST
def deactivate(request: HttpRequest) -> JsonResponse:
    client = LicenseManagerClient()
    return JsonResponse(client.deactivate_license())


@csrf_exempt
@require_POST
def update_check(request: HttpRequest) -> JsonResponse:
    client = LicenseManagerClient()
    body = json.loads(request.body)
    result = client.check_for_update(body.get("current_version", "0.0.0"))
    return JsonResponse(result)


@require_GET
def latest_version(request: HttpRequest) -> JsonResponse:
    client = LicenseManagerClient()
    return JsonResponse(client.get_latest_version())
