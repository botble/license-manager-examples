# License Manager - Java Example

Integration example using Java's built-in `HttpClient` (Java 11+) and Jackson for JSON.

## Structure

```
java/
├── pom.xml
├── src/main/java/com/licensemanager/
│   ├── LicenseManagerClient.java   # Reusable HTTP client
│   └── SampleApp.java              # Interactive CLI demo
└── README.md
```

## Requirements

- Java 17+ (uses switch expressions and text blocks)
- Maven 3.8+

## Quick Start

```bash
# Edit SampleApp.java with your credentials, then:
mvn compile exec:java
```

## Using the Client Library

```java
import com.licensemanager.LicenseManagerClient;
import com.licensemanager.LicenseManagerClient.Config;

var config = new Config(
    "https://your-license-server.com",
    "your-api-key",
    "https://your-app.com"
);

try (var client = new LicenseManagerClient(config)) {
    // Activate
    var result = client.activateLicense("PRODUCT_ID", "LICENSE-CODE", "Client Name");
    if (result.isActive) System.out.println("Licensed!");

    // Verify (uses saved license file)
    var verify = client.verifyLicense("PRODUCT_ID");

    // Check for updates
    var update = client.checkForUpdate("PRODUCT_ID", "1.0.0");
    if (update.updateAvailable)
        System.out.println("New version: " + update.version);
}
```

## Integration Tips

- **Spring Boot**: Register `LicenseManagerClient` as a `@Bean` and inject via constructor
- **Android**: Use on a background thread (network calls block the calling thread)
- **Desktop (Swing/JavaFX)**: Call from a background thread, update UI on the event dispatch thread

## License

MIT
