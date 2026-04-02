<?php
/**
 * E-Mail withdrawal edit link
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/email-withdrawal-edit-link.php.
 *
 * HOWEVER, on occasion EU OWB will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @package Vendidero/OrderWithdrawalButton/Templates
 * @version 2.0.0
 */
defined( 'ABSPATH' ) || exit;

$has_multiple = eu_owb_order_withdrawal_request_has_multiple_orders( $withdrawal );
?>
<?php if ( $has_multiple ) : ?>
	<?php echo esc_html_x( 'More than one matching order found', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) . "\n\n"; ?>
<?php else : ?>
	<?php echo esc_html_x( 'Want to withdraw certain items only?', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) . "\n\n"; ?>
<?php endif; ?>

<?php if ( $has_multiple ) : ?>
	<?php echo esc_html_x( 'We found more than one order matching your criteria—please use the link below to edit your withdrawal or select a different order.', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ) . "\n\n"; ?>
<?php endif; ?>

<?php if ( $has_multiple ) : ?>
	<?php echo sprintf( esc_html_x( 'Edit withdrawal request: %s', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), esc_url( $edit_withdrawal_link ) ) . "\n\n"; ?>
<?php else : ?>
	<?php echo sprintf( esc_html_x( 'Choose items to withdraw now: %s', 'owb', 'eu-order-withdrawal-button-for-woocommerce' ), esc_url( $edit_withdrawal_link ) ) . "\n\n"; ?>
	<?php
endif;
