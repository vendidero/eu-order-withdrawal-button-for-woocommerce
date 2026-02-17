<?php
/**
 * Core Functions
 *
 * @version 1.0.0
 */
defined( 'ABSPATH' ) || exit;

/**
 * Given an element name, returns a class name.
 *
 * If the WP-related function is not defined, return empty string.
 *
 * @param string $element The name of the element.
 *
 * @return string
 */
function eu_owb_wp_theme_get_element_class_name( $element ) {
	if ( function_exists( 'wc_wp_theme_get_element_class_name' ) ) {
		return wc_wp_theme_get_element_class_name( $element );
	} elseif ( function_exists( 'wp_theme_get_element_class_name' ) ) {
		return wp_theme_get_element_class_name( $element );
	}

	return '';
}

function eu_owb_get_withdrawable_order_statuses( $prefixed = true ) {
	$order_statuses = array_diff_key(
		wc_get_order_statuses(),
		array(
			'wc-cancelled'            => '',
			'wc-refunded'             => '',
			'wc-failed'               => '',
			'wc-pending-cancellation' => '',
		)
	);

	$order_statuses = apply_filters( 'eu_owb_woocommerce_withdrawable_order_statuses', array_keys( $order_statuses ) );

	if ( ! $prefixed ) {
		$order_statuses = array_map(
			function ( $status ) {
				if ( strpos( $status, 'wc-' ) === 0 ) {
						$status = substr( $status, 3 );
				}

				return $status;
			},
			$order_statuses
		);
	}

	return $order_statuses;
}

/**
 * @param WC_Order|integer $order
 *
 * @return boolean
 */
function eu_owb_order_is_withdrawable( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return false;
	}

	$is_withdrawable = true;

	if ( ! $order->has_status( eu_owb_get_withdrawable_order_statuses( false ) ) ) {
		$is_withdrawable = false;
	}

	$items = eu_owb_get_withdrawable_order_items( $order );

	if ( empty( $items ) ) {
		$is_withdrawable = false;
	}

	if ( $date_delivered = eu_owb_order_get_date_delivered( $order ) ) {
		/**
		 * Calculate day diff in local timezone
		 */
		$datetime = new WC_DateTime( 'now', new DateTimeZone( 'UTC' ) );

		if ( get_option( 'timezone_string' ) ) {
			$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$datetime->set_utc_offset( wc_timezone_offset() );
		}

		$diff = $date_delivered->diff( $datetime );

		if ( $diff->days > eu_owb_get_number_of_days_to_withdraw() ) {
			$is_withdrawable = false;
		}
	}

	return apply_filters( 'eu_owb_woocommerce_order_is_withdrawable', $is_withdrawable, $order );
}

function eu_owb_get_number_of_days_to_withdraw() {
	return absint( \Vendidero\OrderWithdrawalButton\Package::get_setting( 'number_of_days_to_withdraw', 14 ) );
}

/**
 * @param WC_Order|integer $order
 *
 * @return null|WC_DateTime
 */
function eu_owb_order_get_date_delivered( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return null;
	}

	$date_delivered = $order->get_date_completed();

	/**
	 * Allow cancellation requests until next day at 00:00:01.
	 */
	if ( $date_delivered ) {
		$date_delivered->modify( 'midnight' );
		$date_delivered->modify( '+1 second' );
		$date_delivered->modify( '+1 day' );
	}

	return apply_filters( 'eu_owb_woocommerce_get_order_date_delivered', $date_delivered, $order );
}

/**
 * @param WC_Order|integer $order
 *
 * @return bool
 */
function eu_owb_order_supports_partial_withdrawal( $order ) {
	$cancelable_items = eu_owb_get_withdrawable_order_items( $order );
	$supports         = false;

	if ( \Vendidero\OrderWithdrawalButton\Package::enable_partial_withdrawals() ) {
		if ( count( $cancelable_items ) > 1 || array_values( $cancelable_items )[0]['quantity'] > 1 ) {
			$supports = true;
		}
	}

	return apply_filters( 'eu_owb_woocommerce_order_supports_partial_withdrawal', $supports, $order );
}

function eu_owb_get_edit_withdrawal_url( $order ) {
	$url = eu_owb_get_withdrawal_form_page_permalink();

	if ( ! empty( $url ) ) {
		$url = add_query_arg(
			array(
				'order_id'              => $order->get_id(),
				'order_key'             => $order->get_order_key(),
				'manually_select_items' => 'yes',
			),
			$url
		);
	}

	return apply_filters( 'eu_owb_woocommerce_edit_withdrawal_url', $url, $order );
}

function eu_owb_get_withdrawal_form_page_permalink() {
	$page_id = eu_owb_get_withdrawal_form_page_id();
	$link    = ( $page_id > 0 ) ? get_permalink( $page_id ) : '';

	return apply_filters( 'eu_owb_woocommerce_withdrawal_form_page_permalink', $link );
}

function eu_owb_get_withdrawal_form_page_id() {
	return apply_filters( 'eu_owb_woocommerce_withdrawal_form_page_id', wc_get_page_id( 'withdrawal_form' ) );
}

/**
 * @param WC_Order|integer $order
 *
 * @return WC_Order_Item_Product[]
 */
function eu_owb_get_withdrawable_order_items( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return null;
	}

	$items_to_withdraw = array();

	foreach ( $order->get_items() as $item ) {
		$total_qty_left = eu_owb_get_order_item_quantity_left_to_withdraw( $item, $order );

		if ( $total_qty_left <= 0 ) {
			continue;
		}

		$items_to_withdraw[ $item->get_id() ] = array(
			'item'     => $item,
			'quantity' => $total_qty_left,
		);
	}

	return apply_filters( 'eu_owb_woocommerce_withdrawable_order_items', $items_to_withdraw, $order );
}

/**
 * @param WC_Order_Item_Product $item
 *
 * @return mixed
 */
function eu_owb_get_order_item_quantity_left_to_withdraw( $item, $order = null ) {
	$order        = ! $order ? $item->get_order_id() : $order;
	$refunded_qty = $order->get_qty_refunded_for_item( $item->get_id() );
	$total_qty    = $item->get_quantity();

	if ( $refunded_qty < 0 ) {
		$refunded_qty *= -1;
	}

	$total_qty_left = $total_qty - $refunded_qty;

	if ( $total_qty_left <= 0 ) {
		$total_qty_left = 0;
	}

	if ( ! eu_owb_order_item_is_withdrawable( $item, $order ) ) {
		$total_qty_left = 0;
	}

	return apply_filters( 'eu_owb_woocommerce_order_item_quantity_left_to_withdraw', $total_qty_left, $item, $order );
}

/**
 * @param WC_Order $order
 *
 * @return bool
 */
function eu_owb_order_has_pending_withdrawal_request( $order ) {
	return $order->has_status( 'pending-wdraw' );
}

/**
 * @param WC_Order $order
 *
 * @return bool
 */
function eu_owb_order_has_withdrawal_status( $order ) {
	return eu_owb_order_has_pending_withdrawal_request( $order ) || eu_owb_order_is_withdrawn( $order );
}

/**
 * @param WC_Order $order
 *
 * @return bool
 */
function eu_owb_order_is_partial_withdrawal( $order ) {
	return eu_owb_order_has_withdrawal_status( $order ) && 'yes' !== $order->get_meta( '_is_full_withdrawal' );
}

/**
 * @param WC_Order $order
 *
 * @return bool
 */
function eu_owb_order_is_withdrawal_update( $order ) {
	return eu_owb_order_has_withdrawal_status( $order ) && 'yes' === $order->get_meta( '_is_withdrawal_update' );
}

/**
 * @param WC_Order $order
 *
 * @return bool
 */
function eu_owb_order_is_guest_withdrawal( $order ) {
	return eu_owb_order_has_withdrawal_status( $order ) && 'yes' === $order->get_meta( '_is_guest_withdrawal' );
}

/**
 * @param WC_Order $order
 *
 * @return bool
 */
function eu_owb_order_is_withdrawn( $order ) {
	return $order->has_status( 'withdrawn' );
}

function eu_owb_get_order_withdrawal_email( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return '';
	}

	$email = $order->get_meta( '_withdrawal_request_email', true );

	if ( empty( $email ) ) {
		$email = $order->get_billing_email();
	}

	return $email;
}

/**
 * @param $order
 *
 * @return WC_DateTime|null
 */
function eu_owb_get_order_withdrawal_date( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return null;
	}

	$timestamp = $order->get_meta( '_date_withdrawn', true );
	$date      = null;

	if ( ! empty( $timestamp ) ) {
		$date = new WC_DateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );

		// Set local timezone or offset.
		if ( get_option( 'timezone_string' ) ) {
			$date->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$date->set_utc_offset( wc_timezone_offset() );
		}
	}

	return $date;
}

/**
 * @param WC_Order|integer $order
 *
 * @return array
 */
function eu_owb_get_withdrawal_order_items( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return array();
	}

	$items = array();

	if ( eu_owb_order_has_pending_withdrawal_request( $order ) || eu_owb_order_is_withdrawn( $order ) ) {
		$withdrawal_items = array_filter( (array) $order->get_meta( '_withdrawal_items', true ) );

		foreach ( $withdrawal_items as $item_id => $item_data ) {
			$item_data = wp_parse_args(
				(array) $item_data,
				array(
					'quantity' => 1,
				)
			);

			if ( $item = $order->get_item( $item_id ) ) {
				$items[ $item_id ] = array(
					'item'     => $item,
					'quantity' => $item_data['quantity'],
				);
			}
		}
	}

	return $items;
}

/**
 * @param WC_Order|integer $order
 * @param $email
 * @param $items
 * @param boolean $as_guest
 *
 * @return WP_Error|true
 */
function eu_owb_create_order_withdrawal_request( $order, $email, $items = array(), $as_guest = true ) {
	$error = new \WP_Error();

	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		$error->add( 'invalid_order', _x( 'Invalid order.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) );
		return $error;
	}

	$is_update          = false;
	$item_map           = array();
	$is_full_withdrawal = true;
	$item_desc          = array();
	$order_note         = _x( 'A new withdrawal request has been submitted to this order', 'wbo', 'eu-order-withdrawal-button-for-woocommerce' );

	if ( eu_owb_order_has_pending_withdrawal_request( $order ) ) {
		$is_update = true;
	}

	if ( ! empty( $items ) ) {
		$items_available = eu_owb_get_withdrawable_order_items( $order );

		foreach ( $items_available as $item_id => $item ) {
			if ( ! array_key_exists( $item_id, $items ) ) {
				$is_full_withdrawal = false;
				continue;
			}

			$item_data = wp_parse_args(
				$items[ $item_id ],
				array(
					'quantity' => 1,
				)
			);

			$quantity = min( $item_data['quantity'], $item['quantity'] );

			if ( $quantity < $item['quantity'] ) {
				$is_full_withdrawal = false;
			}

			$items[ $item_id ]['quantity'] = $quantity;
		}

		$items = array_intersect_key( $items, $items_available );
	} else {
		$items = eu_owb_get_withdrawable_order_items( $order );
	}

	if ( ! $is_update ) {
		$order->update_meta_data( '_status_before_withdrawal', $order->get_status() );
	}

	$order->update_meta_data( '_is_full_withdrawal', wc_bool_to_string( $is_full_withdrawal ) );
	$order->update_meta_data( '_is_withdrawal_update', wc_bool_to_string( $is_update ) );
	$order->update_meta_data( '_is_guest_withdrawal', wc_bool_to_string( $as_guest ) );
	$order->update_meta_data( '_date_withdrawn', time() );
	$order->update_meta_data( '_withdrawal_request_email', $email );

	foreach ( $order->get_items() as $item_id => $item ) {
		$item->delete_meta_data( '_withdrawal_quantity' );
		$item->delete_meta_data( '_has_withdrawal' );
	}

	foreach ( $items as $item_id => $item_data ) {
		$item_map[ $item_id ] = array(
			'quantity' => $item_data['quantity'],
		);

		if ( $item = $order->get_item( $item_id, false ) ) {
			$withdrawal_quantity = $item_data['quantity'];

			$item->update_meta_data( '_has_withdrawal', 'yes' );
			$item->update_meta_data( '_withdrawn_quantity', min( $withdrawal_quantity, $item->get_quantity() ) );

			$item_desc[] = $item->get_name() . ' &times; ' . $item_data['quantity'];
		}
	}

	$order->update_meta_data( '_withdrawal_items', $item_map );

	if ( ! $is_full_withdrawal ) {
		$order_note = _x( 'A new partial withdrawal request has been submitted to this order', 'wbo', 'eu-order-withdrawal-button-for-woocommerce' );
	}

	if ( ! empty( $item_desc ) ) {
		$order_note .= ': ' . implode( ', ', $item_desc ) . '.';
	} else {
		$order_note .= '.';
	}

	$order->update_status( 'wc-pending-wdraw', $order_note );

	do_action( 'eu_owb_woocommerce_withdrawal_request_created', $order, $items, $is_full_withdrawal, $is_update );

	WC()->mailer()->emails['EU_OWB_Email_Customer_Withdrawal_Request_Received']->trigger( $order->get_id(), $order );
	WC()->mailer()->emails['EU_OWB_Email_New_Withdrawal_Request']->trigger( $order->get_id(), $order );

	return true;
}

function eu_owb_order_confirm_withdrawal_request( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order || ! eu_owb_order_has_pending_withdrawal_request( $order ) ) {
		return false;
	}

	$items              = eu_owb_get_withdrawal_order_items( $order );
	$is_full_withdrawal = ! eu_owb_order_is_partial_withdrawal( $order );
	$is_update          = eu_owb_order_is_withdrawal_update( $order );

	$order->update_status( apply_filters( 'eu_owb_woocommerce_withdrawal_request_confirmed_status', 'wc-withdrawn', $order ), _x( 'A withdrawal request has been confirmed.', 'wbo', 'eu-order-withdrawal-button-for-woocommerce' ) );

	do_action( 'eu_owb_woocommerce_withdrawal_request_confirmed', $order, $items, $is_full_withdrawal, $is_update );

	WC()->mailer()->emails['EU_OWB_Email_Customer_Withdrawal_Request_Confirmed']->trigger( $order->get_id(), $order );

	return true;
}

function eu_owb_order_reject_withdrawal_request( $order, $reason = '' ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! $order ) {
		return false;
	}

	$last_known_status = $order->get_meta( '_status_before_withdrawal' );

	$items              = eu_owb_get_withdrawal_order_items( $order );
	$is_full_withdrawal = ! eu_owb_order_is_partial_withdrawal( $order );
	$is_update          = eu_owb_order_is_withdrawal_update( $order );

	foreach ( $order->get_items() as $item_id => $item ) {
		$item->delete_meta_data( '_withdrawal_quantity' );
		$item->delete_meta_data( '_has_withdrawal' );
	}

	$order->delete_meta_data( '_status_before_withdrawal' );
	$order->delete_meta_data( '_is_full_withdrawal' );
	$order->delete_meta_data( '_is_withdrawal_update' );
	$order->delete_meta_data( '_date_withdrawn' );
	$order->delete_meta_data( '_withdrawal_items' );

	/**
	 * Prevent notifications and other actions from firing when resetting the order status.
	 */
	add_action(
		'woocommerce_after_order_object_save',
		function ( $the_order ) use ( $order ) {
			if ( $the_order->get_id() === $order->get_id() ) {
				$status = $order->get_status();

				remove_all_actions( 'woocommerce_order_status_' . $status );
				remove_all_actions( 'woocommerce_order_status_changed' );
				remove_all_actions( 'woocommerce_order_payment_status_changed' );
			}
		},
		999999
	);

	$order->update_status( apply_filters( 'eu_owb_woocommerce_withdrawal_request_rejected_status', $last_known_status, $order ), sprintf( _x( 'A withdrawal request has been rejected: %1$s', 'wbo', 'eu-order-withdrawal-button-for-woocommerce' ), $reason ) );

	do_action( 'eu_owb_woocommerce_withdrawal_request_rejected', $order, $reason, $items, $is_full_withdrawal, $is_update );

	WC()->mailer()->emails['EU_OWB_Email_Customer_Withdrawal_Request_Rejected']->trigger( $order->get_id(), $order, '', $reason );

	return true;
}

/**
 * @param WC_Order_Item_Product $order_item
 *
 * @return boolean
 */
function eu_owb_order_item_is_withdrawable( $order_item, $order = null ) {
	$is_withdrawable = true;

	return apply_filters( 'eu_owb_woocommerce_order_item_is_withdrawable', $is_withdrawable, $order_item, $order );
}

function eu_owb_get_withdrawable_orders_for_user( $user_id = 0 ) {
	$user_id          = 0 === $user_id ? get_current_user_id() : $user_id;
	$min_date_created = strtotime( '-12 months' );
	$orders           = wc_get_orders(
		array(
			'customer_id'  => $user_id,
			'status'       => eu_owb_get_withdrawable_order_statuses(),
			'limit'        => -1,
			'orderby'      => 'date_created',
			'date_created' => '>' . $min_date_created,
		)
	);
	$orders_to_cancel = array();

	foreach ( $orders as $order ) {
		if ( eu_owb_order_is_withdrawable( $order ) ) {
			$orders_to_cancel[] = $order;
		}
	}

	return $orders_to_cancel;
}

/**
 * Parses a string and finds the longest, contiguous number which is assumed to be the order id.
 *
 * @param $order_id_str
 *
 * @return string
 */
function eu_owb_get_order_id_from_string( $order_id_str ) {
	$order_id_parsed = trim( preg_replace( '/[^0-9]/', '_', $order_id_str ) );
	$order_id_comp   = explode( '_', $order_id_parsed );

	usort(
		$order_id_comp,
		function ( $a, $b ) {
			if ( strlen( $a ) === strlen( $b ) ) {
				return 0;
			}

			return ( strlen( $a ) < strlen( $b ) ) ? 1 : -1;
		}
	);

	// Prefer longer, contiguous order numbers
	$order_id = reset( $order_id_comp );

	return apply_filters( 'eu_owb_woocommerce_get_order_id_from_string', $order_id, $order_id_str );
}

/**
 * @param $order_id
 * @param $email
 *
 * @return false|integer
 */
function eu_owb_find_order( $order_id, $email ) {
	$order_id_parsed = eu_owb_get_order_id_from_string( $order_id );
	$db_order_id     = false;
	$orders          = wc_get_orders(
		apply_filters(
			'eu_owb_woocommerce_find_order_query_args',
			array(
				'billing_email' => $email,
				'post__in'      => array( $order_id_parsed ),
				'limit'         => 1,
				'return'        => 'ids',
			)
		)
	);

	// Now lets try to find the order by a custom order number field
	if ( empty( $orders ) ) {
		$custom_query_filter = add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			function ( $query, $query_vars ) {
				$meta_field_name = apply_filters( 'eu_owb_woocommerce_customer_order_number_meta_key', '_order_number' );

				if ( ! empty( $query_vars['order_number'] ) ) {
					$query['meta_query'][] = array(
						'key'     => $meta_field_name,
						'value'   => esc_attr( wc_clean( $query_vars['order_number'] ) ),
						'compare' => '=',
					);
				}

				return $query;
			},
			10,
			2
		);

		$orders = wc_get_orders(
			apply_filters(
				'eu_owb_woocommerce_find_order_alternate_order_query_args',
				array(
					'billing_email' => $email,
					'order_number'  => $order_id,
					'limit'         => 1,
					'return'        => 'ids',
				)
			)
		);

		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $custom_query_filter, 10 );
	}

	if ( ! empty( $orders ) ) {
		$db_order_id = $orders[0];
	}

	return apply_filters( 'eu_owb_woocommerce_find_order', $db_order_id, $order_id, $email );
}

/**
 * Get HTML for the order items to be shown in emails.
 *
 * @param WC_Order $order Order object.
 * @param array    $args Arguments.
 *
 * @since 3.0.0
 * @return string
 */
function eu_owb_get_email_withdrawal_items( $order, $args = array() ) {
	ob_start();

	$email_improvements_enabled = \Vendidero\OrderWithdrawalButton\Package::has_email_improvements_enabled();
	$image_size                 = $email_improvements_enabled ? 48 : 32;

	$defaults = array(
		'show_sku'      => false,
		'show_image'    => $email_improvements_enabled,
		'image_size'    => array( $image_size, $image_size ),
		'plain_text'    => false,
		'sent_to_admin' => false,
	);

	$args     = wp_parse_args( $args, $defaults );
	$template = $args['plain_text'] ? 'emails/plain/email-withdrawal-items.php' : 'emails/email-withdrawal-items.php';

	wc_get_template(
		$template,
		apply_filters(
			'eu_owb_woocommerce_email_withdrawal_items_args',
			array(
				'order'         => $order,
				'items'         => eu_owb_get_withdrawal_order_items( $order ),
				'show_sku'      => $args['show_sku'],
				'show_image'    => $args['show_image'],
				'image_size'    => $args['image_size'],
				'plain_text'    => $args['plain_text'],
				'sent_to_admin' => $args['sent_to_admin'],
			)
		)
	);

	return apply_filters( 'eu_owb_woocommerce_email_withdrawal_items_table', ob_get_clean(), $order );
}
