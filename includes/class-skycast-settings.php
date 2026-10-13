<?php
/**
 * Settings page: Settings > Skycast Weather.
 *
 * The API key is saved as an option and is never printed back into the
 * page. Leaving the field empty keeps the saved key.
 *
 * @package SkycastWeather
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen for the API key, units and a connection test.
 */
class Skycast_Settings {

	const PAGE = 'skycast-weather';

	/**
	 * Hook into the admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_skycast_test', array( __CLASS__, 'test_connection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SKYCAST_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add the page under Settings.
	 */
	public static function add_page(): void {
		add_options_page(
			__( 'Skycast Weather', 'skycast-weather' ),
			__( 'Skycast Weather', 'skycast-weather' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 */
	public static function action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'skycast-weather' ) . '</a>' );
		return $links;
	}

	/**
	 * Register the option and its fields with the Settings API.
	 */
	public static function register(): void {
		register_setting(
			'skycast',
			SKYCAST_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'skycast_main', '', '__return_false', self::PAGE );

		add_settings_field( 'api_key', __( 'OpenWeatherMap API key', 'skycast-weather' ), array( __CLASS__, 'field_api_key' ), self::PAGE, 'skycast_main', array( 'label_for' => 'skycast-api-key' ) );
		add_settings_field( 'units', __( 'Units', 'skycast-weather' ), array( __CLASS__, 'field_units' ), self::PAGE, 'skycast_main', array( 'label_for' => 'skycast-units' ) );
	}

	/**
	 * Clean the submitted values before they are saved.
	 *
	 * @param mixed $input Raw form values.
	 */
	public static function sanitize( $input ): array {
		$current = Skycast_API::get_settings();
		$input   = is_array( $input ) ? $input : array();

		$api_key = $current['api_key'];
		// Pasted keys often carry spaces or line breaks; a valid key never contains any.
		$new_key = isset( $input['api_key'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( $input['api_key'] ) ) : '';

		if ( ! empty( $input['remove_key'] ) ) {
			$api_key = '';
		} elseif ( '' !== $new_key ) {
			// OpenWeatherMap keys are 32 hexadecimal characters.
			if ( preg_match( '/^[a-f0-9]{32}$/i', $new_key ) ) {
				$api_key = $new_key;
			} else {
				add_settings_error( SKYCAST_OPTION, 'skycast_bad_key', __( 'That does not look like an OpenWeatherMap key (32 letters and numbers). The previous key was kept.', 'skycast-weather' ) );
			}
		}

		$units = ( isset( $input['units'] ) && in_array( $input['units'], array( 'metric', 'imperial' ), true ) ) ? $input['units'] : 'metric';

		return array(
			'api_key' => $api_key,
			'units'   => $units,
		);
	}

	/**
	 * Key field that is always empty: the saved key is never printed back.
	 *
	 * A plain text input on purpose. As a password input, browsers' password
	 * managers autofill the WordPress login password into it.
	 */
	public static function field_api_key(): void {
		$has_key = Skycast_API::has_key();

		printf(
			'<input type="text" id="skycast-api-key" name="%1$s[api_key]" value="" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="%2$s" />',
			esc_attr( SKYCAST_OPTION ),
			esc_attr( $has_key ? __( 'A key is saved. Leave empty to keep it.', 'skycast-weather' ) : __( 'Paste your API key', 'skycast-weather' ) )
		);

		if ( $has_key ) {
			printf(
				'<p><label><input type="checkbox" name="%1$s[remove_key]" value="1" /> %2$s</label></p>',
				esc_attr( SKYCAST_OPTION ),
				esc_html__( 'Remove the saved key', 'skycast-weather' )
			);
		}

		printf(
			'<p class="description">%1$s <a href="https://home.openweathermap.org/api_keys" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
			esc_html__( 'The key is stored on your server and is never sent to visitors\' browsers.', 'skycast-weather' ),
			esc_html__( 'Get a free key', 'skycast-weather' )
		);
	}

	/**
	 * Metric or imperial units.
	 */
	public static function field_units(): void {
		$units   = Skycast_API::get_settings()['units'];
		$choices = array(
			'metric'   => __( 'Metric (°C, m/s)', 'skycast-weather' ),
			'imperial' => __( 'Imperial (°F, mph)', 'skycast-weather' ),
		);

		printf( '<select id="skycast-units" name="%s[units]">', esc_attr( SKYCAST_OPTION ) );
		foreach ( $choices as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $units, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php self::render_test_notice(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'skycast' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<?php if ( Skycast_API::has_key() ) : ?>
				<h2><?php esc_html_e( 'Connection', 'skycast-weather' ); ?></h2>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="skycast_test" />
					<?php wp_nonce_field( 'skycast_test' ); ?>
					<?php submit_button( __( 'Test connection', 'skycast-weather' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'How to use', 'skycast-weather' ); ?></h2>
			<p><?php esc_html_e( 'Add this shortcode to any page or post:', 'skycast-weather' ); ?> <code>[skycast_weather city="Lahore"]</code></p>
		</div>
		<?php
	}

	/**
	 * Call the API once without cache and report the result.
	 */
	public static function test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'skycast-weather' ), 403 );
		}
		check_admin_referer( 'skycast_test' );

		$result  = Skycast_API::get_weather( 'London', false );
		$message = is_wp_error( $result )
			? array(
				'type' => 'error',
				'text' => $result->get_error_message(),
			)
			: array(
				'type' => 'success',
				'text' => __( 'Connected. The weather service answered correctly.', 'skycast-weather' ),
			);

		// Short-lived, per user, so the message survives the redirect.
		set_transient( 'skycast_test_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Show the result of the last connection test, once.
	 */
	private static function render_test_notice(): void {
		$key     = 'skycast_test_' . get_current_user_id();
		$message = get_transient( $key );

		if ( ! is_array( $message ) ) {
			return;
		}
		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( 'success' === $message['type'] ? 'success' : 'error' ),
			esc_html( $message['text'] )
		);
	}
}
