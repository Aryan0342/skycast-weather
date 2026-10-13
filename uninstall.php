<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package SkycastWeather
 */

// Only run when WordPress itself triggers the uninstall.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove the saved settings, including the API key. Cached weather expires on its own.
delete_option( 'skycast_settings' );
