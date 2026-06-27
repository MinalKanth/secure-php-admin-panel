<div align="center">

# 🔐 Secure PHP Admin Panel

**A self-contained, security-hardened admin panel built in vanilla PHP — no frameworks, no bloat, no public registration surface.**

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](#license)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](#contributing)

[Features](#-features) •
[Security](#-security-checklist) •
[Setup](#-quick-start) •
[Structure](#-project-structure) •
[FAQ](#-faq)

</div>

---

## 📖 Overview

This is a complete admin panel — login, dashboard, profile management, and full CRUD over a `users` table — built with raw PHP and PDO instead of a framework, so every security control is visible, auditable, and easy to reason about.

There is **no public or authenticated registration page anywhere**. Admin accounts are provisioned only via a CLI script run on the server, so the only way to create a login is if you already have shell access.

> Built for developers who want a real, hardened starting point for an internal tool or client admin panel — not a toy demo with `if ($_POST['password'] == 'admin')`.

---

## ✨ Features

| Area | What you get |
|---|---|
| 🔑 **Authentication** | Login with rate limiting, account lockout, and constant-time password checks |
| 👤 **Profile management** | Admin can update their own name/email and change their password |
| 📋 **User CRUD** | Create, read, update, delete — with search and pagination |
| 📊 **Dashboard** | Live stats + a real activity audit log (who did what, when, from where) |
| 🚫 **No registration** | Admin accounts only created via `php create_admin.php` on the server |
| 🛡️ **Defense in depth** | CSRF tokens, security headers, hardened sessions, `.htaccess` lockdown |

---

## 🛡️ Security Checklist

This isn't "secure" as a marketing word — here's exactly what's implemented and why.

| Threat | Mitigation |
|---|---|
| **SQL Injection** | Every query uses PDO prepared statements with bound parameters; `PDO::ATTR_EMULATE_PREPARES` disabled so binding is real |
| **XSS** | All output escaped through `htmlspecialchars()`; strict Content-Security-Policy header restricts script sources |
| **CSRF** | Every state-changing form carries a per-session token verified with `hash_equals()` |
| **Session hijacking / fixation** | Session ID regenerates on login + every 5 minutes; `HttpOnly` + `SameSite=Strict` + `Secure` cookies; sessions bound to a browser fingerprint |
| **Brute-force login** | DB-backed lockout after 5 failed attempts (15 min cooldown) + session-based rate limiting |
| **Timing attacks** | `password_verify()` always runs, even for unknown usernames, so response time can't leak which accounts exist |
| **Password storage** | Bcrypt via `password_hash()` / `password_verify()`, with automatic rehash if the cost factor increases |
| **Clickjacking** | `X-Frame-Options: DENY` |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` |
| **Info disclosure** | Errors logged to file, never shown to visitors; generic messages on DB failure |
| **Unauthorized account creation** | No registration page exists — full stop |
| **Direct file access** | `.htaccess` denies access to `config/`, `includes/`, `logs/`, `uploads/`, and `create_admin.php` |
| **Audit trail** | Every login, logout, failed attempt, and user change is logged with admin ID + IP |

---

## 🚀 Quick Start

### Requirements
- PHP 8.0+ with the `pdo_mysql` extension
- MySQL or MariaDB
- Apache (for `.htaccess` rules) — see the [Nginx note](#-nginx-note) if you're not on Apache

### 1. Clone and import the database
```bash
git clone https://github.com/your-username/secure-php-admin-panel.git
cd secure-php-admin-panel
mysql -u root -p < database.sql
```
This creates the `admin_panel` database and its tables. It does **not** create a working login — the seeded row has a placeholder hash on purpose, so nothing ships with a guessable default password.

### 2. Create a dedicated database user
Never point this app at `root`:
```sql
CREATE USER 'admin_panel_user'@'localhost' IDENTIFIED BY 'a-strong-unique-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON admin_panel.* TO 'admin_panel_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure the app
Copy the example config and fill in your own values — `config.php` is gitignored, so your real credentials never get committed:
```bash
cp config/config.example.php config/config.php
```
Then edit `config/config.php`:
```php
define('DB_USER', 'admin_panel_user');
define('DB_PASS', 'a-strong-unique-password');
define('FORCE_SECURE_COOKIES', true); // flip on once HTTPS is live
```

### 4. Create your admin account
```bash
php create_admin.php
```
Interactive prompt — asks for username, email, full name, and a password (12+ chars, mixed case, includes a number). This is the **only** way an admin account gets created.

### 5. Deploy
Upload the folder to your server. The included `.htaccess` files already block direct browser access to `config/`, `includes/`, `logs/`, `uploads/`, and `create_admin.php`.

### 6. Sign in
Visit `login.php` (or `/`, which redirects there) and log in with the account you just created.

---

## 📁 Project Structure

```
secure-php-admin-panel/
├── config/
│   ├── config.example.php   # Template — copy to config.php and edit
│   ├── config.php           # Your real settings (gitignored, not tracked)
│   └── database.php         # PDO connection w/ secure defaults
├── includes/
│   ├── bootstrap.php        # Security headers + shared includes
│   ├── session.php          # Hardened session handling
│   ├── csrf.php              # CSRF token generation/validation
│   ├── auth.php               # Login, lockout, rate limiting helpers
│   ├── header.php / footer.php  # Shared page chrome
├── assets/css/style.css     # Clean, dependency-free styling
├── database.sql             # Schema: admins, users, activity_log
├── create_admin.php         # CLI-only admin account creator
├── login.php / logout.php   # Authentication
├── dashboard.php             # Stats + activity log
├── profile.php                # Self-service profile + password change
├── users.php / user_form.php   # Full user CRUD
└── README.md
```

---

## 🌐 Nginx Note

The included `.htaccess` rules only apply to Apache. On Nginx, add the equivalent to your server block:

```nginx
location ~ ^/(config|includes|logs|uploads)/ {
    deny all;
}
location ~ \.(sql|bak|swp)$ {
    deny all;
}
location = /create_admin.php {
    deny all;
}
```

---

## ❓ FAQ

**Why is there no registration page?**
By design. Admin panels shouldn't let anyone self-register into privileged access. Accounts are created by whoever already has server access, via `create_admin.php`.

**Can I add more admins later?**
Yes — run `php create_admin.php` again on the server any time.

**Is this "unhackable"?**
No software is. This implements current best practices (OWASP-aligned: parameterized queries, CSRF tokens, hardened sessions, bcrypt, rate limiting). Real-world security also depends on keeping PHP patched, using HTTPS, and good server hygiene.

**Can I use this for client projects?**
Yes — MIT licensed, no attribution required (though it's appreciated).

---

## 🔧 Ongoing Maintenance

- Keep PHP and your MySQL driver patched
- Rotate database credentials periodically
- Never share one admin login across multiple people — create separate accounts
- Review `activity_log` periodically for unexpected access patterns

---

## 🤝 Contributing

Issues and PRs welcome. If you find a security issue, please open an issue describing it generally rather than posting a full exploit — happy to work through it together.

## 📄 License

MIT — do whatever you'd like with it.

---

<div align="center">

If this saved you some setup time, a ⭐ on the repo is always appreciated.

</div>
