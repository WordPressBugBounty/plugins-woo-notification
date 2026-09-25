<?php
/**
 * Plugin Name: Notivo Notification for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/woocommerce-notification-boost-sales/
 * Description: Display recent orders as popup notifications, boosting conversion rates by showing real-time purchase, creating urgency, and showcasing new products.
 * Version: 1.4.3
 * Author: Andy Ha (villatheme.com)
 * Author URI: https://villatheme.com
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0
 * Text Domain: woo-notification
 * Copyright 2016-2026 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * WC requires at least: 7.0
 * WC tested up to: 11.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Historical VI_WNOTIFICATION_F_ / vi_ / woonotification_ prefixes.

define( 'VI_WNOTIFICATION_F_VERSION', '1.4.3' );
define( 'VI_WNOTIFICATION_F_FILE', __FILE__ );

/**
 * Class VI_WNOTIFICATION_F
 */
class VI_WNOTIFICATION_F {

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'custom_order_tables_declare_compatibility' ) );
	}

	/**
	 * Bootstrap plugin after dependencies are available.
	 */
	public function init() {
		$include_dir = plugin_dir_path( __FILE__ ) . 'includes/';

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'woocommerce-notification/woocommerce-notification.php' ) ) {
			return;
		}

		if ( is_plugin_active( 'notivo-notification/notivo-notification.php' ) ) {
			return;
		}

		if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
			include_once $include_dir . 'support.php';
		}

		$environment = new \VillaTheme_Require_Environment(
			array(
				'plugin_name'     => 'Notivo Notification for WooCommerce',
				'php_version'     => '7.4',
				'wp_version'      => '5.0',
				'wc_version'      => '7.0',
				'require_plugins' => array(
					array(
						'slug'            => 'woocommerce',
						'name'            => 'WooCommerce',
						'defined_version' => 'WC_VERSION',
						'version'         => '7.0',
					),
				),
			)
		);

		if ( $environment->has_error() ) {
			return;
		}

		require_once $include_dir . 'define.php';
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 */
	public function custom_order_tables_declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
}

new VI_WNOTIFICATION_F();
