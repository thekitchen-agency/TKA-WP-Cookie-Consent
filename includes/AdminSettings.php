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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
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
        </div>
        <?php
    }
}
