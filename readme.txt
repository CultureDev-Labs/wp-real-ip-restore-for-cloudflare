=== Cloudflare Real IP ===
Contributors: edouardchelbi
Tags: cloudflare, real ip, proxy, cpanel, logging
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restores real visitor IP behind Cloudflare proxy on cPanel hosts that lack native mod_remoteip support.

== Description ==

On shared cPanel hosts (o2switch, Hostinger, etc.), Apache does not natively restore the client IP from Cloudflare headers. This causes WordPress, WooCommerce, security plugins and access logs to record Cloudflare's edge IP instead of the real visitor IP.

**Cloudflare Real IP** fixes this transparently:

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

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
