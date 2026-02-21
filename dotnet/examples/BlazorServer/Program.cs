using LicenseManager;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddRazorPages();
builder.Services.AddServerSideBlazor();

// ── Register License Manager client ─────────────────────────────────────

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
    };
});

builder.Services.AddSingleton<LicenseManagerClient>(sp =>
    new LicenseManagerClient(sp.GetRequiredService<LicenseManagerOptions>()));

var app = builder.Build();

app.UseStaticFiles();
app.UseRouting();
app.MapBlazorHub();
app.MapFallbackToPage("/_Host");

app.Run();
