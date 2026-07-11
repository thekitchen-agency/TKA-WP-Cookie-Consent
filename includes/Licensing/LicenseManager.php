<?php

namespace Tka\CookieConsent\Licensing;

/**
 * TKA License Manager Client
 * Handles communication with the licensing server.
 */
class LicenseManager
{
	private string $serverUrl;
	private string $licenseKey;
	private string $productRef;
	private string $domain;

	public function __construct(string $serverUrl, string $licenseKey, string $productRef)
	{
		$this->serverUrl = rtrim($serverUrl, '/');
		$this->licenseKey = trim($licenseKey);
		$this->productRef = trim($productRef);

		// Normalize domain
		$raw_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost';
		$host = wp_parse_url($raw_host, PHP_URL_HOST);
		$this->domain = $host ? $host : $raw_host;
	}

	/**
	 * Activate domain seat.
	 */
	public function activate(): array
	{
		return $this->sendRequest('/api/license/activate');
	}

	/**
	 * Check current license validity (Heartbeat).
	 */
	public function verify(): array
	{
		return $this->sendRequest('/api/license/verify');
	}

	/**
	 * Deactivate domain seat.
	 */
	public function deactivate(): array
	{
		return $this->sendRequest('/api/license/deactivate');
	}

	/**
	 * Internal cURL dispatcher.
	 */
	private function sendRequest(string $endpoint): array
	{
		$url = $this->serverUrl . $endpoint;
		$payload = [
			'license_key' => $this->licenseKey,
			'domain'      => $this->domain,
			'product_ref' => $this->productRef,
		];

		$response = wp_remote_post($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => json_encode($payload),
			'timeout' => 5,
		]);

		if (is_wp_error($response)) {
			return [
				'success' => false,
				'status'  => 'network_error',
				'error'   => 'Licensing server unreachable.',
			];
		}

		$httpCode = wp_remote_retrieve_response_code($response);
		$body     = wp_remote_retrieve_body($response);
		$data     = json_decode($body, true);

		if (!is_array($data)) {
			$data = [];
		}

		$data['http_code'] = $httpCode;
		return $data;
	}
}
