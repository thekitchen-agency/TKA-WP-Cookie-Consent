# Changelog

All notable changes to the TKA Cookie Consent plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] - 2026-10-10

### Added
- **Zero-Code Full-Page Output Buffer Blocker (`HtmlBlocker.php`)**: Intercepts, parses, and defers tracking scripts and embedded media directly from the rendered HTML output without requiring manual template code wrapping.
- **Google Consent Mode v2 & dataLayer Integration**:
  - Automatically declares Consent Mode defaults (`ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`) in `<head>` at priority 1 before GTM or tracking containers execute.
  - Dynamically dispatches `gtag('consent', 'update')` and pushes `tka_consent_update` events to `window.dataLayer` upon user choice.
- **Built-in Enterprise Service Catalog (`ServiceRegistry.php`)**: Pre-configured automatic detection signatures for 14+ services including Google Analytics 4, GTM, Meta Pixel, Google Ads, Hotjar, Microsoft Clarity, LinkedIn Insight, TikTok, HubSpot, YouTube, Vimeo, and Google Maps.
- **Third-Party Iframe Blocker**:
  - Automatically blocks third-party iframes (YouTube, Vimeo, Google Maps) before consent.
  - Renders a responsive visual placeholder with a lock icon, explanation, and an interactive **"Allow & Load Content"** button that unlocks content immediately in-place.
- **Sequential Script Execution Engine**: Promise-based external script loader in `banner.js` ensuring external SDKs finish loading before dependent inline scripts run, preventing race conditions.
- **Enterprise Settings UI in Admin**: Toggles for Output Buffer Blocker, Consent Mode v2, and Iframe Blocker, plus a visual table of recognized enterprise services.
- **Comprehensive Developer Documentation**: Detailed `README.md` covering architecture, script integration, edge-case handling, and API events.

### Changed
- **WordPress 6.7+ JIT Compatibility**:
  - Moved `load_plugin_textdomain` to the `init` action hook.
  - Removed early translation calls from internal registries to prevent `_load_textdomain_just_in_time` notices.
- **Standardized Button Hierarchy & Layout**:
  - Reorganized action buttons: `Accept All` and `Reject All` grouped on the left, `Cookie Preferences` on the right.
  - Removed legacy keyword override from `banner.js` that was hijacking customize button clicks.
  - Standardized default labels across code and database options.

---

## [1.0.5] - 2026-10-08

### Added
- Environment variable support for license keys (`.env` / constant).
- Domain whitelisting for automatic activation on approved environments.
- Manual "Check for Updates" trigger button in admin panel.

### Changed
- Refined domain detection logic for local development bypasses.
- Improved license status reporting and admin notices.

---

## [1.0.4] - 2026-07-14

### Added
- WPML translation configuration (`wpml-config.xml`).
- Multilingual `.po` and `.mo` language files (de_DE, de_CH, it_IT, fr_FR).

### Fixed
- CSS variable inheritance and z-index positioning for banner container.

---

## [1.0.0] - 2026-05-10

- Initial release of TKA Cookie Consent with WP Consent API compatibility.
