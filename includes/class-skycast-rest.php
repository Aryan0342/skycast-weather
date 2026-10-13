<?php
/**
 * Public REST route used by the shortcode.
 *
 * The browser calls /wp-json/skycast/v1/weather on the site itself. This
 * route then calls the weather API from PHP, so the browser never sees the
 * API key and never talks to the weather service directly.
 *
 * @package SkycastWeather
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and guards the weather proxy route.
 */
class Skycast_REST {

	const NAMESPACE_V1 = 'skycast/v1';
	const RATE_LIMIT   = 30;
	const RATE_WINDOW  = 10 * MINUTE_IN_SECONDS;

	/**
	 * Hook into WordPress.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register GET /skycast/v1/weather?city=...
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/weather',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_weather' ),
				'permission_callback' => array( __CLASS__, 'check_request' ),
				'args'                => array(
					'city' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_city' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Reject empty or absurdly long city names before any API call.
	 *
	 * @param mixed $value Raw parameter value.
	 */
	public static function validate_city( $value ): bool {
		return is_string( $value ) && '' !== trim( $value ) && mb_strlen( $value ) <= 80;
	}

	/**
	 * Nonce check, then a simple per-IP throttle.
	 *
	 * Core only checks the REST nonce for logged-in users, so we verify it
	 * ourselves to cover anonymous visitors too.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public static function check_request( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'skycast_bad_nonce', __( 'This page has expired. Please reload it.', 'skycast-weather' ), array( 'status' => 403 ) );
		}

		if ( self::is_rate_limited() ) {
			return new WP_Error( 'skycast_rate_limited', __( 'Too many requests. Please wait a few minutes.', 'skycast-weather' ), array( 'status' => 429 ) );
		}

		return true;
	}

	/**
	 * Return the weather, or a message safe to show to any visitor.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_weather( WP_REST_Request $request ) {
		$result = Skycast_API::get_weather( $request->get_param( 'city' ) );

		if ( is_wp_error( $result ) ) {
			// Configuration problems are for the site owner, not for visitors.
			if ( in_array( $result->get_error_code(), array( 'skycast_no_key', 'skycast_auth' ), true ) ) {
				return new WP_Error( 'skycast_unavailable', __( 'Weather is temporarily unavailable.', 'skycast-weather' ), array( 'status' => 503 ) );
			}
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Count requests per IP in a transient. Allows RATE_LIMIT calls per RATE_WINDOW.
	 */
	private static function is_rate_limited(): bool {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'skycast_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );

		if ( $hits >= self::RATE_LIMIT ) {
			return true;
		}

		set_transient( $key, $hits + 1, self::RATE_WINDOW );
		return false;
	}
}
