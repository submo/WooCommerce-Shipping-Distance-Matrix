<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.0.0
 *
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
class Wcsdm extends WC_Shipping_Method {

	/**
	 * URL of Google Maps Distance Matrix API
	 *
	 * @since    1.0.0
	 * @var string
	 */
	private $_google_api_url = 'https://maps.googleapis.com/maps/api/distancematrix/json';

	/**
	 * All options data
	 *
	 * @since    1.4.2
	 * @var array
	 */
	private $_options = array();

	/**
	 * All debugs data
	 *
	 * @since    1.4.2
	 * @var array
	 */
	private $_debugs = array();

	/**
	 * Rate fields data
	 *
	 * @since    1.4.8
	 * @var array
	 */
	private $_instance_rate_fields = array();

	/**
	 * Default data
	 *
	 * @since    1.4.8
	 * @var array
	 */
	private $_field_default = array(
		'title'             => '',
		'disabled'          => false,
		'class'             => '',
		'css'               => '',
		'placeholder'       => '',
		'type'              => 'text',
		'desc_tip'          => false,
		'description'       => '',
		'default'           => '',
		'custom_attributes' => array(),
		'is_required'       => false,
		'is_pro'            => false,
	);

	/**
	 * Constructor for your shipping class
	 *
	 * @since    1.0.0
	 * @param int $instance_id ID of shipping method instance.
	 */
	public function __construct( $instance_id = 0 ) {
		// ID for your shipping method. Should be unique.
		$this->id = WCSDM_METHOD_ID;

		// Title shown in admin.
		$this->method_title = WCSDM_METHOD_TITLE;

		// Title shown in admin.
		$this->title = WCSDM_METHOD_TITLE;

		// Description shown in admin.
		$this->method_description = __( 'Shipping rates calculator that allows you to easily offer shipping rates based on the distance.', 'wcsdm' );

		$this->enabled = $this->get_option( 'enabled' );

		$this->instance_id = absint( $instance_id );

		$this->supports = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		// Check if this shipping method is availbale for current order.
		add_filter( 'woocommerce_shipping_' . $this->id . '_is_available', array( $this, 'check_is_available' ), 10, 2 );

		// Sanitize settings fields.
		add_filter( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', array( $this, 'instance_settings_values' ), 10 );

		// Show city field on the cart shipping calculator.
		add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_true' );

		$this->init();
	}

	/**
	 * Init settings
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function init() {
		// Load the settings API.
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		$this->init_rate_fields(); // Init rate fields.

		// Define user set variables.
		foreach ( $this->instance_form_fields as $key => $field ) {
			$default = isset( $field['default'] ) ? $field['default'] : null;

			$this->_options[ $key ] = $this->get_option( $key, $default );

			$this->{$key} = $this->_options[ $key ];
		}
	}

	/**
	 * Init form fields.
	 *
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$instance_form_fields = array(
			'shipping_label'        => array(
				'title'       => __( 'Label', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'text',
				'description' => __( 'This controls the label which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'tax_status'            => array(
				'title'     => __( 'Tax Status', 'wcsdm' ),
				'type'      => 'wcsdm',
				'orig_type' => 'select',
				'default'   => 'taxable',
				'options'   => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'api_key'               => array(
				'title'       => __( 'API Key', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'map_edit',
				'description' => __( 'Google maps platform API Key', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the pencil icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'disabled'    => true,
			),
			'lat'                   => array(
				'title'       => __( 'Store Location Latitude', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'map_edit',
				'description' => __( 'Store location latitude coordinates', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the pencil icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'disabled'    => true,
			),
			'lng'                   => array(
				'title'       => __( 'Store Location Longitude', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'map_edit',
				'description' => __( 'Store location longitude coordinates', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the pencil icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'disabled'    => true,
			),
			'store_location_picker' => array(
				'title'       => __( 'Store Location Picker', 'wcsdm' ),
				'type'        => 'store_location_picker',
				'description' => __( 'Drag the store icon marker or search your address in the input box below.', 'wcsdm' ),
			),
			'travel_mode'           => array(
				'title'       => __( 'Travel Mode', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Google Maps Distance Matrix API travel mode parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'driving',
				'options'     => array(
					'driving'   => __( 'Driving', 'wcsdm' ),
					'walking'   => __( 'Walking', 'wcsdm' ),
					'bicycling' => __( 'Bicycling', 'wcsdm' ),
				),
			),
			'route_estrictions'     => array(
				'title'       => __( 'Route Restrictions', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Google Maps Distance Matrix API restrictions parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'driving',
				'options'     => array(
					''         => __( 'None', 'wcsdm' ),
					'tolls'    => __( 'Avoid Tolls', 'wcsdm' ),
					'highways' => __( 'Avoid Highways', 'wcsdm' ),
					'ferries'  => __( 'Avoid Ferries', 'wcsdm' ),
					'indoor'   => __( 'Avoid Indoor', 'wcsdm' ),
				),
			),
			'distance_unit'         => array(
				'title'       => __( 'Distance Units', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Google Maps Distance Matrix API distance units parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'metric',
				'options'     => array(
					'metric'   => __( 'Kilometer', 'wcsdm' ),
					'imperial' => __( 'Mile', 'wcsdm' ),
				),
			),
			'prefered_route'        => array(
				'title'       => __( 'Prefered Route', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Prefered route that will be used for calculation if API provide several routes', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'shortest_distance',
				'options'     => array(
					'shortest_distance' => __( 'Shortest Distance', 'wcsdm' ),
					'longest_distance'  => __( 'Longest Distance', 'wcsdm' ),
					'shortest_duration' => __( 'Shortest Duration', 'wcsdm' ),
					'longest_duration'  => __( 'Longest Duration', 'wcsdm' ),
				),
			),
			'round_up_distance'     => array(
				'title'       => __( 'Round Up Distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Round up distance to the nearest integer.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'show_distance'         => array(
				'title'       => __( 'Show Distance Info', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Show the distance info to customer during checkout.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'enable_address_picker' => array(
				'title'       => __( 'Enable Address Picker', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Enable the map address picker to user during checkout so can set more accurate shipping address latitude and longitude coordinate using drap and drop map picker.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'is_pro'      => true,
			),
			'table_rates'           => array(
				'type'  => 'table_rates',
				'title' => __( 'Table Rates Settings', 'wcsdm' ),
			),
			'table_advanced'        => array(
				'type'  => 'table_advanced',
				'title' => __( 'Advanced Table Rate Settings', 'wcsdm' ),
			),
			'js_template'           => array(
				'type' => 'js_template',
			),
		);

		$this->instance_form_fields = apply_filters( $this->id . '_form_fields', $instance_form_fields );
	}

	/**
	 * Init rate fields.
	 *
	 * @since    1.4.8
	 */
	public function init_rate_fields() {
		$instance_rate_fields = array(
			'section_shipping_rules' => array(
				'type'        => 'section',
				'title'       => __( 'Shipping Rules', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'max_distance'           => array(
				'type'              => 'text',
				'title'             => __( 'Maximum Distances', 'wcsdm' ),
				'description'       => __( 'The maximum distances rule for the shipping rate. This input is required.', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '1',
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '1',
				),
			),
			'min_order_quantity'     => array(
				'type'              => 'text',
				'title'             => __( 'Minimum Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order quantity. Leave blank to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'max_order_quantity'     => array(
				'type'              => 'text',
				'title'             => __( 'Maximum Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order quantity. Leave blank to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'min_order_amount'       => array(
				'type'              => 'text',
				'title'             => __( 'Minimum Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order amount. Leave blank to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'max_order_amount'       => array(
				'type'              => 'text',
				'title'             => __( 'Maximum Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order amount. Leave blank to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'section_shipping_rates' => array(
				'type'        => 'section',
				'title'       => __( 'Shipping Rates', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'rate_type'              => array(
				'type'        => 'select',
				'title'       => __( 'Rate Type', 'wcsdm' ),
				'default'     => 'fixed',
				'options'     => array(
					'fixed'    => __( 'Fixed', 'wcsdm' ),
					'flexible' => __( 'Flexible', 'wcsdm' ),
				),
				'description' => __( 'Determine rate type either fixed or flexible rate. This input is required.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_advanced' => true,
				'is_dummy'    => true,
				'is_hidden'   => true,
				'is_required' => true,
			),
			'class_0'                => array(
				'type'              => 'text',
				'title'             => __( 'Shipping Rate', 'wcsdm' ),
				'description'       => __( 'The shipping rate within the distances range. Zero value will be assumed as free shipping.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rate'           => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'section_total_cost'     => array(
				'type'        => 'section',
				'title'       => __( 'Total Cost', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'surcharge'              => array(
				'type'              => 'text',
				'title'             => __( 'Surcharge', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Surcharge that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'total_cost_type'        => array(
				'type'        => 'select',
				'title'       => __( 'Total Cost Type', 'wcsdm' ),
				'default'     => 'flat__highest',
				'options'     => array(
					'flat__highest'                   => __( 'Flat - Set highest item cost as total', 'wcsdm' ),
					'flat__average'                   => __( 'Flat - Set average item cost as total', 'wcsdm' ),
					'flat__lowest'                    => __( 'Flat - Set lowest item cost as total', 'wcsdm' ),
					'progressive__per_shipping_class' => __( 'Progressive - Accumulate total by product shipping class', 'wcsdm' ),
					'progressive__per_product'        => __( 'Progressive - Accumulate total by product', 'wcsdm' ),
					'progressive__per_item'           => __( 'Progressive - Accumulate total by quantity', 'wcsdm' ),
					'formula'                         => __( 'Advanced - Use math formula to calculate the total', 'wcsdm' ) . ( $this->is_pro() ? '' : ' (' . __( 'Pro Version', 'wcsdm' ) . ')' ),
				),
				'description' => __( 'Determine how is the total shipping cost calculated.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => true,
				'is_required' => true,
			),
			'section_miscellaneous'  => array(
				'type'        => 'section',
				'title'       => __( 'Miscellaneous', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'shipping_label_rate'    => array_merge(
				$this->instance_form_fields['shipping_label'], array(
					'description' => $this->instance_form_fields['shipping_label']['description'] . ' ' . __( 'Leave blank to use the global title settings.', 'wcsdm' ),
					'default'     => '',
					'desc_tip'    => true,
					'is_advanced' => true,
					'is_dummy'    => true,
					'is_hidden'   => true,
				)
			),
			'link_advanced'          => array(
				'type'        => 'link_advanced',
				'title'       => __( 'Advanced', 'wcsdm' ),
				'is_advanced' => false,
				'is_dummy'    => true,
				'is_hidden'   => false,
			),
		);

		$shipping_classes = array();
		foreach ( WC()->shipping->get_shipping_classes() as $shipping_classes_key => $shipping_classes_value ) {
			$shipping_classes[ $shipping_classes_value->term_id ] = $shipping_classes_value;
		}

		if ( $shipping_classes ) {
			$new_fields = array();
			foreach ( $instance_rate_fields as $key => $field ) {
				$new_fields[ $key ] = $field;
				if ( 'class_0' === $key ) {
					foreach ( $shipping_classes as $class_id => $class_obj ) {
						$new_fields[ 'class_' . $class_id ] = array_merge(
							$field, array(
								// translators: %s is Product shipping class name.
								'title'       => sprintf( __( '"%s" Shipping Class Rate', 'wcsdm' ), $class_obj->name ),
								// translators: %s is Product shipping class name.
								'description' => sprintf( __( 'Rate for "%s" shipping class products. Leave blank to use defined default rate above.', 'wcsdm' ), $class_obj->name ),
								'default'     => '',
								'desc_tip'    => true,
								'is_advanced' => true,
								'is_dummy'    => false,
								'is_hidden'   => true,
								'is_required' => false,
							)
						);
					}
				}
			}
			$instance_rate_fields = $new_fields;
		}

		$this->_instance_rate_fields = apply_filters( $this->id . '_rate_fields', $instance_rate_fields );
	}

	/**
	 * Get rate fields
	 *
	 * @since    1.4.2
	 *
	 * @param string $context Data context filter.
	 * @return array
	 */
	public function get_rates_fields( $context = '' ) {
		$rates_fields = array();

		foreach ( $this->_instance_rate_fields as $key => $field ) {
			if ( ! empty( $context ) && ! $field[ 'is_' . $context ] ) {
				continue;
			}

			$rate_field_default = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array(),
				'default'           => '',
			);

			$rate_field = wp_parse_args( $field, $rate_field_default );

			$field_type = isset( $rate_field['orig_type'] ) ? $rate_field['orig_type'] : $rate_field['type'];

			$rate_field_class = array(
				'wcsdm-field',
				'wcsdm-field--rate',
				'wcsdm-field--rate--' . $context,
				'wcsdm-field--rate--' . $context . '--' . $field_type,
				'wcsdm-field--rate--' . $context . '--' . $key,
			);

			if ( 'dummy' === $context ) {
				$rate_field_class[] = 'wcsdm-fullwidth';
			}

			if ( ! empty( $rate_field['class'] ) ) {
				$rate_field_class[] = array_merge( array_filter( explode( ' ', $rate_field['class'] ) ), $rate_field_class );
			}

			$rate_field['class'] = implode( ' ', array_unique( $rate_field_class ) );

			$custom_attributes = array(
				'data-type'     => $field_type,
				'data-id'       => $this->get_field_key( $key ),
				'data-required' => empty( $rate_field['is_required'] ) ? '0' : '1',
				'data-title'    => isset( $rate_field['title'] ) ? $rate_field['title'] : $key,
				'data-options'  => isset( $rate_field['options'] ) ? wp_json_encode( $rate_field['options'] ) : wp_json_encode( array() ),
				'data-validate' => isset( $rate_field['validate'] ) ? $rate_field['validate'] : 'text',
			);

			$rate_field['custom_attributes'] = isset( $rate_field['custom_attributes'] ) ? array_merge( $rate_field['custom_attributes'], $custom_attributes ) : $custom_attributes;

			$rates_fields[ $key ] = $rate_field;
		}

		return $rates_fields;
	}

	/**
	 * Generate wcsdm HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_wcsdm_html( $key, $data ) {
		$data = $this->populate_field( $data );

		if ( isset( $data['orig_type'] ) ) {
			$data['type'] = $data['orig_type'];
		}

		if ( 'wcsdm' === $data['type'] ) {
			$data['type'] = 'text';
		}

		if ( $data['is_required'] ) {
			$data['custom_attributes']['required'] = 'required';
		}

		if ( $data['is_pro'] && ! $this->is_pro() ) {
			$data['title'] = $data['title'] . ' (' . __( 'Pro Version', 'wcsdm' ) . ')';

			$data['disabled'] = true;
		}

		return $this->generate_settings_html( array( $key => $data ), false );
	}

	/**
	 * Generate JS templates.
	 *
	 * @since 1.2.4
	 */
	public function generate_js_template_html() {
		ob_start();
		?>
		<script type="text/template" id="tmpl-wcsdm-errors">
			<div id="{{ data.id }}" class="wcsdm-errors">
				<ul class="notice notice-error">
					<li class="wcsdm-errors--heading"><?php esc_html_e( 'Errors', 'wcsdm' ); ?>:</li>
					<# _.each(data.errors, function(error, key) { #>
					<li id="wcsdm-errors--{{ key }}">{{ error }}</li>
					<# }); #>
				</ul>
			</div>
		</script>

		<script type="text/template" id="tmpl-wcsdm-buttons">
			<div id="wcsdm-buttons" class="wcsdm-buttons">
				<# if(data.btn_left) { #>
				<button id="wcsdm-btn--{{data.btn_left.id}}" class="button button-primary button-large wcsdm-buttons-item wcsdm-buttons-item--left"><span class="dashicons dashicons-{{data.btn_left.icon}}"></span> {{data.btn_left.label}}</button>
				<# } #>
				<# if(data.btn_right) { #>
				<button id="wcsdm-btn--{{data.btn_right.id}}" class="button button-primary button-large wcsdm-buttons-item wcsdm-buttons-item--right"><span class="dashicons dashicons-{{data.btn_right.icon}}"></span> {{data.btn_right.label}}</button>
				<# } #>
			</div>
		</script>
		<script type="text/template" id="tmpl-wcsdm-map-search-panel">
			<div id="wcsdm-map-search-panel" class="wcsdm-map-search-panel">
				<a href="#" id="wcsdm-map-search-panel-toggle" class="wcsdm-map-search-panel-toggle wcsdm-link"><span class="dashicons dashicons-dismiss"></span></a>
				<div id="wcsdm-map-search-panel-main">
					<h3>Store Location Picker</h3>
					<p class="description">Drag the store icon marker or search your address in the input box below.</p>
					<input id="wcsdm-map-search-input" class="wcsdm-fullwidth wcsdm-map-search-input" type="search" placeholder="Search your store address here" autocomplete="off">
				</div>
			</div>
		</script>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate map_edit HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_map_edit_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" readonly="readonly" /> <a href="#" class="wcsdm-link wcsdm-link--show-map"><span class="dashicons dashicons-edit"></span></a>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate store_location_picker HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_store_location_picker_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
			'default'           => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top" id="wcsdm-row-map-picker" class="wcsdm-row wcsdm-row-map-picker wcsdm-hidden">
			<td colspan="2" class="wcsdm-no-padding">
				<table id="wcsdm-table-map-picker" class="form-table wcsdm-table wcsdm-table-map-picker" cellspacing="0">
					<tr>
						<th scope="row" class="titledesc">
							<label for="woocommerce_wcsdm_api_key__dummy"><?php esc_html_e( 'API Key', 'wcsdm' ); ?></label>
						</th>
						<td class="forminp">
							<fieldset>
								<legend class="screen-reader-text"><span><?php echo esc_html( $this->instance_form_fields['api_key']['title'] ); ?></span></legend>
								<input class="input-text regular-input" type="text" id="woocommerce_wcsdm_api_key__dummy" value="" placeholder="<?php esc_attr_e( 'Enter the API Key here', 'wcsdm' ); ?>" /> <a href="#" id="wcsdm-show-instructions"><?php esc_html_e( 'How to Get API Key?', 'wcsdm' ); ?></a>
							</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<td colspan="2" class="wcsdm-no-padding">
							<div id="wcsdm-map-wrap" class="wcsdm-map-wrap">
								<div id="wcsdm-map-canvas" class="wcsdm-map-canvas"></div>
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr valign="top" id="wcsdm-row-map-instructions" class="wcsdm-row wcsdm-row-map-instructions wcsdm-hidden">
			<td colspan="2" class="wcsdm-no-padding">
				<div id="wcsdm-map-instructions">
					<div class="wcsdm-map-instructions">
						<p><?php echo wp_kses_post( __( 'This plugin uses Google Maps Platform APIs where users are required to have a valid API key to be able to use their APIs. Make sure you checked 3 the checkboxes as shown below when creating the API Key.', 'wcsdm' ) ); ?></p>
						<img src="<?php echo esc_attr( WCSDM_URL ); ?>assets/img/map-instructions.jpg" />
					</div>
					<div class="wcsdm-map-instructions">
						<p><?php echo wp_kses_post( __( 'As of July 16, 2018, Google Maps Platform APIs has new pay-as-you-go pricing model. You will be charged if the monthly usage exceed the $200 free monthly credit. You can read more about the pricing <a href="https://cloud.google.com/maps-platform/pricing/sheet/" target="_blank">here</a>.', 'wcsdm' ) ); ?></p>
					</div>
					<div class="wcsdm-map-instructions">
						<p><?php echo wp_kses_post( __( 'As the API Key will be used on the server side during calculate the distance in the checkout form and on the browser side when picking the store location in the settings form, so the API Key <strong>MUST NOT</strong> be restricted to any method.', 'wcsdm' ) ); ?></p>
					</div>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table rates HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_table_rates_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top" id="wcsdm-row-dummy" class="wcsdm-row wcsdm-row-dummy">
			<td colspan="2" class="wcsdm-no-padding">
				<h3 class="wcsdm-settings-form-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<table id="wcsdm-table-dummy" class="form-table wcsdm-table wcsdm-table-dummy" cellspacing="0">
					<thead>
						<tr>
							<td class="wcsdm-col wcsdm-col--select-item">
								<input class="select-item" type="checkbox">
							</td>
							<?php foreach ( $this->get_rates_fields( 'dummy' ) as $key => $field ) : ?>
								<td class="wcsdm-col wcsdm-col--<?php echo esc_html( $key ); ?>">
									<label><?php echo esc_html( $field['title'] ); ?><?php echo $this->get_tooltip_html( $field ); // WPCS: XSS ok. ?></label>
								</td>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( $this->table_rates ) :
							foreach ( $this->table_rates as $table_rate ) :
								$this->generate_rate_row_body( $field_key, $table_rate );
							endforeach;
						endif;
						?>
					</tbody>
				</table>
				<script type="text/template" id="tmpl-wcsdm-dummy-row">
					<?php $this->generate_rate_row_body( $field_key ); ?>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table rate fieldumns
	 *
	 * @param string $field_key Table rate column key.
	 * @param array  $rate Table rate data.
	 * @return void
	 */
	private function generate_rate_row_body( $field_key, $rate = array() ) {
		?>
		<tr>
			<td class="wcsdm-col wcsdm-col--select-item">
				<input class="select-item" type="checkbox">
			</td>
			<?php foreach ( $this->get_rates_fields( 'dummy' ) as $key => $data ) : ?>
			<td class="wcsdm-col wcsdm-col--<?php echo esc_html( $key ); ?>">
				<?php
				$field_value = isset( $rate[ $key ] ) ? $rate[ $key ] : $data['default'];

				switch ( $data['type'] ) {
					case 'link_advanced':
						?>
						<a href="#" class="<?php echo esc_attr( $data['class'] ); ?>" title="<?php echo esc_attr( $data['title'] ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
						<?php
						foreach ( $this->get_rates_fields( 'hidden' ) as $hidden_key => $hidden_data ) :
							$hidden_field_value = isset( $rate[ $hidden_key ] ) ? $rate[ $hidden_key ] : $hidden_data['default'];
						?>
						<input class="<?php echo esc_attr( $hidden_data['class'] ); ?>" type="hidden" name="<?php echo esc_attr( $field_key ); ?>__<?php echo esc_attr( $hidden_key ); ?>[]" value="<?php echo esc_attr( $hidden_field_value ); ?>" <?php echo $this->get_custom_attribute_html( $hidden_data ); // WPCS: XSS ok. ?> />
						<?php
						endforeach;
						break;

					default:
						$html = $this->generate_settings_html( array( 'dummy-key---' . $key => $data ), false );

						preg_match( '/<fieldset>(.*?)<\/fieldset>/s', $html, $matches );

						if ( ! empty( $matches[0] ) ) {
							$find    = 'select' === $data['type'] ? 'value="' . $field_value . '"' : 'value=""';
							$replace = 'select' === $data['type'] ? 'value="' . $field_value . '" ' . selected( true, true, false ) : 'value="' . $field_value . '"';

							echo preg_replace( '#\s(name|id)="[^"]+"#', '', str_replace( $find, $replace, $matches[0] ) ); // WPCS: XSS ok.
						}
						break;
				}
				?>
			</td>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * Generate advanced settings form
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_table_advanced_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top" id="wcsdm-row-advanced" class="wcsdm-row wcsdm-row-advanced wcsdm-hidden">
			<td colspan="2" class="wcsdm-no-padding">
				<h3 class="wcsdm-settings-form-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<table id="wcsdm-table-advanced" class="form-table wcsdm-table wcsdm-table-advanced" cellspacing="0">
					<?php
					foreach ( $this->get_rates_fields( 'advanced' ) as $key => $data ) {
						echo preg_replace( '#\s(name)="[^"]+"#', '', $this->generate_wcsdm_html( $key, $data ) ); // WPCS: XSS ok.
					}
					?>
				</table>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate section field type HTML output
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_section_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'       => '',
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<td colspan="2" class="wcsdm-no-padding">
				<h3 class="wcsdm-settings-sub-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<?php if ( ! empty( $data['description'] ) ) : ?>
				<p><?php echo wp_kses_post( $data['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate WCSDM Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param  string $key Field key.
	 * @param  string $value Posted Value.
	 * @param  bool   $is_rate_field Is this validating rate field.
	 * @throws Exception If the field value is invalid.
	 * @return string
	 */
	public function validate_wcsdm_field( $key, $value, $is_rate_field = false ) {
		if ( $is_rate_field ) {
			$field = isset( $this->_instance_rate_fields[ $key ] ) ? $this->_instance_rate_fields[ $key ] : false;
		} else {
			$field = isset( $this->instance_form_fields[ $key ] ) ? $this->instance_form_fields[ $key ] : false;
		}

		if ( $field ) {
			$field = $this->populate_field( $field );

			if ( isset( $field['orig_type'] ) ) {
				$field['type'] = $field['orig_type'];
			}

			$type = $this->get_field_type( $field );

			if ( 'wcsdm' === $type ) {
				$type = 'text';
			}

			// Look for a validate_FIELDTYPE_field method.
			if ( is_callable( array( $this, 'validate_' . $type . '_field' ) ) ) {
				$value = $this->{'validate_' . $type . '_field'}( $key, $value );
			} else {
				$value = $this->validate_text_field( $key, $value );
			}

			// Validate required field value.
			if ( $field['is_required'] && ( ! strlen( trim( $value ) ) || is_null( $value ) ) ) {
				throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.field_required' ), $field['title'] ) );
			}

			if ( strlen( $value ) ) {
				// Validate min field value.
				if ( isset( $field['custom_attributes']['min'] ) && $value < $field['custom_attributes']['min'] ) {
					throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.field_min_value' ), $field['title'], $field['custom_attributes']['min'] ) );
				}

				// Validate max field value.
				if ( isset( $field['custom_attributes']['max'] ) && $value > $field['custom_attributes']['max'] ) {
					throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.field_max_value' ), $field['title'], $field['custom_attributes']['max'] ) );
				}
			}

			// Validate pro field.
			if ( $field['is_pro'] && ! $this->is_pro() && $value !== $field['default'] ) {
				throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.need_upgrade.general' ), $field['title'] ) );
			}

			return $value;
		}

		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Validate and format table_rates settings field.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param string $value Input field currenet value.
	 * @throws Exception If the field value is invalid.
	 * @return array
	 */
	public function validate_table_rates_field( $key, $value ) {
		$rates = array();

		$post_data = $this->get_post_data();

		$rate_fields = $this->get_rates_fields( 'hidden' );

		$errors = array();

		foreach ( $rate_fields as $rate_field_key => $rate_field ) {
			$field_key = $this->get_field_key( $key . '__' . $rate_field_key );

			$values = isset( $post_data[ $field_key ] ) ? (array) $post_data[ $field_key ] : array();

			foreach ( $values as $index => $value ) {
				try {
					$value = $this->validate_wcsdm_field( $rate_field_key, $value, true );

					if ( 'total_cost_type' === $rate_field_key && ! $this->is_pro() && 'formula' === $value ) {
						throw new Exception( wcsdm_i18n( 'errors.need_upgrade.total_cost_type' ) );
					}

					$rates[ $index ][ $rate_field_key ] = $value;
				} catch ( Exception $e ) {
					// translators: %1$d = row number, %2$s = error message.
					$errors[] = wp_sprintf( __( 'Table rates row %1$d: %2$s', 'wcsdm' ), ( $index + 1 ), $e->getMessage() );
				}
			}
		}

		if ( $errors ) {
			throw new Exception( implode( '</p><p>', $errors ) );
		}

		$rule_fields = array();

		foreach ( $rate_fields as $rate_field_key => $rate_field ) {
			if ( ! isset( $rate_field['is_rule'] ) || ! $rate_field['is_rule'] ) {
				continue;
			}

			$rule_fields[] = $rate_field_key;
		}

		$filtered = array();

		$errors = array();
		foreach ( $rates as $index => $rate ) {
			try {
				$rules = array();

				foreach ( $rule_fields as $rule_field ) {
					$rules[ $rule_field ] = isset( $rate[ $rule_field ] ) ? $rate[ $rule_field ] : false;
				}

				$rate_key = implode( '___', array_values( $rules ) );

				if ( isset( $filtered[ $rate_key ] ) ) {
					$error_msg = array();
					foreach ( $rules as $rule_key => $rule_value ) {
						if ( false === $rule_value ) {
							continue;
						}

						$error_msg[] = wp_sprintf( '%s: %s', $rate_fields[ $rule_key ]['title'], $rule_value );
					}

					throw new Exception( implode( ', ', $error_msg ) );
				}

				$filtered[ $rate_key ] = $rate;
			} catch ( Exception $e ) {
				$errors[] = wp_sprintf( wcsdm_i18n( 'errors.duplicate_rate' ), ( $index + 1 ), $e->getMessage() );
			}
		}

		if ( $errors ) {
			throw new Exception( implode( '</p><p>', $errors ) );
		}

		if ( empty( $filtered ) ) {
			throw new Exception( __( 'Shipping rates table is empty', 'wcsdm' ) );
		}

		$filtered = array_values( $filtered );

		// get a list of sort columns and their data to pass to array_multisort.
		$sorted = array();
		foreach ( $filtered as $k => $v ) {
			foreach ( $rule_fields as $rule_field ) {
				$sorted[ $rule_field ][ $k ] = $v[ $rule_field ];
			}
		}

		// sort by event_type desc and then title asc.
		array_multisort(
			$sorted['max_distance'],
			SORT_ASC,
			$sorted['min_order_quantity'],
			SORT_ASC,
			$sorted['max_order_quantity'],
			SORT_ASC,
			$sorted['min_order_amount'],
			SORT_ASC,
			$sorted['max_order_amount'],
			SORT_ASC,
			$filtered
		);

		return apply_filters( $this->id . '_validate_table_rates', $filtered );
	}

	/**
	 * Validate and format table_rates settings field.
	 *
	 * @since    1.0.0
	 * @throws Exception If the field value is invalid.
	 * @return array
	 */
	public function validate_store_location_picker_field() {
		$post_data = $this->get_post_data();

		$api_key_field = $this->get_field_key( 'api_key' );
		$api_key_value = isset( $post_data[ $api_key_field ] ) ? $post_data[ $api_key_field ] : false;

		if ( ! empty( $api_key_value ) ) {
			$origin      = array( WCSDM_DEFAULT_LAT, WCSDM_DEFAULT_LNG );
			$destination = array( WCSDM_TEST_LAT, WCSDM_TEST_LNG );
			$args        = array( 'settings' => array( 'api_key' => $api_key_value ) );

			$result = $this->api_request( $origin, $destination, $args, false );

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}
		}

		return null;
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @param array   $origin Shipping origin info.
	 * @param array   $destination Shipping destination info.
	 * @param array   $args Custom arguments for $settings and $package data.
	 * @param boolean $cache Is use the cached data.
	 * @throws Exception If error happen.
	 * @return array
	 */
	private function api_request( $origin, $destination, $args = array(), $cache = true ) {
		try {
			// Check origin parameter is valid.
			if ( empty( $origin ) ) {
				throw new Exception( __( 'Origin parameter is invalid', 'wcsdm' ) );
			}

			// Check destination parameter is valid.
			if ( empty( $destination ) ) {
				throw new Exception( __( 'Destination parameter is invalid', 'wcsdm' ) );
			}

			$package = isset( $args['package'] ) ? (array) $args['package'] : array();

			$settings = isset( $args['settings'] ) ? (array) $args['settings'] : array();
			$settings = wp_parse_args( $settings, $this->_options );

			if ( $cache && ! $this->is_debug_mode() ) {
				$cache_key = $this->id . '_api_request_' . md5(
					wp_json_encode(
						array(
							'origin'      => $origin,
							'destination' => $destination,
							'package'     => $package,
							'settings'    => $settings,
						)
					)
				);

				// Check if the data already chached and return it.
				$cached_data = get_transient( $cache_key );

				if ( false !== $cached_data ) {
					$this->show_debug( __( 'Cache key', 'wcsdm' ) . ': ' . $cache_key );
					$this->show_debug( __( 'Cached data', 'wcsdm' ) . ': ' . wp_json_encode( $cached_data ) );

					return $cached_data;
				}
			}

			$request_url_args = array(
				'key'          => $settings['api_key'],
				'mode'         => $settings['travel_mode'],
				'avoid'        => $settings['route_estrictions'],
				'units'        => $settings['distance_unit'],
				'language'     => get_locale(),
				'origins'      => is_array( $origin ) ? implode( ',', $origin ) : $origin,
				'destinations' => is_array( $destination ) ? implode( ',', $destination ) : $destination,
			);

			foreach ( $request_url_args as $key => $value ) {
				$request_url_args[ $key ] = is_string( $value ) ? rawurlencode( $value ) : array_map( $value, 'rawurlencode' );
			}

			$request_url = add_query_arg( $request_url_args, $this->_google_api_url );

			$this->show_debug( __( 'API Request URL', 'wcsdm' ) . ': ' . str_replace( rawurlencode( $settings['api_key'] ), '**********', $request_url ) );

			$raw_response = wp_remote_get( esc_url_raw( $request_url ) );

			// Check if HTTP request is error.
			if ( is_wp_error( $raw_response ) ) {
				throw new Exception( $raw_response->get_error_message() );
			}

			$response_body = wp_remote_retrieve_body( $raw_response );

			// Check if API response is empty.
			if ( empty( $response_body ) ) {
				throw new Exception( __( 'API response is empty', 'wcsdm' ) );
			}

			// Decode API response body.
			$response_data = json_decode( $response_body, true );

			// Check if JSON data is valid.
			$json_last_error_msg = json_last_error_msg();
			if ( $json_last_error_msg && 'No error' !== $json_last_error_msg ) {
				// translators: %s = Json error message.
				$error_message = sprintf( __( 'Error occured while decoding API response: %s', 'wcsdm' ), $json_last_error_msg );

				throw new Exception( $error_message );
			}

			// Check API response is OK.
			$status = isset( $response_data['status'] ) ? $response_data['status'] : '';
			if ( 'OK' !== $status ) {
				$error_message = __( 'API Response Error', 'wcsdm' ) . ': ' . $status;
				if ( isset( $response_data['error_message'] ) ) {
					$error_message .= ' - ' . $response_data['error_message'];
				}

				throw new Exception( $error_message );
			}

			$errors  = array();
			$results = array();

			// Get the shipping distance.
			foreach ( $response_data['rows'] as $row ) {
				foreach ( $row['elements'] as $element ) {
					// Check element status code.
					if ( 'OK' !== $element['status'] ) {
						$errors[] = $element['status'];
						continue;
					}

					$results[] = array(
						'distance'      => $this->convert_distance( $element['distance']['value'] ),
						'distance_text' => $element['distance']['text'],
						'duration'      => $element['duration']['value'],
						'duration_text' => $element['duration']['text'],
					);
				}
			}

			if ( empty( $results ) ) {
				$error_template = array(
					'NOT_FOUND'                 => __( 'Origin and/or destination of this pairing could not be geocoded', 'wcsdm' ),
					'ZERO_RESULTS'              => __( 'No route could be found between the origin and destination', 'wcsdm' ),
					'MAX_ROUTE_LENGTH_EXCEEDED' => __( 'Requested route is too long and cannot be processed', 'wcsdm' ),
				);

				if ( ! empty( $errors ) ) {
					foreach ( $errors as $error_key ) {
						if ( isset( $error_template[ $error_key ] ) ) {
							throw new Exception( __( 'API Response Error', 'wcsdm' ) . ': ' . $error_template[ $error_key ] );
						}
					}
				}

				throw new Exception( __( 'API Response Error', 'wcsdm' ) . ': ' . __( 'No results found', 'wcsdm' ) );
			}

			if ( count( $results ) > 1 ) {
				switch ( $settings['prefered_route'] ) {
					case 'longest_duration':
						usort( $results, array( $this, 'longest_duration_results' ) );
						break;

					case 'longest_distance':
						usort( $results, array( $this, 'longest_distance_results' ) );
						break;

					case 'shortest_duration':
						usort( $results, array( $this, 'shortest_duration_results' ) );
						break;

					default:
						usort( $results, array( $this, 'shortest_distance_results' ) );
						break;
				}
			}

			$result = $results[0];

			// Rounds distance UP to the nearest integer.
			if ( 'yes' === $settings['round_up_distance'] ) {
				$result['distance']      = ceil( $result['distance'] );
				$result['distance_text'] = $result['distance'] . preg_replace( '/[0-9\.,]/', '', $result['distance_text'] );
			}

			$result['response'] = $response_data;

			if ( $cache && ! $this->is_debug_mode() ) {
				delete_transient( $cache_key ); // To make sure the transient data re-created, delete it first.
				set_transient( $cache_key, $result, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.
			}

			return $result;
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), 'error' );

			return new WP_Error( 'api_request', $e->getMessage() );
		}
	}

	/**
	 * Populate field data
	 *
	 * @since    1.4.8
	 * @param array $field Current field data.
	 * @return array
	 */
	private function populate_field( $field ) {
		return wp_parse_args( $field, $this->_field_default );
	}

	/**
	 * Sanitize settings value before store to DB.
	 *
	 * @since    1.0.0
	 * @param array $settings Current settings data.
	 * @return array
	 */
	public function instance_settings_values( $settings ) {
		if ( $this->get_errors() ) {
			return $this->_options;
		}

		return $settings;
	}

	/**
	 * Check if this method available
	 *
	 * @since    1.0.0
	 * @param boolean $available Current status is available.
	 * @param array   $package Current order package data.
	 * @return bool
	 */
	public function check_is_available( $available, $package ) {
		if ( empty( $package['contents'] ) || empty( $package['destination'] ) ) {
			return false;
		}
		return $available;
	}

	/**
	 * Calculate shipping function.
	 *
	 * @since    1.0.0
	 * @param array $package Package data array.
	 * @throws Exception Throw error if validation not passed.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		global $woocommerce;

		try {
			$origin      = $this->get_origin_info( $package );
			$destination = $this->get_destination_info( $package );

			$api_request = $this->api_request( $origin, $destination, array( 'package' => $package ) );

			// Bail early if the API request error.
			if ( is_wp_error( $api_request ) ) {
				throw new Exception( $api_request->get_error_message() );
			}

			if ( ! $api_request ) {
				return;
			}

			$cost_data = $this->get_cost( $api_request['distance'], $package );

			// Bail early if there is no rate found.
			if ( is_wp_error( $cost_data ) ) {
				throw new Exception( $cost_data->get_error_message() );
			}

			// Set shipping courier label.
			$label = empty( $cost_data['shipping_label_rate'] ) ? $this->shipping_label : $cost_data['shipping_label_rate'];

			// Show the distance info.
			if ( 'yes' === $this->show_distance && ! empty( $api_request['distance_text'] ) ) {
				$label = sprintf( '%s (%s)', $label, $api_request['distance_text'] );
			}

			$cost_total              = 0;
			$cost_per_order          = 0;
			$cost_per_shipping_class = array();
			$cost_per_product        = array();
			$cost_per_item           = 0;

			foreach ( $package['contents'] as $hash => $item ) {
				$class_id   = $item['data']->get_shipping_class_id();
				$product_id = $item['data']->get_id();

				$default_cost = isset( $cost_data['class_0'] ) ? $cost_data['class_0'] : 0;
				$class_cost   = isset( $cost_data[ 'class_' . $class_id ] ) ? $cost_data[ 'class_' . $class_id ] : false;

				if ( ! $class_cost && $default_cost ) {
					$class_cost = $default_cost;
				}

				// Multiply shipping cost with distance unit.
				if ( 'flexible' === $cost_data['rate_type'] ) {
					$class_cost = $class_cost * $api_request['distance'];
				}
			}

			$rate = array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => $cost_total,
				'package'   => $package,
				'meta_data' => $api_request,
			);

			// Register the rate.
			$this->register_rate( $rate );
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Register shipping rate to cart.
	 *
	 * @since 1.4
	 * @param array $rate Shipping rate date.
	 * @return void
	 */
	private function register_rate( $rate ) {

		// Register the rate.
		$this->add_rate( $rate );

		/**
		 * Developers can add additional rates via action.
		 *
		 * This example shows how you can add an extra rate via custom function:
		 *
		 *      add_filter( 'wcsdm_shipping_add_rate', 'add_another_custom_flat_rate', 10, 2 );
		 *
		 *      function add_another_custom_flat_rate( $method, $rate ) {
		 *          $new_rate          = $rate;
		 *          $new_rate['id']    .= ':' . 'custom_rate_name'; // Append a custom ID.
		 *          $new_rate['label'] = 'Rushed Shipping'; // Rename to 'Rushed Shipping'.
		 *          $new_rate['cost']  += 2; // Add $2 to the cost.
		 *
		 *          // Add it to WC.
		 *          $method->add_rate( $new_rate );
		 *      }.
		 */
		do_action( 'woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate );
	}

	/**
	 * Get shipping cost by distance and shipping class
	 *
	 * @since    1.0.0
	 * @param int $distance Distance of shipping destination.
	 * @return mixed rate row array data or WP_Error on failure.
	 */
	private function get_cost( $distance ) {
		/**
		 * Developers can modify the $rate via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the $rate var via custom function:
		 *
		 *      add_filter( 'wcsdm_get_cost_pre', 'modify_get_rate_pre', 10, 3 );
		 *
		 *      function modify_get_rate_pre( $false, $distance, $table_rates ) {
		 *          // Return the table rate row match with the rule
		 *          return array('max_distance' => 100, 'class_0' => 10);
		 *      }
		 */
		$pre = apply_filters( 'wcsdm_get_cost_pre', false, $distance, $this->table_rates );

		if ( false !== $pre ) {
			return $pre;
		}

		if ( $this->table_rates ) {
			$offset = 0;
			foreach ( $this->table_rates as $rate ) {
				if ( $distance > $offset && $distance <= $rate['max_distance'] ) {
					/**
					 * Developers can modify the $rate via filter hooks.
					 *
					 * @since 1.0.1
					 *
					 * This example shows how you can modify the $$rate var via custom function:
					 *
					 *      add_filter( 'wcsdm_get_cost', 'modify_get_rate', 10, 3 );
					 *
					 *      function modify_get_cost( $rate, $distance, $table_rates ) {
					 *          // Return the table rate row match with the rule
					 *          return array('max_distance' => 100, 'class_0' => 10);
					 *      }
					 */
					return apply_filters( 'wcsdm_get_cost', $rate, $distance, $this->table_rates );
				}
				$offset = $rate['max_distance'];
			}
		}

		// translators: %1$s distance value, %2$s distance unit.
		return new WP_Error( 'no_rates', sprintf( __( 'No shipping rates defined within distance range: %1$s %2$s', 'wcsdm' ), $distance, 'imperial' === $this->distance_unit ? 'mi' : 'km' ) );
	}

	/**
	 * Get shipping origin info
	 *
	 * @since    1.0.0
	 * @param array $package The cart content data.
	 * @return array
	 */
	private function get_origin_info( $package ) {
		$origin_info = array();

		if ( ! empty( $this->lat ) && ! empty( $this->lng ) ) {
			$origin_info['lat'] = $this->lat;
			$origin_info['lng'] = $this->lng;
		}

		/**
		 * Developers can modify the origin info via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the $origin_info var via custom function:
		 *
		 *      add_filter( 'wcsdm_origin_info', 'modify_origin_info', 10, 2 );
		 *
		 *      function modify_origin_info( $origin_info, $package ) {
		 *          return '1600 Amphitheatre Parkway,Mountain View,CA,94043';
		 *      }
		 */
		return apply_filters( $this->id . '_origin_info', $origin_info, $package );
	}

	/**
	 * Get shipping destination info
	 *
	 * @since    1.0.0
	 * @throws Exception Throw error if validation not passed.
	 * @param array $package The cart content data.
	 * @return string
	 */
	private function get_destination_info( $package ) {
		$destination_info = array();

		// Set initial destination info
		if ( isset( $package['destination'] ) ) {
			foreach ( $package['destination'] as $key => $value ) {
				switch ( $key ) {
					case 'address':
						$destination_info['address_1'] = $value;
						break;

					default:
						$destination_info[ $key ] = $value;
						break;
				}
			}
		}

		// $data   = $package['destination'];
		$errors = array();

		$country_code = ! empty( $destination_info['country'] ) ? $destination_info['country'] : false;

		$country_locale = WC()->countries->get_country_locale();

		$rules = $country_locale['default'];

		if ( $country_code && isset( $country_locale[ $country_code ] ) ) {
			$rules = array_merge( $rules, $country_locale[ $country_code ] );
		}

		// Validate shiipping fields.
		foreach ( $rules as $rule_key => $rule ) {
			if ( in_array( $rule_key, array( 'first_name', 'last_name', 'company' ) ) ) {
				continue;
			}

			$field_value = isset( $destination_info[ $rule_key ] ) ? $destination_info[ $rule_key ] : '';
			$is_required = isset( $rule['required'] ) ? $rule['required'] : false;

			if ( $is_required && ! strlen( strval( $field_value ) ) ) {
				$errors[ $rule_key ] = sprintf( __( 'Shipping destination field is empty: %s', 'wcsdm' ), $rule['label'] );
			}

			if ( $country_code && $field_value && 'postcode' === $rule_key && ! WC_Validation::is_postcode( $field_value, $country_code ) ) {
				$errors[ $rule_key ] = sprintf( __( 'Shipping destination field is invalid: %s', 'wcsdm' ), $rule['label'] );
			}
		}

		if ( $errors ) {
			// Set debug if error.
			foreach ( $errors as $error ) {
				$this->show_debug( $error, 'error' );
			}

			// Reset destionation info if error.
			$destination_info = array();
		} else {
			// Remove unwanted fields when in shipping calculator mode.
			if ( $this->is_calc_shipping() ) {
				$calculator_fields = array(
					'address_1' => false,
					'address_2' => false,
					'city'      => true,
					'postcode'  => true,
					'state'     => true,
					'country'   => true,
				);

				foreach ( $calculator_fields as $calculator_field_key => $calculator_enable ) {
					if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_' . $calculator_field_key, $calculator_enable ) ) {
						unset( $destination_info[ $calculator_field_key ] );
					}
				}
			}

			$destination = array();
			$states      = WC()->countries->states;
			$countries   = WC()->countries->countries;

			foreach ( $destination_info as $key => $value ) {
				// Skip for empty field
				if ( ! strlen( strval( $field_value ) ) ) {
					continue;
				}

				switch ( $key ) {
					case 'country':
						if ( ! $country_code ) {
							$country_code = $value;
						}

						$destination[ $key ] = isset( $countries[ $value ] ) ? $countries[ $value ] : $value; // Set country full name.
						break;

					case 'state':
						if ( ! $country_code ) {
							$country_code = isset( $destination_info['country'] ) ? $destination_info['country'] : 'undefined';
						}

						$destination[ $key ] = isset( $states[ $country_code ][ $value ] ) ? $states[ $country_code ][ $value ] : $value; // Set state full name.
						break;

					default:
						$destination[ $key ] = $value;
						break;
				}
			}

			if ( ! $country_code ) {
				$country_code = isset( $destination['country'] ) ? $destination['country'] : false;
			}

			// Try to format the address.
			if ( $country_code ) {
				$formats = WC()->countries->get_address_formats();
				$format  = isset( $formats[ $country_code ] ) ? $formats[ $country_code ] : $formats['default'];

				if ( $format ) {
					$destination_format = array();
					$parts              = explode( "\n", str_replace( array( '{', '}' ), '', $format ) );
					foreach ( $parts as $part ) {
						if ( isset( $destination[ $part ] ) ) {
							$destination_format[ $part ] = $destination[ $part ];
						}
					}

					$destination = $destination_format;
				}
			}

			$destination_info = $destination;
		}

		/**
		 * Developers can modify the $destination_info var via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the shipping destination info via custom function:
		 *
		 *      add_filter( 'wcsdm_destination_info', 'modify_destination_info', 10, 2 );
		 *
		 *      function modify_destination_info( $destination_info, $destination_info_arr ) {
		 *          return '1600 Amphitheatre Parkway,Mountain View,CA,94043';
		 *      }
		 */
		return apply_filters( $this->id . '_destination_info', $destination_info, $package );
	}

	/**
	 * Check if current request is shipping calculator form.
	 *
	 * @return bool
	 */
	private function is_calc_shipping() {
		$nonce_field  = 'woocommerce-shipping-calculator-nonce';
		$nonce_action = 'woocommerce-shipping-calculator';

		if ( isset( $_POST['calc_shipping'], $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert Meters to Distance Unit
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_distance( $meters ) {
		return ( 'metric' === $this->distance_unit ) ? $this->convert_distance_to_km( $meters ) : $this->convert_distance_to_mi( $meters );
	}

	/**
	 * Convert Meters to Miles
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_distance_to_mi( $meters ) {
		return wc_format_decimal( ( $meters * 0.000621371 ), 1 );
	}

	/**
	 * Convert Meters to Kilometres
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_distance_to_km( $meters ) {
		return wc_format_decimal( ( $meters * 0.001 ), 1 );
	}

	/**
	 * Sort ascending API response array by duration.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function shortest_duration_results( $a, $b ) {
		if ( $a['duration'] === $b['duration'] ) {
			return 0;
		}
		return ( $a['duration'] < $b['duration'] ) ? -1 : 1;
	}

	/**
	 * Sort descending API response array by duration.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function longest_duration_results( $a, $b ) {
		if ( $a['duration'] === $b['duration'] ) {
			return 0;
		}
		return ( $a['duration'] > $b['duration'] ) ? -1 : 1;
	}

	/**
	 * Sort ascending API response array by distance.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function shortest_distance_results( $a, $b ) {
		if ( $a['max_distance'] === $b['max_distance'] ) {
			return 0;
		}
		return ( $a['max_distance'] < $b['max_distance'] ) ? -1 : 1;
	}

	/**
	 * Sort descending API response array by distance.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function longest_distance_results( $a, $b ) {
		if ( $a['max_distance'] === $b['max_distance'] ) {
			return 0;
		}
		return ( $a['max_distance'] > $b['max_distance'] ) ? -1 : 1;
	}

	/**
	 * Check if pro version plugin is installed and activated
	 *
	 * @since    1.5.0
	 * @return bool
	 */
	private function is_pro() {
		return wcsdm_is_pro();
	}

	/**
	 * Check if run in debug mode
	 *
	 * @since    1.5.0
	 * @return bool
	 */
	private function is_debug_mode() {
		return get_option( 'woocommerce_shipping_debug_mode', 'no' ) === 'yes';
	}

	/**
	 * Show debug info
	 *
	 * @since    1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $type The type of notice.
	 * @return void
	 */
	private function show_debug( $message, $type = '' ) {
		if ( empty( $message ) ) {
			return;
		}

		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			return;
		}

		if ( defined( 'WC_DOING_AJAX' ) ) {
			return;
		}

		if ( ! $this->is_debug_mode() ) {
			return;
		}

		// if ( is_admin() ) {
		// return;
		// }
		// if ( ! current_user_can( 'administrator' ) ) {
		// return;
		// }
		$debug_key = md5( $message );

		if ( isset( $this->_debugs[ $debug_key ] ) ) {
			return;
		}

		$this->_debugs[ $debug_key ] = $message;

		$debug_prefix = strtoupper( $this->id );

		if ( ! empty( $type ) ) {
			$debug_prefix .= '_' . strtoupper( $type );
		}

		wc_add_notice( $debug_prefix . ' => ' . $message, 'notice' );
	}
}
