<?php

namespace Tka\CookieConsent;

class Plugin
{
    private static $instance = null;
    private $admin_settings = null;

    /**
     * Singleton instance access.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->admin_settings = new AdminSettings();
    }

    /**
     * Run actions and hooks.
     */
    public function run()
    {
        // Initialize Licensing
        \Tka\CookieConsent\Licensing\Licensing::init();

        // Register Admin Settings page
        if (is_admin()) {
            $this->admin_settings->register();
            add_action('wp_ajax_tka_scan_scripts', [$this, 'ajax_scan_scripts']);
        }

        // If license is not active, do not load other features
        if (!\Tka\CookieConsent\Licensing\Licensing::isActive()) {
            return;
        }

        // Script tag interceptor to block cookies before consent
        add_filter('script_loader_tag', [$this, 'block_scripts'], 999, 3);

        // Enqueue frontend scripts & styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Render banner HTML in wp_footer
        add_action('wp_footer', [$this, 'render_consent_banner']);

        // Inject custom script blocks into header
        add_action('wp_head', [$this, 'inject_custom_scripts']);
    }

    /**
     * Enqueue CSS and JavaScript assets on the frontend.
     */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'tka-cookie-consent-banner',
            TKA_COOKIE_CONSENT_URL . 'assets/css/banner.css',
            [],
            TKA_COOKIE_CONSENT_VERSION
        );

        wp_enqueue_script(
            'tka-cookie-consent-banner',
            TKA_COOKIE_CONSENT_URL . 'assets/js/banner.js',
            [],
            TKA_COOKIE_CONSENT_VERSION,
            true // Load in footer
        );

        // Localize options for Javascript
        $settings = get_option('tka_cookie_consent_options', []);
        wp_localize_script('tka-cookie-consent-banner', 'tkaConsentSettings', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'categories' => ['functional', 'statistics', 'marketing', 'preferences'],
            'labels'     => [
                'back' => __('Back', 'tka-cookie-consent'),
            ]
        ]);
    }

    /**
     * Rewrite script tags for blocked scripts until consent is given.
     */
    public function block_scripts($tag, $handle, $src)
    {
        if (is_admin()) {
            return $tag;
        }

        // Define mapping of category to setting key
        $categories = [
            'statistics'  => 'blocked_statistics',
            'marketing'   => 'blocked_marketing',
            'preferences' => 'blocked_preferences',
        ];

        $matched_category = null;

        foreach ($categories as $category => $option_key) {
            $blocked_input = $this->admin_settings->get_option($option_key, '');
            if (empty($blocked_input)) {
                continue;
            }

            $signatures = array_map('trim', explode(',', $blocked_input));
            foreach ($signatures as $sig) {
                if (empty($sig)) {
                    continue;
                }
                if (stripos($src, $sig) !== false || stripos($handle, $sig) !== false) {
                    $matched_category = $category;
                    break 2; // Break both loops
                }
            }
        }

        if ($matched_category !== null) {
            // Check if consent has already been granted (server-side check)
            if (function_exists('wp_has_consent') && wp_has_consent($matched_category)) {
                return $tag;
            }

            // Replace src with data-src
            if (preg_match('/src=["\']([^"\']+)["\']/', $tag, $matches)) {
                $tag = str_replace($matches[0], 'data-src="' . esc_url($matches[1]) . '"', $tag);
            }

            // Remove any existing type attribute
            $tag = preg_replace('/type=["\']([^"\']+)["\']/', '', $tag);

            // Set type to text/plain and inject the data attribute
            $tag = preg_replace('/<script\b/', '<script type="text/plain" data-consent-category="' . esc_attr($matched_category) . '"', $tag);
        }

        return $tag;
    }

    /**
     * Render the Cookie Consent banner in the footer.
     */
    public function render_consent_banner()
    {
        if (is_admin()) {
            return;
        }

        $title = __($this->admin_settings->get_option('banner_title', 'We value your privacy'), 'tka-cookie-consent');
        $text  = __($this->admin_settings->get_option('banner_text', 'We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.'), 'tka-cookie-consent');
        $btn_accept = __($this->admin_settings->get_option('btn_accept', 'Accept All'), 'tka-cookie-consent');
        $btn_reject = __($this->admin_settings->get_option('btn_reject', 'Reject All'), 'tka-cookie-consent');
        $btn_customize = __($this->admin_settings->get_option('btn_customize', 'Customize'), 'tka-cookie-consent');
        $policy_url = get_privacy_policy_url();
        
        ?>
        <div id="tka-cookie-banner" class="tka-cookie-banner tka-hidden" role="dialog" aria-modal="true" aria-labelledby="tka-cb-title">
            <div class="tka-cb-container">
                <div class="tka-cb-content">
                    <h3 id="tka-cb-title"><?php echo esc_html($title); ?></h3>
                    <p class="tka-cb-text">
                        <?php echo wp_kses_post($text); ?>
                        <?php if ($policy_url) : ?>
                            <a href="<?php echo esc_url($policy_url); ?>" class="tka-cb-policy-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read our Privacy Policy', 'tka-cookie-consent'); ?></a>
                        <?php endif; ?>
                    </p>

                    <!-- Collapsible Preferences Checklist -->
                    <div id="tka-cb-preferences" class="tka-cb-preferences tka-hidden">
                        <div class="tka-cb-preference-item">
                            <label>
                                <input type="checkbox" id="tka-consent-functional" checked disabled />
                                <strong><?php esc_html_e('Strictly Necessary', 'tka-cookie-consent'); ?></strong>
                                <span class="tka-cb-preference-desc"><?php esc_html_e('Required for the site to function properly.', 'tka-cookie-consent'); ?></span>
                            </label>
                        </div>
                        <div class="tka-cb-preference-item">
                            <label>
                                <input type="checkbox" id="tka-consent-preferences" />
                                <strong><?php esc_html_e('Preferences', 'tka-cookie-consent'); ?></strong>
                                <span class="tka-cb-preference-desc"><?php esc_html_e('Remember your preferences and settings.', 'tka-cookie-consent'); ?></span>
                            </label>
                        </div>
                        <div class="tka-cb-preference-item">
                            <label>
                                <input type="checkbox" id="tka-consent-statistics" />
                                <strong><?php esc_html_e('Statistics', 'tka-cookie-consent'); ?></strong>
                                <span class="tka-cb-preference-desc"><?php esc_html_e('Help us understand how you interact with our site.', 'tka-cookie-consent'); ?></span>
                            </label>
                        </div>
                        <div class="tka-cb-preference-item">
                            <label>
                                <input type="checkbox" id="tka-consent-marketing" />
                                <strong><?php esc_html_e('Marketing', 'tka-cookie-consent'); ?></strong>
                                <span class="tka-cb-preference-desc"><?php esc_html_e('Used to deliver personalized ads and content.', 'tka-cookie-consent'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="tka-cb-actions">
                    <button type="button" id="tka-cb-btn-accept" class="tka-cb-btn tka-cb-btn-primary"><?php echo esc_html($btn_accept); ?></button>
                    <?php if (!empty($btn_reject)) : ?>
                        <button type="button" id="tka-cb-btn-reject" class="tka-cb-btn tka-cb-btn-secondary"><?php echo esc_html($btn_reject); ?></button>
                    <?php endif; ?>
                    <button type="button" id="tka-cb-btn-customize" class="tka-cb-btn tka-cb-btn-link"><?php echo esc_html($btn_customize); ?></button>
                    <button type="button" id="tka-cb-btn-save" class="tka-cb-btn tka-cb-btn-primary tka-hidden"><?php esc_html_e('Save Preferences', 'tka-cookie-consent'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to scan frontend scripts and suggest blocking rules.
     */
    public function ajax_scan_scripts()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'tka-cookie-consent')], 403);
        }

        check_ajax_referer('tka_scan_scripts_nonce', 'nonce');

        // Fetch homepage HTML (disable SSL verification to support self-signed certs in local environments)
        $response = wp_remote_get(home_url('/'), [
            'sslverify' => false,
        ]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            wp_send_json_error(['message' => __('Empty response from site homepage.', 'tka-cookie-consent')], 500);
        }

        $scripts = [];

        // Safely parse HTML with DOMDocument
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $script_elements = $dom->getElementsByTagName('script');

        $blocked_stats_input = $this->admin_settings->get_option('blocked_statistics', '');
        $blocked_mktg_input = $this->admin_settings->get_option('blocked_marketing', '');
        $blocked_pref_input = $this->admin_settings->get_option('blocked_preferences', '');

        $blocked_stats = array_filter(array_map('trim', explode(',', $blocked_stats_input)));
        $blocked_mktg = array_filter(array_map('trim', explode(',', $blocked_mktg_input)));
        $blocked_pref = array_filter(array_map('trim', explode(',', $blocked_pref_input)));

        $signatures = [
            'statistics'  => ['google-analytics.com', 'gstatic.com', 'googletagmanager.com', 'gtag', 'analytics.js'],
            'marketing'   => ['connect.facebook.net', 'fbevents.js', 'doubleclick.net', 'ads-twitter.com', 'adnxs.com', 'linkedin.com/px'],
            'preferences' => [],
        ];

        foreach ($script_elements as $element) {
            $src = $element->getAttribute('src');
            $inline = $element->nodeValue;

            if (empty($src) && empty(trim($inline))) {
                continue;
            }

            if ($src && (strpos($src, 'tka-cookie-consent') !== false || strpos($src, 'wp-includes/js/admin-bar') !== false)) {
                continue;
            }

            $content = $src ? $src : $inline;
            $type = $src ? 'external' : 'inline';
            $display_name = $src ? basename(parse_url($src, PHP_URL_PATH)) : __('Inline Script', 'tka-cookie-consent');

            $suggested_category = 'uncategorized';
            foreach ($signatures as $category => $sigs) {
                foreach ($sigs as $sig) {
                    if (strpos($content, $sig) !== false) {
                        $suggested_category = $category;
                        break 2;
                    }
                }
            }

            $is_blocked = false;
            $current_sigs = array_merge($blocked_stats, $blocked_mktg, $blocked_pref);
            foreach ($current_sigs as $sig) {
                if (strpos($content, $sig) !== false) {
                    $is_blocked = true;
                    break;
                }
            }

            $preview = '';
            if (!$src) {
                $lines = array_filter(array_map('trim', explode("\n", $inline)));
                $preview = implode("\n", array_slice($lines, 0, 3));
                if (count($lines) > 3) {
                    $preview .= "\n...";
                }
            }

            $scripts[] = [
                'type'               => $type,
                'src'                => $src,
                'name'               => $display_name,
                'preview'            => $preview,
                'suggested_category' => $suggested_category,
                'is_blocked'         => $is_blocked,
                'full_content'       => $content,
            ];
        }

        wp_send_json_success(['scripts' => $scripts]);
    }

    /**
     * Inject custom scripts from settings into the site head.
     */
    public function inject_custom_scripts()
    {
        if (is_admin()) {
            return;
        }

        $categories = ['statistics', 'marketing', 'preferences'];

        foreach ($categories as $cat) {
            $script_block = $this->admin_settings->get_option('scripts_' . $cat, '');
            if (empty($script_block)) {
                continue;
            }

            // Check if consent has already been granted (server-side check)
            if (function_exists('wp_has_consent') && wp_has_consent($cat)) {
                echo $script_block . "\n";
            } else {
                // Not consented yet: rewrite script tags to type="text/plain" and data-src
                echo $this->block_html_scripts($script_block, $cat) . "\n";
            }
        }
    }

    /**
     * Helper to rewrite raw script tags to block them client-side.
     */
    private function block_html_scripts($html, $category)
    {
        if (empty($html)) {
            return '';
        }

        // Convert <script ... src="..."> to <script ... type="text/plain" data-consent-category="..." data-src="...">
        return preg_replace_callback('/<script\b([^>]*)>/i', function($matches) use ($category) {
            $attrs = $matches[1];

            // If it has src="..." or src='...', replace with data-src
            if (preg_match('/src=["\']([^"\']+)["\']/', $attrs, $src_match)) {
                $attrs = str_replace($src_match[0], 'data-src="' . esc_url($src_match[1]) . '"', $attrs);
            }

            // Remove any existing type attribute
            $attrs = preg_replace('/type=["\']([^"\']+)["\']/', '', $attrs);

            // Set type to text/plain and inject the data attribute
            return '<script type="text/plain" data-consent-category="' . esc_attr($category) . '"' . $attrs . '>';
        }, $html);
    }
}
