# Seraph Systems — WP Hardening

Professional WordPress hardening plugin focused on **secure configuration**, not malware scanning or cloud lock-in.

## Principles

- Administrator always in control (analyze → explain → backup → apply → verify)
- Lightweight, local-only (no telemetry / remote license)
- Reversible changes with timestamped backups

## Install

1. Copy `wp-hardening` into `wp-content/plugins/`
2. Activate **Seraph Systems WP Hardening**
3. Open **WP Hardening → Setup Wizard**

## Recovery (Stealth Login)

If locked out:

- Define `SERAPH_DISABLE_LOGIN_PROTECTION` as `true` in `wp-config.php`, or
- Create empty file `wp-content/seraph-wph-disable-login.lock`

## Requirements

- PHP 8.2+
- WordPress 6.4+

Vendor: **Seraph Systems** · Product: **WP Hardening**
