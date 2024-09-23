<?php
/**
 * Plugin Name: Notification for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/woocommerce-notification-boost-sales/
 * Description: Increase conversion rate by highlighting other customers that have bought products.
 * Version: 1.2.17
 * Author: Andy Ha (villatheme.com)
 * Author URI: http://villatheme.com
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0
 * Text Domain: woo-notification
 * Copyright 2016-2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 7.0
 * WC tested up to: 8.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'VI_WNOTIFICATION_F_VERSION', '1.2.17' );

/**
 * Class VI_WNOTIFICATION_F
 */

class VI_WNOTIFICATION_F {
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'before_woocommerce_init', [ $this, 'custom_order_tables_declare_compatibility' ] );
    }
    
    /**
     *
     */
    function init() {
        
        $include_dir = plugin_dir_path( __FILE__ ) . 'includes/';
        
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        if ( is_plugin_active( 'woocommerce-notification/woocommerce-notification.php' ) ) {
            return;
        }
        
        if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
            include_once $include_dir . 'support.php';
        }
        
        $environment = new \VillaTheme_Require_Environment( [
                'plugin_name'     => 'Notification for WooCommerce',
                'php_version'     => '7.0',
                'wp_version'      => '5.0',
                'wc_version'      => '7.0',
                'require_plugins' => [ [ 'slug' => 'woocommerce', 'name' => 'WooCommerce' ] ]
            ]
        );
        
        if ( $environment->has_error() ) {
            return;
        }
        
        require_once $include_dir . "define.php";
    }
    
    public function custom_order_tables_declare_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
}

new VI_WNOTIFICATION_F();