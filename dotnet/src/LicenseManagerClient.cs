using System.Net.Http.Json;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace LicenseManager;

/// <summary>
/// HTTP client for the License Manager External API.
/// Works with any .NET app: console, WPF, WinForms, ASP.NET Core, Blazor, MAUI.
/// </summary>
public sealed class LicenseManagerClient : IDisposable
{
    private readonly HttpClient _http;
    private readonly LicenseManagerOptions _options;
    private readonly string _licenseFilePath;

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    };

    public LicenseManagerClient(LicenseManagerOptions options, HttpClient? httpClient = null)
    {
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _licenseFilePath = options.LicenseFilePath
            ?? Path.Combine(AppContext.BaseDirectory, ".license");

        _http = httpClient ?? new HttpClient();
        _http.BaseAddress = new Uri(options.ServerUrl.TrimEnd('/'));
        _http.DefaultRequestHeaders.Add("X-API-KEY", options.ApiKey);
        _http.DefaultRequestHeaders.Add("X-API-URL", options.ApplicationUrl);
        _http.DefaultRequestHeaders.Add("X-API-IP", options.IpAddress ?? "127.0.0.1");
        _http.DefaultRequestHeaders.Add("X-API-LANGUAGE", options.Language ?? "en");
    }

    // ── Connection ──────────────────────────────────────────────────────

    public async Task<ApiResponse> CheckConnectionAsync(CancellationToken ct = default)
    {
        var response = await _http.GetAsync("/api/external/connection-check", ct);
        return await ParseResponse(response, ct);
    }

    // ── License Operations ──────────────────────────────────────────────

    public async Task<ActivationResponse> ActivateLicenseAsync(
        string productId,
        string licenseCode,
        string clientName,
        CancellationToken ct = default)
    {
        var payload = new
        {
            product_id = productId,
            license_code = licenseCode,
            client_name = clientName,
            verify_type = "non_envato",
        };

        var response = await _http.PostAsJsonAsync(
            "/api/external/license/activate", payload, JsonOptions, ct);

        var result = await ParseResponse<ActivationResponse>(response, ct);

        if (result.IsActive)
        {
            var licenseData = result.LicResponse ?? result.Data?.LicenseData;
            if (!string.IsNullOrEmpty(licenseData))
            {
                await File.WriteAllTextAsync(_licenseFilePath, licenseData, ct);
            }
        }

        return result;
    }

    public async Task<ApiResponse> VerifyLicenseAsync(
        string productId,
        CancellationToken ct = default)
    {
        var licenseData = await ReadLicenseDataAsync(ct);
        if (licenseData is null)
            return new ApiResponse { Status = false, Message = "No license file found." };

        var payload = new { product_id = productId, license_data = licenseData };

        var response = await _http.PostAsJsonAsync(
            "/api/external/license/verify", payload, JsonOptions, ct);

        return await ParseResponse(response, ct);
    }

    public async Task<ApiResponse> DeactivateLicenseAsync(
        string productId,
        CancellationToken ct = default)
    {
        var licenseData = await ReadLicenseDataAsync(ct);
        if (licenseData is null)
            return new ApiResponse { Status = false, Message = "No license file found." };

        var payload = new { product_id = productId, license_data = licenseData };

        var response = await _http.PostAsJsonAsync(
            "/api/external/license/deactivate", payload, JsonOptions, ct);

        var result = await ParseResponse(response, ct);

        if (result.IsActive && File.Exists(_licenseFilePath))
        {
            File.Delete(_licenseFilePath);
        }

        return result;
    }

    // ── Update Operations ───────────────────────────────────────────────

    public async Task<UpdateCheckResponse> CheckForUpdateAsync(
        string productId,
        string currentVersion,
        CancellationToken ct = default)
    {
        var payload = new { product_id = productId, current_version = currentVersion };

        var response = await _http.PostAsJsonAsync(
            "/api/external/update/check", payload, JsonOptions, ct);

        return await ParseResponse<UpdateCheckResponse>(response, ct);
    }

    public async Task<LatestVersionResponse> GetLatestVersionAsync(
        string productId,
        CancellationToken ct = default)
    {
        var payload = new { product_id = productId };

        var response = await _http.PostAsJsonAsync(
            "/api/external/update/latest", payload, JsonOptions, ct);

        return await ParseResponse<LatestVersionResponse>(response, ct);
    }

    public async Task<string> DownloadUpdateAsync(
        string updateId,
        string outputPath,
        string type = "main",
        CancellationToken ct = default)
    {
        var licenseData = await ReadLicenseDataAsync(ct);
        var payload = licenseData is not null
            ? new { license_data = licenseData }
            : null;

        var url = $"/api/external/update/{Uri.EscapeDataString(updateId)}/download/{Uri.EscapeDataString(type)}";

        var request = new HttpRequestMessage(HttpMethod.Post, url);
        if (payload is not null)
        {
            request.Content = JsonContent.Create(payload, options: JsonOptions);
        }

        var response = await _http.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, ct);
        response.EnsureSuccessStatusCode();

        var extension = type == "sql" ? "sql" : "zip";
        var filePath = Path.Combine(outputPath, $"update_{updateId}.{extension}");

        await using var stream = await response.Content.ReadAsStreamAsync(ct);
        await using var file = File.Create(filePath);
        await stream.CopyToAsync(file, ct);

        return filePath;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public bool HasLicenseFile() => File.Exists(_licenseFilePath);

    private async Task<string?> ReadLicenseDataAsync(CancellationToken ct)
    {
        if (!File.Exists(_licenseFilePath)) return null;
        return await File.ReadAllTextAsync(_licenseFilePath, ct);
    }

    private static async Task<ApiResponse> ParseResponse(
        HttpResponseMessage response, CancellationToken ct)
    {
        return await ParseResponse<ApiResponse>(response, ct);
    }

    private static async Task<T> ParseResponse<T>(
        HttpResponseMessage response, CancellationToken ct) where T : ApiResponse, new()
    {
        try
        {
            var result = await response.Content.ReadFromJsonAsync<T>(JsonOptions, ct);
            return result ?? new T { Status = false, Message = "Empty response from server." };
        }
        catch (JsonException)
        {
            var body = await response.Content.ReadAsStringAsync(ct);
            return new T
            {
                Status = false,
                Message = $"Invalid JSON response (HTTP {(int)response.StatusCode}): {body[..Math.Min(body.Length, 200)]}",
            };
        }
    }

    public void Dispose() => _http.Dispose();
}

// ── Configuration ───────────────────────────────────────────────────────

public class LicenseManagerOptions
{
    /// <summary>License Manager server URL (e.g. https://license.example.com).</summary>
    public string ServerUrl { get; set; } = "";

    /// <summary>External API key from the License Manager admin panel.</summary>
    public string ApiKey { get; set; } = "";

    /// <summary>Your application's URL sent in the X-API-URL header.</summary>
    public string ApplicationUrl { get; set; } = "";

    /// <summary>IP address sent in the X-API-IP header.</summary>
    public string? IpAddress { get; set; }

    /// <summary>Locale sent in the X-API-LANGUAGE header (default: "en").</summary>
    public string? Language { get; set; }

    /// <summary>Path to store the encrypted license data file.</summary>
    public string? LicenseFilePath { get; set; }
}

// ── Response Models ─────────────────────────────────────────────────────

public class ApiResponse
{
    [JsonPropertyName("status")]
    public bool Status { get; set; }

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; }

    [JsonPropertyName("message")]
    public string? Message { get; set; }
}

public class ActivationResponse : ApiResponse
{
    [JsonPropertyName("lic_response")]
    public string? LicResponse { get; set; }

    [JsonPropertyName("data")]
    public ActivationData? Data { get; set; }
}

public class ActivationData
{
    [JsonPropertyName("license_data")]
    public string? LicenseData { get; set; }
}

public class UpdateCheckResponse : ApiResponse
{
    [JsonPropertyName("update_available")]
    public bool UpdateAvailable { get; set; }

    [JsonPropertyName("version")]
    public string? Version { get; set; }

    [JsonPropertyName("release_date")]
    public string? ReleaseDate { get; set; }

    [JsonPropertyName("summary")]
    public string? Summary { get; set; }

    [JsonPropertyName("changelog")]
    public string? Changelog { get; set; }

    [JsonPropertyName("update_id")]
    public string? UpdateId { get; set; }

    [JsonPropertyName("has_sql")]
    public bool HasSql { get; set; }
}

public class LatestVersionResponse : ApiResponse
{
    [JsonPropertyName("data")]
    public LatestVersionData? Data { get; set; }
}

public class LatestVersionData
{
    [JsonPropertyName("version")]
    public string? Version { get; set; }

    [JsonPropertyName("released_at")]
    public string? ReleasedAt { get; set; }

    [JsonPropertyName("summary")]
    public string? Summary { get; set; }

    [JsonPropertyName("changelog")]
    public string? Changelog { get; set; }

    [JsonPropertyName("update_id")]
    public string? UpdateId { get; set; }
}
