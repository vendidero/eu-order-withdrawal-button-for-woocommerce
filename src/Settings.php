<?php

namespace Vendidero\OrderWithdrawalButton;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Settings {

	public static function get_sections() {
		return array(
			'' => _x( 'General', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
		);
	}

	public static function get_description() {
		return sprintf( _x( 'Configure your EU conformant order withdrawal button.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) );
	}

	public static function get_help_url() {
		return '';
	}

	public static function get_settings( $current_section = '' ) {
		$settings = array(
			array(
				'title' => '',
				'type'  => 'title',
				'id'    => 'owb_options',
				'desc'  => Package::is_integration() ? '' : self::get_description(),
			),

			array(
				'title'    => _x( 'Withdrawal page', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'id'       => 'woocommerce_withdrawal_form_page_id',
				'type'     => 'single_select_page_with_search',
				'class'    => 'wc-page-search',
				'desc_tip' => _x( 'This page should contain your withdrawal form shortcode.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'args'     => array(
					'exclude' => array(),
				),
				'default'  => '',
				'css'      => 'min-width:300px;',
			),

			array(
				'title'   => _x( 'Partial withdrawals', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc'    => _x( 'Allow your customers to select which order items to withdraw.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'id'      => 'eu_owb_woocommerce_enable_partial_withdrawals',
				'type'    => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default' => 'yes',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'owb_options',
			),
		);

		return $settings;
	}

	public static function before_save() {
	}

	public static function after_save() {
	}

	public static function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=owb' );
	}
}
