package com.licensemanager;

import java.io.IOException;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Duration;
import java.util.Map;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import com.fasterxml.jackson.annotation.JsonProperty;
import com.fasterxml.jackson.databind.ObjectMapper;

/**
 * HTTP client for the License Manager External API.
 * Works with any Java app: desktop (Swing/JavaFX), Spring Boot, CLI, Android.
 *
 * <p>Dependencies: Java 11+, Jackson Databind.
 */
public class LicenseManagerClient implements AutoCloseable {

    private final HttpClient http;
    private final ObjectMapper json;
    private final Config config;
    private final Path licenseFilePath;

    public LicenseManagerClient(Config config) {
        this.config = config;
        this.http = HttpClient.newBuilder()
                .connectTimeout(Duration.ofSeconds(10))
                .build();
        this.json = new ObjectMapper();
        this.licenseFilePath = config.licenseFilePath != null
                ? Path.of(config.licenseFilePath)
                : Path.of(System.getProperty("user.dir"), ".license");
    }

    // ── Connection ──────────────────────────────────────────────────────

    public ApiResponse checkConnection() throws IOException, InterruptedException {
        var response = sendGet("/api/external/connection-check");
        return parseResponse(response, ApiResponse.class);
    }

    // ── License Operations ──────────────────────────────────────────────

    public ActivationResponse activateLicense(String productId, String licenseCode, String clientName)
            throws IOException, InterruptedException {

        var payload = Map.of(
                "product_id", productId,
                "license_code", licenseCode,
                "client_name", clientName,
                "verify_type", "non_envato"
        );

        var response = sendPost("/api/external/license/activate", payload);
        var result = parseResponse(response, ActivationResponse.class);

        if (result.isActive) {
            String licenseData = result.licResponse;
            if (licenseData == null && result.data != null) {
                licenseData = result.data.licenseData;
            }
            if (licenseData != null) {
                Files.writeString(licenseFilePath, licenseData);
            }
        }

        return result;
    }

    public ApiResponse verifyLicense(String productId) throws IOException, InterruptedException {
        String licenseData = readLicenseData();
        if (licenseData == null) {
            var resp = new ApiResponse();
            resp.status = false;
            resp.message = "No license file found.";
            return resp;
        }

        var payload = Map.of("product_id", productId, "license_data", licenseData);
        var response = sendPost("/api/external/license/verify", payload);
        return parseResponse(response, ApiResponse.class);
    }

    public ApiResponse deactivateLicense(String productId) throws IOException, InterruptedException {
        String licenseData = readLicenseData();
        if (licenseData == null) {
            var resp = new ApiResponse();
            resp.status = false;
            resp.message = "No license file found.";
            return resp;
        }

        var payload = Map.of("product_id", productId, "license_data", licenseData);
        var response = sendPost("/api/external/license/deactivate", payload);
        var result = parseResponse(response, ApiResponse.class);

        if (result.isActive && Files.exists(licenseFilePath)) {
            Files.delete(licenseFilePath);
        }

        return result;
    }

    // ── Update Operations ───────────────────────────────────────────────

    public UpdateCheckResponse checkForUpdate(String productId, String currentVersion)
            throws IOException, InterruptedException {

        var payload = Map.of("product_id", productId, "current_version", currentVersion);
        var response = sendPost("/api/external/update/check", payload);
        return parseResponse(response, UpdateCheckResponse.class);
    }

    public LatestVersionResponse getLatestVersion(String productId)
            throws IOException, InterruptedException {

        var payload = Map.of("product_id", productId);
        var response = sendPost("/api/external/update/latest", payload);
        return parseResponse(response, LatestVersionResponse.class);
    }

    public Path downloadUpdate(String updateId, String outputDir, String type)
            throws IOException, InterruptedException {

        String licenseData = readLicenseData();
        String body = licenseData != null
                ? json.writeValueAsString(Map.of("license_data", licenseData))
                : "{}";

        String encodedId = URLEncoder.encode(updateId, StandardCharsets.UTF_8);
        String encodedType = URLEncoder.encode(type, StandardCharsets.UTF_8);
        String url = config.serverUrl + "/api/external/update/" + encodedId + "/download/" + encodedType;

        var request = HttpRequest.newBuilder()
                .uri(URI.create(url))
                .timeout(Duration.ofMinutes(5))
                .headers(commonHeaders())
                .POST(HttpRequest.BodyPublishers.ofString(body))
                .build();

        String ext = "sql".equals(type) ? "sql" : "zip";
        Path outPath = Path.of(outputDir, "update_" + updateId + "." + ext);

        http.send(request, HttpResponse.BodyHandlers.ofFile(outPath));
        return outPath;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public boolean hasLicenseFile() {
        return Files.exists(licenseFilePath);
    }

    private String readLicenseData() throws IOException {
        if (!Files.exists(licenseFilePath)) return null;
        return Files.readString(licenseFilePath).trim();
    }

    private HttpResponse<String> sendGet(String path) throws IOException, InterruptedException {
        var request = HttpRequest.newBuilder()
                .uri(URI.create(config.serverUrl + path))
                .timeout(Duration.ofSeconds(30))
                .headers(commonHeaders())
                .GET()
                .build();

        return http.send(request, HttpResponse.BodyHandlers.ofString());
    }

    private HttpResponse<String> sendPost(String path, Object payload)
            throws IOException, InterruptedException {

        String body = json.writeValueAsString(payload);

        var request = HttpRequest.newBuilder()
                .uri(URI.create(config.serverUrl + path))
                .timeout(Duration.ofSeconds(30))
                .headers(commonHeaders())
                .POST(HttpRequest.BodyPublishers.ofString(body))
                .build();

        return http.send(request, HttpResponse.BodyHandlers.ofString());
    }

    private String[] commonHeaders() {
        return new String[]{
                "Content-Type", "application/json",
                "X-API-KEY", config.apiKey,
                "X-API-URL", config.applicationUrl,
                "X-API-IP", config.ipAddress != null ? config.ipAddress : "127.0.0.1",
                "X-API-LANGUAGE", config.language != null ? config.language : "en"
        };
    }

    private <T> T parseResponse(HttpResponse<String> response, Class<T> clazz) throws IOException {
        return json.readValue(response.body(), clazz);
    }

    @Override
    public void close() {
        // HttpClient does not require explicit close in Java 11+
    }

    // ── Configuration ───────────────────────────────────────────────────

    public static class Config {
        public String serverUrl;
        public String apiKey;
        public String applicationUrl;
        public String ipAddress;
        public String language;
        public String licenseFilePath;

        public Config(String serverUrl, String apiKey, String applicationUrl) {
            this.serverUrl = serverUrl.replaceAll("/+$", "");
            this.apiKey = apiKey;
            this.applicationUrl = applicationUrl;
        }
    }

    // ── Response Models ─────────────────────────────────────────────────

    @JsonIgnoreProperties(ignoreUnknown = true)
    public static class ApiResponse {
        @JsonProperty("status") public boolean status;
        @JsonProperty("is_active") public boolean isActive;
        @JsonProperty("message") public String message;
    }

    @JsonIgnoreProperties(ignoreUnknown = true)
    public static class ActivationResponse extends ApiResponse {
        @JsonProperty("lic_response") public String licResponse;
        @JsonProperty("data") public ActivationData data;
    }

    @JsonIgnoreProperties(ignoreUnknown = true)
    public static class ActivationData {
        @JsonProperty("license_data") public String licenseData;
    }

    @JsonIgnoreProperties(ignoreUnknown = true)
    public static class UpdateCheckResponse extends ApiResponse {
        @JsonProperty("update_available") public boolean updateAvailable;
        @JsonProperty("version") public String version;
        @JsonProperty("release_date") public String releaseDate;
        @JsonProperty("summary") public String summary;
        @JsonProperty("changelog") public String changelog;
        @JsonProperty("update_id") public String updateId;
        @JsonProperty("has_sql") public boolean hasSql;
    }

    @JsonIgnoreProperties(ignoreUnknown = true)
    public static class LatestVersionResponse extends ApiResponse {
        @JsonProperty("data") public LatestVersionData data;
    }

    @JsonIgnoreProperties(ignoreUnknown = true)
    public static class LatestVersionData {
        @JsonProperty("version") public String version;
        @JsonProperty("released_at") public String releasedAt;
        @JsonProperty("summary") public String summary;
        @JsonProperty("changelog") public String changelog;
        @JsonProperty("update_id") public String updateId;
    }
}
