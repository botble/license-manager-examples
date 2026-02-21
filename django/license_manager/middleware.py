"""
License verification middleware for Django.

Add to MIDDLEWARE in settings.py:
    MIDDLEWARE = [
        ...
        'license_manager.middleware.VerifyLicenseMiddleware',
    ]

Or apply per-view with the decorator:
    from license_manager.middleware import license_required

    @license_required
    def premium_view(request):
        ...
"""

from __future__ import annotations

import logging
from functools import wraps
from typing import Callable

from django.http import HttpRequest, HttpResponse, JsonResponse

from .client import LicenseManagerClient

logger = logging.getLogger(__name__)


class VerifyLicenseMiddleware:
    """Middleware that blocks all requests when the license is invalid."""

    def __init__(self, get_response: Callable[[HttpRequest], HttpResponse]) -> None:
        self.get_response = get_response
        self.client = LicenseManagerClient()

    def __call__(self, request: HttpRequest) -> HttpResponse:
        if not self.client.is_licensed():
            if request.content_type == "application/json" or request.headers.get("Accept") == "application/json":
                return JsonResponse(
                    {"message": "A valid license is required to access this resource."},
                    status=403,
                )
            return HttpResponse("A valid license is required to access this resource.", status=403)

        return self.get_response(request)


def license_required(view_func: Callable) -> Callable:
    """Decorator to protect individual views with license verification."""

    @wraps(view_func)
    def wrapper(request: HttpRequest, *args, **kwargs) -> HttpResponse:
        client = LicenseManagerClient()
        if not client.is_licensed():
            if request.content_type == "application/json" or request.headers.get("Accept") == "application/json":
                return JsonResponse(
                    {"message": "A valid license is required to access this resource."},
                    status=403,
                )
            return HttpResponse("A valid license is required to access this resource.", status=403)
        return view_func(request, *args, **kwargs)

    return wrapper
