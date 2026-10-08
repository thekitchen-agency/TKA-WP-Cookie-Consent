<?php

namespace Tka\CookieConsent;

class AdminSettings
{
    private $option_name = 'tka_cookie_consent_options';

    /**
     * Initialize Settings hooks.
     */
    public function register()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Add Settings page to menu.
     */
    public function add_settings_page()
    {
        add_options_page(
            __('Cookie Consent Settings', 'tka-cookie-consent'),
            __('Cookie Consent', 'tka-cookie-consent'),
            'manage_options',
            'tka-cookie-consent',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Get saved option or default value.
     */
    public function get_option($key, $default = '')
    {
        $options = get_option($this->option_name, []);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings()
    {
        register_setting($this->option_name, $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);

        // General settings section
        add_settings_section(
            'tka_cookie_consent_general',
            __('General Banner Settings', 'tka-cookie-consent'),
            null,
            'tka-cookie-consent'
        );

        add_settings_field(
            'banner_title',
            __('Banner Title', 'tka-cookie-consent'),
            [$this, 'render_text_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_general',
            ['key' => 'banner_title', 'default' => __('We value your privacy', 'tka-cookie-consent')]
        );

        add_settings_field(
            'banner_text',
            __('Banner Text', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_general',
            [
                'key' => 'banner_text',
                'default' => __('We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'tka-cookie-consent')
            ]
        );

        add_settings_field(
            'btn_accept',
            __('Accept Button Label', 'tka-cookie-consent'),
            [$this, 'render_text_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_general',
            ['key' => 'btn_accept', 'default' => __('Accept All', 'tka-cookie-consent')]
        );

        add_settings_field(
            'btn_reject',
            __('Reject Button Label', 'tka-cookie-consent'),
            [$this, 'render_text_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_general',
            ['key' => 'btn_reject', 'default' => __('Reject All', 'tka-cookie-consent')]
        );

        add_settings_field(
            'btn_customize',
            __('Customize Button Label', 'tka-cookie-consent'),
            [$this, 'render_text_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_general',
            ['key' => 'btn_customize', 'default' => __('Customize', 'tka-cookie-consent')]
        );

        // Advanced / Cookie blocking settings section
        add_settings_section(
            'tka_cookie_consent_blocking',
            __('Script Blocking Settings', 'tka-cookie-consent'),
            null,
            'tka-cookie-consent'
        );

        add_settings_field(
            'blocked_statistics',
            __('Statistics Scripts', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_blocking',
            [
                'key' => 'blocked_statistics',
                'default' => 'google-analytics.com, gstatic.com, googletagmanager.com',
                'description' => __('Enter a comma-separated list of script handles or URL keywords (e.g., google-analytics.com, gtag) to block until Statistics consent is granted.', 'tka-cookie-consent')
            ]
        );

        add_settings_field(
            'blocked_marketing',
            __('Marketing Scripts', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_blocking',
            [
                'key' => 'blocked_marketing',
                'default' => 'connect.facebook.net, fbevents.js, doubleclick.net, ads-twitter.com',
                'description' => __('Enter a comma-separated list of script handles or URL keywords to block until Marketing consent is granted.', 'tka-cookie-consent')
            ]
        );

        add_settings_field(
            'blocked_preferences',
            __('Preferences Scripts', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_blocking',
            [
                'key' => 'blocked_preferences',
                'default' => '',
                'description' => __('Enter a comma-separated list of script handles or URL keywords to block until Preferences consent is granted.', 'tka-cookie-consent')
            ]
        );

        // Custom Script Injection Blocks section
        add_settings_section(
            'tka_cookie_consent_scripts',
            __('Custom Script Injection Blocks', 'tka-cookie-consent'),
            null,
            'tka-cookie-consent'
        );

        add_settings_field(
            'scripts_statistics',
            __('Statistics Scripts Code', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_scripts',
            [
                'key' => 'scripts_statistics',
                'default' => '',
                'description' => __('Pasted script tags (including `<script>`) to run when Statistics consent is granted (e.g. Google Analytics or Hotjar).', 'tka-cookie-consent')
            ]
        );

        add_settings_field(
            'scripts_marketing',
            __('Marketing Scripts Code', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_scripts',
            [
                'key' => 'scripts_marketing',
                'default' => '',
                'description' => __('Pasted script tags (including `<script>`) to run when Marketing consent is granted (e.g. Facebook Pixel).', 'tka-cookie-consent')
            ]
        );

        add_settings_field(
            'scripts_preferences',
            __('Preferences Scripts Code', 'tka-cookie-consent'),
            [$this, 'render_textarea_field'],
            'tka-cookie-consent',
            'tka_cookie_consent_scripts',
            [
                'key' => 'scripts_preferences',
                'default' => '',
                'description' => __('Pasted script tags (including `<script>`) to run when Preferences consent is granted.', 'tka-cookie-consent')
            ]
        );
    }

    /**
     * Sanitize submitted options.
     */
    public function sanitize_options($input)
    {
        $sanitized = [];
        if (isset($input['banner_title'])) {
            $sanitized['banner_title'] = sanitize_text_field($input['banner_title']);
        }
        if (isset($input['banner_text'])) {
            $sanitized['banner_text'] = wp_kses_post($input['banner_text']);
        }
        if (isset($input['btn_accept'])) {
            $sanitized['btn_accept'] = sanitize_text_field($input['btn_accept']);
        }
        if (isset($input['btn_reject'])) {
            $sanitized['btn_reject'] = sanitize_text_field($input['btn_reject']);
        }
        if (isset($input['btn_customize'])) {
            $sanitized['btn_customize'] = sanitize_text_field($input['btn_customize']);
        }
        if (isset($input['blocked_statistics'])) {
            $sanitized['blocked_statistics'] = sanitize_textarea_field($input['blocked_statistics']);
        }
        if (isset($input['blocked_marketing'])) {
            $sanitized['blocked_marketing'] = sanitize_textarea_field($input['blocked_marketing']);
        }
        if (isset($input['blocked_preferences'])) {
            $sanitized['blocked_preferences'] = sanitize_textarea_field($input['blocked_preferences']);
        }

        if (current_user_can('unfiltered_html')) {
            $sanitized['scripts_statistics'] = $input['scripts_statistics'] ?? '';
            $sanitized['scripts_marketing'] = $input['scripts_marketing'] ?? '';
            $sanitized['scripts_preferences'] = $input['scripts_preferences'] ?? '';
        } else {
            $allowed_html = [
                'script' => [
                    'src' => true,
                    'type' => true,
                    'async' => true,
                    'defer' => true,
                    'data-src' => true,
                    'data-consent-category' => true,
                ]
            ];
            $sanitized['scripts_statistics'] = wp_kses($input['scripts_statistics'] ?? '', $allowed_html);
            $sanitized['scripts_marketing'] = wp_kses($input['scripts_marketing'] ?? '', $allowed_html);
            $sanitized['scripts_preferences'] = wp_kses($input['scripts_preferences'] ?? '', $allowed_html);
        }

        return $sanitized;
    }

    /**
     * Render a standard text input field.
     */
    public function render_text_field($args)
    {
        $value = $this->get_option($args['key'], $args['default']);
        printf(
            '<input type="text" class="regular-text" name="%s[%s]" value="%s" />',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            esc_attr($value)
        );
    }

    /**
     * Render a URL input field.
     */
    public function render_url_field($args)
    {
        $value = $this->get_option($args['key'], $args['default']);
        printf(
            '<input type="url" class="regular-text" name="%s[%s]" value="%s" />',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            esc_attr($value)
        );
    }

    /**
     * Render a textarea field.
     */
    public function render_textarea_field($args)
    {
        $value = $this->get_option($args['key'], $args['default']);
        printf(
            '<textarea class="large-text" rows="5" name="%s[%s]">%s</textarea>',
            esc_attr($this->option_name),
            esc_attr($args['key']),
            esc_textarea($value)
        );
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Enqueue CSS & JS for admin page.
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'settings_page_tka-cookie-consent') {
            return;
        }

        wp_enqueue_script(
            'tka-cookie-consent-admin',
            TKA_COOKIE_CONSENT_URL . 'assets/js/admin.js',
            ['jquery'],
            TKA_COOKIE_CONSENT_VERSION,
            true
        );

        wp_localize_script('tka-cookie-consent-admin', 'tkaConsentAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tka_scan_scripts_nonce'),
            'labels'  => [
                'type' => __('Type', 'tka-cookie-consent'),
                'script' => __('Script / Handle', 'tka-cookie-consent'),
                'suggestedCategory' => __('Suggested Category', 'tka-cookie-consent'),
                'status' => __('Status', 'tka-cookie-consent'),
                'actions' => __('Actions', 'tka-cookie-consent'),
                'blocked' => __('Blocked', 'tka-cookie-consent'),
                'allowed' => __('Not Blocked', 'tka-cookie-consent'),
                'blockAs' => __('Block as %s', 'tka-cookie-consent'),
                'alreadyBlocked' => __('Already Blocked', 'tka-cookie-consent'),
                'noScripts' => __('No scripts detected on the homepage.', 'tka-cookie-consent'),
                'scanning' => __('Scanning site homepage...', 'tka-cookie-consent'),
                'scanFailed' => __('Scan failed: %s', 'tka-cookie-consent'),
                'unknownError' => __('Unknown error', 'tka-cookie-consent'),
                'external' => __('External', 'tka-cookie-consent'),
                'inline' => __('Inline', 'tka-cookie-consent'),
                'inlineScript' => __('Inline Script', 'tka-cookie-consent'),
                'noSignature' => __('No signature detected', 'tka-cookie-consent'),
            ]
        ]);
    }

    /**
     * Render the admin settings page.
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status = get_option('tka_cookie_consent_license_status', []);
        $message = '';
        $message_type = 'updated';
        $server_url = 'https://plugins.thekitchen.agency';

        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';

        if ($request_method !== 'POST' && !empty($status['license_key']) && !\Tka\CookieConsent\Licensing\Licensing::isLocalEnvironment()) {
            $manager = new \Tka\CookieConsent\Licensing\LicenseManager($server_url, $status['license_key'], 'tka-cookie-consent');
            $result = $manager->verify();

            if (isset($result['success']) && $result['success'] && isset($result['status']) && $result['status'] === 'active') {
                $status['status'] = 'active';
                $status['last_check'] = time();
                $status['grace_active'] = false;
                update_option('tka_cookie_consent_license_status', $status);
                set_transient('tka_cookie_consent_license_check_transient', 'active', 24 * HOUR_IN_SECONDS);
            } else {
                if (isset($result['status']) && $result['status'] !== 'network_error') {
                    $status['status'] = 'suspended';
                    $status['error_message'] = $result['error'] ?? 'Unregistered domain seat.';
                    update_option('tka_cookie_consent_license_status', $status);
                    set_transient('tka_cookie_consent_license_check_transient', 'suspended', 24 * HOUR_IN_SECONDS);
                }
            }
        }

        if ($request_method === 'POST' && isset($_POST['tka_license_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tka_license_nonce'])), 'tka_license_action')) {
            $action = isset($_POST['tka_license_action_type']) ? sanitize_text_field(wp_unslash($_POST['tka_license_action_type'])) : '';
            $license_key = isset($_POST['tka_license_key']) ? sanitize_text_field(wp_unslash($_POST['tka_license_key'])) : '';

            if ($action === 'check_update') {
                $check = \Tka\CookieConsent\Licensing\Licensing::forceCheckUpdate();
                if (!empty($check['has_update'])) {
                    $message = sprintf(__('Update available! Version %s is ready to install.', 'tka-cookie-consent'), $check['new_version']);
                    $message_type = 'updated';
                } elseif (!empty($check['success'])) {
                    $message = sprintf(__('You are running the latest version (%s).', 'tka-cookie-consent'), $check['current_version'] ?? (defined('TKA_COOKIE_CONSENT_VERSION') ? TKA_COOKIE_CONSENT_VERSION : '0.0.0'));
                    $message_type = 'updated';
                } else {
                    $message = $check['error'] ?? __('Failed to check for updates.', 'tka-cookie-consent');
                    $message_type = 'error';
                }
            } elseif ($action === 'activate' && !empty($license_key)) {
                if (\Tka\CookieConsent\Licensing\Licensing::isLicenseKeyFromEnv()) {
                    $message = __('License key is managed via .env.', 'tka-cookie-consent');
                    $message_type = 'error';
                } else {
                    $manager = new \Tka\CookieConsent\Licensing\LicenseManager($server_url, $license_key, 'tka-cookie-consent');
                    $result = $manager->activate();

                    if (isset($result['success']) && $result['success']) {
                        $status = [
                            'license_key' => $license_key,
                            'status' => 'active',
                            'last_check' => time(),
                            'grace_active' => false
                        ];
                        update_option('tka_cookie_consent_license_status', $status);
                        set_transient('tka_cookie_consent_license_check_transient', 'active', 24 * HOUR_IN_SECONDS);
                        $message = __('License activated successfully.', 'tka-cookie-consent');
                        $message_type = 'updated';
                    } else {
                        $message = $result['error'] ?? __('Failed to activate license.', 'tka-cookie-consent');
                        $message_type = 'error';
                    }
                }
            } elseif ($action === 'deactivate') {
                if (\Tka\CookieConsent\Licensing\Licensing::isLicenseKeyFromEnv()) {
                    $message = __('License key is managed via .env.', 'tka-cookie-consent');
                    $message_type = 'error';
                } else {
                    if (!empty($status['license_key'])) {
                        $manager = new \Tka\CookieConsent\Licensing\LicenseManager($server_url, $status['license_key'], 'tka-cookie-consent');
                        $manager->deactivate();
                    }
                    
                    $status = [];
                    update_option('tka_cookie_consent_license_status', $status);
                    delete_transient('tka_cookie_consent_license_check_transient');
                    
                    $message = __('License deactivated locally.', 'tka-cookie-consent');
                    $message_type = 'updated';
                }
            }
        }

        $is_active = \Tka\CookieConsent\Licensing\Licensing::isActive();
        $is_local = \Tka\CookieConsent\Licensing\Licensing::isLocalEnvironment();
        $is_env = \Tka\CookieConsent\Licensing\Licensing::isLicenseKeyFromEnv();
        $is_whitelisted = \Tka\CookieConsent\Licensing\Licensing::isDomainWhitelisted();
        $current_key = \Tka\CookieConsent\Licensing\Licensing::getActiveLicenseKey();
        $error_message = $status['error_message'] ?? '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (!empty($message)): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Licensing Box -->
            <div class="card" style="max-width: 900px; margin-bottom: 20px; padding: 15px; border-left: 4px solid <?php echo ($is_active || $is_whitelisted) ? '#22c55e' : '#f59e0b'; ?>;">
                <h2><?php esc_html_e('Licensing', 'tka-cookie-consent'); ?></h2>
                <?php if ($is_local): ?>
                    <p style="color: #22c55e; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e('Local development bypass active.', 'tka-cookie-consent'); ?></p>
                    <p style="font-size: 13px; color: #646970; margin-top: 0; margin-bottom: 15px;"><?php esc_html_e('Plugin features are unlocked for local development. You can activate a valid license key below to receive updates.', 'tka-cookie-consent'); ?></p>
                <?php elseif ($is_whitelisted): ?>
                    <p style="color: #22c55e; font-weight: bold; margin-bottom: 5px;">
                        <span class="dashicons dashicons-shield-alt" style="color: #22c55e; vertical-align: middle;"></span>
                        <?php esc_html_e('Domain is Whitelisted on License Server.', 'tka-cookie-consent'); ?>
                    </p>
                <?php elseif ($is_active): ?>
                    <p style="color: #22c55e; font-weight: bold;"><?php esc_html_e('License is active and verified.', 'tka-cookie-consent'); ?></p>
                <?php elseif (!empty($status['status']) && $status['status'] === 'suspended'): ?>
                    <p style="color: #ef4444; font-weight: bold;"><?php echo esc_html($error_message ? $error_message : __('Your license is suspended or invalid.', 'tka-cookie-consent')); ?></p>
                <?php else: ?>
                    <p><?php esc_html_e('Please enter your license key to activate the plugin features.', 'tka-cookie-consent'); ?></p>
                <?php endif; ?>

                <?php if ($is_env): ?>
                    <div style="margin-bottom: 12px; font-size: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 10px; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px; color: #166534;">
                        <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Configured via .env / environment variable', 'tka-cookie-consent'); ?>
                    </div>
                <?php endif; ?>

                <?php
                $is_really_active = !empty($status['status']) && $status['status'] === 'active';
                ?>
                <form method="post" action="">
                    <?php wp_nonce_field('tka_license_action', 'tka_license_nonce'); ?>
                    <input type="text" name="tka_license_key" value="<?php echo esc_attr($current_key); ?>" class="regular-text" <?php echo ($is_env || ($is_really_active && !$is_local)) ? 'readonly' : ''; ?> />
                    <?php if (!$is_env && $is_really_active && !$is_local): ?>
                        <input type="hidden" name="tka_license_action_type" value="deactivate">
                        <button type="submit" class="button button-link-delete" style="color: #ef4444;"><?php esc_html_e('Deactivate License', 'tka-cookie-consent'); ?></button>
                    <?php elseif (!$is_env): ?>
                        <input type="hidden" name="tka_license_action_type" value="activate">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Activate License', 'tka-cookie-consent'); ?></button>
                    <?php endif; ?>
                </form>

                <?php if ($is_active || $is_whitelisted): ?>
                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
                    <form method="post" action="">
                        <?php wp_nonce_field('tka_license_action', 'tka_license_nonce'); ?>
                        <input type="hidden" name="tka_license_action_type" value="check_update">
                        <button type="submit" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Check for Updates Now', 'tka-cookie-consent'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($is_active): ?>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('tka-cookie-consent');
                submit_button();
                ?>
            </form>

            <hr style="margin: 40px 0 20px;" />
            
            <h2><?php esc_html_e('Script Scanner', 'tka-cookie-consent'); ?></h2>
            <p><?php esc_html_e('Scan the homepage of your website to discover loaded scripts and add them to the blocking lists.', 'tka-cookie-consent'); ?></p>
            
            <p>
                <button type="button" id="tka-scan-btn" class="button button-secondary"><?php esc_html_e('Scan Homepage', 'tka-cookie-consent'); ?></button>
                <span id="tka-scan-loader" class="spinner" style="float: none; margin: 0 10px; display: none;"></span>
            </p>
            
            <div id="tka-scan-results" style="margin-top: 20px; max-width: 900px;"></div>
            <?php else: ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Please activate your license to configure the Cookie Consent settings.', 'tka-cookie-consent'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
