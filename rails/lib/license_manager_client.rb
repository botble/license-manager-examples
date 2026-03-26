# frozen_string_literal: true

require "net/http"
require "json"
require "uri"

# HTTP client for the License Manager External API.
# Works with any Ruby app: Rails, Sinatra, CLI scripts, etc.
#
# Usage:
#   client = LicenseManagerClient.new(
#     server_url: "https://your-license-server.com",
#     api_key: "your-api-key",
#     application_url: "https://your-app.com"
#   )
#   result = client.activate_license("PRODUCT_ID", "LICENSE-CODE", "Client Name")
#
class LicenseManagerClient
  attr_reader :config

  def initialize(server_url:, api_key:, application_url:, ip_address: "127.0.0.1", language: "en", license_file_path: nil)
    @config = {
      server_url: server_url.chomp("/"),
      api_key: api_key,
      application_url: application_url,
      ip_address: ip_address,
      language: language
    }
    @license_file_path = license_file_path || File.join(Dir.pwd, ".license")
  end

  # ── Connection ──────────────────────────────────────────────────────

  def check_connection
    send_get("/api/external/connection-check")
  end

  # ── License Operations ──────────────────────────────────────────────

  def activate_license(product_id, license_code, client_name)
    result = send_post("/api/external/license/activate", {
      product_id: product_id,
      license_code: license_code,
      client_name: client_name,
      verify_type: "non_envato"
    })

    if result["is_active"]
      license_data = result.dig("lic_response") || result.dig("data", "license_data")
      if license_data
        File.open(@license_file_path, "w", 0600) { |f| f.write(license_data) }
      end
    end

    result
  end

  def verify_license(product_id)
    license_data = read_license_data
    return { "status" => false, "is_active" => false, "message" => "No license file found." } unless license_data

    send_post("/api/external/license/verify", {
      product_id: product_id,
      license_data: license_data
    })
  end

  def deactivate_license(product_id)
    license_data = read_license_data
    return { "status" => false, "is_active" => false, "message" => "No license file found." } unless license_data

    result = send_post("/api/external/license/deactivate", {
      product_id: product_id,
      license_data: license_data
    })

    File.delete(@license_file_path) if result["is_active"] && File.exist?(@license_file_path)

    result
  end

  # ── Update Operations ───────────────────────────────────────────────

  def check_for_update(product_id, current_version)
    send_post("/api/external/update/check", {
      product_id: product_id,
      current_version: current_version
    })
  end

  def get_latest_version(product_id)
    send_post("/api/external/update/latest", {
      product_id: product_id
    })
  end

  def download_update(update_id, output_dir, type = "main")
    # Sanitize inputs to prevent path traversal
    raise ArgumentError, "Invalid update_id" unless update_id.to_s.match?(/\A[a-zA-Z0-9_\-]+\z/)
    raise ArgumentError, "Invalid type" unless %w[main sql].include?(type.to_s)

    license_data = read_license_data
    body = license_data ? { license_data: license_data } : {}

    encoded_id = URI.encode_www_form_component(update_id)
    path = "/api/external/update/#{encoded_id}/download/#{type}"

    uri = URI("#{@config[:server_url]}#{path}")
    request = Net::HTTP::Post.new(uri)
    apply_headers(request)
    request.body = body.to_json

    response = Net::HTTP.start(uri.hostname, uri.port, use_ssl: uri.scheme == "https") do |http|
      http.read_timeout = 300
      http.request(request)
    end

    unless response.is_a?(Net::HTTPSuccess)
      raise "Download failed: HTTP #{response.code}"
    end

    ext = type == "sql" ? "sql" : "zip"
    file_path = File.join(output_dir, "update_#{update_id}.#{ext}")
    File.open(file_path, "wb", 0600) { |f| f.write(response.body) }

    file_path
  end

  # ── Helpers ─────────────────────────────────────────────────────────

  def license_file?
    File.exist?(@license_file_path)
  end

  private

  def read_license_data
    return nil unless File.exist?(@license_file_path)

    File.read(@license_file_path).strip
  end

  def send_get(path)
    uri = URI("#{@config[:server_url]}#{path}")
    request = Net::HTTP::Get.new(uri)
    apply_headers(request)
    execute(uri, request)
  end

  def send_post(path, payload)
    uri = URI("#{@config[:server_url]}#{path}")
    request = Net::HTTP::Post.new(uri)
    apply_headers(request)
    request.body = payload.to_json
    execute(uri, request)
  end

  def apply_headers(request)
    request["Content-Type"] = "application/json"
    request["X-API-KEY"] = @config[:api_key]
    request["X-API-URL"] = @config[:application_url]
    request["X-API-IP"] = @config[:ip_address]
    request["X-API-LANGUAGE"] = @config[:language]
  end

  def execute(uri, request)
    response = Net::HTTP.start(uri.hostname, uri.port, use_ssl: uri.scheme == "https") do |http|
      http.open_timeout = 10
      http.read_timeout = 30
      http.request(request)
    end

    unless response.is_a?(Net::HTTPSuccess)
      return { "status" => false, "is_active" => false, "message" => "HTTP error #{response.code}" }
    end

    JSON.parse(response.body)
  rescue JSON::ParserError
    { "status" => false, "is_active" => false, "message" => "Invalid JSON response (HTTP #{response&.code})" }
  end
end
