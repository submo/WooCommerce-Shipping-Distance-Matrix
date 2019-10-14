<?php
/**
 * Helpers file
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.5.0
 *
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Check if plugin is active
 *
 * @param string $plugin_file Plugin file name.
 */
function wcsdm_is_plugin_active( $plugin_file ) {
	$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}

	return in_array( $plugin_file, $active_plugins, true ) || array_key_exists( $plugin_file, $active_plugins );
}

/**
 * Check if pro version plugin is installed and activated
 *
 * @since    1.5.0
 * @return bool
 */
function wcsdm_is_pro() {
	return wcsdm_is_plugin_active( 'wcsdm-pro/wcsdm-pro.php' );
}

/**
 * Get i18n strings
 *
 * @param string $key Strings key.
 * @param string $default Default value.
 * @return mixed
 */
function wcsdm_i18n( $key = '', $default = '' ) {
	$i18n = array(
		'drag_marker'  => __( 'Drag this marker or search your address at the input above.', 'wcsdm' ),
		// translators: %s = distance unit.
		'per_unit'     => __( 'Per %s', 'wcsdm' ),
		'map_is_error' => __( 'Map is error', 'wcsdm' ),
		'latitude'     => __( 'Latitude', 'wcsdm' ),
		'longitude'    => __( 'Longitude', 'wcsdm' ),
		'buttons'      => array(
			'Get API Key'           => __( 'Get API Key', 'wcsdm' ),
			'Back'                  => __( 'Back', 'wcsdm' ),
			'Cancel'                => __( 'Cancel', 'wcsdm' ),
			'Apply Changes'         => __( 'Apply Changes', 'wcsdm' ),
			'Confirm Delete'        => __( 'Confirm Delete', 'wcsdm' ),
			'Delete Selected Rates' => __( 'Delete Selected Rates', 'wcsdm' ),
			'Add New Rate'          => __( 'Add New Rate', 'wcsdm' ),
			'Save Changes'          => __( 'Save Changes', 'wcsdm' ),
		),
		'errors'       => array(
			// translators: %s = Field name.
			'field_required'        => __( '%s field is required', 'wcsdm' ),
			// translators: %1$s = Field name, %2$d = Minimum field value rule.
			'field_min_value'       => __( '%1$s field value cannot be lower than %2$d', 'wcsdm' ),
			// translators: %1$s = Field name, %2$d = Maximum field value rule.
			'field_max_value'       => __( '%1$s field value cannot be greater than %2$d', 'wcsdm' ),
			// translators: %s = Field name.
			'field_numeric'         => __( '%s field value must be numeric', 'wcsdm' ),
			// translators: %s = Field name.
			'field_numeric_decimal' => __( '%s field value must be numeric and decimal', 'wcsdm' ),
			// translators: %s = Field name.
			'field_select'          => __( '%s field value selected is not exists', 'wcsdm' ),
			// translators: %1$d = row number, %2$s = error message.
			'duplicate_rate'        => __( 'Each shipping rules combination for each row must be unique. Please fix duplicate shipping rules for rate row %1$d: %2$s', 'wcsdm' ),
			'need_upgrade'          => array(
				// translators: %s = Field name.
				'general'         => __( '%s field value only changeable in pro version. Please upgrade!', 'wcsdm' ),
				'total_cost_type' => __( 'Total cost type "Match Formula" options only available in pro version. Please upgrade!', 'wcsdm' ),
			),
		),
		'Save Changes' => __( 'Save Changes', 'wcsdm' ),
		'Add New Rate' => __( 'Add New Rate', 'wcsdm' ),
	);

	if ( ! empty( $key ) && is_string( $key ) ) {
		$keys = explode( '.', $key );

		$temp = $i18n;
		foreach ( $keys as $path ) {
			$temp = &$temp[ $path ];
		}

		return is_null( $temp ) ? $default : $temp;
	}

	return $i18n;
}

/**
 * Get shipping method instances
 *
 * @since 2.0
 *
 * @param bool $enabled_only Filter to includes only enabled instances.
 * @return array
 */
function wcsdm_instances( $enabled_only = true ) {
	$instances = array();

	$zone_data_store = new WC_Shipping_Zone_Data_Store();

	$shipping_methods = $zone_data_store->get_methods( '0', $enabled_only );

	if ( $shipping_methods ) {
		foreach ( $shipping_methods as $shipping_method ) {
			if ( WCSDM_METHOD_ID !== $shipping_method->method_id ) {
				continue;
			}

			$instances[] = array(
				'zone_id'     => 0,
				'method_id'   => $shipping_method->method_id,
				'instance_id' => $shipping_method->instance_id,
			);
		}
	}

	$zones = WC_Shipping_Zones::get_zones();

	if ( ! empty( $zones ) ) {
		foreach ( $zones as $zone ) {
			$shipping_methods = $zone_data_store->get_methods( $zone['id'], $enabled_only );
			if ( $shipping_methods ) {
				foreach ( $shipping_methods as $shipping_method ) {
					if ( WCSDM_METHOD_ID !== $shipping_method->method_id ) {
						continue;
					}

					$instances[] = array(
						'zone_id'     => 0,
						'method_id'   => $shipping_method->method_id,
						'instance_id' => $shipping_method->instance_id,
					);
				}
			}
		}
	}

	return apply_filters( 'wcsdm_instances', $instances );
}

/**
 * Inserts a new key/value before the key in the array.
 *
 * @since 2.0.7
 *
 * @param string $before_key The key to insert before.
 * @param array  $array An array to insert in to.
 * @param string $new_key The new key to insert.
 * @param mixed  $new_value The new value to insert.
 *
 * @return array
 */
function wcsdm_array_insert_before( $before_key, $array, $new_key, $new_value ) {
	if ( ! array_key_exists( $before_key, $array ) ) {
		return $array;
	}

	$new = array();

	foreach ( $array as $k => $value ) {
		if ( $k === $before_key ) {
			$new[ $new_key ] = $new_value;
		}

		$new[ $k ] = $value;
	}

	return $new;
}

/**
 * Inserts a new key/value after the key in the array.
 *
 * @since 2.0.7
 *
 * @param string $after_key The key to insert after.
 * @param array  $array An array to insert in to.
 * @param string $new_key The new key to insert.
 * @param mixed  $new_value The new value to insert.
 *
 * @return array
 */
function wcsdm_array_insert_after( $after_key, $array, $new_key, $new_value ) {
	if ( ! array_key_exists( $after_key, $array ) ) {
		return $array;
	}

	$new = array();

	foreach ( $array as $k => $value ) {
		$new[ $k ] = $value;

		if ( $k === $after_key ) {
			$new[ $new_key ] = $new_value;
		}
	}

	return $new;
}

/**
 * Check is in development environment.
 *
 * @since ??
 *
 * @return bool
 */
function wcsdm_is_dev_env() {
	if ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) {
		return true;
	}

	if ( function_exists( 'getenv' ) && getenv( 'WCSDM_DEV' ) ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'wcsdm_autoload' ) ) :
	/**
	 * Class autoload
	 *
	 * @since ??
	 *
	 * @param string $class Class name.
	 *
	 * @return void
	 */
	function wcsdm_autoload( $class ) {
		$class = strtolower( $class );

		if ( strpos( $class, 'wcsdm' ) !== 0 ) {
			return;
		}

		require_once WCSDM_PATH . 'includes/classes/class-' . str_replace( '_', '-', $class ) . '.php';
	}
endif;

if ( ! function_exists( 'wcsdm_is_calc_shipping' ) ) :
	/**
	 * Check if current request is shipping calculator form.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	function wcsdm_is_calc_shipping() {
		$field  = 'woocommerce-shipping-calculator-nonce';
		$action = 'woocommerce-shipping-calculator';

		if ( isset( $_POST['calc_shipping'], $_POST[ $field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			return true;
		}

		return false;
	}
endif;
