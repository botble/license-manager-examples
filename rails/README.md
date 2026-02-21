# License Manager - Ruby on Rails Example

Integration example using Ruby's `Net::HTTP` (zero external dependencies for the client).

## Structure

```
rails/
├── lib/
│   ├── license_manager_client.rb  # Reusable HTTP client
│   └── license_middleware.rb      # Rack middleware for license verification
├── app/controllers/
│   └── licenses_controller.rb     # Example Rails controller
└── README.md
```

## Requirements

- Ruby 3.0+
- Rails 7+ (for the controller example; the client works standalone)

## Quick Start (Standalone)

```ruby
require_relative "lib/license_manager_client"

client = LicenseManagerClient.new(
  server_url: "https://your-license-server.com",
  api_key: "your-api-key",
  application_url: "https://your-app.com"
)

# Check connection
puts client.check_connection

# Activate
result = client.activate_license("PRODUCT_ID", "LICENSE-CODE", "Client Name")
puts result["is_active"] ? "Licensed!" : result["message"]

# Verify
puts client.verify_license("PRODUCT_ID")

# Check for updates
update = client.check_for_update("PRODUCT_ID", "1.0.0")
puts "New version: #{update['version']}" if update["update_available"]
```

## Rails Integration

### 1. Initializer

Create `config/initializers/license_manager.rb`:

```ruby
require_relative "../../lib/license_manager_client"

LICENSE_CLIENT = LicenseManagerClient.new(
  server_url:      ENV.fetch("LM_SERVER_URL"),
  api_key:         ENV.fetch("LM_API_KEY"),
  application_url: ENV.fetch("LM_APP_URL")
)
```

### 2. Routes

Add to `config/routes.rb`:

```ruby
resource :license, only: [] do
  get  :status,     on: :collection
  post :activate,   on: :collection
  post :deactivate, on: :collection
  post :verify,     on: :collection
end
```

### 3. License-gating Middleware (optional)

Add to `config/application.rb`:

```ruby
require_relative "../lib/license_middleware"

config.middleware.use LicenseMiddleware,
  product_id: ENV.fetch("LM_PRODUCT_ID"),
  cache_ttl: 300  # seconds
```

The middleware verifies the license and caches the result. Returns 403 if invalid.

### 4. Environment Variables

```bash
LM_SERVER_URL=https://your-license-server.com
LM_API_KEY=your-api-key
LM_APP_URL=https://your-app.com
LM_PRODUCT_ID=ABC12345
```

## License

MIT
