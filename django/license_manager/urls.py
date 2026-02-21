from django.urls import path

from . import views

app_name = "license_manager"

urlpatterns = [
    path("connection/", views.connection_check, name="connection-check"),
    path("activate/", views.activate, name="activate"),
    path("verify/", views.verify, name="verify"),
    path("deactivate/", views.deactivate, name="deactivate"),
    path("update-check/", views.update_check, name="update-check"),
    path("latest-version/", views.latest_version, name="latest-version"),
]
