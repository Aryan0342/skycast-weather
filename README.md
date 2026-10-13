# Skycast Weather

A small WordPress plugin that shows live weather with a shortcode. I built it to demonstrate a pattern that matters for any plugin talking to a paid API: **the API key never reaches the browser.**

```
[skycast_weather city="Lahore"]
```

## How it works

```
Visitor's browser
   │  GET /wp-json/skycast/v1/weather?city=Lahore
   │  header X-WP-Nonce            (no API key anywhere in the page)
   ▼
WordPress (this plugin, PHP)
   │  1. checks the nonce and a per-IP rate limit
   │  2. returns cached data if it is less than 10 min old
   │  3. otherwise reads the key from wp_options and calls the API (8 s timeout)
   │  4. returns only whitelisted fields
   ▼
OpenWeatherMap API
```

## What this demonstrates

| Concern | Where |
|---|---|
| API key stored server side, never printed in HTML or passed to JS | `includes/class-skycast-settings.php`, `includes/class-skycast-shortcode.php` |
| REST proxy with nonce check (also for logged-out visitors) | `includes/class-skycast-rest.php` |
| Per-IP throttle using transients | `Skycast_REST::is_rate_limited()` |
| Caching (10 min) and fallback to last known data when the API is down | `Skycast_API::get_weather()` |
| Raw API errors never shown to visitors; config errors hidden from the public | `Skycast_REST::get_weather()` |
| Response whitelisting instead of forwarding the raw API payload | `Skycast_API::shape()` |
| Assets loaded only on pages that use the shortcode | `Skycast_Shortcode::render()` |
| XSS-safe rendering (`textContent`, no `innerHTML`) | `assets/js/skycast.js` |
| Settings API, capability checks, nonces on admin actions | `includes/class-skycast-settings.php` |
| WordPress.org-style readme with an "External services" section | `readme.txt` |

## Install

1. Copy the `skycast-weather` folder into `wp-content/plugins/` and activate it.
2. Go to **Settings > Skycast Weather**, paste a free [OpenWeatherMap](https://home.openweathermap.org/api_keys) key and click **Test connection**.
3. Add `[skycast_weather city="Lahore"]` to any page.

Requires WordPress 6.3+ and PHP 8.1+.

## License

GPL-2.0-or-later
