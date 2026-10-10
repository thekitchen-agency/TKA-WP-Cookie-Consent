<?php

namespace Tka\CookieConsent\Services;

class ServiceRegistry
{
    /**
     * Get list of known services and their detection signatures.
     * Note: String literals are stored untranslated here to prevent premature
     * _load_textdomain_just_in_time calls before WordPress 'init' hook.
     *
     * @return array
     */
    public static function get_services(): array
    {
        return [
            // --- STATISTICS ---
            'google_analytics' => [
                'name'        => 'Google Analytics 4',
                'category'    => 'statistics',
                'type'        => 'script',
                'signatures'  => [
                    'google-analytics.com',
                    'googletagmanager.com/gtag/js',
                    'analytics.js',
                    'gtag(',
                ],
                'cookies'     => ['_ga', '_gid', '_gat', '_ga_'],
                'description' => 'Google Analytics performance and usage measurement.',
            ],
            'google_tag_manager' => [
                'name'        => 'Google Tag Manager',
                'category'    => 'statistics',
                'type'        => 'script',
                'signatures'  => [
                    'googletagmanager.com/gtm.js',
                ],
                'cookies'     => ['_gtm'],
                'description' => 'Tag management container by Google.',
            ],
            'hotjar' => [
                'name'        => 'Hotjar',
                'category'    => 'statistics',
                'type'        => 'script',
                'signatures'  => [
                    'static.hotjar.com',
                    'hotjar.js',
                    'hjSetting',
                ],
                'cookies'     => ['_hjSessionUser', '_hjIncludedInPageviewSample', '_hjid'],
                'description' => 'User behavior analytics and heatmaps.',
            ],
            'matomo' => [
                'name'        => 'Matomo (Piwik)',
                'category'    => 'statistics',
                'type'        => 'script',
                'signatures'  => [
                    'matomo.js',
                    'piwik.js',
                    '_paq.push',
                ],
                'cookies'     => ['_pk_id', '_pk_ses'],
                'description' => 'Privacy-friendly web analytics platform.',
            ],
            'clarity' => [
                'name'        => 'Microsoft Clarity',
                'category'    => 'statistics',
                'type'        => 'script',
                'signatures'  => [
                    'clarity.ms/tag',
                    'clarity.ms/s/',
                ],
                'cookies'     => ['_clck', '_clsk'],
                'description' => 'Microsoft Clarity user behavior and session recording.',
            ],

            // --- MARKETING ---
            'meta_pixel' => [
                'name'        => 'Meta Pixel (Facebook)',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'connect.facebook.net',
                    'fbevents.js',
                    'fbq(',
                ],
                'cookies'     => ['_fbp', '_fbc'],
                'description' => 'Meta advertising and conversion tracking pixel.',
            ],
            'google_ads' => [
                'name'        => 'Google Ads / DoubleClick',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'doubleclick.net',
                    'googleadservices.com',
                    'adsbygoogle.js',
                ],
                'cookies'     => ['IDE', 'DSID', '__gads', '__gpi'],
                'description' => 'Targeted advertising and conversion measurement.',
            ],
            'linkedin_insight' => [
                'name'        => 'LinkedIn Insight Tag',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'snap.licdn.com',
                    'linkedin.com/px',
                    '_linkedin_partner_id',
                ],
                'cookies'     => ['li_sugr', 'bcookie', 'lidc'],
                'description' => 'LinkedIn audience insights and conversion tracking.',
            ],
            'tiktok_pixel' => [
                'name'        => 'TikTok Pixel',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'analytics.tiktok.com',
                    'ttq.load',
                ],
                'cookies'     => ['_ttp'],
                'description' => 'TikTok conversion and campaign optimization.',
            ],
            'twitter_pixel' => [
                'name'        => 'X (Twitter) Pixel',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'ads-twitter.com',
                    'static.ads-twitter.com',
                    'twq(',
                ],
                'cookies'     => ['muc_ads', 'personalization_id'],
                'description' => 'X / Twitter advertising tracking pixel.',
            ],
            'pinterest_tag' => [
                'name'        => 'Pinterest Tag',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'assets.pinterest.com',
                    'pintrk(',
                ],
                'cookies'     => ['_epik', '_pinterest_ct_ua'],
                'description' => 'Pinterest campaign conversion tracking.',
            ],
            'hubspot' => [
                'name'        => 'HubSpot',
                'category'    => 'marketing',
                'type'        => 'script',
                'signatures'  => [
                    'js.hs-scripts.com',
                    'js.hsforms.net',
                    'js.hs-analytics.net',
                ],
                'cookies'     => ['hubspotutk', '__hstc', '__hssc'],
                'description' => 'HubSpot marketing automation, CRM, and forms.',
            ],
            'youtube' => [
                'name'        => 'YouTube',
                'category'    => 'marketing',
                'type'        => 'iframe',
                'signatures'  => [
                    'youtube.com/embed/',
                    'youtube-nocookie.com/embed/',
                    'youtu.be/',
                ],
                'cookies'     => ['VISITOR_INFO1_LIVE', 'YSC', 'PREF'],
                'description' => 'YouTube embedded video player.',
            ],
            'vimeo' => [
                'name'        => 'Vimeo',
                'category'    => 'marketing',
                'type'        => 'iframe',
                'signatures'  => [
                    'player.vimeo.com/video/',
                ],
                'cookies'     => ['vuid', '__cf_bm'],
                'description' => 'Vimeo embedded video player.',
            ],

            // --- PREFERENCES ---
            'google_maps' => [
                'name'        => 'Google Maps',
                'category'    => 'preferences',
                'type'        => 'iframe',
                'signatures'  => [
                    'google.com/maps',
                    'maps.google.com',
                    'maps.googleapis.com',
                ],
                'cookies'     => ['NID'],
                'description' => 'Interactive map services.',
            ],
            'soundcloud' => [
                'name'        => 'SoundCloud',
                'category'    => 'preferences',
                'type'        => 'iframe',
                'signatures'  => [
                    'w.soundcloud.com/player',
                    'soundcloud.com',
                ],
                'cookies'     => ['sc_anonymous_id'],
                'description' => 'Embedded audio streaming.',
            ],
            'spotify' => [
                'name'        => 'Spotify',
                'category'    => 'preferences',
                'type'        => 'iframe',
                'signatures'  => [
                    'open.spotify.com/embed',
                ],
                'cookies'     => ['sp_t', 'sp_landing'],
                'description' => 'Embedded music player.',
            ],
        ];
    }

    /**
     * Get compiled signatures grouped by category for script/iframe matching.
     *
     * @param array $custom_signatures Custom signatures defined by admin per category.
     * @return array
     */
    public static function get_compiled_signatures(array $custom_signatures = []): array
    {
        $services = self::get_services();
        $compiled = [
            'statistics'  => [],
            'marketing'   => [],
            'preferences' => [],
        ];

        foreach ($services as $service) {
            $cat = $service['category'];
            if (!isset($compiled[$cat])) {
                $compiled[$cat] = [];
            }
            foreach ($service['signatures'] as $sig) {
                $compiled[$cat][] = $sig;
            }
        }

        // Merge custom signatures
        foreach (['statistics', 'marketing', 'preferences'] as $cat) {
            if (!empty($custom_signatures[$cat]) && is_array($custom_signatures[$cat])) {
                $compiled[$cat] = array_unique(array_merge($compiled[$cat], $custom_signatures[$cat]));
            }
        }

        return $compiled;
    }

    /**
     * Find matching service for a URL or content string.
     *
     * @param string $content
     * @return array|null
     */
    public static function match_service(string $content): ?array
    {
        $services = self::get_services();
        foreach ($services as $key => $service) {
            foreach ($service['signatures'] as $sig) {
                if (stripos($content, $sig) !== false) {
                    $service['key'] = $key;
                    return $service;
                }
            }
        }
        return null;
    }
}
