# RGBmind Ephemere – Temporary Logins & Timed Access Manager

[![WordPress Compatibility](https://img.shields.io/badge/WordPress-5.8%20--%207.1%2B-blue.svg)](https://wordpress.org/plugins/rgbmind-ephemere/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%20--%208.2%2B-purple.svg)](https://php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Release](https://img.shields.io/badge/Release-v1.2.1-orange.svg)](https://github.com/RGBmind-Team/ephemere/releases)

A lightweight, secure, and cybersecurity-focused access control plugin designed to manage temporary and time-limited user access on any WordPress site.

Built to meet strict WordPress development standards and cybersecurity principles, **RGBmind Ephemere** allows administrators to grant access to external developers, partner agencies, IT contractors, auditors, or clients with the guarantee that access will be automatically and reliably revoked when predefined limits are reached.

---

## 🚀 Key Features

* **⏱️ Precise Expiration Schedule**: Set exact expiration date and time using native HTML5 datetime-local inputs, fully synchronized with your WordPress site timezone.
* **🔢 Maximum Login Threshold**: Limit total allowed logins (e.g. allow only 3 logins). Once the threshold is met, the account is automatically locked out.
* **🔒 Instant Account Lockout**: Instantly revoke access with a single checkbox toggle, without altering user passwords or deleting accounts.
* **📊 Access Monitoring**: Real-time read-only view of login counts and last login timestamp, with an option to reset the counter when re-enabling access.
* **⚡ Real-Time Active Session Invalidation**: If an active temporary user is browsing the admin dashboard when their expiration time arrives, their session and authentication cookies are revoked immediately.
* **🏷️ Users List Status Column**: Visual account health badge right inside the WordPress Users screen (*Active, Expired, Locked, Logins Exhausted*).
* **🌐 Multi-Language Global Suite**: Fully translated into 6 languages (*English, Italian, Spanish, French, German, Brazilian Portuguese*) with automatic detection and manual override.

---

## 🛡️ Architecture & Cybersecurity

* **Zero Custom Database Tables**: Uses native WordPress user metadata (`wp_usermeta`) and `wp_options`.
* **Zero External Network Dependencies**: 100% local execution, zero external API tracking calls, fully GDPR compliant.
* **Strict Permission Checks**: Only users with the `promote_users` capability (Administrators) can view or modify temporary parameters.
* **CSRF Protection**: Every action is guarded by WordPress cryptographic nonces.
* **Clean Uninstallation**: `uninstall.php` completely wipes all created user metadata and options upon plugin deletion.

---

## 💼 Commercial Companion Add-on (PRO)

For web agencies and IT maintenance professionals requiring advanced capabilities, the separate commercial companion add-on **RGBmind Ephemere PRO** seamlessly integrates with this free core plugin:

* **⚡ One-Click Temporary Login Link (Magic Link)**: Generate encrypted, single-use login links for direct passwordless access for contractors.
* **🛡️ Detailed GDPR Access Audit Log**: View the last 10 logins directly in the user profile with anonymized IPs and authentication methods.
* **🔔 Security Email & Webhook Alerts**: Receive instant notifications upon account lockout or expiration (compatible with Slack, Discord, Zapier).

Learn more at [rgbmind.com](https://rgbmind.com).

---

## 📦 Installation

1. Clone or download this repository.
2. Place the `rgbmind-ephemere` folder into your `/wp-content/plugins/` directory.
3. Activate the plugin via the **Plugins** screen in WordPress.
4. Navigate to **Users > All Users** and edit any user account to configure temporary access limits.

---

## 📄 License

This project is licensed under the **GNU General Public License v2 or later** (GPLv2+).