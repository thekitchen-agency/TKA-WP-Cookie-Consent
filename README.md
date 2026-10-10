# TKA Cookie Consent

A lightweight, enterprise-grade Cookie Consent Management Platform (CMP) for WordPress. Designed for agency workflows, client privacy compliance (EU GDPR, ePrivacy Directive, Swiss revDSG), and seamless integration with Google Consent Mode v2.

---

## Table of Contents

- [Overview & Architecture](#overview--architecture)
- [Key Features](#key-features)
- [How Script Blocking Works](#how-script-blocking-works)
- [How to Add Tracking Scripts](#how-to-add-tracking-scripts)
- [Edge Cases & Developer Handling](#edge-cases--developer-handling)
  - [The Site's Own Scripts (Theme JS, Bundles)](#1-the-sites-own-scripts-theme-js-bundles)
  - [Functional & UI Plugins](#2-functional--ui-plugins)
  - [Third-Party Plugins That Inject Tracking Scripts](#3-third-party-plugins-that-inject-tracking-scripts)
  - [Exempting Scripts from Blocking (`data-tka-ignore`)](#4-exempting-scripts-from-blocking-data-tka-ignore)
  - [Async Race Conditions & Sequential Execution](#5-async-race-conditions--sequential-execution)
- [Third-Party Iframe & Embed Blocker](#third-party-iframe--embed-blocker)
- [Google Consent Mode v2 & dataLayer](#google-consent-mode-v2--datalayer)
- [Built-In Service Catalog](#built-in-service-catalog)
- [Developer API & Events](#developer-api--events)
- [UI Customization & CSS Variables](#ui-customization--css-variables)
- [WordPress 6.7+ Translation Architecture](#wordpress-67-translation-architecture)

---

## Overview & Architecture

Unlike legacy cookie plugins that force developers to manually wrap tags in custom PHP conditionals, TKA Cookie Consent operates on a **zero-code, allowlist-by-default architecture**:

```
                              [ Incoming Visitor Request ]
                                           │
                                           ▼
                       [ WordPress Output Buffer (HtmlBlocker) ]
                                           │
         ┌─────────────────────────────────┴─────────────────────────────────┐
         ▼                                                                   ▼
   [ <script> Tags ]                                                  [ <iframe> Embeds ]
         │                                                                   │
   Matches Tracking Signature?                                         Matches Embed Service?
   (GA4, Meta, Hotjar, etc.)                                          (YouTube, Maps, Vimeo)
    ├── No  ──► Passes untouched (Theme JS, Forms, UI)                  ├── No  ──► Passes untouched
    └── Yes ──► Rewritten to `type="text/plain"`                        └── Yes ──► Replaced with interactive
                with `data-tka-category`                                            consent placeholder
                                           │
                                           ▼
                                [ Visitor Grants Consent ]
                                           │
   ┌───────────────────────────────────────┼───────────────────────────────────────┐
   ▼                                       ▼                                       ▼
[ Google Consent Mode v2 ]      [ Sequential Script Loader ]            [ Unlock Iframes ]
gtag('consent', 'update')       Loads external SDKs first,              Loads target src
dataLayer.push(update)          then executes inline snippets           without page reload
```

---

## Key Features

1. **Zero-Code Full-Page Output Buffer Blocker**: Captures and defers scripts and embeds in the rendered HTML output without requiring template edits.
2. **Google Consent Mode v2 Built-in**: Defaults declared before GTM/GA runs; dynamic updates dispatched upon user choice.
3. **Predefined Enterprise Service Catalog**: Automatic pattern detection for Google Analytics 4, GTM, Meta Pixel, Google Ads, LinkedIn Insight, Hotjar, Clarity, TikTok, Twitter/X, Pinterest, HubSpot, YouTube, Vimeo, and Google Maps.
4. **Interactive Iframe Placeholders**: Branded visual overlays for embedded media with one-click in-place unblocking.
5. **Sequential Execution Engine**: Promise-based external script loader to eliminate `gtag is not defined` race conditions.
6. **Accessible Settings Panel**: Clean preference toggles for *Strictly Necessary*, *Preferences*, *Statistics*, and *Marketing*.
7. **WordPress 6.7+ JIT Translation Compliant**: Safe execution avoiding premature textdomain loading notices.

---

## How Script Blocking Works

The plugin inspects scripts via `HtmlBlocker` using a **targeted allowlist-by-default model**:

1. If a script's `src` or inline content matches a known tracking service or custom admin signature, it is rewritten to:
   ```html
   <script type="text/plain" data-tka-category="statistics" data-tka-script data-tka-src="https://www.googletagmanager.com/gtag/js?id=G-XXXXX"></script>
   ```
2. The browser ignores `type="text/plain"` tags during initial DOM compilation.
3. When the user clicks **Accept All** or checks **Statistics**, `banner.js` dynamically turns `type` back to `text/javascript` and loads the script.
4. Scripts that do **not** match tracking signatures (e.g., your theme JS, forms, carousels) are untouched.

---

## How to Add Tracking Scripts

Developers can add tracking scripts normally using any standard WordPress method:

### Method A: Direct HTML / Blade Template
Add your snippet directly in `<head>` or before `</body>` in your theme:
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```
*The plugin intercepts this automatically and blocks it until Statistics consent is granted.*

### Method B: Standard WordPress Enqueue
```php
wp_enqueue_script(
    'my-analytics',
    'https://www.google-analytics.com/analytics.js',
    [],
    null,
    true
);
```

### Method C: Google Tag Manager (GTM)
Paste the standard GTM container snippet into `<head>`. The plugin automatically initializes **Google Consent Mode v2** defaults before GTM loads.

---

## Edge Cases & Developer Handling

### 1. The Site's Own Scripts (Theme JS, Bundles)
- **Status:** **Always Run Immediately (Never Blocked).**
- **Details:** Theme scripts (e.g., Vite/Webpack bundles like `app-DzKcPmrT.js`, GSAP, sliders, navigation) do not match any tracking domains in the Service Registry. They run immediately on page load without latency.

### 2. Functional & UI Plugins
- **Status:** **Always Run Immediately (Never Blocked).**
- **Details:** Plugins like WooCommerce, Contact Form 7, Gravity Forms, and ACF use functional scripts. Because they contain no tracking signatures, they are never intercepted.

### 3. Third-Party Plugins That Inject Tracking Scripts
- **Status:** **Automatically Blocked.**
- **Details:** Plugins like *PixelYourSite* or *MonsterInsights* inject raw scripts into `wp_head`. Because `HtmlBlocker` inspects the final output buffer, it intercepts the generated `<script>` tag regardless of which plugin created it.

### 4. Exempting Scripts from Blocking (`data-tka-ignore`)
If you have a custom script containing words like `analytics` or `maps` that is purely functional and must never be blocked, add `data-tka-ignore`:
```html
<script src="/path/to/internal-dashboard.js" data-tka-ignore></script>
```
Or in `wp_enqueue_script`:
```php
wp_enqueue_script('my-script', '/path/to/script.js', [], '1.0', [
    'in_footer' => true,
    'attributes' => ['data-tka-ignore' => true], // WP 6.3+
]);
```

### 5. Async Race Conditions & Sequential Execution
A common issue in cookie consent plugins is that external SDKs (like `fbevents.js` or `gtag.js`) load asynchronously, but dependent inline snippets execute immediately, resulting in `Uncaught ReferenceError: gtag is not defined`.

**How TKA solves this:**
`banner.js` uses a sequential Promise queue:
1. All blocked external scripts (`data-tka-src`) are fetched and executed in order first.
2. Only after dependencies finish loading are dependent inline scripts executed.

---

## Third-Party Iframe & Embed Blocker

Embedded media like YouTube, Vimeo, and Google Maps drop third-party cookies and transmit visitor IP addresses before consent is obtained.

### Behavior
- The plugin intercepts matching `<iframe>` elements and replaces `src` with `about:blank`.
- A responsive placeholder overlay is rendered in place of the iframe with a lock icon, explanation, and an **"Allow [Service] & Load Content"** button.
- Clicking the inline button grants consent for that category (*Marketing* or *Preferences*), updates the cookie, pushes Consent Mode updates, and unlocks the video/map immediately without page refresh.

---

## Google Consent Mode v2 & dataLayer

Enabled by default in **Settings &rarr; Cookie Consent &rarr; Enterprise Automation**.

### Initial Defaults (`wp_head`, priority 1)
```javascript
gtag('consent', 'default', {
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'analytics_storage': 'denied',
    'personalization_storage': 'denied',
    'functionality_storage': 'granted',
    'security_storage': 'granted',
    'wait_for_update': 500
});
dataLayer.push({'event': 'tka_consent_default_ready'});
```

### On User Consent Change
When the user accepts or saves preferences, the plugin automatically calls:
```javascript
gtag('consent', 'update', {
    'ad_storage': 'granted',
    'ad_user_data': 'granted',
    'ad_personalization': 'granted',
    'analytics_storage': 'granted',
    'personalization_storage': 'granted'
});

dataLayer.push({
    'event': 'tka_consent_update',
    'consent_status': {
        'functional': 'granted',
        'preferences': 'granted',
        'statistics': 'granted',
        'marketing': 'granted'
    }
});
```

---

## Built-In Service Catalog

| Service | Type | Category | Default Signatures |
| :--- | :--- | :--- | :--- |
| **Google Analytics 4** | Script | Statistics | `google-analytics.com`, `gtag/js`, `analytics.js`, `gtag(` |
| **Google Tag Manager** | Script | Statistics | `googletagmanager.com/gtm.js` |
| **Hotjar** | Script | Statistics | `static.hotjar.com`, `hotjar.js`, `hjSetting` |
| **Matomo (Piwik)** | Script | Statistics | `matomo.js`, `piwik.js`, `_paq.push` |
| **Microsoft Clarity** | Script | Statistics | `clarity.ms/tag`, `clarity.ms/s/` |
| **Meta Pixel** | Script | Marketing | `connect.facebook.net`, `fbevents.js`, `fbq(` |
| **Google Ads / DoubleClick** | Script | Marketing | `doubleclick.net`, `googleadservices.com`, `adsbygoogle.js` |
| **LinkedIn Insight** | Script | Marketing | `snap.licdn.com`, `linkedin.com/px`, `_linkedin_partner_id` |
| **TikTok Pixel** | Script | Marketing | `analytics.tiktok.com`, `ttq.load` |
| **X (Twitter) Pixel** | Script | Marketing | `ads-twitter.com`, `twq(` |
| **Pinterest Tag** | Script | Marketing | `assets.pinterest.com`, `pintrk(` |
| **HubSpot** | Script | Marketing | `js.hs-scripts.com`, `js.hsforms.net`, `js.hs-analytics.net` |
| **YouTube** | Iframe | Marketing | `youtube.com/embed/`, `youtube-nocookie.com/embed/`, `youtu.be/` |
| **Vimeo** | Iframe | Marketing | `player.vimeo.com/video/` |
| **Google Maps** | Iframe | Preferences | `google.com/maps`, `maps.google.com`, `maps.googleapis.com` |
| **SoundCloud** | Iframe | Preferences | `w.soundcloud.com/player`, `soundcloud.com` |
| **Spotify** | Iframe | Preferences | `open.spotify.com/embed` |

*Custom signatures can be added at any time under **Settings &rarr; Cookie Consent &rarr; Script Blocking Settings**.*

---

## Developer API & Events

### JavaScript Helper Functions
```javascript
// Check if user has consented to a category
window.wp_has_consent('statistics'); // returns boolean

// Set consent programmatically
window.wp_set_consent('marketing', 'allow'); // 'allow' or 'deny'

// Open the preference settings panel programmatically
window.tkaShowConsentSettings();
```

### Event Listeners
```javascript
// Native WP Consent API standard listener
document.addEventListener('wp_listen_for_consent_change', function(e) {
    if (e.detail.statistics === 'allow') {
        console.log('Statistics tracking allowed');
    }
});
```

### Trigger Links
Any HTML element with the class `.tka-cookie-settings-toggle` or `href="#tka-cookie-settings"` automatically opens the preferences panel:
```html
<a href="#tka-cookie-settings" class="tka-cookie-settings-toggle">Cookie Settings</a>
```

---

## UI Customization & CSS Variables

The banner styles can be overridden using CSS custom properties:

```css
#tka-cookie-banner.tka-cookie-banner {
  --tka-cb-bg: rgba(20, 20, 24, 0.95);
  --tka-cb-primary-color: #E8FE87;
  --tka-cb-primary-color-hover: #ffffff;
  --tka-cb-text-color: #ffffff;
  --tka-cb-title-color: #E8FE87;
  --tka-cb-font-family: "Space Mono", monospace;
  --tka-cb-border-radius: 0px;
}
```

---

## WordPress 6.7+ Translation Architecture

WordPress 6.7 strictly audits Just-In-Time (JIT) textdomain loading. This plugin:
- Hooks `load_plugin_textdomain` to `init` instead of `plugins_loaded`.
- Keeps internal service dictionaries untranslated at compile time, deferring translation function calls (`__()`) to admin rendering hooks.
- Guarantees 0 `_load_textdomain_just_in_time was called incorrectly` warnings.
