<?php

namespace Tka\CookieConsent\Licensing;

/**
 * TKA License Manager Client for Cookie Consent
 * Handles communication with the licensing server.
 */
class LicenseManager
{
	private string $serverUrl;
	private string $licenseKey;
	private string $productRef;
	private string $domain;

	public function __construct(string $serverUrl, string $licenseKey, string $productRef, ?string $domain = null)
	{
		$this->serverUrl  = rtrim($serverUrl, '/');
		$this->licenseKey = trim($licenseKey);
		$this->productRef = trim($productRef);

		if (!empty($domain)) {
			$this->domain = strtolower(trim($domain));
		} else {
			$this->domain = self::determineDomain();
		}
	}

	/**
	 * Determine the current site domain.
	 */
	public static function determineDomain(): string
	{
		$domain = '';
		if (function_exists('home_url')) {
			$host = wp_parse_url(home_url(), PHP_URL_HOST);
			if (!empty($host)) {
				$domain = $host;
			}
		}

		if (empty($domain) && isset($_SERVER['HTTP_HOST'])) {
			$raw_host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
			$domain = preg_replace('/:\d+$/', '', $raw_host);
		}

		if (empty($domain) && isset($_SERVER['SERVER_NAME'])) {
			$domain = sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME']));
		}

		if (empty($domain)) {
			$domain = 'localhost';
		}

		return strtolower(trim($domain));
	}

	public function getDomain(): string
	{
		return $this->domain;
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
	 * Check if domain is on whitelist.
	 */
	public function checkWhitelist(): array
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
	 * Helper to check if a response indicates a whitelisted domain.
	 */
	public static function isWhitelistedResponse(array $response): bool
	{
		if (empty($response['success'])) {
			return false;
		}

		if (isset($response['status']) && $response['status'] === 'active') {
			if (!empty($response['message']) && stripos($response['message'], 'whitelisted') !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Internal HTTP dispatcher.
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
