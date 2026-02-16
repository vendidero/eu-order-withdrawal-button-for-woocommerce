<?php

namespace Vendidero\OrderWithdrawalButton;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Install {

	public static function install() {
		$current_version = get_option( 'eu_owb_woocommerce_version', null );

		self::create_default_options();

		update_option( 'eu_owb_woocommerce_version', Package::get_version() );
		update_option( 'eu_owb_woocommerce_db_version', Package::get_version() );
	}

	public static function deactivate() {}

	protected static function create_default_options() {
	}
}
