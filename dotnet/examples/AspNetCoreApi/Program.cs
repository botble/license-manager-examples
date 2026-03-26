using LicenseManager;

var builder = WebApplication.CreateBuilder(args);

// ── Register LicenseManagerClient from appsettings.json ─────────────────

builder.Services.AddSingleton(sp =>
{
    var config = sp.GetRequiredService<IConfiguration>();
    var section = config.GetSection("LicenseManager");

    return new LicenseManagerOptions
    {
        ServerUrl = section["ServerUrl"] ?? "",
        ApiKey = section["ApiKey"] ?? "",
        ApplicationUrl = section["ApplicationUrl"] ?? "",
        IpAddress = section["IpAddress"],
        Language = section["Language"],
        LicenseFilePath = section["LicenseFilePath"],
    };
});

builder.Services.AddSingleton<LicenseManagerClient>(sp =>
    new LicenseManagerClient(sp.GetRequiredService<LicenseManagerOptions>()));

var app = builder.Build();

// ── Minimal API endpoints ───────────────────────────────────────────────
// NOTE: These endpoints expose license management operations.
// In production, protect them with authentication/authorization:
//   - Add builder.Services.AddAuthentication(...) and builder.Services.AddAuthorization()
//   - Attach .RequireAuthorization() to each endpoint, or add [Authorize] if using controllers
//   - At minimum, restrict access to internal/admin roles.

// Check connection to the license server
app.MapGet("/license/connection", async (LicenseManagerClient client) =>
{
    var result = await client.CheckConnectionAsync();
    return Results.Ok(result);
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Activate a license
app.MapPost("/license/activate", async (ActivateLicenseRequest req, LicenseManagerClient client) =>
{
    var result = await client.ActivateLicenseAsync(
        req.ProductId, req.LicenseCode, req.ClientName);

    return result.IsActive ? Results.Ok(result) : Results.BadRequest(result);
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Verify the current license
app.MapGet("/license/verify/{productId}", async (string productId, LicenseManagerClient client) =>
{
    var result = await client.VerifyLicenseAsync(productId);
    return result.IsActive ? Results.Ok(result) : Results.BadRequest(result);
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Deactivate the current license
app.MapPost("/license/deactivate/{productId}", async (string productId, LicenseManagerClient client) =>
{
    var result = await client.DeactivateLicenseAsync(productId);
    return Results.Ok(result);
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Check for product updates
app.MapPost("/license/update-check", async (UpdateCheckRequest req, LicenseManagerClient client) =>
{
    var result = await client.CheckForUpdateAsync(req.ProductId, req.CurrentVersion);
    return Results.Ok(result);
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Get latest version info
app.MapGet("/license/latest/{productId}", async (string productId, LicenseManagerClient client) =>
{
    var result = await client.GetLatestVersionAsync(productId);
    return Results.Ok(result);
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Download an update file — returns only the filename, not the full server path
app.MapPost("/license/update-download", async (UpdateDownloadRequest req, LicenseManagerClient client) =>
{
    try
    {
        var outputDir = Path.Combine(Directory.GetCurrentDirectory(), "updates");
        Directory.CreateDirectory(outputDir);

        var filePath = await client.DownloadUpdateAsync(
            req.UpdateId, outputDir, req.Type ?? "main");

        // Return only the filename, not the full filesystem path, to avoid path disclosure
        var fileName = Path.GetFileName(filePath);
        return Results.Ok(new { success = true, file = fileName });
    }
    catch (ArgumentException ex)
    {
        return Results.BadRequest(new { success = false, message = ex.Message });
    }
    catch (HttpRequestException ex)
    {
        return Results.BadRequest(new { success = false, message = ex.Message });
    }
});
// .RequireAuthorization(); // Uncomment after configuring auth

// Middleware: verify license on every request (optional - see README)
// app.Use(async (context, next) =>
// {
//     var client = context.RequestServices.GetRequiredService<LicenseManagerClient>();
//     var result = await client.VerifyLicenseAsync("YOUR_PRODUCT_ID");
//     if (!result.IsActive)
//     {
//         context.Response.StatusCode = 403;
//         await context.Response.WriteAsJsonAsync(new { error = "Invalid license" });
//         return;
//     }
//     await next();
// });

app.Run();

// ── Request DTOs ────────────────────────────────────────────────────────

public record ActivateLicenseRequest(string ProductId, string LicenseCode, string ClientName);
public record UpdateCheckRequest(string ProductId, string CurrentVersion);
public record UpdateDownloadRequest(string UpdateId, string? Type);
