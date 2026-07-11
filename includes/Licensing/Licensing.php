<?php

namespace Tka\CookieConsent\Licensing;

/**
 * TKA Systems WordPress Licensing Core
 */
class Licensing
{
	private static string $option_key = 'tka_cookie_consent_license_status';
	private static string $transient_key = 'tka_cookie_consent_license_check_transient';
	private static string $server_url = 'https://plugins.thekitchen.agency';

	public static function init(): void
	{
		add_action('admin_init', [self::class, 'run_daily_heartbeat']);
		add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_update']);
		add_filter('plugins_api', [self::class, 'plugin_info'], 20, 3);
	}

	/**
	 * Check if running on a local development environment.
	 */
	public static function isLocalEnvironment(): bool
	{
		$host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
		if (empty($host)) {
			return false;
		}

		$local_suffixes = ['.local', '.localhost', '.ddev.site', '.test', 'localhost', '127.0.0.1', '::1'];
		foreach ($local_suffixes as $suffix) {
			if ($suffix === $host || (strpos($suffix, '.') === 0 && str_ends_with($host, $suffix))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Retrieve local license status (Checking transient first to prevent remote call bottlenecks).
	 */
	public static function isActive(): bool
	{
		if (self::isLocalEnvironment()) {
			return true;
		}

		$status = get_transient(self::$transient_key);

		if ($status !== false) {
			return $status === 'active';
		}

		// Cache expired; load raw saved options
		$saved = get_option(self::$option_key, []);
		if (empty($saved['license_key']) || empty($saved['status'])) {
			return false;
		}

		// If active locally, rebuild transient to prevent blocking checks during thread execution
		if ($saved['status'] === 'active') {
			set_transient(self::$transient_key, 'active', 24 * HOUR_IN_SECONDS);
			return true;
		}

		return false;
	}

	/**
	 * Heartbeat validation cron hook.
	 */
	public static function run_daily_heartbeat(): void
	{
		if (self::isLocalEnvironment()) {
			return;
		}

		if (get_transient(self::$transient_key) !== false) {
			return; // Throttle: Check only once every 24 hours
		}

		$saved = get_option(self::$option_key, []);
		if (empty($saved['license_key'])) {
			return;
		}

		$manager = new LicenseManager(self::$server_url, $saved['license_key'], 'tka-cookie-consent');
		$result = $manager->verify();

		if (isset($result['success']) && $result['success'] && isset($result['status']) && $result['status'] === 'active') {
			// Heartbeat validated successfully
			$saved['status'] = 'active';
			$saved['last_check'] = time();
			$saved['grace_active'] = false;
			update_option(self::$option_key, $saved);

			set_transient(self::$transient_key, 'active', 24 * HOUR_IN_SECONDS);
		} else {
			// Check for temporary server offline
			if (isset($result['status']) && $result['status'] === 'network_error') {
				self::handle_grace_period($saved);
				return;
			}

			// Revoked, suspended or expired! Lock features locally
			$saved['status'] = 'suspended';
			$saved['error_message'] = $result['error'] ?? 'Unregistered domain seat.';
			update_option(self::$option_key, $saved);

			set_transient(self::$transient_key, 'suspended', 24 * HOUR_IN_SECONDS);
		}
	}

	/**
	 * Grace Period Handler for network fault tolerance.
	 */
	private static function handle_grace_period(array &$saved): void
	{
		$now = time();
		if (empty($saved['grace_started_at'])) {
			$saved['grace_started_at'] = $now;
			$saved['grace_active'] = true;
		}

		$elapsed = $now - $saved['grace_started_at'];
		$grace_threshold = 5 * DAY_IN_SECONDS; // 5-day grace threshold

		if ($elapsed < $grace_threshold) {
			// Server offline but within grace period; preserve plugin availability
			set_transient(self::$transient_key, 'active', 12 * HOUR_IN_SECONDS); // recheck sooner
			update_option(self::$option_key, $saved);
		} else {
			// Grace period expired; deactivate license features
			$saved['status'] = 'suspended';
			$saved['error_message'] = 'Licensing authentication timeout. Server unreachable.';
			update_option(self::$option_key, $saved);
			set_transient(self::$transient_key, 'suspended', 12 * HOUR_IN_SECONDS);
		}
	}

	/**
	 * Check for plugin updates.
	 */
	public static function check_update($transient)
	{
		if (empty($transient->checked)) {
			return $transient;
		}

		$saved = get_option(self::$option_key, []);
		if (empty($saved['license_key'])) {
			return $transient;
		}

		$plugin_file = 'tka-cookie-consent/tka-cookie-consent.php';
		$current_version = $transient->checked[$plugin_file] ?? '0.0.0';

		$response = wp_remote_post(self::$server_url . '/api/license/update-check', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body' => json_encode([
				'license_key' => $saved['license_key'],
				'domain'      => isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost',
				'product_ref' => 'tka-cookie-consent',
				'version'     => $current_version,
			]),
			'timeout' => 15,
		]);

		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return $transient;
		}

		$data = json_decode(wp_remote_retrieve_body($response));

		if ($data && isset($data->success) && $data->success && !empty($data->package) && version_compare($current_version, $data->new_version, '<')) {
			$obj = new \stdClass();
			$obj->slug = $data->slug;
			$obj->plugin = $plugin_file;
			$obj->new_version = $data->new_version;
			$obj->package = $data->package;

			$transient->response[$plugin_file] = $obj;
		}

		return $transient;
	}

	/**
	 * Retrieve plugin information for the update details modal.
	 */
	public static function plugin_info($res, $action, $args)
	{
		if ($action !== 'plugin_information' || $args->slug !== 'tka-cookie-consent') {
			return $res;
		}

		$saved = get_option(self::$option_key, []);
		if (empty($saved['license_key'])) {
			return $res;
		}

		$response = wp_remote_post(self::$server_url . '/api/license/update-check', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body' => json_encode([
				'license_key' => $saved['license_key'],
				'domain'      => isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost',
				'product_ref' => 'tka-cookie-consent',
				'version'     => '0.0.0',
			]),
			'timeout' => 15,
		]);

		if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
			$data = json_decode(wp_remote_retrieve_body($response));
			if ($data && isset($data->success) && $data->success) {
				$res = new \stdClass();
				$res->name = $data->name;
				$res->slug = $data->slug;
				$res->version = $data->new_version;
				$res->package = $data->package;
				$res->sections = (array) $data->sections;
				return $res;
			}
		}

		return $res;
	}
}
