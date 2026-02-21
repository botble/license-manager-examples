using LicenseManager;

// ── Configuration ───────────────────────────────────────────────────────
// For a real desktop app, load these from appsettings.json or user settings.

var options = new LicenseManagerOptions
{
    ServerUrl = "https://your-license-server.com",
    ApiKey = "your-api-key-here",
    ApplicationUrl = "https://my-desktop-app.local",
};

const string ProductId = "ABC12345";
const string LicenseCode = "XXXX-XXXX-XXXX-XXXX";
const string ClientName = "John Doe";

using var client = new LicenseManagerClient(options);

Console.WriteLine("========================================");
Console.WriteLine("  License Manager - C# Console Example");
Console.WriteLine("========================================\n");

while (true)
{
    Console.WriteLine("1. Check Connection");
    Console.WriteLine("2. Activate License");
    Console.WriteLine("3. Verify License");
    Console.WriteLine("4. Deactivate License");
    Console.WriteLine("5. Check for Updates");
    Console.WriteLine("6. Download Update");
    Console.WriteLine("7. Exit\n");
    Console.Write("Choice: ");

    var choice = Console.ReadLine()?.Trim();

    switch (choice)
    {
        case "1":
            var conn = await client.CheckConnectionAsync();
            PrintResult("Connection", conn);
            break;

        case "2":
            var activation = await client.ActivateLicenseAsync(
                ProductId, LicenseCode, ClientName);
            PrintResult("Activation", activation);
            if (activation.IsActive)
                Console.WriteLine("  License data saved to disk.");
            break;

        case "3":
            var verify = await client.VerifyLicenseAsync(ProductId);
            PrintResult("Verification", verify);
            break;

        case "4":
            var deactivation = await client.DeactivateLicenseAsync(ProductId);
            PrintResult("Deactivation", deactivation);
            break;

        case "5":
            Console.Write("Current version: ");
            var version = Console.ReadLine()?.Trim() ?? "1.0.0";
            var update = await client.CheckForUpdateAsync(ProductId, version);
            PrintResult("Update Check", update);
            if (update.UpdateAvailable)
            {
                Console.WriteLine($"  New version: {update.Version}");
                Console.WriteLine($"  Update ID:   {update.UpdateId}");
                Console.WriteLine($"  Summary:     {update.Summary}");
            }
            break;

        case "6":
            Console.Write("Update ID (from update check): ");
            var updateId = Console.ReadLine()?.Trim() ?? "";
            Console.Write("Type (main/sql) [main]: ");
            var dlType = Console.ReadLine()?.Trim();
            if (string.IsNullOrEmpty(dlType)) dlType = "main";
            try
            {
                var filePath = await client.DownloadUpdateAsync(
                    updateId, Directory.GetCurrentDirectory(), dlType);
                Console.WriteLine($"  [OK] Downloaded: {filePath}");
            }
            catch (Exception ex)
            {
                Console.WriteLine($"  [FAILED] Download: {ex.Message}");
            }
            break;

        case "7":
            Console.WriteLine("Goodbye!");
            return;

        default:
            Console.WriteLine("Invalid choice.\n");
            continue;
    }

    Console.WriteLine();
}

static void PrintResult(string operation, ApiResponse result)
{
    var status = result.IsActive ? "OK" : "FAILED";
    Console.WriteLine($"  [{status}] {operation}: {result.Message}");
}
