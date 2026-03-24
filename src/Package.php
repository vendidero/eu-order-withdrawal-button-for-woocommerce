<?php

namespace Vendidero\OrderWithdrawalButton;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Package {
	/**
	 * Version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Init the package
	 */
	public static function init() {
		if ( ! self::has_dependencies() ) {
			return;
		}

		self::init_hooks();
		self::includes();

		do_action( 'eu_owb_woocommerce_init' );
	}

	protected static function init_hooks() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_feature_compatibility' ) );

		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'init', array( __CLASS__, 'check_version' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_plugin_links' ) );
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'filter_templates' ), 50, 3 );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'register_order_statuses' ) );
		add_action( 'init', array( __CLASS__, 'register_post_statuses' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_emails' ), 20 );
		add_action( 'init', array( __CLASS__, 'email_hooks' ), 10 );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'email_styles' ), 20 );
		add_filter( 'woocommerce_template_directory', array( __CLASS__, 'set_woocommerce_template_dir' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'order_withdrawal_details' ), 100, 1 );
		add_filter( 'woocommerce_admin_order_actions', array( __CLASS__, 'admin_order_actions' ), 1500, 2 );
		add_filter( 'woocommerce_get_sections_advanced', array( __CLASS__, 'register_sections' ) );
		add_filter( 'woocommerce_get_settings_advanced', array( __CLASS__, 'register_settings' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'register_hidden_itemmeta' ), 10, 2 );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'display_custom_itemmeta' ), 10, 3 );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'process_withdrawal_rejection' ), 45 );
		add_filter( 'woocommerce_menu_order_count', array( __CLASS__, 'menu_order_count' ) );

		add_action( 'init', array( __CLASS__, 'maybe_embed' ) );
	}

	public static function maybe_embed() {
		if ( 'yes' === self::get_setting( 'enable_embed', 'yes' ) && eu_owb_has_public_withdrawal_page() ) {
			$footer_hook = self::get_theme_footer_hook();

			add_action( $footer_hook['hook'], array( __CLASS__, 'print_button' ), (int) $footer_hook['priority'] );
		}
	}

	public static function is_shop_request() {
		return apply_filters( 'eu_owb_woocommerce_is_shop_request', ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) );
	}

	public static function print_button() {
		if ( self::is_shop_request() ) {
			wc_get_template( 'global/order-withdrawal-button.php' );
		}
	}

	public static function register_plugin_links() {
		if ( self::is_standalone() && ! self::is_integration() ) {
			add_filter( 'plugin_action_links_' . plugin_basename( trailingslashit( self::get_path() ) . 'eu-order-withdrawal-button-for-woocommerce.php' ), array( __CLASS__, 'plugin_action_links' ) );
		}
	}

	public static function plugin_action_links( $links ) {
		return array_merge(
			array(
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=owb' ) ) . '">' . _x( 'Settings', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) . '</a>',
			),
			$links
		);
	}

	public static function menu_order_count( $count ) {
		$count += wc_orders_count( 'pending-wdraw' );

		return $count;
	}

	public static function process_withdrawal_rejection( $order_id ) {
		if ( $order = wc_get_order( $order_id ) ) {
			if ( isset( $_POST['reject_withdrawal_request'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$reason = isset( $_POST['eu_owb_reject_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['eu_owb_reject_reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

				eu_owb_order_reject_withdrawal_request( $order, $reason );
			}
		}
	}

	protected static function get_theme_footer_hook() {
		$custom_hooks = array(
			'astra'      => array(
				'hook'     => 'astra_footer',
				'priority' => 50,
			),
			'storefront' => array(
				'hook'     => 'storefront_footer',
				'priority' => 20,
			),
		);

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : '';
		$hook  = array(
			'hook'     => 'wp_footer',
			'priority' => 5,
		);

		if ( array_key_exists( $theme->get_template(), $custom_hooks ) ) {
			$hook = wp_parse_args(
				$custom_hooks[ $theme->get_template() ],
				array(
					'hook'     => '',
					'priority' => 10,
				)
			);
		}

		return $hook;
	}

	public static function display_custom_itemmeta( $item_id, $item, $product ) {
		$show_withdrawn_quantity = false;

		if ( $order = $item->get_order() ) {
			if ( eu_owb_order_has_withdrawal_status( $order ) ) {
				$show_withdrawn_quantity = true;
			}
		}

		if ( $show_withdrawn_quantity && 'yes' === $item->get_meta( '_has_withdrawal' ) ) {
			$quantity = (float) wc_format_decimal( $item->get_meta( '_withdrawn_quantity', true ) );
			?>
			<span class="eu-owb-order-item-has-withdrawal"><?php echo wp_kses_post( sprintf( _x( 'Withdrawn %1$sx', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), $quantity ) ); ?></span>
			<?php
		}
	}

	public static function register_hidden_itemmeta( $hidden_meta ) {
		return array_merge(
			$hidden_meta,
			array(
				'_withdrawn_quantity',
				'_has_withdrawal',
			)
		);
	}

	public static function admin_init() {
		add_filter( 'handle_bulk_actions-' . ( 'shop_order' === self::get_order_screen_id() ? 'edit-shop_order' : self::get_order_screen_id() ), array( __CLASS__, 'handle_order_bulk_actions' ), 10, 3 );
		add_filter( 'bulk_actions-' . ( 'shop_order' === self::get_order_screen_id() ? 'edit-shop_order' : self::get_order_screen_id() ), array( __CLASS__, 'register_order_bulk_actions' ), 10, 1 );
	}

	public static function register_settings( $settings, $section_id ) {
		if ( ! self::is_integration() && 'owb' === $section_id ) {
			$settings = Settings::get_settings();
		}

		return $settings;
	}

	public static function register_sections( $sections ) {
		if ( ! self::is_integration() ) {
			$sections['owb'] = _x( 'Withdrawals', 'owb-setting-section', 'eu-order-withdrawal-button-for-woocommerce' );
		}

		return $sections;
	}

	public static function set_woocommerce_template_dir( $dir, $template ) {
		if ( file_exists( self::get_path() . '/templates/' . $template ) ) {
			return untrailingslashit( self::get_template_path() );
		}

		return $dir;
	}

	public static function has_email_improvements_enabled() {
		$is_enabled = false;

		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			$is_enabled = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'email_improvements' );
		}

		return $is_enabled;
	}

	public static function register_emails( $emails ) {
		$emails['EU_OWB_Email_Customer_Withdrawal_Request_Received']  = include self::get_path() . '/includes/emails/class-eu-owb-email-customer-withdrawal-request-received.php';
		$emails['EU_OWB_Email_Customer_Withdrawal_Request_Confirmed'] = include self::get_path() . '/includes/emails/class-eu-owb-email-customer-withdrawal-request-confirmed.php';
		$emails['EU_OWB_Email_Customer_Withdrawal_Request_Rejected']  = include self::get_path() . '/includes/emails/class-eu-owb-email-customer-withdrawal-request-rejected.php';
		$emails['EU_OWB_Email_New_Withdrawal_Request']                = include self::get_path() . '/includes/emails/class-eu-owb-email-new-withdrawal-request.php';

		return $emails;
	}

	public static function email_hooks() {
		add_action( 'eu_owb_woocommerce_withdrawal_request_details', array( __CLASS__, 'withdrawal_email_edit_link' ), 10, 4 );
		add_action( 'eu_owb_woocommerce_withdrawal_request_details', array( __CLASS__, 'withdrawal_email_details' ), 20, 4 );
	}

	public static function email_styles( $styles ) {
		return $styles . '
			#body_content .email-withdrawal-details tbody tr:last-child td {
                border-bottom: 0;
                padding-bottom: 0;
            }
		';
	}

	public static function withdrawal_email_edit_link( $order, $sent_to_admin, $plain_text, $email ) {
		if ( is_a( $email, 'EU_OWB_Email_Customer_Withdrawal_Request_Received' ) ) {
			$is_same_recipient    = $order->get_billing_email() === eu_owb_get_order_withdrawal_email( $order );
			$edit_withdrawal_link = eu_owb_get_edit_withdrawal_url( $order );

			if ( ! eu_owb_order_is_partial_withdrawal( $order ) && ! empty( $edit_withdrawal_link ) && eu_owb_order_is_guest_withdrawal( $order ) && $is_same_recipient && eu_owb_order_supports_partial_withdrawal( $order ) ) {
				if ( $plain_text ) {
					wc_get_template(
						'emails/plain/email-withdrawal-edit-link.php',
						array(
							'order'                => $order,
							'sent_to_admin'        => $sent_to_admin,
							'plain_text'           => $plain_text,
							'email'                => $email,
							'edit_withdrawal_link' => $edit_withdrawal_link,
						)
					);
				} else {
					wc_get_template(
						'emails/email-withdrawal-edit-link.php',
						array(
							'order'                => $order,
							'sent_to_admin'        => $sent_to_admin,
							'plain_text'           => $plain_text,
							'email'                => $email,
							'edit_withdrawal_link' => $edit_withdrawal_link,
						)
					);
				}
			}
		}
	}

	public static function withdrawal_email_details( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $plain_text ) {
			wc_get_template(
				'emails/plain/email-withdrawal-details.php',
				array(
					'order'         => $order,
					'sent_to_admin' => $sent_to_admin,
					'plain_text'    => $plain_text,
					'email'         => $email,
				)
			);
		} else {
			wc_get_template(
				'emails/email-withdrawal-details.php',
				array(
					'order'         => $order,
					'sent_to_admin' => $sent_to_admin,
					'plain_text'    => $plain_text,
					'email'         => $email,
				)
			);
		}
	}

	public static function handle_order_bulk_actions( $redirect_to, $action, $ids ) {
		$ids           = apply_filters( 'woocommerce_bulk_action_ids', array_reverse( array_map( 'absint', $ids ) ), $action, 'order' );
		$changed       = 0;
		$report_action = '';

		if ( 'confirm_withdrawal_requests' === $action ) {
			foreach ( $ids as $id ) {
				$order         = wc_get_order( $id );
				$report_action = 'confirm_withdrawal_requests';

				if ( $order && eu_owb_order_has_pending_withdrawal_request( $order ) ) {
					$result = eu_owb_order_confirm_withdrawal_request( $order );

					if ( $result ) {
						++$changed;
					}
				}
			}
		}

		if ( $changed ) {
			$redirect_query_args = array(
				'post_type'   => 'shop_order',
				'bulk_action' => $report_action,
				'changed'     => $changed,
				'ids'         => join( ',', $ids ),
				'status'      => 'wc-withdrawn',
			);

			if ( self::is_hpos_enabled() ) {
				unset( $redirect_query_args['post_type'] );
				$redirect_query_args['page'] = 'wc-orders';
			}

			$redirect_to = add_query_arg(
				$redirect_query_args,
				$redirect_to
			);

			return esc_url_raw( $redirect_to );
		} else {
			return $redirect_to;
		}
	}

	public static function register_order_bulk_actions( $actions ) {
		$actions['confirm_withdrawal_requests'] = _x( 'Confirm withdrawal requests', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );

		return $actions;
	}

	public static function get_order_screen_id() {
		return function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	}

	protected static function get_order_screen_ids() {
		$screen_ids = array();

		foreach ( wc_get_order_types() as $type ) {
			$screen_ids[] = $type;
			$screen_ids[] = 'edit-' . $type;
		}

		$screen_ids[] = self::get_order_screen_id();

		return array_filter( $screen_ids );
	}

	public static function get_screen_ids() {
		$other_screen_ids = array();

		return array_merge( self::get_order_screen_ids(), $other_screen_ids );
	}

	public static function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		wp_register_script( 'eu-owb-woocommerce-admin-order', self::get_assets_url( 'static/admin-order.js' ), array( 'jquery', 'woocommerce_admin' ), self::get_version() ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter

		// Register admin styles.
		wp_register_style( 'eu-owb-woocommerce-admin-styles', self::get_assets_url( 'static/admin-styles.css' ), array( 'woocommerce_admin_styles' ), self::get_version() );

		// Admin styles for WC pages only.
		if ( in_array( $screen_id, self::get_screen_ids(), true ) ) {
			wp_localize_script(
				'eu-owb-woocommerce-admin-order',
				'eu_owb_woocommerce_admin_order_params',
				array(
					'i18n_reject_withdrawal'  => _x( 'Do you really want to reject the withdrawal?', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
					'i18n_confirm_withdrawal' => _x( 'Are you sure to confirm the withdrawal?', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				)
			);

			wp_enqueue_style( 'eu-owb-woocommerce-admin-styles' );
			wp_enqueue_script( 'eu-owb-woocommerce-admin-order' );
		}
	}

	/**
	 * @param array $actions
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	public static function admin_order_actions( $actions, $order ) {
		if ( eu_owb_order_has_pending_withdrawal_request( $order ) ) {
			$actions                               = array();
			$actions['confirm_withdrawal_request'] = array(
				'url'    => self::get_edit_withdrawal_url( $order->get_id() ),
				'name'   => _x( 'Confirm withdrawal request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'action' => 'complete',
			);
		}

		return $actions;
	}

	public static function order_withdrawal_details( $order ) {
		if ( ! eu_owb_order_has_withdrawal_status( $order ) ) {
			return;
		}

		$confirmation_needs_confirm = apply_filters( 'eu_owb_woocommerce_withdrawal_confirmation_needs_confirm', true ) ? 'eu-owb-woocommerce-needs-confirmation' : '';
		$rejection_needs_confirm    = apply_filters( 'eu_owb_woocommerce_withdrawal_rejection_needs_confirm', true ) ? 'eu-owb-woocommerce-needs-confirmation' : '';
		?>
		<div class="eu-owb-order-withdrawal-request">
			<h3><?php echo ( eu_owb_order_is_partial_withdrawal( $order ) ) ? esc_html_x( 'Partial Withdrawal Request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) : esc_html_x( 'Full Withdrawal Request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?></h3>

			<p><?php echo wp_kses_post( sprintf( _x( 'Received on %1$s @ %2$s by <a href="mailto:%3$s">%3$s</a>', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), wc_format_datetime( eu_owb_get_order_withdrawal_date( $order ) ), wc_format_datetime( eu_owb_get_order_withdrawal_date( $order ), get_option( 'time_format' ) ), eu_owb_get_order_withdrawal_email( $order ) ) ); ?></p>

			<?php if ( eu_owb_order_has_pending_withdrawal_request( $order ) ) : ?>
				<div class="eu-owb-order-withdrawal-request-buttons">
					<a href="<?php echo esc_url( self::get_edit_withdrawal_url( $order->get_id() ) ); ?>" class="eu-owb-confirm-withdrawal-request button button-primary tips <?php echo esc_attr( $confirmation_needs_confirm ); ?>" data-confirm="<?php echo esc_attr_x( 'Are you sure to confirm the withdrawal request?', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>" data-tip="<?php echo esc_attr_x( 'Confirm the withdrawal request to the customer.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>"><?php echo esc_html_x( 'Confirm withdrawal request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?></a>
					<a href="#" class="eu-owb-reject-withdrawal-request-start tips" data-tip="<?php echo esc_attr_x( 'Reject the withdrawal request by providing a reason for the rejection.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>"><?php echo esc_html_x( 'Reject request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?></a>
				</div>
				<div class="eu-owb-reject-withdrawal-request-form hidden">
					<p class="form-field form-field-wide">
						<label for="eu_owb_reject_reason"><?php echo esc_html_x( 'Reason', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>:</label>
						<textarea rows="5" cols="40" name="eu_owb_reject_reason" tabindex="6" id="eu_owb_reject_reason" placeholder="<?php echo esc_attr_x( 'Describe why you\'ve rejected the withdrawal request.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>"></textarea>
					</p>

					<input type="hidden" name="eu_owb_reject_order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
					<?php wp_nonce_field( 'eu_owb_reject_withdrawal_request', 'eu_owb_reject_withdrawal_request_nonce' ); ?>

					<p>
						<button type="submit" class="button button-primary <?php echo esc_attr( $rejection_needs_confirm ); ?>" data-confirm="<?php echo esc_attr_x( 'Are you sure to reject the withdrawal request?', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>" id="eu-owb-reject-withdrawal-request-submit" name="reject_withdrawal_request" value="<?php echo esc_attr_x( 'Reject withdrawal request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?>"><?php echo esc_html_x( 'Reject withdrawal request', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ); ?></button>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function get_edit_withdrawal_url( $order_id, $type = 'confirm', $args = array() ) {
		return esc_url_raw( wp_nonce_url( add_query_arg( $args, admin_url( "admin-ajax.php?action=eu_owb_woocommerce_{$type}_withdrawal_request&order_id={$order_id}" ) ), "eu_owb_woocommerce_{$type}_withdrawal_request" ) );
	}

	public static function register_post_statuses() {
		register_post_status(
			'wc-pending-wdraw',
			array(
				'label'                     => _x( 'Pending withdrawal', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'public'                    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _nx_noop( 'Pending withdrawal (%s)', 'Pending withdrawals (%s)', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			)
		);

		register_post_status(
			'wc-withdrawn',
			array(
				'label'                     => _x( 'Withdrawn', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
				'public'                    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _nx_noop( 'Withdrawn (%s)', 'Withdrawn (%s)', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ),
			)
		);
	}

	public static function register_order_statuses( $statuses ) {
		/**
		 * Need to shorten wc-pending-withdrawal as it is too long for the custom order table limitation.
		 */
		$statuses['wc-pending-wdraw'] = _x( 'Pending withdrawal', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );
		$statuses['wc-withdrawn']     = _x( 'Withdrawn', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );

		return $statuses;
	}

	public static function register_shortcodes() {
		add_shortcode( 'eu_owb_order_withdrawal_request_form', array( __CLASS__, 'order_withdrawal_request_form' ) );

		/**
		 * Mark the return page as a Woo page to make sure default form styles work.
		 */
		add_filter(
			'is_woocommerce',
			function ( $is_woocommerce ) {
				if ( wc_post_content_has_shortcode( 'eu_owb_order_withdrawal_request_form' ) ) {
					$is_woocommerce = true;
				}

				return $is_woocommerce;
			}
		);
	}

	public static function order_withdrawal_request_form( $args = array() ) {
		$order_key             = isset( $_GET['order_key'] ) ? wc_clean( wp_unslash( $_GET['order_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id              = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$manually_select_items = isset( $_GET['manually_select_items'] ) ? wc_string_to_bool( wc_clean( wp_unslash( $_GET['manually_select_items'] ) ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order                 = null;

		if ( ! empty( $order_id ) && ( $the_order = wc_get_order( $order_id ) ) ) {
			if ( $order_id === $the_order->get_id() && ! empty( $the_order->get_order_key() ) && hash_equals( $the_order->get_order_key(), $order_key ) ) {
				$order = $the_order;
			} elseif ( is_user_logged_in() && current_user_can( 'view_order', $the_order->get_id() ) ) {
				$order = $the_order;
			}
		}

		$defaults = array(
			'order'                 => $order,
			'order_key'             => $order_key,
			'manually_select_items' => $manually_select_items,
		);

		$args    = wp_parse_args( $args, $defaults );
		$notices = function_exists( 'wc_print_notices' ) ? wc_print_notices( true ) : '';
		$html    = '';

		// Output notices in case notices have not been outputted yet.
		if ( ! empty( $notices ) ) {
			$html .= '<div class="woocommerce">' . $notices . '</div>';
		}

		$html .= wc_get_template_html( 'forms/order-withdrawal-request.php', $args );

		return $html;
	}

	public static function is_integration() {
		return apply_filters( 'eu_owb_woocommerce_is_integration', false );
	}

	public static function is_hpos_enabled() {
		if ( ! is_callable( array( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	public static function deactivate() {
		Install::deactivate();
	}

	public static function install() {
		self::init();

		if ( ! self::has_dependencies() ) {
			return;
		}

		Install::install();
	}

	public static function install_integration() {
		self::install();
	}

	public static function is_standalone() {
		return defined( 'EU_OWB_WC_IS_STANDALONE_PLUGIN' ) && EU_OWB_WC_IS_STANDALONE_PLUGIN;
	}

	public static function check_version() {
		if ( self::is_standalone() && self::has_dependencies() && ! defined( 'IFRAME_REQUEST' ) && ( get_option( 'eu_owb_woocommerce_version' ) !== self::get_version() ) ) {
			Install::install();

			do_action( 'eu_owb_woocommerce_updated' );
		}
	}

	public static function log( $message, $type = 'info', $source = '' ) {
		/**
		 * Filter that allows adjusting whether to enable or disable
		 * logging for the shipments package
		 *
		 * @param boolean $enable_logging True if logging should be enabled. False otherwise.
		 */
		if ( ! apply_filters( 'eu_owb_woocommerce_enable_logging', false ) ) {
			return;
		}

		$logger = wc_get_logger();

		if ( ! $logger ) {
			return;
		}

		if ( ! is_callable( array( $logger, $type ) ) ) {
			$type = 'info';
		}

		$logger->{$type}( $message, array( 'source' => 'eu-owb-woocommerce' . ( ! empty( $source ) ? '-' . $source : '' ) ) );
	}

	public static function has_dependencies() {
		return class_exists( 'WooCommerce' );
	}

	private static function includes() {
		Ajax::init();

		include_once self::get_path() . '/includes/eu-owb-core-functions.php';
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_template_path() {
		return apply_filters( 'eu_owb_woocommerce_template_path', 'woocommerce/' );
	}

	/**
	 * Filter WooCommerce Templates to look into /templates before looking within theme folder
	 *
	 * @param string $template
	 * @param string $template_name
	 * @param string $template_path
	 *
	 * @return string
	 */
	public static function filter_templates( $template, $template_name, $template_path ) {
		$default_template_path = apply_filters( 'eu_owb_woocommerce_default_template_path', self::get_path() . '/templates/' . $template_name, $template_name );

		if ( file_exists( $default_template_path ) ) {
			$template_path = self::get_template_path();

			// Check for Theme overrides
			$theme_template = locate_template(
				apply_filters(
					'eu_owb_woocommerce_locate_theme_template_locations',
					array(
						trailingslashit( $template_path ) . $template_name,
					),
					$template_name
				)
			);

			if ( 'forms/order-withdrawal-request.php' === $template_name ) {
				self::register_script( 'eu-owb-woocommerce', 'static/order-withdrawal.js', array( 'jquery', 'woocommerce' ) );

				wp_register_style( 'eu-owb-woocommerce-form', self::get_assets_url( 'static/form-styles.css' ), array(), self::get_version() );
				wp_enqueue_style( 'eu-owb-woocommerce-form' );

				wp_localize_script(
					'eu-owb-woocommerce',
					'eu_owb_woocommerce_order_withdrawal_params',
					array(
						'wc_ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
					)
				);

				wp_enqueue_script( 'eu-owb-woocommerce' );
			}

			if ( ! $theme_template ) {
				$template = $default_template_path;
			} else {
				$template = $theme_template;
			}
		}

		return $template;
	}

	public static function declare_feature_compatibility() {
		if ( ! self::is_standalone() ) {
			return;
		}

		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', trailingslashit( self::get_path() ) . 'eu-order-withdrawal-button-for-woocommerce.php', true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', trailingslashit( self::get_path() ) . 'eu-order-withdrawal-button-for-woocommerce.php', true );
		}
	}

	/**
	 * Return the version of the package.
	 *
	 * @return string
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_path( $rel_path = '' ) {
		return trailingslashit( dirname( __DIR__ ) ) . $rel_path;
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_url( $rel_path = '' ) {
		return trailingslashit( plugins_url( '', __DIR__ ) ) . $rel_path;
	}

	public static function register_script( $handle, $path, $dep = array(), $ver = '', $in_footer = array( 'strategy' => 'defer' ) ) {
		global $wp_version;

		if ( version_compare( $wp_version, '6.3', '<' ) ) {
			$in_footer = true;
		}

		$ver = empty( $ver ) ? self::get_version() : $ver;

		wp_register_script(
			$handle,
			self::get_assets_url( $path ),
			$dep,
			$ver,
			$in_footer
		);
	}

	public static function get_assets_url( $script_or_style ) {
		$assets_url = self::get_url( 'build' );
		$is_debug   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$is_style   = '.css' === substr( $script_or_style, -4 );
		$is_static  = strstr( $script_or_style, 'static/' );

		if ( $is_debug && $is_static && ! $is_style ) {
			$assets_url = self::get_url( 'assets/js' );
		}

		return trailingslashit( $assets_url ) . $script_or_style;
	}

	public static function enable_partial_withdrawals() {
		return wc_string_to_bool( self::get_setting( 'enable_partial_withdrawals', 'yes' ) );
	}

	public static function get_setting( $name, $default_value = false ) {
		$option_name = "eu_owb_woocommerce_{$name}";

		return get_option( $option_name, $default_value );
	}
}
