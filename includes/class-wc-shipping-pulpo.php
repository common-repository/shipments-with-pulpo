<?php

/**
 * WC_Shipping_Pulpo class.
 *
 * @class 		WC_Shipping_Pulpo
 * @version		1.0.0
 * @package		Shipping-for-WooCommerce/Classes
 */
class WC_Shipping_Pulpo extends WC_Shipping_Method {

	public static $ID = 'pulpo_method';


	/**
	 * Constructor. The instance ID is passed to this.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                    = self::$ID;
		$this->instance_id           = absint( $instance_id );
		$this->method_title          = esc_html__( 'Pulpo Shipping Method', 'pulpo' );
		$this->method_description    = esc_html__( 'Pulpo Shipping method.', 'pulpo' );
		$this->supports              = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->instance_form_fields = array(
			'enabled' => array(
				'title'         => esc_html__('Enable/Disable'),
				'type'          => 'checkbox',
				'label'         => esc_html__('Enable this shipping method'),
				'default'       => 'yes',
			),
			'title' => array(
				'title' 		=> esc_html__( 'Method Title', 'pulpo' ),
				'type' 			=> 'text',
				'description' 	=> esc_html__( 'This controls the title which the user sees during checkout.', 'pulpo' ),
				'default'		=> esc_html__( 'Shipping using Pulpo', 'pulpo' ),
				'desc_tip'      => true
			),
			'carrier'   => array(
				'title'     => esc_html__('Shipping method'),
				'type'      => 'select',
				'options'   => $this->getPulpoCarrierList(),
			),
			'tax_status' => array(
				'title'         => __( 'Tax status', 'woocommerce' ),
				'type'          => 'select',
				'class'         => 'wc-enhanced-select',
				'default'       => 'taxable',
				'options'       => array(
					'taxable' => __( 'Taxable', 'woocommerce' ),
					'none'    => _x( 'None', 'Tax status', 'woocommerce' ),
				),
			),
			'cost'       => array(
				'title'             => __( 'Cost', 'woocommerce' ),
				'type'              => 'text',
				'placeholder'       => '',
				'default'           => '0',
				'desc_tip'          => true,
				'sanitize_callback' => array( $this, 'sanitize_cost' ),
			),
		);

		$this->init();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	protected function getPulpoCarrierList() {
		$shipping_methods_options = [];

		if (is_admin() &&
			( !is_ajax() && ( 'wc-settings' === ( isset($_GET['page'])?sanitize_text_field($_GET['page']):'' )  || 'shipping' === ( isset($_GET['tab'])?sanitize_text_field($_GET['tab']):'' ) )
			|| ( is_ajax()
				&& isset($_GET['action'])
				&& in_array($_GET['action'], ['woocommerce_shipping_zone_add_method', 'woocommerce_shipping_zone_methods_save_settings']) )
			)
		) {
			$service = new \PulpoService();
			$shipping_methods_list =  $service->get_shipping_methods();
			$shipping_methods_options = [];

			if ($shipping_methods_list) {
				$shipping_methods_options = [
					''  =>  esc_html__('Do not use any method', 'pulpo')
				];

				foreach ($shipping_methods_list as $shipping_method) {
					$shipping_methods_options[$shipping_method['id']] = $shipping_method['name'];
				}
			}
		}

		return $shipping_methods_options;
	}

	/**
	 * Init user set variables.
	 */
	public function init() {
		$this->title                = $this->get_option( 'title' );
		$this->tax_status           = $this->get_option( 'tax_status' );
		$this->cost                 = $this->get_option( 'cost' );
		$this->type                 = $this->get_option( 'type', 'class' );
		$this->carrier              = $this->get_option('carrier');
	}

	/**
	 * Evaluate a cost from a sum/string.
	 *
	 * @param  string $sum Sum of shipping.
	 * @param  array  $args Args, must contain `cost` and `qty` keys. Having `array()` as default is for back compat reasons.
	 * @return string
	 */
	protected function evaluate_cost( $sum, $args = array() ) {
		// Add warning for subclasses.
		if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
			wc_doing_it_wrong( __FUNCTION__, '$args must contain `cost` and `qty` keys.', '4.0.1' );
		}

		include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

		// Allow 3rd parties to process shipping cost arguments.
		/**
		 * Filter to process shipping cost arguments.
		 *
		 * @since 3.4.0
		 */
		$args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $this );
		$locale         = localeconv();
		$decimals       = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );
		$this->fee_cost = $args['cost'];

		// Expand shortcodes.
		add_shortcode( 'fee', array( $this, 'fee' ) );

		$sum = do_shortcode(
			str_replace(
				array(
					'[qty]',
					'[cost]',
				),
				array(
					$args['qty'],
					$args['cost'],
				),
				$sum
			)
		);

		remove_shortcode( 'fee', array( $this, 'fee' ) );

		// Remove whitespace from string.
		$sum = preg_replace( '/\s+/', '', $sum );

		// Remove locale from string.
		$sum = str_replace( $decimals, '.', $sum );

		// Trim invalid start/end characters.
		$sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// Do the math.
		return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
	}

	/**
	 * Work out fee (shortcode).
	 *
	 * @param  array $atts Attributes.
	 * @return string
	 */
	public function fee( $atts ) {
		$atts = shortcode_atts(
			array(
				'percent' => '',
				'min_fee' => '',
				'max_fee' => '',
			),
			$atts,
			'fee'
		);

		$calculated_fee = 0;

		if ( $atts['percent'] ) {
			$calculated_fee = $this->fee_cost * ( floatval( $atts['percent'] ) / 100 );
		}

		if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
			$calculated_fee = $atts['min_fee'];
		}

		if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
			$calculated_fee = $atts['max_fee'];
		}

		return $calculated_fee;
	}

	/**
	 * Calculate the shipping costs.
	 *
	 * @param array $package Package of items from cart.
	 */
	public function calculate_shipping( $package = array() ) {
		$rate = array(
			'id'      => $this->get_rate_id(),
			'label'   => $this->title,
			'cost'    => 0,
			'package' => $package,
		);

		// Calculate the costs.
		$has_costs = false; // True when a cost is set. False if all costs are blank strings.
		$cost      = $this->get_option( 'cost' );

		if ( '' !== $cost ) {
			$has_costs    = true;
			$rate['cost'] = $this->evaluate_cost(
				$cost,
				array(
					'qty'  => $this->get_package_item_qty( $package ),
					'cost' => $package['contents_cost'],
				)
			);
		}

		// Add shipping class costs.
		$shipping_classes = WC()->shipping()->get_shipping_classes();

		if ( ! empty( $shipping_classes ) ) {
			$found_shipping_classes = $this->find_shipping_classes( $package );
			$highest_class_cost     = 0;

			foreach ( $found_shipping_classes as $shipping_class => $products ) {
				// Also handles BW compatibility when slugs were used instead of ids.
				$shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
				$class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $this->get_option( 'class_cost_' . $shipping_class_term->term_id, $this->get_option( 'class_cost_' . $shipping_class, '' ) ) : $this->get_option( 'no_class_cost', '' );

				if ( '' === $class_cost_string ) {
					continue;
				}

				$has_costs  = true;
				$class_cost = $this->evaluate_cost(
					$class_cost_string,
					array(
						'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
						'cost' => array_sum( wp_list_pluck( $products, 'line_total' ) ),
					)
				);

				if ( 'class' === $this->type ) {
					$rate['cost'] += $class_cost;
				} else {
					$highest_class_cost = $class_cost > $highest_class_cost ? $class_cost : $highest_class_cost;
				}
			}

			if ( 'order' === $this->type && $highest_class_cost ) {
				$rate['cost'] += $highest_class_cost;
			}
		}

		if ( $has_costs ) {
			$this->add_rate( $rate );
		}
	}

	/**
	 * Get items in package.
	 *
	 * @param  array $package Package of items from cart.
	 * @return int
	 */
	public function get_package_item_qty( $package ) {
		$total_quantity = 0;
		foreach ( $package['contents'] as $item_id => $values ) {
			if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
				$total_quantity += $values['quantity'];
			}
		}
		return $total_quantity;
	}

	/**
	 * Finds and returns shipping classes and the products with said class.
	 *
	 * @param mixed $package Package of items from cart.
	 * @return array
	 */
	public function find_shipping_classes( $package ) {
		$found_shipping_classes = array();

		foreach ( $package['contents'] as $item_id => $values ) {
			if ( $values['data']->needs_shipping() ) {
				$found_class = $values['data']->get_shipping_class();

				if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
					$found_shipping_classes[ $found_class ] = array();
				}

				$found_shipping_classes[ $found_class ][ $item_id ] = $values;
			}
		}

		return $found_shipping_classes;
	}

	/**
	 * Sanitize the cost field.
	 *
	 * @since 3.4.0
	 * @param string $value Unsanitized value.
	 * @throws Exception Last error triggered.
	 * @return string
	 */
	public function sanitize_cost( $value ) {
		$value = is_null( $value ) ? '' : $value;
		$value = wp_kses_post( trim( wp_unslash( $value ) ) );
		$value = str_replace( array( get_woocommerce_currency_symbol(), html_entity_decode( get_woocommerce_currency_symbol() ) ), '', $value );
		// Thrown an error on the front end if the evaluate_cost will fail.
		$dummy_cost = $this->evaluate_cost(
			$value,
			array(
				'cost' => 1,
				'qty'  => 1,
			)
		);
		if ( false === $dummy_cost ) {
			throw new Exception( WC_Eval_Math::$last_error );
		}
		return $value;
	}
}
