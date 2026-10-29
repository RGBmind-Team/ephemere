=== RGBmind Ephemere – Temporary Login & Timed Access Control ===
Contributors: rgbmind
Donate link: https://rgbmind.com
Tags: temporary login, temporary access, expire users, temporary user, login limits
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure temporary login and timed access for WordPress. Create expiring temporary users, set login limits, instant lockout, and passwordless access.

== Description ==

**RGBmind Ephemere** is a secure, lightweight, and cybersecurity-focused **temporary login** and timed user access manager for WordPress.

Need to create a secure **temporary login** for a developer, support agent, freelance designer, IT contractor, auditor, or client? RGBmind Ephemere allows administrators to grant **temporary access** with automated expiration timers, login limits, instant kill-switch lockout, and real-time active session revocation.

Unlike basic login plugins, RGBmind Ephemere is built to meet strict WordPress development standards and cybersecurity principles (IAM - Identity & Access Management). All temporary access restrictions are enforced locally with zero external tracking and zero custom database bloat.

The core plugin is 100% free, fully functional, and unrestricted. For advanced agency workflows (such as passwordless One-Click Magic Links, GDPR Access Audit Log, Activity Tracking, and Real-Time Webhook/Email Security Alerts), a separate commercial companion add-on is available.

### Key Features for Temporary Login & Access Control

* **⚡ 1-Click Quick Temporary Login**: Generate temporary user accounts in seconds directly from the JIT dashboard, with email alias support (`name+1@domain.com`, `name+role@domain.com`).
* **⏱️ Precise Expiration & Timed Access**: Schedule automated expiration down to the exact minute, synchronized with your WordPress site timezone.
* **🔒 Maximum Login Limits (Threshold)**: Limit allowed logins (e.g. allow only 3 logins). Once reached, the temporary user is automatically locked out.
* **🛡️ Instant Account Lockout (Kill-Switch)**: Instantly revoke access with a single checkbox toggle, without altering user passwords or deleting accounts.
* **🛑 Real-Time Active Session Invalidation**: If an active temporary user is browsing the admin dashboard when their expiration arrives, their session and authentication cookies are revoked immediately.
* **👥 Role & Permission Management (RBAC)**: Create and customize user roles, clone capabilities from standard roles, and enforce the Principle of Least Privilege.
* **📊 Multi-Format Data Export**: Export temporary accounts and access audit logs to CSV, OpenXML Excel (.xlsx), and JSON with formula injection protection.
* **📅 Registration & Creation Date Tracking**: View exact registration dates and relative time elapsed (`Created: 2 hours ago`) in the JIT table, user profiles, and exports.
* **🔍 Search & Filter Toolbar**: Filter temporary users by role, lockout status, or username directly from the administrative dashboard.
* **🌐 Translation Ready (i18n)**: Fully internationalized with POT template included, adhering to WordPress core localization standards.

### Architecture & Cybersecurity

* **Zero Custom Database Tables**: Uses native WordPress user metadata (`get_user_meta`, `update_user_meta`, `delete_user_meta`) and `wp_options`.
* **Zero External Server Latency**: 100% local execution, zero external API tracking calls, fully GDPR compliant.
* **Strict Permission Checks**: Only users with the `promote_users` capability (Administrators) can view or modify temporary parameters.
* **CSRF Protection**: Every action is guarded by WordPress cryptographic nonces.
* **Clean Uninstallation**: `uninstall.php` completely wipes all created user metadata and options upon plugin deletion.

== Commercial Companion Add-on (PRO) ==

For web agencies and IT maintenance professionals requiring advanced capabilities, the separate commercial companion add-on **RGBmind Ephemere PRO** seamlessly integrates with this free plugin:

* **⚡ One-Click Temporary Login Link (Magic Link)**:
  Generate an encrypted, single-use login link from the user profile or dashboard. Contractors and auditors can log in with a single click without sharing passwords. Only the SHA-256 hash is persisted in the database.
* **🛡️ Detailed Access Audit Log & Forensics**:
  View the last 20 logins directly in the user profile and central dashboard, showing exact timestamps, authentication method, duration, and configurable IP addresses (Full Clear Text or GDPR Anonymized).
* **⏱️ Real-Time Activity Tracking & Session Duration**:
  Monitor contractor session duration, active online status, and visited admin pages in real time.
* **🔔 Real-Time Security Email & Webhook Alerts**:
  Receive immediate notifications whenever a temporary account expires, reaches its login cap, or is locked out. Supports HTTP Webhooks for Slack, Discord, Zapier, or Make integrations.

Visit [rgbmind.com/ephemere](https://rgbmind.com/ephemere/) to learn more about the PRO companion add-on.

== Installation ==

1. Upload the `rgbmind-ephemere` folder to your `/wp-content/plugins/` directory, or install directly through **Plugins > Add New** searching for `RGBmind Ephemere` or `temporary login`.
2. Activate the plugin via the **Plugins** screen in WordPress.
3. Use the **1-Click Quick Creation** tool in **RGBmind Ephemere** dashboard, or go to **Users > All Users** and click **Edit** on any user account.
4. Set the desired expiration date/time or maximum login count.
5. Access will be automatically revoked once expiration or login limits are reached.

== Frequently Asked Questions ==

= How do I create a temporary login for a developer or contractor? =
You can create a temporary login in two ways:
1. In the **RGBmind Ephemere** dashboard, use the **1-Click Quick Account Creation** form to instantly generate a temporary account with an expiration timer and email alias.
2. Go to **Users > All Users**, edit any user, check **Enable temporary access restrictions**, and set an expiration date or login limit.

= Does RGBmind Ephemere expire users automatically? =
Yes. Once the expiration date/time passes or the maximum login threshold is reached, the account is automatically locked out. Furthermore, if the user is actively logged in when access expires, their active session is terminated in real time.

= Can I limit the number of logins? =
Yes. You can specify the exact number of allowed logins (e.g., 1, 3, 5). The plugin increments the counter on each login and automatically blocks access once the threshold is reached.

= Is RGBmind Ephemere free to use? =
Yes, 100%. All core features—expiration schedules, login limits, instant lockout, real-time session termination, user table status columns, role management, and multi-format exports—are completely free and unrestricted.

= How is timezone handled for expiration? =
The entered date and time are converted to UTC Unix timestamps based on your site's timezone setting in Settings > General. Every login check compares the current Unix timestamp against the expiration timestamp.

= What happens if a user is logged in when their expiration time arrives? =
The plugin checks session validity in real time on the `init` hook. When an expired temporary user attempts to browse any page, their session is destroyed, cookies are cleared, and they are redirected to the login page with a notification.

= Does this plugin create custom database tables? =
No. RGBmind Ephemere follows WordPress core architecture standards by storing data exclusively in the standard `wp_usermeta` and `wp_options` tables.

= Can temporary users alter their own restrictions? =
No. All fields and handlers enforce strict `current_user_can('promote_users')` permission checks. Non-admin users cannot view the fields or manipulate POST requests.

= What is RGBmind Ephemere PRO? =
RGBmind Ephemere PRO is a separate commercial add-on plugin for agencies needing passwordless One-Click Magic Links, GDPR access audit trails, activity duration tracking, and instant email/webhook security alerts. It installs as a companion plugin alongside this free core plugin.

= Do you offer 1-on-1 customer or technical support? =
No. RGBmind Ephemere and RGBmind Ephemere PRO are provided "as is" under standard licensing without personalized 1-on-1 technical assistance. We actively maintain the software and welcome bug reports, regression findings, and security issues via our official Bug Report & Feedback Hub at https://rgbmind.com/bug-report/?product=rgbmind-ephemere.

== Screenshots ==

1. Temporary access restrictions and security status panel in WordPress user profile edit screen.

== Changelog ==

= 1.3.2 =
* Fix: resolved unparsed PHP code string rendering inside the PRO companion add-on preview accordion for the free edition.
* Fix: removed orphan closing HTML tag in the settings accordion markup.

= 1.3.1 =
* UI & UX Polishing: Enhanced status icons with clearer visual cues and increased contrast.
* Account Details Drawer: Streamlined user info popover trigger and positioning for smoother inspection.
* Settings Accordions: Restructured Settings tab into clean, collapsible accordion panels, closed by default with intuitive titles and context summaries.
* Table Layout Optimization: Refined dashboard table structure and moved account count indicators to the table footer summary for improved layout balance.
* Live Expiration Countdown: Added real-time ticker updating remaining access time dynamically.

= 1.3.0 =
* 1-Click Quick Account Creation: instantly generate temporary accounts with customizable email alias patterns and JIT expirations.
* Role & Capability Management (RBAC): create, clone, customize, and manage user roles and granular permissions.
* Multi-Format Audit Export: download accounts and audit logs in CSV, OpenXML Excel (.xlsx), and JSON formats.
* Native OpenXML Engine: pure-PHP spreadsheet generator with zero external PHP extension dependencies (no ZipArchive required).
* CSV Security Hardening: formula injection mitigation across all exported files.
* Creation Date Tracking: view account registration timestamps and relative time intervals across tables, profiles, and export reports.
* Action Handling Architecture: decoupled dashboard actions to admin_init to ensure clean, header-safe redirects.
* Header UI: added compact PRO security shield indicator.

= 1.2.3 =
* Security Architecture: introduced clear IAM separation into JIT (Just-In-Time Access), RBAC (Role-Based Access Control), and MAC (Mandatory Access Control) sections in both user profiles and administrative dashboard.
* RBAC Matrix & Least-Privilege Protection: added visual role capability table and dynamic zero-trust warning badge when temporary accounts are granted Administrator privileges.
* Mandatory Access Control (MAC): dedicated section for instant kill-switch session revocation and global access auditing.
* Bug Report & Feedback Hub: added direct links to the official RGBmind Bug Report & Feedback Hub in the admin header, dashboard sidebar, and plugin row meta.
* Backward Compatibility: old dashboard query arguments (tab=accounts, tab=license) automatically alias to the new structure.

= 1.2.2 =
* Community Review Card: added dedicated feedback and 5-star review prompt in Settings & Extensions dashboard.
* Plugin Row Meta: added quick review action link in the WordPress plugins management screen.

= 1.2.1 =
* Initial official release on WordPress.org.
* Precise expiration scheduling fully synchronized with WordPress site timezone.
* Login count limits with automatic account lockout upon threshold.
* Instant account lockout toggle for immediate access revocation.
* Real-time active session and authentication cookie invalidation upon expiration.
* Dedicated administrative overview dashboard and user table status column.
* 100% WordPress.org compliant architecture with clean separation between free core and commercial companion add-on.
* Translation Ready (i18n): fully internationalized adhering to native WordPress localization standards (POT template included for community translations via translate.wordpress.org).
* Verified compatibility with WordPress 6.7+ and PHP 8.2+.
