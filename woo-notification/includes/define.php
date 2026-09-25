<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Historical VI_WNOTIFICATION_F_ / vi_ prefixes.

define( 'VI_WNOTIFICATION_F_DIR', plugin_dir_path( VI_WNOTIFICATION_F_FILE ) );
define( 'VI_WNOTIFICATION_F_ADMIN', VI_WNOTIFICATION_F_DIR . 'admin' . DIRECTORY_SEPARATOR );
define( 'VI_WNOTIFICATION_F_FRONTEND', VI_WNOTIFICATION_F_DIR . 'frontend' . DIRECTORY_SEPARATOR );
define( 'VI_WNOTIFICATION_F_LANGUAGES', VI_WNOTIFICATION_F_DIR . 'languages' . DIRECTORY_SEPARATOR );
define( 'VI_WNOTIFICATION_F_INCLUDES', VI_WNOTIFICATION_F_DIR . 'includes' . DIRECTORY_SEPARATOR );

$vi_wnotification_f_plugin_url = untrailingslashit( plugins_url( '', VI_WNOTIFICATION_F_FILE ) );
define( 'VI_WNOTIFICATION_F_CSS', $vi_wnotification_f_plugin_url . '/css/' );
define( 'VI_WNOTIFICATION_F_JS', $vi_wnotification_f_plugin_url . '/js/' );
define( 'VI_WNOTIFICATION_F_IMAGES', $vi_wnotification_f_plugin_url . '/images/' );
define( 'VI_WNOTIFICATION_F_BACKGROUND_IMAGES', VI_WNOTIFICATION_F_IMAGES . 'background/' );
unset( $vi_wnotification_f_plugin_url );

/*Include functions file*/
if ( is_file( VI_WNOTIFICATION_F_INCLUDES . 'functions.php' ) ) {
	require_once VI_WNOTIFICATION_F_INCLUDES . 'functions.php';
}
if ( is_file( VI_WNOTIFICATION_F_INCLUDES . 'data.php' ) ) {
	require_once VI_WNOTIFICATION_F_INCLUDES . 'data.php';
}
if ( ! class_exists( 'VillaTheme_Support' ) && is_file( VI_WNOTIFICATION_F_INCLUDES . 'support.php' ) ) {
	require_once VI_WNOTIFICATION_F_INCLUDES . 'support.php';
}
if ( is_file( VI_WNOTIFICATION_F_INCLUDES . 'mobile_detect.php' ) ) {
	require_once VI_WNOTIFICATION_F_INCLUDES . 'mobile_detect.php';
}

vi_include_folder( VI_WNOTIFICATION_F_ADMIN, 'VI_WNOTIFICATION_F_Admin_' );
vi_include_folder( VI_WNOTIFICATION_F_FRONTEND, 'VI_WNOTIFICATION_F_Frontend_' );
