=== Skycast Weather ===
Contributors: aryan
Tags: weather, shortcode, forecast
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show live weather on any page with a shortcode. Your API key stays on your server.

== Description ==

Skycast Weather adds a small weather card to any page or post.

* Add `[skycast_weather city="Lahore"]` anywhere, including Elementor, Divi and the classic editor.
* Your API key is stored on your server and is never sent to visitors' browsers.
* Results are cached for 10 minutes, so pages stay fast.
* If the weather service is down, the last known weather is shown instead of an error.
* Scripts and styles load only on pages that use the shortcode.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings > Skycast Weather and paste your free OpenWeatherMap API key.
3. Click "Test connection".
4. Add `[skycast_weather city="Your city"]` to any page.

== Frequently Asked Questions ==

= Where do I get an API key? =

Create a free account at openweathermap.org, then open "API keys". New keys can take up to 2 hours to activate.

= Can visitors see my API key? =

No. The visitor's browser only talks to your own site. Your site calls the weather service from the server.

== External services ==

This plugin connects to the OpenWeatherMap API to get current weather data.

* What is sent: the city name from the shortcode, the units setting and your API key.
* When: the first time a page with the shortcode is viewed, then at most every 10 minutes per city, and when you click "Test connection".
* Sent from: your web server. Visitors' browsers do not contact OpenWeatherMap.

OpenWeatherMap terms: https://openweather.co.uk/api/files/file/Openweather_website_terms_and_conditions_of_use.pdf
OpenWeatherMap privacy policy: https://openweather.co.uk/privacy-policy

== Changelog ==

= 1.0.0 =
* First release.
