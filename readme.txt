=== Real IP Restore for Cloudflare ===
Contributors: websolman
Tags: cloudflare, real ip, proxy, cpanel, logging
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.3.1
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restores real visitor IP behind Cloudflare proxy on cPanel hosts that lack native mod_remoteip support.

== Description ==

On many shared cPanel hosts - Bluehost, HostGator, Namecheap, A2 Hosting, GoDaddy, and European providers such as IONOS, OVHcloud or Krystal - Apache does not natively restore the client IP from Cloudflare headers. This causes WordPress, WooCommerce, security plugins and access logs to record Cloudflare's edge IP instead of the real visitor IP.

**Real IP Restore for Cloudflare** fixes this transparently:

1. On every request, checks whether `REMOTE_ADDR` belongs to a Cloudflare IP range.
2. If yes, replaces `REMOTE_ADDR` with the value from `CF-Connecting-IP` (or `X-Forwarded-For` as fallback).
3. IP ranges are fetched once per day from Cloudflare's official endpoints and cached as a WordPress transient.

**Features:**

* Zero configuration — works out of the box.
* IPv4 and IPv6 support.
* Daily auto-refresh of Cloudflare IP ranges.
* Manual refresh from Settings → CF Real IP.
* Falls back to hardcoded ranges if Cloudflare endpoints are unreachable.
* Lightweight — no database tables, no options, no JavaScript.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via Plugins → Installed Plugins.
3. Done. Visitor IPs are now correctly logged.

== Frequently Asked Questions ==

= Does this work with WooCommerce fraud detection? =
Yes. All plugins that read `$_SERVER['REMOTE_ADDR']` will receive the real visitor IP.

= What if my server already handles real IPs via mod_remoteip? =
The plugin checks the IP against Cloudflare ranges before replacing it. If `REMOTE_ADDR` is not a Cloudflare IP, nothing is changed.

= Can I manually refresh the IP range cache? =
Yes — Settings → CF Real IP → Refresh IP Ranges Cache.

== Changelog ==

= 1.3.1 =
* Compatibility check against WordPress 7.0.
* Translations loaded automatically via plugin slug (removed manual load_plugin_textdomain call).

= 1.3.0 =
* Added a manual "Refresh IP Ranges Cache" action on the settings screen.
* Settings page now displays the current detected REMOTE_ADDR.

= 1.2.0 =
* Hardened header handling: visitor IP is only trusted when the request originates from a verified Cloudflare range (anti-spoofing).
* Added French and Spanish translations.

= 1.1.0 =
* Added IPv6 range matching.
* Daily auto-refresh of Cloudflare IP ranges via WP-Cron.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.3.1 =
Compatibility with WordPress 7.0 and cleaner translation loading.
