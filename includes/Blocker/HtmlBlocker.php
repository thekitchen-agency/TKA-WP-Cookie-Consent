<?php

namespace Tka\CookieConsent\Blocker;

use Tka\CookieConsent\Services\ServiceRegistry;

class HtmlBlocker
{
    private $enabled = true;
    private $iframe_blocking = true;
    private $signatures = [];
    private $custom_signatures = [];

    /**
     * Constructor.
     */
    public function __construct(bool $enabled = true, bool $iframe_blocking = true, array $custom_signatures = [])
    {
        $this->enabled = $enabled;
        $this->iframe_blocking = $iframe_blocking;
        $this->custom_signatures = $custom_signatures;
        $this->signatures = ServiceRegistry::get_compiled_signatures($custom_signatures);
    }

    /**
     * Start the HTML output buffer if on the frontend and enabled.
     */
    public function init()
    {
        if (!$this->enabled) {
            return;
        }

        add_action('template_redirect', [$this, 'start_buffer'], 1);
    }

    /**
     * Determine if output buffering should run on the current request.
     */
    public function should_buffer(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        if (is_feed() || is_trackback()) {
            return false;
        }

        // Avoid buffering non-GET requests
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }

        return true;
    }

    /**
     * Start output buffer callback.
     */
    public function start_buffer()
    {
        if (!$this->should_buffer()) {
            return;
        }

        ob_start([$this, 'filter_html']);
    }

    /**
     * Filter full page HTML.
     *
     * @param string $html
     * @return string
     */
    public function filter_html($html)
    {
        if (empty($html) || !is_string($html)) {
            return $html;
        }

        // 1. Intercept <script> tags
        $html = preg_replace_callback('/<script\b([^>]*)>(.*?)<\/script>/is', [$this, 'rewrite_script_tag'], $html);

        // 2. Intercept <iframe> tags if iframe blocking is active
        if ($this->iframe_blocking) {
            $html = preg_replace_callback('/<iframe\b([^>]*)>(.*?)<\/iframe>/is', [$this, 'rewrite_iframe_tag'], $html);
        }

        return $html;
    }

    /**
     * Callback for script tag rewriting.
     *
     * @param array $matches
     * @return string
     */
    public function rewrite_script_tag($matches)
    {
        $attributes = $matches[1];
        $content    = $matches[2];
        $full_tag   = $matches[0];

        // Skip ignored or already handled scripts
        if (
            stripos($attributes, 'data-tka-ignore') !== false ||
            stripos($attributes, 'data-tka-category') !== false ||
            stripos($attributes, 'data-consent-category') !== false ||
            stripos($attributes, 'data-cfasync="false"') !== false ||
            stripos($attributes, 'id="tka-consent-mode-init"') !== false
        ) {
            return $full_tag;
        }

        // Skip non-executable script types (JSON-LD, templates, etc.)
        if (preg_match('/type=["\']([^"\']+)["\']/i', $attributes, $type_match)) {
            $type = strtolower(trim($type_match[1]));
            $ignored_types = [
                'application/ld+json',
                'application/json',
                'application/manifest+json',
                'text/template',
                'text/x-template',
                'text/html',
                'importmap',
                'speculationrules',
            ];
            if (in_array($type, $ignored_types, true)) {
                return $full_tag;
            }
        }

        // Skip plugin's own banner scripts
        if (stripos($attributes, 'tka-cookie-consent') !== false) {
            return $full_tag;
        }

        // Extract src
        $src = '';
        if (preg_match('/src=["\']([^"\']+)["\']/i', $attributes, $src_match)) {
            $src = $src_match[1];
        }

        // Skip core WordPress administrative or essential frontend assets
        if ($src && (
            stripos($src, 'wp-includes/js/wp-emoji') !== false ||
            stripos($src, 'wp-includes/js/dist/') !== false
        )) {
            return $full_tag;
        }

        $check_string = $src ? $src : $content;
        $matched_category = $this->match_category($check_string);

        if (!$matched_category) {
            return $full_tag;
        }

        // If user already granted consent, keep unblocked
        if (function_exists('wp_has_consent') && wp_has_consent($matched_category)) {
            return $full_tag;
        }

        // Rewrite external script
        if ($src) {
            $attributes = preg_replace('/src=["\']([^"\']+)["\']/i', 'data-tka-src="$1"', $attributes);
        }

        // Remove any existing type attribute
        $attributes = preg_replace('/\btype=["\'][^"\']*["\']/i', '', $attributes);

        // Prepend type="text/plain" and consent data attributes
        $new_tag = sprintf(
            '<script type="text/plain" data-tka-category="%s" data-tka-script %s>%s</script>',
            esc_attr($matched_category),
            trim($attributes),
            $content
        );

        return $new_tag;
    }

    /**
     * Callback for iframe tag rewriting.
     *
     * @param array $matches
     * @return string
     */
    public function rewrite_iframe_tag($matches)
    {
        $attributes = $matches[1];
        $inner_html = $matches[2];
        $full_tag   = $matches[0];

        // Skip ignored or already handled
        if (
            stripos($attributes, 'data-tka-ignore') !== false ||
            stripos($attributes, 'data-tka-category') !== false ||
            stripos($attributes, 'data-tka-src') !== false
        ) {
            return $full_tag;
        }

        // Extract src
        if (!preg_match('/src=["\']([^"\']+)["\']/i', $attributes, $src_match)) {
            return $full_tag;
        }

        $src = $src_match[1];
        if (empty($src) || $src === 'about:blank') {
            return $full_tag;
        }

        $matched_category = $this->match_category($src);
        if (!$matched_category) {
            return $full_tag;
        }

        // Check if consent has already been granted
        if (function_exists('wp_has_consent') && wp_has_consent($matched_category)) {
            return $full_tag;
        }

        // Identify service name
        $service = ServiceRegistry::match_service($src);
        $service_name = $service ? $service['name'] : __('External Content', 'tka-cookie-consent');
        $service_key = $service ? $service['key'] : 'external';

        // Rewrite src to data-tka-src
        $rewritten_attributes = preg_replace(
            '/src=["\']([^"\']+)["\']/i',
            'src="about:blank" data-tka-src="' . esc_url($src) . '"',
            $attributes
        );

        // Preserve width/height style hints if present
        $style = '';
        if (preg_match('/style=["\']([^"\']+)["\']/i', $attributes, $style_match)) {
            $style = 'style="' . esc_attr($style_match[1]) . '"';
        }

        $category_label = ucfirst($matched_category);
        $blocked_msg = sprintf(
            /* translators: 1: Service name (e.g. YouTube), 2: Cookie category (e.g. Marketing) */
            __('%1$s is blocked until %2$s cookies are accepted.', 'tka-cookie-consent'),
            '<strong>' . esc_html($service_name) . '</strong>',
            '<strong>' . esc_html($category_label) . '</strong>'
        );

        $btn_label = sprintf(
            /* translators: %s: Service name */
            __('Allow %s & Load Content', 'tka-cookie-consent'),
            esc_html($service_name)
        );

        return sprintf(
            '<div class="tka-iframe-placeholder" data-tka-category="%s" data-tka-service="%s" %s>
                <div class="tka-iframe-placeholder-content">
                    <div class="tka-iframe-icon" aria-hidden="true">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div class="tka-iframe-text">%s</div>
                    <div class="tka-iframe-actions">
                        <button type="button" class="tka-btn tka-btn-primary tka-iframe-unlock-btn" data-category="%s" data-service="%s">%s</button>
                        <a href="#tka-cookie-settings" class="tka-cookie-settings-toggle tka-iframe-settings-link">%s</a>
                    </div>
                </div>
                <iframe %s data-tka-category="%s">%s</iframe>
            </div>',
            esc_attr($matched_category),
            esc_attr($service_key),
            $style,
            $blocked_msg,
            esc_attr($matched_category),
            esc_attr($service_key),
            esc_html($btn_label),
            esc_html__('Manage Privacy Settings', 'tka-cookie-consent'),
            trim($rewritten_attributes),
            esc_attr($matched_category),
            $inner_html
        );
    }

    /**
     * Match a string against compiled signatures to find the category.
     *
     * @param string $content
     * @return string|null
     */
    private function match_category(string $content): ?string
    {
        foreach ($this->signatures as $category => $sigs) {
            foreach ($sigs as $sig) {
                if (empty($sig)) {
                    continue;
                }
                if (stripos($content, $sig) !== false) {
                    return $category;
                }
            }
        }
        return null;
    }
}
