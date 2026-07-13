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
        // Set secure flag if on HTTPS
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

    // Unlocking blocked scripts based on consent category
    function unlockScripts(category) {
        const scripts = document.querySelectorAll(`script[type="text/plain"][data-consent-category="${category}"]`);
        scripts.forEach(function(oldScript) {
            const newScript = document.createElement('script');
            
            // Copy attributes
            Array.from(oldScript.attributes).forEach(function(attr) {
                if (attr.name === 'data-src') {
                    newScript.setAttribute('src', attr.value);
                } else if (attr.name !== 'type') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });
            newScript.setAttribute('type', 'text/javascript');
            
            // Copy content for inline script
            if (oldScript.innerHTML) {
                newScript.innerHTML = oldScript.innerHTML;
            }
            
            // Replace script in DOM to trigger execution
            if (oldScript.parentNode) {
                oldScript.parentNode.replaceChild(newScript, oldScript);
            }
        });
    }

    // Initialize Banner UI & Interaction
    document.addEventListener('DOMContentLoaded', function() {
        const banner = document.getElementById('tka-cookie-banner');
        if (!banner) return;

        // Check if user has already interacted with the banner
        // We use the functional cookie presence as the interaction indicator
        const consentInteracted = getCookie('wp_consent_functional');

        if (!consentInteracted) {
            // Show banner
            banner.classList.remove('tka-hidden');
        } else {
            // User already gave consent. Unlock allowed scripts.
            const categories = window.tkaConsentSettings && window.tkaConsentSettings.categories 
                ? window.tkaConsentSettings.categories 
                : ['statistics', 'marketing', 'preferences'];

            categories.forEach(function(cat) {
                if (window.wp_has_consent(cat)) {
                    unlockScripts(cat);
                }
            });
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
        btnAccept.addEventListener('click', function() {
            window.wp_set_consent('functional', 'allow');
            window.wp_set_consent('preferences', 'allow');
            window.wp_set_consent('statistics', 'allow');
            window.wp_set_consent('marketing', 'allow');
            
            // Unlock scripts
            unlockScripts('preferences');
            unlockScripts('statistics');
            unlockScripts('marketing');

            banner.classList.add('tka-hidden');
        });

        // Reject All
        if (btnReject) {
            btnReject.addEventListener('click', function() {
                window.wp_set_consent('functional', 'allow');
                window.wp_set_consent('preferences', 'deny');
                window.wp_set_consent('statistics', 'deny');
                window.wp_set_consent('marketing', 'deny');

                banner.classList.add('tka-hidden');
            });
        }

        // Customize Preferences Toggle
        btnCustomize.addEventListener('click', function() {
            prefsPanel.classList.toggle('tka-hidden');
            
            // Toggle visibility of action buttons
            if (prefsPanel.classList.contains('tka-hidden')) {
                btnAccept.classList.remove('tka-hidden');
                if (btnReject) btnReject.classList.remove('tka-hidden');
                btnSave.classList.add('tka-hidden');
                btnCustomize.textContent = btnCustomize.getAttribute('data-default-label') || 'Customize';
            } else {
                btnAccept.classList.add('tka-hidden');
                if (btnReject) btnReject.classList.add('tka-hidden');
                btnSave.classList.remove('tka-hidden');
                
                // Store default label and rename button
                if (!btnCustomize.getAttribute('data-default-label')) {
                    btnCustomize.setAttribute('data-default-label', btnCustomize.textContent);
                }
                btnCustomize.textContent = (window.tkaConsentSettings && window.tkaConsentSettings.labels && window.tkaConsentSettings.labels.back) || 'Back';

                // Pre-fill checkboxes based on current cookies
                chkPref.checked = window.wp_has_consent('preferences');
                chkStats.checked = window.wp_has_consent('statistics');
                chkMktg.checked = window.wp_has_consent('marketing');
            }
        });

        // Save customized preferences
        btnSave.addEventListener('click', function() {
            window.wp_set_consent('functional', 'allow');
            
            const prefStatus = chkPref.checked ? 'allow' : 'deny';
            const statsStatus = chkStats.checked ? 'allow' : 'deny';
            const mktgStatus = chkMktg.checked ? 'allow' : 'deny';

            window.wp_set_consent('preferences', prefStatus);
            window.wp_set_consent('statistics', statsStatus);
            window.wp_set_consent('marketing', mktgStatus);

            // Unlock scripts if newly allowed
            if (chkPref.checked) unlockScripts('preferences');
            if (chkStats.checked) unlockScripts('statistics');
            if (chkMktg.checked) unlockScripts('marketing');

            banner.classList.add('tka-hidden');
        });

        function showSettingsPanel() {
            banner.classList.remove('tka-hidden');
            prefsPanel.classList.remove('tka-hidden');
            btnAccept.classList.add('tka-hidden');
            if (btnReject) btnReject.classList.add('tka-hidden');
            btnSave.classList.remove('tka-hidden');
            
            if (!btnCustomize.getAttribute('data-default-label')) {
                btnCustomize.setAttribute('data-default-label', btnCustomize.textContent);
            }
            btnCustomize.textContent = (window.tkaConsentSettings && window.tkaConsentSettings.labels && window.tkaConsentSettings.labels.back) || 'Back';

            chkPref.checked = window.wp_has_consent('preferences');
            chkStats.checked = window.wp_has_consent('statistics');
            chkMktg.checked = window.wp_has_consent('marketing');
        }

        window.tkaShowConsentSettings = showSettingsPanel;

        document.addEventListener('click', function(e) {
            const target = e.target.closest('.tka-cookie-settings-toggle, a[href="#tka-cookie-settings"]');
            if (target) {
                e.preventDefault();
                showSettingsPanel();
            }
        });
    });
})();
