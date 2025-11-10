<?php
/**
 * Settings handler.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	const OPTION = 'spb_settings';

	/**
	 * Initialize hooks if needed.
	 *
	 * @return void
	 */
	public static function init() {
		// Reserved for future runtime hooks if needed.
	}

	/**
	 * Bootstrap default settings on activation.
	 *
	 * @return void
	 */
	public static function bootstrap_defaults() {
		$defaults = array(
			'enable_api'                => true,
			'default_webhook_url'       => '',
			'webhook_secret'            => random_string( 48 ),
			'rate_limit_per_hour'       => 100,
			'default_key_expiration_days'=> 0,
			'auth_mode'                 => 'api_key', // api_key|jwt (optional bonus).
			'delete_data_on_uninstall'  => false,
		);
		$current = get_option( self::OPTION );
		if ( ! is_array( $current ) ) {
			update_option( self::OPTION, $defaults, false );
		} else {
			$merged = array_merge( $defaults, $current );
			update_option( self::OPTION, $merged, false );
		}
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public static function all() {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return $settings;
	}

	/**
	 * Get a setting.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update a setting.
	 *
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set( $key, $value ) {
		$settings         = self::all();
		$settings[ $key ] = $value;
		update_option( self::OPTION, $settings, false );
	}
}


