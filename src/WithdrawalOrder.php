<?php

namespace Vendidero\OrderWithdrawalButton;

defined( 'ABSPATH' ) || exit;

class WithdrawalOrder extends \WC_Abstract_Order {

	/**
	 * Which data store to load.
	 *
	 * @var string
	 */
	protected $data_store_name = 'order-withdrawal';

	/**
	 * This is the name of this object type.
	 *
	 * @var string
	 */
	protected $object_type = 'order_withdrawal';

	/**
	 * @var null|\WC_Order
	 */
	protected $parent = null;

	/**
	 * Stores product data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'withdrawal_number'  => '',
		'date_confirmed'     => null,
		'date_rejected'      => null,
		'original_status'    => '',
		'rejection_reason'   => '',
		'is_partial'         => false,
		'is_guest'           => false,
		'has_verified_email' => true,
		'order_number'       => '',
		'is_update'          => false,
		'refund_id'          => 0,
		'customer_id'        => 0,
		'email'              => '',
		'first_name'         => '',
		'last_name'          => '',
		'order_key'          => '',
	);

	protected $legacy_datastore_props = array();

	/**
	 * Stores data about status changes so relevant hooks can be fired.
	 *
	 * @var bool|array
	 */
	protected $status_transition = false;

	public function __construct( $order = 0 ) {
		/**
		 * Use a tweak to prevent overriding the actual prop which has PHP 7.3 features in newer WC versions
		 */
		$this->item_types_to_group = array(
			'withdrawal' => 'withdrawal_lines',
		);

		parent::__construct( $order );
	}

	/**
	 * Get internal type (post type.)
	 *
	 * @return string
	 */
	public function get_type() {
		return 'shop_order_withdraw';
	}

	public function get_withdrawal_number( $context = 'view' ) {
		return $this->get_prop( 'withdrawal_number', $context );
	}

	public function set_withdrawal_number( $value ) {
		$this->set_prop( 'withdrawal_number', $value );
	}

	public function get_email( $context = 'view' ) {
		$value = $this->get_prop( 'email', $context );

		if ( 'view' === $context && empty( $value ) ) {
			if ( $parent = $this->get_parent() ) {
				$value = $parent->get_billing_email();
			}
		}

		return $value;
	}

	public function set_email( $value ) {
		$this->set_prop( 'email', $value );
	}

	protected function has_first_or_last_name() {
		return ! empty( $this->get_first_name( 'edit' ) ) || ! empty( $this->get_last_name( 'edit' ) );
	}

	public function get_first_name( $context = 'view' ) {
		$value = $this->get_prop( 'first_name', $context );

		if ( 'view' === $context && empty( $value ) && ! $this->has_first_or_last_name() ) {
			if ( $parent = $this->get_parent() ) {
				$value = $parent->get_billing_first_name();
			}
		}

		return $value;
	}

	public function set_first_name( $value ) {
		$this->set_prop( 'first_name', $value );
	}

	public function get_last_name( $context = 'view' ) {
		$value = $this->get_prop( 'last_name', $context );

		if ( 'view' === $context && empty( $value ) && ! $this->has_first_or_last_name() ) {
			if ( $parent = $this->get_parent() ) {
				$value = $parent->get_billing_last_name();
			}
		}

		return $value;
	}

	public function set_last_name( $value ) {
		$this->set_prop( 'last_name', $value );
	}

	public function has_items() {
		return count( $this->get_items() ) > 0;
	}

	/**
	 * Get a formatted full name.
	 *
	 * @return string
	 */
	public function get_formatted_full_name( $placeholder = false, $context = 'view' ) {
		$full_name_placeholder = $placeholder ? ( is_bool( $placeholder ) ? _x( 'Not specified', 'owb-full-name-placeholder', 'eu-order-withdrawal-button-for-woocommerce' ) : $placeholder ) : '';
		$first_name            = $this->get_first_name( $context );
		$last_name             = $this->get_last_name( $context );

		if ( empty( $last_name ) && ! empty( $first_name ) ) {
			/* translators: 1: first name */
			$full_name = sprintf( _x( '%1$s', 'owb-first-name', 'eu-order-withdrawal-button-for-woocommerce' ), $first_name ); // phpcs:ignore WordPress.WP.I18n.NoEmptyStrings
		} elseif ( empty( $first_name ) && ! empty( $last_name ) ) {
			/* translators: 1: last name */
			$full_name = sprintf( _x( '%1$s', 'owb-last-name', 'eu-order-withdrawal-button-for-woocommerce' ), $last_name ); // phpcs:ignore WordPress.WP.I18n.NoEmptyStrings
		} elseif ( ! empty( $first_name ) && ! empty( $last_name ) ) {
			/* translators: 1: last name 2: last name */
			$full_name = sprintf( _x( '%1$s %2$s', 'owb-full-name', 'eu-order-withdrawal-button-for-woocommerce' ), $first_name, $last_name );
		}

		if ( empty( $full_name ) ) {
			$full_name = $full_name_placeholder;
		}

		return $full_name;
	}

	public function get_date_confirmed( $context = 'view' ) {
		return $this->get_prop( 'date_confirmed', $context );
	}

	public function set_date_confirmed( $date = null ) {
		$this->set_date_prop( 'date_confirmed', $date );
	}

	public function get_date_rejected( $context = 'view' ) {
		return $this->get_prop( 'date_rejected', $context );
	}

	public function set_date_rejected( $date = null ) {
		$this->set_date_prop( 'date_rejected', $date );
	}

	public function get_date_received( $context = 'view' ) {
		return $this->get_date_created( $context );
	}

	public function set_date_received( $date = null ) {
		$this->set_date_created( $date );
	}

	public function get_original_status( $context = 'view' ) {
		return $this->get_prop( 'original_status', $context );
	}

	public function set_original_status( $status ) {
		$this->set_prop( 'original_status', $status );
	}

	public function get_rejection_reason( $context = 'view' ) {
		return $this->get_prop( 'rejection_reason', $context );
	}

	public function set_rejection_reason( $reason ) {
		$this->set_prop( 'rejection_reason', $reason );
	}

	public function get_is_partial( $context = 'view' ) {
		return $this->get_prop( 'is_partial', $context );
	}

	public function is_partial() {
		return $this->get_is_partial();
	}

	public function set_is_partial( $is_partial ) {
		$this->set_prop( 'is_partial', wc_string_to_bool( $is_partial ) );
	}

	public function get_is_guest( $context = 'view' ) {
		$is_guest = $this->get_prop( 'is_guest', $context );

		if ( 'view' === $context && ! empty( $this->get_customer_id() ) ) {
			$is_guest = false;
		}

		return $is_guest;
	}

	public function is_guest() {
		return $this->get_is_guest();
	}

	public function set_is_guest( $is_guest ) {
		$this->set_prop( 'is_guest', wc_string_to_bool( $is_guest ) );
	}

	public function get_has_verified_email( $context = 'view' ) {
		return $this->get_prop( 'has_verified_email', $context );
	}

	public function has_verified_email() {
		return $this->get_has_verified_email();
	}

	public function set_has_verified_email( $verified_email ) {
		$this->set_prop( 'has_verified_email', wc_string_to_bool( $verified_email ) );
	}

	public function get_order_number( $context = 'view' ) {
		$value = $this->get_prop( 'order_number', $context );

		if ( 'view' === $context && ( $parent = $this->get_parent() ) ) {
			$value = $parent->get_order_number();
		}

		return $value;
	}

	public function set_order_number( $order_number ) {
		$this->set_prop( 'order_number', $order_number );
	}

	public function has_parent() {
		return $this->get_parent() ? true : false;
	}

	public function get_parent() {
		if ( is_null( $this->parent ) && $this->get_parent_id() > 0 ) {
			$this->parent = wc_get_order( $this->get_parent_id() );
		}

		return $this->parent;
	}

	public function get_is_update( $context = 'view' ) {
		return $this->get_prop( 'is_update', $context );
	}

	public function is_update() {
		return $this->get_is_update();
	}

	public function set_is_update( $is_update ) {
		$this->set_prop( 'is_update', wc_string_to_bool( $is_update ) );
	}

	public function get_refund_id( $context = 'view' ) {
		return $this->get_prop( 'refund_id', $context );
	}

	public function set_refund_id( $refund_id ) {
		$this->set_prop( 'refund_id', absint( $refund_id ) );
	}

	/**
	 * Get customer_id.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return int
	 */
	public function get_customer_id( $context = 'view' ) {
		return $this->get_prop( 'customer_id', $context );
	}

	public function set_customer_id( $value ) {
		$this->set_prop( 'customer_id', absint( $value ) );
	}

	/**
	 * Alias for get_customer_id().
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return int
	 */
	public function get_user_id( $context = 'view' ) {
		return $this->get_customer_id( $context );
	}

	/**
	 * Get the user associated with the order. False for guests.
	 *
	 * @return \WP_User|false
	 */
	public function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Get order key.
	 *
	 * @since  3.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_order_key( $context = 'view' ) {
		return $this->get_prop( 'order_key', $context );
	}

	/**
	 * Set order key.
	 *
	 * @param string $value Max length 22 chars.
	 * @return void
	 */
	public function set_order_key( $value ) {
		$this->set_prop( 'order_key', substr( $value, 0, 22 ) );
	}

	public function set_parent_id( $value ) {
		parent::set_parent_id( $value );

		$this->parent = null;

		if ( true === $this->object_read && array_key_exists( 'parent_id', $this->changes ) ) {
			if ( $parent = $this->get_parent() ) {
				$this->set_order_number( $parent->get_order_number() );
			}
		}
	}

	public function calculate_taxes( $args = array() ) {}

	public function calculate_shipping() {}

	public function calculate_totals( $and_taxes = true ) {}

	public function recalculate_coupons() {}

	/**
	 * Return the order statuses without wc- internal prefix.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		$status = $this->get_prop( 'status', $context );

		if ( empty( $status ) && 'view' === $context ) {
			$status = 'owb-requested';
		}

		return $status;
	}

	/**
	 * Set order status.
	 *
	 * @since 3.0.0
	 * @param string       $new_status    Status to change the order to. No internal wc- prefix is required.
	 * @param bool         $manual_update Is this a manual order status change?.
	 * @return array
	 */
	public function set_status( $new_status, $manual_update = false ) {
		$new_status = 'trash' === $new_status ? $new_status : 'owb-' . Package::maybe_remove_withdrawal_order_status_prefix( $new_status );
		$result     = parent::set_status( $new_status );

		if ( true === $this->object_read && ! empty( $result['from'] ) && $result['from'] !== $result['to'] ) {
			$this->status_transition = array(
				'from'   => ! empty( $this->status_transition['from'] ) ? $this->status_transition['from'] : $result['from'],
				'to'     => $result['to'],
				'manual' => (bool) $manual_update,
			);

			$this->maybe_set_date_rejected();
			$this->maybe_set_date_confirmed();
		}

		return $result;
	}

	/**
	 * Maybe set date rejected.
	 *
	 * @return void
	 */
	public function maybe_set_date_rejected() {
		if ( $this->has_status( 'rejected' ) ) {
			$this->set_date_rejected( time() );
		}
	}

	public function get_search_props() {
		return array(
			'order_number' => $this->get_order_number(),
			'first_name'   => $this->get_first_name(),
			'last_name'    => $this->get_last_name(),
			'email'        => $this->get_email(),
		);
	}

	public function has_status( $status ) {
		$statuses = (array) $status;

		foreach ( $statuses as $k => $status ) {
			if ( 'owb-' !== substr( $status, 0, 4 ) ) {
				$statuses[ $k ] = 'owb-' . $status;
			}
		}

		return apply_filters( 'eu_owb_woocommerce_order_withdrawal_has_status', in_array( $this->get_status(), $statuses, true ), $this, $statuses );
	}

	/**
	 * Maybe set date confirmed.
	 *
	 * @return void
	 */
	public function maybe_set_date_confirmed() {
		if ( $this->has_status( 'confirmed' ) ) {
			$this->set_date_confirmed( time() );
		}
	}

	public function get_edit_order_url() {
		if ( $parent = $this->get_parent() ) {
			return $parent->get_edit_order_url();
		}

		return '';
	}

	/**
	 * Updates status of order immediately.
	 *
	 * @uses self::set_status()
	 * @param string $new_status    Status to change the order to. No internal wc- prefix is required.
	 * @param bool   $manual        Is this a manual order status change?.
	 * @return bool
	 */
	public function update_status( $new_status, $manual = false ) {
		if ( ! $this->get_id() ) { // Order must exist.
			return false;
		}

		try {
			$this->set_status( $new_status, $manual );
			$this->save();
		} catch ( \Exception $e ) {
			Package::log( sprintf( 'Error updating status for withdrawal order #%d', $this->get_id() ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * @param $types
	 *
	 * @return WithdrawalItem[]
	 */
	public function get_items( $types = 'withdrawal' ) {
		$types = 'withdrawal';

		return parent::get_items( $types );
	}

	protected function get_items_key( $item ) {
		$key = '';

		if ( is_a( $item, '\Vendidero\OrderWithdrawalButton\WithdrawalItem' ) ) {
			$key = 'withdrawal_lines';
		}

		return $key;
	}

	/**
	 * Save data to the database.
	 *
	 * @since 3.0.0
	 * @return int order ID
	 */
	public function save() {
		parent::save();
		$this->status_transition();

		return $this->get_id();
	}

	protected function get_valid_statuses() {
		return array_keys( Package::get_withdrawal_statuses() );
	}

	/**
	 * Handle the status transition.
	 *
	 * @return void
	 */
	protected function status_transition() {
		$status_transition = $this->status_transition;

		// Reset status transition variable.
		$this->status_transition = false;

		if ( $status_transition ) {
			try {
				do_action( 'eu_owb_woocommerce_withdrawal_order_status_' . $status_transition['to'], $this->get_id(), $this, $status_transition );

				if ( ! empty( $status_transition['from'] ) ) {
					do_action( 'eu_owb_woocommerce_withdrawal_order_status_' . $status_transition['from'] . '_to_' . $status_transition['to'], $this->get_id(), $this );
					do_action( 'eu_owb_woocommerce_withdrawal_order_status_changed', $this->get_id(), $status_transition['from'], $status_transition['to'], $this );
				}
			} catch ( \Exception $e ) {
				Package::log( sprintf( 'Status transition of withdrawal order #%d errored!', $this->get_id() ), 'error' );
			}
		}
	}

	/**
	 * Get all class data in array format.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_data() {
		return array_merge(
			array(
				'id' => $this->get_id(),
			),
			$this->data,
			array(
				'meta_data'        => $this->get_meta_data(),
				'line_items'       => array(),
				'tax_lines'        => array(),
				'shipping_lines'   => array(),
				'fee_lines'        => array(),
				'coupon_lines'     => array(),
				'withdrawal_lines' => $this->get_items(),
			)
		);
	}
}
