# frozen_string_literal: true

# Rack middleware that verifies the license on every request.
# Caches the result for a configurable TTL to avoid hammering the license server.
#
# Usage in config/application.rb:
#   require_relative "../lib/license_middleware"
#   config.middleware.use LicenseMiddleware,
#     product_id: ENV.fetch("LM_PRODUCT_ID"),
#     cache_ttl: 300
#
class LicenseMiddleware
  def initialize(app, product_id:, cache_ttl: 300)
    @app = app
    @product_id = product_id
    @cache_ttl = cache_ttl
    @cached_result = nil
    @cached_at = nil
  end

  def call(env)
    unless license_valid?
      body = JSON.generate({ error: "License is not active. Please activate your license." })
      return [403, { "Content-Type" => "application/json" }, [body]]
    end

    @app.call(env)
  end

  private

  def license_valid?
    if @cached_result && @cached_at && (Time.now - @cached_at < @cache_ttl)
      return @cached_result
    end

    result = license_client.verify_license(@product_id)
    @cached_result = result["is_active"] == true
    @cached_at = Time.now
    @cached_result
  rescue StandardError => e
    Rails.logger.error("License verification failed: #{e.message}") if defined?(Rails)
    # Allow access on network errors to avoid blocking users
    true
  end

  def license_client
    @license_client ||= LicenseManagerClient.new(
      server_url: ENV.fetch("LM_SERVER_URL"),
      api_key: ENV.fetch("LM_API_KEY"),
      application_url: ENV.fetch("LM_APP_URL")
    )
  end
end
