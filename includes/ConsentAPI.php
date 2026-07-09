<?php

namespace Tka\CookieConsent;

class ConsentAPI
{
    /**
     * Initialize the Consent API integration.
     */
    public static function init()
    {
        // 1. Declare compatibility with the official WP Consent API plugin if it is active.
        $plugin_path = TKA_COOKIE_CONSENT_PATH . 'tka-cookie-consent.php';
        $plugin_base = plugin_basename($plugin_path);
        add_filter("wp_consent_api_registered_{$plugin_base}", '__return_true');

        // 2. Define fallbacks if the official WP Consent API plugin is not active.
        self::register_fallbacks();
    }

    /**
     * Register fallback helper functions if they do not exist.
     */
    private static function register_fallbacks()
    {
        if (!function_exists('wp_has_consent')) {
            /**
             * Check if user has consented to a category.
             *
             * @param string $category The consent category.
             * @return bool True if consent is granted, false otherwise.
             */
            function wp_has_consent($category)
            {
                // Functional consent is always granted
                if ($category === 'functional') {
                    return true;
                }

                // Check cookie name: wp_consent_{category}
                $cookie_name = 'wp_consent_' . sanitize_key($category);
                if (isset($_COOKIE[$cookie_name])) {
                    return $_COOKIE[$cookie_name] === 'allow';
                }

                return false;
            }
        }

        if (!function_exists('wp_set_consent')) {
            /**
             * Set the consent status for a category (server-side helper).
             *
             * @param string $category The consent category.
             * @param string $status   The consent status ('allow' or 'deny').
             */
            function wp_set_consent($category, $status)
            {
                $cookie_name = 'wp_consent_' . sanitize_key($category);
                $allowed_statuses = ['allow', 'deny'];

                if (!in_array($status, $allowed_statuses, true)) {
                    $status = 'deny';
                }

                // Set cookie for 1 year by default (or let theme config handle it)
                $expiry = time() + (365 * DAY_IN_SECONDS);
                
                // Set cookie. We use path '/' and secure cookie flags where appropriate.
                setcookie(
                    $cookie_name,
                    $status,
                    [
                        'expires'  => $expiry,
                        'path'     => COOKIEPATH ? COOKIEPATH : '/',
                        'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                        'secure'   => is_ssl(),
                        'httponly' => false, // Must be readable via JavaScript
                        'samesite' => 'Lax',
                    ]
                );

                $_COOKIE[$cookie_name] = $status;
            }
        }
    }
}
