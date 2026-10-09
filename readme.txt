=== RGBmind Ephemere ===
Contributors: rgbmind
Donate link: https://rgbmind.com
Tags: temporary login, timed access, temporary users, access control, login limits
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure temporary logins and timed access for WordPress: scheduled expiration, login caps, instant account lockout, and real-time session revocation.

== Description ==

**RGBmind Ephemere** is a lightweight, secure, and cybersecurity-focused access control plugin designed to manage temporary and time-limited user access on any WordPress site.

Built to meet strict WordPress development standards and cybersecurity principles, the plugin allows administrators to grant access to external developers, partner agencies, IT contractors, auditors, or clients with the guarantee that access will be automatically and reliably revoked when predefined limits are reached.

The plugin is 100% free, fully functional, and unrestricted. For advanced agency and enterprise workflows (such as passwordless One-Click Magic Links, GDPR Access Audit Log, and Security Alerts), a separate commercial companion add-on is available.

### Key Free Features

* **Precise Expiration Schedule**: Set exact expiration date and time using native HTML5 datetime-local inputs, fully synchronized with your WordPress site timezone.
* **Maximum Login Threshold**: Limit total allowed logins (e.g. allow only 3 logins). Once the threshold is met, the account is automatically locked out.
* **Instant Account Lockout**: Instantly revoke access with a single checkbox toggle, without altering user passwords or deleting accounts.
* **Access Monitoring**: Real-time read-only view of login counts and last login timestamp, with an option to reset the counter when re-enabling access.
* **Real-Time Active Session Invalidation**: If an active temporary user is browsing the admin dashboard when their expiration time arrives, their session and authentication cookies are revoked immediately.
* **Users List Status Column**: Visual account health badge right inside the WordPress Users screen (Active, Expired, Locked, Logins Exhausted).
* **Multi-Language Global Suite**: Fully translated into 6 languages (English, Italian, Spanish, French, German, Brazilian Portuguese) with automatic detection and manual override.

### Architecture & Cybersecurity

* **Zero Custom Database Tables**: Uses native WordPress user metadata (`get_user_meta`, `update_user_meta`, `delete_user_meta`) and `wp_options`.
* **Zero External Server Latency**: 100% local execution, zero external API tracking calls, fully GDPR compliant.
* **Strict Permission Checks**: Only users with the `promote_users` capability (Administrators) can view or modify temporary parameters.
* **CSRF Protection**: Every action is guarded by WordPress cryptographic nonces.
* **Clean Uninstallation**: `uninstall.php` completely wipes all created user metadata and options upon plugin deletion.

== Commercial Companion Add-on (PRO) ==

For web agencies and IT maintenance professionals requiring advanced capabilities, the separate commercial companion add-on **RGBmind Ephemere PRO** seamlessly integrates with this free plugin:

* **⚡ One-Click Temporary Login Link (Magic Link)**:
  Generate an encrypted, single-use login link from the user profile. Contractors and auditors can log in with a single click without sharing passwords. Only the SHA-256 hash is persisted in the database.
* **🛡️ Detailed Access Audit Log**:
  View the last 10 logins directly in the user profile, showing exact timestamps, authentication method, and GDPR-anonymized IP addresses.
* **🔔 Real-Time Security Email & Webhook Alerts**:
  Receive immediate email notifications whenever a temporary account expires, reaches its login cap, or is locked out. Supports HTTP Webhooks for Slack, Discord, Zapier, or Make integrations.

Visit [rgbmind.com](https://rgbmind.com) to learn more about the PRO companion add-on.

== Installation ==

1. Upload the `rgbmind-ephemere` folder to your `/wp-content/plugins/` directory, or upload the ZIP file through **Plugins > Add New > Upload Plugin**.
2. Activate the plugin via the **Plugins** screen in WordPress.
3. Go to **Users > All Users** and click **Edit** on the user you wish to make temporary.
4. Scroll to the **RGBmind Ephemere – Temporary Access Management** section.
5. Check **Enable temporary access restrictions for this user** and configure the desired expiration date or maximum login count.
6. To configure language preferences or view an overview of temporary accounts, navigate to **RGBmind Ephemere** in your WordPress sidebar.

== Frequently Asked Questions ==

= Is RGBmind Ephemere free to use? =
Yes, 100%. All core features—expiration schedules, login limits, instant lockout, real-time session termination, user table status columns, and multilingual support—are completely free and never restricted.

= How is timezone handled for expiration? =
The entered date and time are converted to UTC Unix timestamps based on your site's timezone setting in Settings > General. Every login check compares the current Unix timestamp against the expiration timestamp.

= What happens if a user is logged in when their expiration time arrives? =
The plugin checks session validity in real time on the `init` hook. When an expired temporary user attempts to browse any page, their session is destroyed, cookies are cleared, and they are redirected to the login page with a notification.

= Does this plugin create custom database tables? =
No. RGBmind Ephemere follows WordPress core architecture standards by storing data exclusively in the standard `wp_usermeta` and `wp_options` tables.

= Can temporary users alter their own restrictions? =
No. All fields and handlers enforce strict `current_user_can('promote_users')` permission checks. Non-admin users cannot view the fields or manipulate POST requests.

= What is RGBmind Ephemere PRO? =
RGBmind Ephemere PRO is a separate commercial add-on plugin for agencies needing passwordless One-Click Magic Links, GDPR access audit trails, and instant email/webhook security alerts. It installs as a companion plugin alongside this free core plugin.

== Changelog ==

= 1.2.1 =
* 100% WordPress.org compliant architecture: clean separation of free core features and commercial companion add-on.
* Progressive version display: Added visible version badges across the administrative dashboard and user profile management sections.
* Enhanced localization loader for seamless compatibility with modern WordPress JIT translation controllers.
* Simplified language selection interface ("Automatic").
* Expanded automated test runner to comprehensive test suites.
* Verified compatibility with WordPress 6.7+ and PHP 8.2.

= 1.2.0 =
* Multilingual Global Suite: Added complete support for 6 major world languages: English (US), Italian (it_IT), Spanish (es_ES), French (fr_FR), German (de_DE), and Brazilian Portuguese (pt_BR).
* Automatic & Manual Language Switcher: Automatically follows the active WordPress locale with a dedicated option to manually override the plugin language in the dashboard.
* Primary Administrative Sidebar Menu: Added dedicated "RGBmind Ephemere" menu item (`manage_options`, `dashicons-clock`).
* Two-Tab Administrative Dashboard: Overview table of all ephemeral accounts with real-time status badges and Settings tab.

= 1.1.0 =
* Internationalization support with English base language and translations included.
* Added extensibility hooks for companion plugins.
* Updated uninstall.php to clean all options and metadata.

= 1.0.0 =
* Initial release conforming to WordPress.org standards.
* Interactive welcome guide notice on first installation.
* Complete ephemeral access management via native user metadata.
* Authentication interceptor via `wp_authenticate_user`.
* Automatic login count and timestamp tracking on `wp_login`.
* Proactive active session termination upon expiration.
* Dedicated visual status column in the users list.
* Complete database cleanup via `uninstall.php`.
