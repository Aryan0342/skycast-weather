<?php
/**
 * Server-side client for the OpenWeatherMap API.
 *
 * This is the only place that reads the API key. It runs in PHP on the
 * server, so the key never reaches the visitor's browser.
 *
 * @package SkycastWeather
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches weather data, caches it and turns API failures into clean errors.
 */
class Skycast_API {

	const ENDPOINT  = 'https://api.openweathermap.org/data/2.5/weather';
	const TIMEOUT   = 8;
	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;
	const STALE_TTL = DAY_IN_SECONDS;

	/**
	 * Saved settings merged with defaults.
	 *
	 * @return array{api_key: string, units: string}
	 */
	public static function get_settings(): array {
		$defaults = array(
			'api_key' => '',
			'units'   => 'metric',
		);

		return wp_parse_args( (array) get_option( SKYCAST_OPTION, array() ), $defaults );
	}

	/**
	 * Whether an API key has been saved.
	 */
	public static function has_key(): bool {
		return '' !== self::get_settings()['api_key'];
	}

	/**
	 * Current weather for a city.
	 *
	 * Fresh results are cached for 10 minutes. A second, longer-lived copy is
	 * kept so we can still show the last known weather if the API is down.
	 *
	 * @param string $city      City name, already sanitized.
	 * @param bool   $use_cache False to force a live call (used by "Test connection").
	 * @return array|WP_Error Whitelisted weather fields, or an error with an HTTP status.
	 */
	public static function get_weather( string $city, bool $use_cache = true ) {
		$settings = self::get_settings();

		if ( '' === $settings['api_key'] ) {
			return new WP_Error( 'skycast_no_key', __( 'No API key is saved yet.', 'skycast-weather' ), array( 'status' => 503 ) );
		}

		$hash      = md5( strtolower( $city ) . '|' . $settings['units'] );
		$cache_key = 'skycast_w_' . $hash;
		$stale_key = 'skycast_ws_' . $hash;

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$url = add_query_arg(
			array(
				'q'     => rawurlencode( $city ),
				'units' => $settings['units'],
				'appid' => rawurlencode( $settings['api_key'] ),
			),
			self::ENDPOINT
		);

		$response = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) );
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Network failure, timeout or server error: fall back to the last known data.
		if ( 0 === $status || $status >= 500 ) {
			$stale = get_transient( $stale_key );
			if ( false !== $stale ) {
				$stale['stale'] = true;
				return $stale;
			}
			// Never forward the raw transport error: it could contain the request URL, and the key with it.
			return new WP_Error( 'skycast_unavailable', __( 'The weather service is not responding. Please try again later.', 'skycast-weather' ), array( 'status' => 503 ) );
		}

		if ( 401 === $status ) {
			return new WP_Error( 'skycast_auth', __( 'The weather service rejected the API key. New keys can take up to 2 hours to activate.', 'skycast-weather' ), array( 'status' => 503 ) );
		}

		if ( 404 === $status ) {
			return new WP_Error( 'skycast_not_found', __( 'City not found.', 'skycast-weather' ), array( 'status' => 404 ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $body ) ) {
			return new WP_Error( 'skycast_bad_response', __( 'Unexpected answer from the weather service.', 'skycast-weather' ), array( 'status' => 502 ) );
		}

		$weather = self::shape( $body, $settings['units'] );

		set_transient( $cache_key, $weather, self::CACHE_TTL );
		set_transient( $stale_key, $weather, self::STALE_TTL );

		return $weather;
	}

	/**
	 * Keep only the fields the front end needs, never the raw API response.
	 *
	 * @param array  $body  Decoded API response.
	 * @param string $units "metric" or "imperial".
	 */
	private static function shape( array $body, string $units ): array {
		return array(
			'city'        => sanitize_text_field( $body['name'] ?? '' ),
			'country'     => sanitize_text_field( $body['sys']['country'] ?? '' ),
			'temp'        => isset( $body['main']['temp'] ) ? (int) round( (float) $body['main']['temp'] ) : null,
			'feels_like'  => isset( $body['main']['feels_like'] ) ? (int) round( (float) $body['main']['feels_like'] ) : null,
			'humidity'    => isset( $body['main']['humidity'] ) ? (int) $body['main']['humidity'] : null,
			'wind'        => isset( $body['wind']['speed'] ) ? round( (float) $body['wind']['speed'], 1 ) : null,
			'description' => sanitize_text_field( $body['weather'][0]['description'] ?? '' ),
			'icon'        => preg_replace( '/[^a-z0-9]/', '', (string) ( $body['weather'][0]['icon'] ?? '' ) ),
			'units'       => $units,
			'updated'     => time(),
			'stale'       => false,
		);
	}
}
