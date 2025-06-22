# WHMCS Flutterwave Payment Gateway Module

This module integrates Flutterwave payment solutions with WHMCS, supporting both Inline (JavaScript) and Redirect (server-to-server/cURL) payment flows.

## Features
- Supports all major Flutterwave payment methods (card, account, USSD, bank transfer, QR, mobile money, Google Pay, Apple Pay, Opay, Barter, WeChat, etc.)
- Admin can select supported payment methods via gateway config (comma-separated list)
- Toggle for Test/Sandbox Mode with separate API credentials and clear UI/log indicators
- Robust error handling and user-friendly messages
- Logs all gateway activity (when enabled)
- Compatible with WHMCS 8.x+

## Installation
1. Copy the contents of this repository to your WHMCS modules/gateways and modules/gateways/callback directories:
   - `flutterwave.php` → `modules/gateways/flutterwave.php`
   - `callback/flutterwave.php` → `modules/gateways/callback/flutterwave.php`
2. In WHMCS admin, go to **Setup > Payments > Payment Gateways** and activate Flutterwave.
3. Configure the gateway:
   - Enter your **Live** and **Test** (Sandbox) Public/Secret Keys from your Flutterwave dashboard.
   - Select payment flow: Inline (JavaScript) or Redirect (server-to-server).
   - Enter supported payment methods as a comma-separated list (e.g. `card,account,ussd`).
   - Enable Test/Sandbox Mode to use sandbox credentials and environment.
   - Optionally enable gateway logging for debugging.

## Usage
- Clients will see a Pay Now button on invoices. Payment options and flow depend on your admin config.
- Inline flow uses Flutterwave's JavaScript widget. Redirect flow posts to the callback handler, which initializes payment via cURL and redirects the user.
- On payment completion, users are redirected back to the invoice with a status message.

## Troubleshooting
- If you see payment errors, check your API keys, payment method selection, and Flutterwave dashboard for sandbox/live restrictions.
- Enable gateway logging for detailed debug info (logs appear in WHMCS admin under Utilities > Logs > Module Log).
- If logs do not appear, check WHMCS permissions, database health, and ensure logging is enabled in both the gateway config and WHMCS admin.

## Security
- All sensitive data is masked in logs.
- Always use HTTPS for your WHMCS installation.

## Support
For issues or feature requests, please contact the module author or open an issue in your repository.

---
**Author:** Atanunu Igbunuroghene <atanunuigbunu@hmjp.com>  
**Copyright:** HMJP Limited 2025

