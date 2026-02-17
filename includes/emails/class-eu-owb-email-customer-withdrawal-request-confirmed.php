<?php
/**
 * Class EU_OWB_Email_Customer_Withdrawal_Request_Confirmed file.
 *
 * @package Vendidero/OrderWithdrawalButton/Emails
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'EU_OWB_Email_Customer_Withdrawal_Request_Confirmed', false ) ) :

	/**
	 * Customer withdrawal request confirmed.
	 *
	 * Confirm the withdrawal request to the customer.
	 *
	 * @class    EU_OWB_Email_Customer_Withdrawal_Request_Confirmed
	 * @version  1.0.0
	 * @extends  WC_Email
	 */
	class EU_OWB_Email_Customer_Withdrawal_Request_Confirmed extends WC_Email {

		/**
		 * Is this a partial withdrawal request?
		 *
		 * @var bool
		 */
		public $partial_withdrawal;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->customer_email = true;
			$this->id             = 'customer_withdrawal_request_confirmed';
			$this->title          = _x( 'Withdrawal request confirmed', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );
			$this->description    = _x( 'Confirms the withdrawal request to the customer.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );

			$this->template_html  = 'emails/customer-withdrawal-request-confirmed.php';
			$this->template_plain = 'emails/plain/customer-withdrawal-request-confirmed.php';
			$this->template_base  = \Vendidero\OrderWithdrawalButton\Package::get_path() . '/templates/';

			$this->placeholders = array(
				'{site_title}'      => $this->get_blogname(),
				'{order_number}'    => '',
				'{order_date}'      => '',
				'{withdrawal_date}' => '',
			);

			// Call parent constructor.
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return _x( 'Your withdrawal request for order #{order_number} has been confirmed.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 */
		public function get_default_heading( $partial = false ) {
			return _x( 'Your withdrawal request has been confirmed.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' );
		}

		/**
		 * Trigger.
		 *
		 * @param int $order_id Order ID.
		 */
		public function trigger( $order_id, $order = false, $recipient = '', $is_partial_withdrawal = null ) {
			$this->setup_locale();

			$this->recipient = $recipient;

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( $order ) {
				$this->object             = $order;
				$this->recipient          = eu_owb_get_order_withdrawal_email( $this->object );
				$this->partial_withdrawal = is_bool( $is_partial_withdrawal ) ? $is_partial_withdrawal : eu_owb_order_is_partial_withdrawal( $this->object );

				$this->placeholders['{order_number}']    = $this->object->get_order_number();
				$this->placeholders['{order_date}']      = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{withdrawal_date}'] = eu_owb_get_order_withdrawal_date( $this->object ) ? wc_format_datetime( eu_owb_get_order_withdrawal_date( $this->object ) ) : '';
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Return content from the additional_content field.
		 *
		 * Displayed above the footer.
		 *
		 * @return string
		 */
		public function get_additional_content() {
			if ( method_exists( get_parent_class( $this ), 'get_additional_content' ) ) {
				return parent::get_additional_content();
			}

			return '';
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'partial_withdrawal' => $this->partial_withdrawal,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				)
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'partial_withdrawal' => $this->partial_withdrawal,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				)
			);
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return '';
		}
	}

endif;

return new EU_OWB_Email_Customer_Withdrawal_Request_Confirmed();
