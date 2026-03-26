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
  NETWORK_ERRORS = [
    Net::OpenTimeout, Net::ReadTimeout, Net::HTTPError,
    Errno::ECONNREFUSED, Errno::ECONNRESET, Errno::ETIMEDOUT,
    SocketError, OpenSSL::SSL::SSLError, JSON::ParserError
  ].freeze

  CACHE_KEY = "license_middleware:valid"

  def initialize(app, product_id:, cache_ttl: 300)
    @app = app
    @product_id = product_id
    @cache_ttl = cache_ttl
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
    cached = Rails.cache.read(CACHE_KEY)
    return cached unless cached.nil?

    result = license_client.verify_license(@product_id)
    valid = result["is_active"] == true
    Rails.cache.write(CACHE_KEY, valid, expires_in: @cache_ttl)
    valid
  rescue *NETWORK_ERRORS => e
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
