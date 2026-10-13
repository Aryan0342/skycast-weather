<?php
/**
 * Plugin Name:       Skycast Weather
 * Description:       Live weather on any page with the [skycast_weather] shortcode. The API key stays on the server: the browser only talks to a WordPress REST route.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Author:            Aryan
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       skycast-weather
 *
 * @package SkycastWeather
 */

// Stop if this file is opened directly instead of being loaded by WordPress.
defined( 'ABSPATH' ) || exit;

define( 'SKYCAST_VERSION', '1.0.0' );
define( 'SKYCAST_FILE', __FILE__ );
define( 'SKYCAST_DIR', plugin_dir_path( __FILE__ ) );
define( 'SKYCAST_URL', plugin_dir_url( __FILE__ ) );
define( 'SKYCAST_OPTION', 'skycast_settings' );

require_once SKYCAST_DIR . 'includes/class-skycast-api.php';
require_once SKYCAST_DIR . 'includes/class-skycast-rest.php';
require_once SKYCAST_DIR . 'includes/class-skycast-shortcode.php';
require_once SKYCAST_DIR . 'includes/class-skycast-settings.php';

Skycast_REST::init();
Skycast_Shortcode::init();

if ( is_admin() ) {
	Skycast_Settings::init();
}
