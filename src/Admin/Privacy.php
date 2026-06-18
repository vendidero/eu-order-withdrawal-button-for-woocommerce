<?php

namespace Vendidero\OrderWithdrawalButton\Admin;

use Vendidero\OrderWithdrawalButton\Package;

defined( 'ABSPATH' ) || exit;

class Privacy {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function register_exporter() {
		$exporters['eu-owb-woocommerce'] = array(
			'exporter_friendly_name' => _x( 'EU Order Withdrawal Button for WooCommerce', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	public static function register_eraser() {
		$exporters['eu-owb-woocommerce'] = array(
			'eraser_friendly_name' => _x( 'EU Order Withdrawal Button for WooCommerce', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $exporters;
	}

	public static function erase( $email_address, $page = 1 ) {
		$response    = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
		$withdrawals = self::query( $email_address, $page );

		if ( 0 < count( $withdrawals ) ) {
			foreach ( $withdrawals as $withdrawal ) {
				if ( apply_filters( 'eu_owb_woocommerce_privacy_erase_withdrawal_personal_data', false, $withdrawal ) ) {
					$anonymized_data = array();
					$props_to_remove = array(
						'customer_ip_address'    => 'ip',
						'customer_user_agent'    => 'text',
						'first_name'             => 'text',
						'last_name'              => 'text',
						'billing_address_index'  => 'text',
						'email'                  => 'email',
						'additional_information' => 'text',
						'customer_id'            => 'numeric_id',
					);

					foreach ( $props_to_remove as $prop => $data_type ) {
						// Get the current value in edit context.
						$value = $withdrawal->{"get_$prop"}( 'edit' );

						// If the value is empty, it does not need to be anonymized.
						if ( empty( $value ) || empty( $data_type ) ) {
							continue;
						}

						$anonymized_data[ $prop ] = function_exists( 'wp_privacy_anonymize_data' ) ? wp_privacy_anonymize_data( $data_type, $value ) : '';
					}

					// Set all new props and persist the new data to the database.
					$withdrawal->set_props( $anonymized_data );

					$withdrawal->update_meta_data( '_anonymized', 'yes' );
					$withdrawal->save();
					$withdrawal->add_order_note( _x( 'Personal data removed.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) );

					/* Translators: %s Order number. */
					$response['messages'][]    = sprintf( _x( 'Removed personal data from withdrawal %s.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), $withdrawal->get_id() );
					$response['items_removed'] = true;
				} else {
					/* Translators: %s Order number. */
					$response['messages'][]     = sprintf( _x( 'Personal data within withdrawal %s has been retained.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), $withdrawal->get_id() );
					$response['items_retained'] = true;
				}
			}

			$response['done'] = 10 > count( $withdrawals );
		} else {
			$response['done'] = true;
		}

		return $response;
	}

	protected static function query( $email_address, $page = 1 ) {
		$page        = max( 1, (int) $page );
		$order_query = array(
			'type'     => 'shop_order_withdraw',
			'limit'    => 10,
			'status'   => array_keys( Package::get_withdrawal_statuses() ),
			'page'     => $page,
			'customer' => array( $email_address ),
		);
		$user        = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		if ( $user instanceof \WP_User ) {
			$order_query['customer'][] = (int) $user->ID;
		}

		return wc_get_orders( $order_query );
	}

	public static function export( $email_address, $page = 1 ) {
		$response    = array(
			'data' => array(),
			'done' => true,
		);
		$withdrawals = self::query( $email_address, $page );
		$props       = array(
			'order_number'           => _x( 'Order number', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'first_name'             => _x( 'First name', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'last_name'              => _x( 'Last name', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'email'                  => _x( 'Email', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'date_received'          => _x( 'Date received', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'additional_information' => _x( 'Additional information', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'customer_ip_address'    => _x( 'IP Address', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'customer_user_agent'    => _x( 'User Agent', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'items'                  => _x( 'Items', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			'status'                 => _x( 'Status', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
		);

		if ( 0 < count( $withdrawals ) ) {
			foreach ( $withdrawals as $withdrawal ) {
				$personal_data = array();

				foreach ( $props as $prop => $name ) {
					$value = null;

					switch ( $prop ) {
						case 'items':
							$item_names = array();
							foreach ( $withdrawal->get_items() as $item ) {
								$item_names[] = $item->get_name() . ' x ' . $item->get_quantity();
							}
							$value = implode( ', ', $item_names );
							break;
						case 'date_received':
							$value = wc_format_datetime( $withdrawal->get_date_received(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );
							break;
						default:
							if ( is_callable( array( $withdrawal, 'get_' . $prop ) ) ) {
								$value = $withdrawal->{"get_$prop"}();
							}
							break;
					}

					if ( $value ) {
						$personal_data[] = array(
							'name'  => $name,
							'value' => $value,
						);
					}
				}

				$response['data'][] = array(
					'group_id'          => 'eu_owb_woocommerce_withdrawals',
					'group_label'       => _x( 'Withdrawals', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
					'group_description' => _x( 'User&#8217;s withdrawals data.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
					'item_id'           => 'withdrawal-' . $withdrawal->get_id(),
					'data'              => $personal_data,
				);
			}

			$response['done'] = 10 > count( $withdrawals );
		} else {
			$response['done'] = true;
		}

		return $response;
	}

	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		ob_start();
		include Package::get_path() . '/includes/admin/views/html-privacy-policy.php';
		$html = ob_get_clean();

		wp_add_privacy_policy_content(
			_x( 'EU Order Withdrawal Button for WooCommerce', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			wp_kses_post( $html )
		);
	}
}
