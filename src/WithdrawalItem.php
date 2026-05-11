<?php

namespace Vendidero\OrderWithdrawalButton;

defined( 'ABSPATH' ) || exit;

class WithdrawalItem extends \WC_Order_Item {

	/**
	 * Order Data array. This is the core order data exposed in APIs since 3.0.0.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	protected $extra_data = array(
		'parent_id' => 0,
		'quantity'  => 1,
	);

	protected $parent = null;

	/**
	 * Get order item type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'withdrawal';
	}

	public function calculate_taxes( $calculate_tax_for = array() ) {
		return true;
	}

	public function get_parent_id( $context = 'view' ) {
		return $this->get_prop( 'parent_id', $context );
	}

	public function set_parent_id( $parent_id ) {
		$this->set_prop( 'parent_id', absint( $parent_id ) );
		$this->parent = null;
	}

	/**
	 * @return \WC_Order_Item_Product|false
	 */
	public function get_parent() {
		if ( is_null( $this->parent ) ) {
			$this->parent = false;

			if ( $this->get_parent_id() > 0 ) {
				if ( $order = $this->get_order() ) {
					$this->parent = $order->get_item( $this->get_parent_id() );
				}
			}
		}

		return $this->parent;
	}

	public function get_product() {
		return $this->get_parent() ? $this->get_parent()->get_product() : null;
	}

	/**
	 * Get quantity.
	 *
	 * @param  string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return int
	 */
	public function get_quantity( $context = 'view' ) {
		return $this->get_prop( 'quantity', $context );
	}

	/**
	 * Set quantity.
	 *
	 * @param int $value Quantity.
	 */
	public function set_quantity( $value ) {
		$this->set_prop( 'quantity', wc_stock_amount( $value ) );
	}
}
