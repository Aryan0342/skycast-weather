<?php
/**
 * The [skycast_weather] shortcode.
 *
 * Usage: [skycast_weather city="Lahore"]
 *
 * @package SkycastWeather
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the weather card placeholder and loads its assets.
 */
class Skycast_Shortcode {

	/**
	 * Whether the JS config has already been printed on this page.
	 *
	 * @var bool
	 */
	private static $config_added = false;

	/**
	 * Hook into WordPress.
	 */
	public static function init(): void {
		add_shortcode( 'skycast_weather', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) the assets, so they load only where the shortcode is used.
	 *
	 * Also called from render(): block themes render post content before
	 * wp_enqueue_scripts fires, and wp_add_inline_script() silently does
	 * nothing on a handle that is not registered yet.
	 */
	public static function register_assets(): void {
		if ( wp_script_is( 'skycast-weather', 'registered' ) ) {
			return;
		}

		wp_register_style( 'skycast-weather', SKYCAST_URL . 'assets/css/skycast.css', array(), SKYCAST_VERSION );
		wp_register_script(
			'skycast-weather',
			SKYCAST_URL . 'assets/js/skycast.js',
			array(),
			SKYCAST_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Output an empty card; the script fills it with live data.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts( array( 'city' => 'London' ), $atts, 'skycast_weather' );
		$city = sanitize_text_field( $atts['city'] );

		self::register_assets();
		wp_enqueue_style( 'skycast-weather' );
		wp_enqueue_script( 'skycast-weather' );
		self::add_config();

		return sprintf(
			'<div class="skycast-card" data-city="%1$s" aria-live="polite"><p class="skycast-loading">%2$s</p></div>',
			esc_attr( $city ),
			esc_html__( 'Loading weather…', 'skycast-weather' )
		);
	}

	/**
	 * Give the script the route URL, a nonce and its labels. Never the API key.
	 */
	private static function add_config(): void {
		if ( self::$config_added ) {
			return;
		}
		self::$config_added = true;

		$config = array(
			'restUrl' => esc_url_raw( rest_url( Skycast_REST::NAMESPACE_V1 . '/weather' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'error'    => __( 'Weather is temporarily unavailable.', 'skycast-weather' ),
				'feels'    => __( 'Feels like', 'skycast-weather' ),
				'humidity' => __( 'Humidity', 'skycast-weather' ),
				'wind'     => __( 'Wind', 'skycast-weather' ),
				'stale'    => __( 'Showing the last known weather.', 'skycast-weather' ),
			),
		);

		wp_add_inline_script( 'skycast-weather', 'window.skycastConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}
