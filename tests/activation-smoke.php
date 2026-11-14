<?php
/** Clean activation under WP_DEBUG, without the MU loader or a legacy copy. */
define( 'WP_DEBUG', true );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$checks = 0;
$assert = static function ( $value, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $value ) { throw new RuntimeException( $message ); }
};
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new RuntimeException( "$message at $file:$line" ); }
	return false;
} );
wp_set_current_user( 1 );
$plugin = 'irisable-title-layer/wp-title-layer.php';
$assert( ! defined( 'WPTL_FILE' ), 'Clean environment already loaded Title Layer.' );
update_option( 'wptl_settings', array( 'default_template' => 'stacked' ) );
$post = wp_insert_post( array( 'post_title' => 'Preserved title', 'post_status' => 'draft' ) );
update_post_meta( $post, 'wptl_subtitle', 'Preserved subtitle' );
ob_start(); $result = activate_plugin( $plugin ); $output = ob_get_clean();
$assert( ! is_wp_error( $result ), 'Activation returned an error.' );
$assert( '' === $output, 'Activation printed unexpected output.' );
$assert( is_plugin_active( $plugin ), 'Activation was not persisted by WordPress.' );
$assert( WPTL_FILE === WP_PLUGIN_DIR . '/' . $plugin, 'Wrong plugin owns the runtime.' );
$assert( get_post_meta( $post, 'wptl_subtitle', true ) === 'Preserved subtitle', 'Activation changed subtitle data.' );
$assert( get_option( 'wptl_settings' ) === array( 'default_template' => 'stacked' ), 'Activation changed settings.' );
deactivate_plugins( $plugin );
$assert( ! is_plugin_active( $plugin ), 'Deactivation failed.' );
$assert( get_post_meta( $post, 'wptl_subtitle', true ) === 'Preserved subtitle', 'Deactivation changed subtitle data.' );
restore_error_handler();
echo "Clean activation checks passed: $checks\n";
