# frozen_string_literal: true

# Example Rails controller for license management.
#
# Setup in config/routes.rb:
#   resource :license, only: [] do
#     get  :status,       on: :collection
#     post :activate,     on: :collection
#     post :deactivate,   on: :collection
#     post :verify,       on: :collection
#     post :check_update, on: :collection
#     post :download_update, on: :collection
#   end
#
# Configure the client in config/initializers/license_manager.rb:
#   require_relative "../../lib/license_manager_client"
#   LICENSE_CLIENT = LicenseManagerClient.new(
#     server_url:      ENV.fetch("LM_SERVER_URL"),
#     api_key:         ENV.fetch("LM_API_KEY"),
#     application_url: ENV.fetch("LM_APP_URL")
#   )
#
class LicensesController < ApplicationController
  # GET /license/status
  def status
    result = license_client.check_connection
    render json: result
  end

  # POST /license/activate
  # Params: product_id, license_code, client_name
  def activate
    result = license_client.activate_license(
      params.require(:product_id),
      params.require(:license_code),
      params.require(:client_name)
    )

    if result["is_active"]
      render json: { status: "activated", message: result["message"] }
    else
      render json: { status: "failed", message: result["message"] }, status: :unprocessable_entity
    end
  end

  # POST /license/verify
  # Params: product_id
  def verify
    result = license_client.verify_license(params.require(:product_id))

    if result["is_active"]
      render json: { status: "valid", message: result["message"] }
    else
      render json: { status: "invalid", message: result["message"] }, status: :forbidden
    end
  end

  # POST /license/deactivate
  # Params: product_id
  def deactivate
    result = license_client.deactivate_license(params.require(:product_id))
    render json: { status: "deactivated", message: result["message"] }
  end

  # POST /license/check_update
  # Params: product_id, current_version
  def check_update
    result = license_client.check_for_update(
      params.require(:product_id),
      params.require(:current_version)
    )
    render json: result
  end

  # POST /license/download_update
  # Params: update_id, type (main/sql)
  def download_update
    output_dir = Rails.root.join("tmp", "updates").to_s
    FileUtils.mkdir_p(output_dir)

    file_path = license_client.download_update(
      params.require(:update_id),
      output_dir,
      params.fetch(:type, "main")
    )

    render json: { success: true, path: file_path }
  rescue StandardError => e
    render json: { success: false, message: e.message }, status: :unprocessable_entity
  end

  private

  def license_client
    # Use the global client from the initializer, or create per-request
    defined?(LICENSE_CLIENT) ? LICENSE_CLIENT : build_client
  end

  def build_client
    LicenseManagerClient.new(
      server_url: ENV.fetch("LM_SERVER_URL"),
      api_key: ENV.fetch("LM_API_KEY"),
      application_url: ENV.fetch("LM_APP_URL")
    )
  end
end
