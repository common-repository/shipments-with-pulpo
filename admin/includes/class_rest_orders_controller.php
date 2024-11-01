<?php
class WC_REST_Orders_Controller_Wrapper {

	public function get_formatted_item_data( $object) {
		$fields = false;
		// Initially skip line items if we can.
		$using_order_class_override = is_a( $object, '\Automattic\WooCommerce\Admin\Overrides\Order' );
		if ( $using_order_class_override ) {
			$data = $object->get_data_without_line_items();
		} else {
			$data = $object->get_data();
		}

		$format_decimal    = array( 'discount_total', 'discount_tax', 'shipping_total', 'shipping_tax', 'shipping_total', 'shipping_tax', 'cart_tax', 'total', 'total_tax' );
		$format_date       = array( 'date_created', 'date_modified', 'date_completed', 'date_paid' );
		$format_line_items = array( 'line_items');

		// Format decimal values.
		foreach ( $format_decimal as $key ) {
			$data[ $key ] = wc_format_decimal($data[ $key ]);
		}

		// Format date values.
		foreach ( $format_date as $key ) {
			$datetime              = $data[ $key ];
			$data[ $key ]          = wc_rest_prepare_date_response( $datetime, false );
			$data[ $key . '_gmt' ] = wc_rest_prepare_date_response( $datetime );
		}

		// Format the order status.
		$data['status'] = 'wc-' === substr( $data['status'], 0, 3 ) ? substr( $data['status'], 3 ) : $data['status'];

		// Format requested line items.
		$formatted_line_items = array();

		foreach ( $format_line_items as $key ) {
			if ( false === $fields || in_array( $key, $fields ) ) {
				if ( $using_order_class_override ) {
					$line_item_data = $object->get_line_item_data( $key );
				} else {
					$line_item_data = $data[ $key ];
				}
				$formatted_line_items[ $key ] = array_values( array_map( array( $this, 'get_order_item_data' ), $line_item_data ) );
			}
		}

		// Refunds.
		$data['refunds'] = array();
		foreach ( $object->get_refunds() as $refund ) {
			$data['refunds'][] = array(
				'id'     => $refund->get_id(),
				'reason' => $refund->get_reason() ? $refund->get_reason() : '',
				'total'  => '-' . wc_format_decimal($refund->get_amount()),
			);
		}

		return array_merge(
			array(
				'id'                    => $object->get_id(),
				'status'                => $data['status'],
				'customer_id'           => $data['customer_id'],
				'billing'               => $data['billing'],
				'shipping'              => $data['shipping'],
				'customer_note'         => $object->get_customer_note()
			),
			$formatted_line_items
		);
	}


	protected function get_order_item_data( $item ) {
		$data           = $item->get_data();
		$format_decimal = array( 'subtotal', 'subtotal_tax', 'total', 'total_tax', 'tax_total', 'shipping_tax_total' );

		// Format decimal values.
		foreach ( $format_decimal as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = wc_format_decimal($data[ $key ]);
			}
		}

		// Add SKU and PRICE to products.
		if ( is_callable( array( $item, 'get_product' ) ) ) {
			$data['sku']   = $item->get_product() ? $item->get_product()->get_sku() : null;

			if (!$data['sku']) {
				throw new Exception("Product id {$item->get_product()->get_id()} without SKU");
			}

			$data['price'] = $item->get_quantity() ? $item->get_total() / $item->get_quantity() : 0;
		}

		// Add parent_name if the product is a variation.
		if ( is_callable( array( $item, 'get_product' ) ) ) {
			$product = $item->get_product();

			if ($product->is_type('bundle') 
				|| $product->is_virtual('yes') 
				|| $product->is_downloadable('yes')) { // Los productos tipo bundle o virtual no se envían a la API

				return false;
			}

			if ( is_callable( array( $product, 'get_parent_data' ) ) ) {
				$data['parent_name'] = $product->get_title();
			} else {
				$data['parent_name'] = null;
			}
		}

		// Format taxes.
		if ( ! empty( $data['taxes']['total'] ) ) {
			$taxes = array();

			foreach ( $data['taxes']['total'] as $tax_rate_id => $tax ) {
				$taxes[] = array(
					'id'       => $tax_rate_id,
					'total'    => $tax,
					'subtotal' => isset( $data['taxes']['subtotal'][ $tax_rate_id ] ) ? $data['taxes']['subtotal'][ $tax_rate_id ] : '',
				);
			}
			$data['taxes'] = $taxes;
		} elseif ( isset( $data['taxes'] ) ) {
			$data['taxes'] = array();
		}

		// Remove names for coupons, taxes and shipping.
		if ( isset( $data['code'] ) || isset( $data['rate_code'] ) || isset( $data['method_title'] ) ) {
			unset( $data['name'] );
		}

		// Remove props we don't want to expose.
		unset( $data['order_id'] );
		unset( $data['type'] );

		// Expand meta_data to include user-friendly values.
		$formatted_meta_data = $item->get_formatted_meta_data( null, true );
		$data['meta_data'] = array_map(
			array( $this, 'merge_meta_item_with_formatted_meta_display_attributes' ),
			$data['meta_data'],
			array_fill( 0, count( $data['meta_data'] ), $formatted_meta_data )
		);

		return $data;
	}

	private function merge_meta_item_with_formatted_meta_display_attributes( $meta_item, $formatted_meta_data ) {
		$result = array(
			'id'            => $meta_item->id,
			'key'           => $meta_item->key,
			'value'         => $meta_item->value,
			'display_key'   => $meta_item->key,   // Default to original key, in case a formatted key is not available.
			'display_value' => $meta_item->value, // Default to original value, in case a formatted value is not available.
		);

		if ( array_key_exists( $meta_item->id, $formatted_meta_data ) ) {
			$formatted_meta_item = $formatted_meta_data[ $meta_item->id ];

			$result['display_key'] = wc_clean( $formatted_meta_item->display_key );
			$result['display_value'] = wc_clean( $formatted_meta_item->display_value );
		}

		return $result;
	}

}

