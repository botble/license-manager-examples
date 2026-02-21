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

// Check connection to the license server
app.MapGet("/license/connection", async (LicenseManagerClient client) =>
{
    var result = await client.CheckConnectionAsync();
    return Results.Ok(result);
});

// Activate a license
app.MapPost("/license/activate", async (ActivateLicenseRequest req, LicenseManagerClient client) =>
{
    var result = await client.ActivateLicenseAsync(
        req.ProductId, req.LicenseCode, req.ClientName);

    return result.IsActive ? Results.Ok(result) : Results.BadRequest(result);
});

// Verify the current license
app.MapGet("/license/verify/{productId}", async (string productId, LicenseManagerClient client) =>
{
    var result = await client.VerifyLicenseAsync(productId);
    return result.IsActive ? Results.Ok(result) : Results.BadRequest(result);
});

// Deactivate the current license
app.MapPost("/license/deactivate/{productId}", async (string productId, LicenseManagerClient client) =>
{
    var result = await client.DeactivateLicenseAsync(productId);
    return Results.Ok(result);
});

// Check for product updates
app.MapPost("/license/update-check", async (UpdateCheckRequest req, LicenseManagerClient client) =>
{
    var result = await client.CheckForUpdateAsync(req.ProductId, req.CurrentVersion);
    return Results.Ok(result);
});

// Get latest version info
app.MapGet("/license/latest/{productId}", async (string productId, LicenseManagerClient client) =>
{
    var result = await client.GetLatestVersionAsync(productId);
    return Results.Ok(result);
});

// Download an update file
app.MapPost("/license/update-download", async (UpdateDownloadRequest req, LicenseManagerClient client) =>
{
    try
    {
        var outputDir = Path.Combine(Directory.GetCurrentDirectory(), "updates");
        Directory.CreateDirectory(outputDir);

        var filePath = await client.DownloadUpdateAsync(
            req.UpdateId, outputDir, req.Type ?? "main");

        return Results.Ok(new { success = true, path = filePath });
    }
    catch (HttpRequestException ex)
    {
        return Results.BadRequest(new { success = false, message = ex.Message });
    }
});

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
