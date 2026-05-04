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
		return sprintf( _x( 'Configure your EU-compliant order withdrawal button. <a href="https://vendidero.com/implementing-legally-compliant-eu-order-withdrawal-button-in-woocommerce" target="_blank">Need help?</a>', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) );
	}

	public static function get_help_url() {
		return 'https://vendidero.com/implementing-legally-compliant-eu-order-withdrawal-button-in-woocommerce';
	}

	public static function get_settings( $current_section = '' ) {
		$default_email = get_option( 'admin_email' );
		$woo_mail      = sanitize_email( get_option( 'woocommerce_email_from_address' ) );

		if ( $woo_mail ) {
			$default_email = $woo_mail;
		}

		$settings = array(
			array(
				'title' => '',
				'type'  => 'title',
				'id'    => 'owb_options',
				'desc'  => Package::is_integration() ? '' : self::get_description(),
			),

			array(
				'title'    => _x( 'Withdrawal page', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'id'       => 'woocommerce_withdraw_from_contract_page_id',
				'type'     => 'single_select_page_with_search',
				'class'    => 'wc-page-search',
				'desc_tip' => _x( 'This page should contain your withdrawal form shortcode.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'args'     => array(
					'exclude' => array(),
				),
				'default'  => '',
				'css'      => 'min-width:300px;',
				'autoload' => false,
			),

			array(
				'title'    => _x( 'Embed in footer', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc'     => _x( 'Embed the withdrawal button directly in the footer. Disable this option if you plan to embed the page manually.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'id'       => 'eu_owb_woocommerce_enable_embed',
				'type'     => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default'  => 'yes',
				'autoload' => true,
			),

			array(
				'title'    => _x( 'Partial withdrawals', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc'     => _x( 'Allow your customers to select which order items to withdraw.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'id'       => 'eu_owb_woocommerce_enable_partial_withdrawals',
				'type'     => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default'  => 'yes',
				'autoload' => false,
			),

			array(
				'title'    => _x( 'Non-refundable', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc'     => _x( 'Choose certain product types to exclude from being withdrawn.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc_tip' => true,
				'id'       => 'eu_owb_woocommerce_excluded_product_types',
				'class'    => 'wc-enhanced-select',
				'type'     => 'multiselect',
				'options'  => self::get_product_type_options(),
				'default'  => array( 'virtual' ),
				'autoload' => false,
			),

			array(
				'title'     => _x( 'Withdrawal period', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc_tip'  => _x( 'Choose the number of days, starting with the orders\' completed date, to accept withdrawals for orders.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc'      => _x( 'Days', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) . '<div class="eu-owb-settings-additional-desc">' . sprintf( _x( 'Keep in mind that the withdrawal period does not begin until the customer receives the order. If necessary add a buffer period depending on your shipping process', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), esc_url( get_admin_url( null, 'admin.php?page=wc-orders&unverified_withdrawals=yes' ) ) ) . '</div>',
				'css'       => 'max-width: 60px;',
				'row_class' => 'withdrawal-period',
				'type'      => 'number',
				'id'        => 'eu_owb_woocommerce_number_of_days_to_withdraw',
				'default'   => '14',
				'autoload'  => false,
			),

			array(
				'title'    => _x( 'Unverified requests', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc'     => _x( 'Separately list unverified withdrawal requests.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) . '<div class="eu-owb-settings-additional-desc">' . sprintf( _x( 'For some requests, the email address differs from the original stored within the order. Make sure these requests are listed under <a href="%1$s">unverified requests</a> and are not automatically set to the pending withdrawal request status.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), esc_url( get_admin_url( null, 'admin.php?page=wc-orders&unverified_withdrawals=yes' ) ) ) . '</div>',
				'id'       => 'eu_owb_woocommerce_separately_store_unverified_withdrawal_requests',
				'type'     => Package::is_integration() ? 'gzd_toggle' : 'checkbox',
				'default'  => 'yes',
				'autoload' => false,
			),

			array(
				'title'        => _x( 'Contact email', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'desc_tip'     => _x( 'Please provide an email address where customers can contact you if they have any issues.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'id'           => 'eu_owb_woocommerce_contact_email_address',
				'type'         => 'email',
				'placeholder'  => $default_email,
				'default'      => '',
				'skip_install' => true,
				'autoload'     => false,
			),

			array(
				'type' => 'sectionend',
				'id'   => 'owb_options',
			),
		);

		return $settings;
	}

	protected static function get_product_type_options() {
		$product_types        = wc_get_product_types();
		$product_type_options = array_merge(
			array(
				'virtual'      => _x( 'Virtual Product', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'downloadable' => _x( 'Downloadable Product', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			),
			$product_types
		);

		return apply_filters( 'eu_owb_woocommerce_product_type_options', $product_type_options );
	}

	public static function before_save() {
	}

	public static function after_save() {
	}

	public static function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=owb' );
	}
}
