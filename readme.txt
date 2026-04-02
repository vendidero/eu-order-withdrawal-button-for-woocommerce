=== EU Order Withdrawal Button for WooCommerce ===
Contributors: vendidero, vdwoocommercesupport
Tags: woocommerce, withdrawal, cancellation, EU, compliance
Requires at least: 5.4
Tested up to: 7.0
WC requires at least: 3.9
WC tested up to: 10.6
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

This plugin helps to comply with the latest EU directive 2023/2673 by embedding a withdrawal button within your WooCommerce store.

== Description ==

EU Order Withdrawal Button for WooCommerce adds compliance with the [EU directive 2023/2673](https://eur-lex.europa.eu/eli/dir/2023/2673/oj/eng) to WooCommerce.
With the help of this little plugin you may easily allow your customers to submit (partial) withdrawal requests to their orders.

* *Withdrawal request form* - Use a shortcode to embed a withdrawal request form which works both for guest orders and registered customers.
* *Manage withdrawals* - Easily manage withdrawals from your WooCommerce order page by either confirming or rejecting requests.
* *Send confirmation emails* - Automatically confirm receipt of the withdrawal by email.
* *Partial withdrawals* - Optionally allow customers to submit partial withdrawal requests.

== Installation ==

= Minimal Requirements =

* WordPress 4.9 or newer
* WooCommerce 3.9 (newest version recommended)
* PHP Version 7.0 or newer

= Automatic Installation =

We recommend installing EU Order Withdrawal Button for WooCommerce through the WordPress backend. Please install WooCommerce before installing the plugin.
After the installation, go to WooCommerce > Settings > Advanced > Withdrawals to manage your settings. During installation, the plugin creates a withdrawal page
containing the shortcode as a draft. After testing the withdrawal process, make sure to publish that page so that your customers can access it too.

== Frequently Asked Questions ==

= How to render the withdrawal form? =
Place the shortcode [eu_owb_order_withdrawal_request_form] anywhere on a page to render the form.

= How to embed the button within my shop? =
You may either use the option to embed the button directly within your footer or you may use your theme's options, e.g. a footer menu, to link the withdrawal page.

= The embedded button does not show =
Make sure that you've published your withdrawal page (which by default is created as a draft during installation). The embedded button does only show on shop-related pages.

= Need help? =

You may ask your questions within our free [WordPress support forum](https://wordpress.org/support/plugin/eu-order-withdrawal-button-for-woocommerce).

= Want to file a bug or improve the plugin? =

Bug reports may be filed via our [GitHub repository](https://github.com/vendidero/eu-order-withdrawal-button-for-woocommerce).

== Screenshots ==

1. Withdrawal form
2. Admin UI

== Changelog ==
= 2.0.0 =
* Improvement: Allow withdrawal requests without order number and differing email address
* Improvement: Mark withdrawal requests as verified/unverified based on email address
* Improvement: Separately list unverified withdrawal requests by default
* Improvement: Allow verified guests to choose another order in case multiple orders are withdrawable
* Improvement: Allow choosing first + last name

= 1.0.2 =
* Improvement: Inform guest customers about partial withdrawals within original form
* Fix: HPOS order number search

= 1.0.1 =
* Fix: Partial withdrawal request check
* Improvement: Fallback to customer_id in case billing_email differs

= 1.0.0 =
* Initial commit

== Upgrade Notice ==

= 1.0.0 =
no upgrade - just install :)