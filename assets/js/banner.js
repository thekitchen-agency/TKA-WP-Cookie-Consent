(function() {
    'use strict';

    // Helper to get cookie value
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    }

    // Helper to set cookie
    function setCookie(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = `; expires=${date.toUTCString()}`;
        }
        const secure = window.location.protocol === 'https:' ? '; secure' : '';
        document.cookie = `${name}=${value || ''}${expires}; path=/; SameSite=Lax${secure}`;
    }

    // Define Consent API client-side fallbacks if not already present
    if (typeof window.wp_has_consent === 'undefined') {
        window.wp_has_consent = function(category) {
            if (category === 'functional') {
                return true;
            }
            return getCookie('wp_consent_' + category) === 'allow';
        };
    }

    if (typeof window.wp_set_consent === 'undefined') {
        window.wp_set_consent = function(category, status) {
            const allowedStatus = (status === 'allow') ? 'allow' : 'deny';
            setCookie('wp_consent_' + category, allowedStatus, 365);
            
            // Dispatch standard WP Consent API event
            const eventDetail = {};
            eventDetail[category] = allowedStatus;
            
            const event = new CustomEvent('wp_listen_for_consent_change', {
                detail: eventDetail
            });
            document.dispatchEvent(event);
        };
    }

    /**
     * Dispatch Google Consent Mode v2 update and dataLayer event.
     */
    function updateGoogleConsentMode(consents) {
        const hasMarketing = consents.marketing === true;
        const hasStats     = consents.statistics === true;
        const hasPrefs     = consents.preferences === true;

        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', {
                'ad_storage': hasMarketing ? 'granted' : 'denied',
                'ad_user_data': hasMarketing ? 'granted' : 'denied',
                'ad_personalization': hasMarketing ? 'granted' : 'denied',
                'analytics_storage': hasStats ? 'granted' : 'denied',
                'personalization_storage': hasPrefs ? 'granted' : 'denied',
                'functionality_storage': 'granted',
                'security_storage': 'granted'
            });
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': 'tka_consent_update',
            'consent_status': {
                'functional': 'granted',
                'preferences': hasPrefs ? 'granted' : 'denied',
                'statistics': hasStats ? 'granted' : 'denied',
                'marketing': hasMarketing ? 'granted' : 'denied'
            }
        });
    }

    /**
     * Unlock blocked iframes for a category.
     */
    function unlockIframes(category) {
        const placeholders = document.querySelectorAll(
            `.tka-iframe-placeholder[data-tka-category="${category}"], .tka-iframe-placeholder[data-consent-category="${category}"]`
        );

        placeholders.forEach(function(holder) {
            const iframe = holder.querySelector('iframe');
            if (iframe) {
                const targetSrc = iframe.getAttribute('data-tka-src') || iframe.getAttribute('data-src');
                if (targetSrc && iframe.src !== targetSrc) {
                    iframe.src = targetSrc;
                }
            }
            holder.classList.add('tka-iframe-unlocked');
        });

        // Also check any standalone iframes with data-tka-src
        const standaloneIframes = document.querySelectorAll(
            `iframe[data-tka-category="${category}"][data-tka-src], iframe[data-consent-category="${category}"][data-src]`
        );
        standaloneIframes.forEach(function(iframe) {
            const targetSrc = iframe.getAttribute('data-tka-src') || iframe.getAttribute('data-src');
            if (targetSrc && iframe.src !== targetSrc) {
                iframe.src = targetSrc;
            }
        });
    }

    /**
     * Sequentially unlock blocked scripts to preserve dependency execution order.
     */
    async function unlockScripts(category) {
        // Unlock iframes first
        unlockIframes(category);

        const selector = `script[type="text/plain"][data-tka-category="${category}"], script[type="text/plain"][data-consent-category="${category}"]`;
        const scripts = Array.from(document.querySelectorAll(selector));

        if (!scripts.length) {
            return;
        }

        const externalScripts = [];
        const inlineScripts = [];

        scripts.forEach(function(s) {
            if (s.hasAttribute('data-tka-src') || s.hasAttribute('data-src')) {
                externalScripts.push(s);
            } else {
                inlineScripts.push(s);
            }
        });

        // 1. Load external scripts sequentially
        for (const oldScript of externalScripts) {
            await new Promise(function(resolve) {
                const newScript = document.createElement('script');
                const src = oldScript.getAttribute('data-tka-src') || oldScript.getAttribute('data-src');

                Array.from(oldScript.attributes).forEach(function(attr) {
                    if (attr.name !== 'data-tka-src' && attr.name !== 'data-src' && attr.name !== 'type') {
                        newScript.setAttribute(attr.name, attr.value);
                    }
                });

                newScript.setAttribute('type', 'text/javascript');
                newScript.src = src;

                newScript.onload = function() { resolve(); };
                newScript.onerror = function() { resolve(); };

                if (oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                } else {
                    document.head.appendChild(newScript);
                    resolve();
                }
            });
        }

        // 2. Execute inline scripts in order
        for (const oldScript of inlineScripts) {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function(attr) {
                if (attr.name !== 'type') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });
            newScript.setAttribute('type', 'text/javascript');
            newScript.text = oldScript.innerHTML || oldScript.textContent;

            if (oldScript.parentNode) {
                oldScript.parentNode.replaceChild(newScript, oldScript);
            } else {
                document.head.appendChild(newScript);
            }
        }
    }

    /**
     * Initialize Banner UI & Interactions.
     */
    document.addEventListener('DOMContentLoaded', function() {
        const banner = document.getElementById('tka-cookie-banner');
        const categories = (window.tkaConsentSettings && window.tkaConsentSettings.categories) 
            ? window.tkaConsentSettings.categories 
            : ['statistics', 'marketing', 'preferences'];

        // Check if user has already interacted with the banner
        const consentInteracted = getCookie('wp_consent_functional');

        if (!consentInteracted) {
            if (banner) {
                banner.classList.remove('tka-hidden');
            }
        } else {
            // User already gave consent. Unlock allowed scripts and iframes.
            categories.forEach(function(cat) {
                if (window.wp_has_consent(cat)) {
                    unlockScripts(cat);
                }
            });

            // Send initial Consent Mode update if enabled
            if (window.tkaConsentSettings && window.tkaConsentSettings.enableConsentMode) {
                updateGoogleConsentMode({
                    functional: true,
                    preferences: window.wp_has_consent('preferences'),
                    statistics: window.wp_has_consent('statistics'),
                    marketing: window.wp_has_consent('marketing')
                });
            }
        }

        // DOM elements
        const btnAccept = document.getElementById('tka-cb-btn-accept');
        const btnReject = document.getElementById('tka-cb-btn-reject');
        const btnCustomize = document.getElementById('tka-cb-btn-customize');
        const btnSave = document.getElementById('tka-cb-btn-save');
        const prefsPanel = document.getElementById('tka-cb-preferences');
        
        const chkPref = document.getElementById('tka-consent-preferences');
        const chkStats = document.getElementById('tka-consent-statistics');
        const chkMktg = document.getElementById('tka-consent-marketing');

        // Accept All
        if (btnAccept) {
            btnAccept.addEventListener('click', function() {
                window.wp_set_consent('functional', 'allow');
                window.wp_set_consent('preferences', 'allow');
                window.wp_set_consent('statistics', 'allow');
                window.wp_set_consent('marketing', 'allow');
                
                unlockScripts('preferences');
                unlockScripts('statistics');
                unlockScripts('marketing');

                updateGoogleConsentMode({
                    functional: true,
                    preferences: true,
                    statistics: true,
                    marketing: true
                });

                if (banner) {
                    banner.classList.add('tka-hidden');
                }
            });
        }

        // Reject All
        if (btnReject) {
            btnReject.addEventListener('click', function() {
                window.wp_set_consent('functional', 'allow');
                window.wp_set_consent('preferences', 'deny');
                window.wp_set_consent('statistics', 'deny');
                window.wp_set_consent('marketing', 'deny');

                updateGoogleConsentMode({
                    functional: true,
                    preferences: false,
                    statistics: false,
                    marketing: false
                });

                if (banner) {
                    banner.classList.add('tka-hidden');
                }
            });
        }

        // Customize Preferences Toggle
        if (btnCustomize && prefsPanel) {
            btnCustomize.addEventListener('click', function() {
                prefsPanel.classList.toggle('tka-hidden');
                
                if (prefsPanel.classList.contains('tka-hidden')) {
                    if (btnAccept) btnAccept.classList.remove('tka-hidden');
                    if (btnReject) btnReject.classList.remove('tka-hidden');
                    if (btnSave) btnSave.classList.add('tka-hidden');
                    btnCustomize.textContent = btnCustomize.getAttribute('data-default-label') || 'Cookie Preferences';
                } else {
                    if (btnAccept) btnAccept.classList.add('tka-hidden');
                    if (btnReject) btnReject.classList.add('tka-hidden');
                    if (btnSave) btnSave.classList.remove('tka-hidden');
                    
                    if (!btnCustomize.getAttribute('data-default-label')) {
                        btnCustomize.setAttribute('data-default-label', btnCustomize.textContent);
                    }
                    btnCustomize.textContent = (window.tkaConsentSettings && window.tkaConsentSettings.labels && window.tkaConsentSettings.labels.back) || 'Back';

                    if (chkPref) chkPref.checked = window.wp_has_consent('preferences');
                    if (chkStats) chkStats.checked = window.wp_has_consent('statistics');
                    if (chkMktg) chkMktg.checked = window.wp_has_consent('marketing');
                }
            });
        }

        // Save customized preferences
        if (btnSave) {
            btnSave.addEventListener('click', function() {
                window.wp_set_consent('functional', 'allow');
                
                const prefStatus = (chkPref && chkPref.checked) ? 'allow' : 'deny';
                const statsStatus = (chkStats && chkStats.checked) ? 'allow' : 'deny';
                const mktgStatus = (chkMktg && chkMktg.checked) ? 'allow' : 'deny';

                window.wp_set_consent('preferences', prefStatus);
                window.wp_set_consent('statistics', statsStatus);
                window.wp_set_consent('marketing', mktgStatus);

                if (prefStatus === 'allow') unlockScripts('preferences');
                if (statsStatus === 'allow') unlockScripts('statistics');
                if (mktgStatus === 'allow') unlockScripts('marketing');

                updateGoogleConsentMode({
                    functional: true,
                    preferences: prefStatus === 'allow',
                    statistics: statsStatus === 'allow',
                    marketing: mktgStatus === 'allow'
                });

                if (banner) {
                    banner.classList.add('tka-hidden');
                }
            });
        }

        function showSettingsPanel() {
            if (!banner || !prefsPanel) return;

            banner.classList.remove('tka-hidden');
            prefsPanel.classList.remove('tka-hidden');
            if (btnAccept) btnAccept.classList.add('tka-hidden');
            if (btnReject) btnReject.classList.add('tka-hidden');
            if (btnSave) btnSave.classList.remove('tka-hidden');
            
            if (btnCustomize && !btnCustomize.getAttribute('data-default-label')) {
                btnCustomize.setAttribute('data-default-label', btnCustomize.textContent);
            }
            if (btnCustomize) {
                btnCustomize.textContent = (window.tkaConsentSettings && window.tkaConsentSettings.labels && window.tkaConsentSettings.labels.back) || 'Back';
            }

            if (chkPref) chkPref.checked = window.wp_has_consent('preferences');
            if (chkStats) chkStats.checked = window.wp_has_consent('statistics');
            if (chkMktg) chkMktg.checked = window.wp_has_consent('marketing');
        }

        window.tkaShowConsentSettings = showSettingsPanel;

        // Global click handler for settings triggers & iframe placeholder unlocks
        document.addEventListener('click', function(e) {
            // 1. Settings toggle links
            const targetToggle = e.target.closest('.tka-cookie-settings-toggle, a[href="#tka-cookie-settings"]');
            if (targetToggle) {
                e.preventDefault();
                showSettingsPanel();
                return;
            }

            // 2. Direct inline iframe unlock button
            const unlockBtn = e.target.closest('.tka-iframe-unlock-btn');
            if (unlockBtn) {
                e.preventDefault();
                const category = unlockBtn.getAttribute('data-category') || 'marketing';
                window.wp_set_consent(category, 'allow');
                unlockScripts(category);

                updateGoogleConsentMode({
                    functional: true,
                    preferences: window.wp_has_consent('preferences'),
                    statistics: window.wp_has_consent('statistics'),
                    marketing: window.wp_has_consent('marketing')
                });
            }
        });
    });
})();
